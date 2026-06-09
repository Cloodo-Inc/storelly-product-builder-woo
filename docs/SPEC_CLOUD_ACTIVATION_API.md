# Storelly — Cloud Activation & Entitlement API Contract (backend hand-off)

> **Status**: DRAFT for backend — the **plugin side is already implemented** (2026-06-09).
> This document is the contract `app.storelly.com` must fulfil to light up the closed-loop
> purchase→unlock flow. It closes **O-6** and the `caps[]`-issuance requirement.
> **Audience**: Storelly Dashboard / API backend team.
> **Plugin code of record**: `includes/class-cloud-activation.php`, `includes/class-license-manager.php`
> (methods `sync_from_api`, `can`, `cloud_license_active`, `caps_catalog`, `plan_family`).
> **Related**: `SPEC_FREEMIUM_V1_1.md` (§1 caps, §6 connect-back), `SPEC_ACCOUNT_PLAN_UX.md` (§5, §9.2).

---

## 0. TL;DR — what the backend must build

| # | Item | Type | Why |
|---|------|------|-----|
| **A** | `GET /connect` intent + checkout screen | hosted web page | entry of the purchase flow |
| **B** | One-time **activation token** issuance on return | part of /connect | binds purchase → this site |
| **C** | `POST /api/v1/license/exchange` | JSON-ish API | token → license (caps[]) |
| **D** | Add **`caps[]`** to `GET /api/v1/license/status` | API change | this is what actually unlocks features |
| **E** | (already exists) `/license/packages`, `/license/activate` | unchanged | manual fallback path |

Nothing unlocks in the plugin until **D** is live (caps drive `can()`). The full
"buy → auto-unlock → land back on the control" loop needs **A+B+C+D**.

---

## 1. The closed-loop flow (sequence)

```
Merchant (wp-admin, Account & Plan page)
  │  clicks "Choose Standard/B2B"  (or an "Unlock →" upsell on a locked control)
  ▼
PLUGIN  (class-cloud-activation.php → handle_checkout)
  │  mints single-use `state`, stores it in a 15-min transient, then 302 →
  ▼
GET https://app.storelly.com/connect?intent=checkout&plan=…&site_url=…&admin_email=…&return_url=…&state=…&ctx=…
  │  [BACKEND A]  account create/login  →  payment (your gateway; card never touches WP)
  │  [BACKEND B]  on success: mint one-time activation token (TTL 10 min), bind it to {site_url, plan, account}
  │  302 → return_url?spbwc_activation_token=<token>&state=<same state>
  ▼
PLUGIN  (admin_init → maybe_handle_return)
  │  verifies `state` == stored (hash_equals, CSRF), deletes the transient (single use)
  │  server-to-server →
  ▼
POST https://app.storelly.com/api/v1/license/exchange   { token, site_url }
  │  [BACKEND C]  validate token (single-use, not expired, site_url matches) → return license + caps[]
  ▼
PLUGIN  persists license_key, calls sync_from_api(), shows success notice, redirects to a clean URL.
        Subsequent GET /license/status [BACKEND D] returns caps[] → can() unlocks features.
```

If C or D is missing/unreachable, the plugin **fails safe**: shows "couldn't activate automatically,
enter your key manually / click Sync" and never breaks the page. So A–E can ship incrementally, but
**caps stay empty (everything locked-as-free) until D ships**.

---

## 2. [A] `GET /connect` — hosted checkout entry

The plugin redirects the admin's browser here. Backend owns this page entirely.

**Query parameters the plugin sends** (all present; values URL-encoded):

| Param | Example | Meaning |
|-------|---------|---------|
| `intent` | `checkout` | always `checkout` in v1 (no `trial` — trials are removed) |
| `plan` | `cloud-b2b-monthly` | requested plan slug (see §5) |
| `site_url` | `https://shop.example.com` | the WordPress site; **bind the purchase to this** |
| `admin_email` | `owner@example.com` | prefill account email (not authoritative) |
| `return_url` | `https://shop…/wp-admin/admin.php?page=storelly-product-builder-for-woocommerce-license` | where to send the browser back |
| `state` | `f3a9…(32 chars)` | opaque single-use nonce — **echo it back unchanged** |
| `ctx` | `cloud_pdf:1234` | optional upsell-by-intent context (cap:object_id); echo back if present |

**Backend behavior:**
1. Let the user create an account / log in (email prefilled from `admin_email`).
2. Run checkout for `plan` on your own gateway. **Never** ask the plugin to handle card data.
3. On payment success → **[B]** mint a one-time activation token, then redirect:
   ```
   <return_url>?spbwc_activation_token=<token>&state=<the same state>&ctx=<the same ctx>
   ```
4. On cancel/abandon → redirect back to `return_url` with **no** token (plugin treats it as "nothing happened"; the stale `state` transient simply expires).

**Security:** the `return_url` is merchant-supplied — validate it is the same host as `site_url`
before redirecting, to avoid open-redirect abuse.

---

## 3. [B] One-time activation token

- **Single use**, TTL **10 minutes**.
- Bound server-side to `{ site_url, plan, account/subscription }` at issue time.
- Opaque, unguessable (≥ 32 bytes of entropy). The plugin only carries it in the URL once, then
  immediately exchanges it server-to-server.
- After a successful exchange (or after TTL), it must be rejected (`token_invalid_or_used`).

---

## 4. [C] `POST /api/v1/license/exchange`

Server-to-server call the plugin makes on return.

**Request** (sent by `SPBWC_Storelly_HTTP::spbwc_post_data` → `wp_remote_post`):
- Content type: `application/x-www-form-urlencoded` (WordPress default for array body).
- Header: `X-STORLY: <unauth_token>` (the store's registration token from connect; use it to
  cross-check the site if you wish).
- Body:
  ```
  token=<one-time token>
  site_url=https://shop.example.com
  ```

**Success response (HTTP 200, JSON):**
```json
{
  "license_key": "SPB-XXXX-XXXX",
  "status": "active",
  "plan_slug": "cloud-b2b-monthly",
  "expires_at": "2027-06-09T00:00:00Z",
  "caps": ["cloud_pdf","order_sync","design_vault","asset_library",
           "option_analytics","config_snapshots","invoice_pdf","email_trigger","analytics_b2b"],
  "quota": { "design_vault_mb": 20480, "used_mb": 0 }
}
```
- The plugin treats the response as success if **any** of `license_key` / `caps` / `status` is
  present and non-empty. It then stores `license_key` and calls `sync_from_api()` (§6) to load the
  authoritative shape — so it is fine if `exchange` returns only `{license_key}` and lets
  `/license/status` carry the caps, **but returning caps here too is recommended** for an instant
  unlock without a second round-trip.

**Error responses** (any non-2xx or a body without those fields → plugin shows a graceful notice):
```json
{ "error": "token_invalid_or_used" }   // 400 — UI: "link expired, start again from Plans"
{ "error": "site_mismatch" }           // 409 — token bound to a different site_url
{ "error": "…" }                       // 5xx/429 — UI: generic, suggests manual key / Sync
```

**Security rules (must enforce):** token single-use + 10-min TTL; reject if `site_url` ≠ the value
the token was bound to (`site_mismatch`); this endpoint is server-to-server only (the token must not
be usable from a browser after the immediate exchange).

---

## 5. Canonical vocabulary the plugin understands

### 5.1 Plan slugs (`plan_slug`)
```
free
cloud-standard-monthly   cloud-standard-annual
cloud-b2b-monthly        cloud-b2b-annual
```
The plugin normalizes to a **family** for UI highlighting (`SPBWC_License_Manager::plan_family`):
`cloud-b2b*` → **b2b**, `cloud-standard*` → **standard**, anything else → **free**.

### 5.2 Capability keys (`caps[]`) — the authoritative unlock list

These exact strings are what `can($cap)` checks (`SPBWC_License_Manager::caps_catalog`). Unknown
strings are ignored (no fatal), so you can add new caps before a plugin release.

| Cap key | Unlocks | Belongs to (recommended) |
|---------|---------|--------------------------|
| `cloud_pdf` | Print-ready PDF rendering | Standard, B2B |
| `order_sync` | Order → Dashboard sync (data backbone) | Standard, B2B |
| `design_vault` | Customer design cloud backup | Standard, B2B |
| `asset_library` | In-canvas cliparts/fonts | Standard, B2B |
| `option_analytics` | Per-option revenue analytics | Standard, B2B |
| `config_snapshots` | Pricing config version history | Standard, B2B |
| `invoice_pdf` | Branded invoice/statement PDFs | B2B only |
| `email_trigger` | Scheduled B2B emails (dunning, statements) | B2B only |
| `analytics_b2b` | B2B account analytics (aging, DSO) | B2B only |

> **Authority rule:** caps are **server-issued and the source of truth**. The plugin does **not**
> derive caps from `plan_slug` locally (so you can change what a plan includes, run promos, or grant
> add-ons without shipping a plugin update). The plugin's plan→caps table is **display only** (the
> comparison matrix); the store's actual unlocked set is whatever `caps[]` you return.

---

## 6. [D] `GET /api/v1/license/status` — must include `caps[]`

Already called by the plugin on the Account & Plan / Overview screens and on "Sync". Today it
returns plan/quota fields; **add `caps`**. The plugin's `sync_from_api()` reads, in this order:

- `caps` — from **top-level `caps`** OR **`package.caps`** (either accepted), each value passed
  through `sanitize_key`, de-duplicated.
- `plan_slug` — from **top-level `plan_slug`** OR **`package.slug`**.
- `status` — top-level (`free` | `active` | `expired`).
- `expires_at` — from `license.expires_at`.

**Recommended response shape:**
```json
{
  "success": true,
  "status": "active",
  "plan_slug": "cloud-standard-monthly",
  "caps": ["cloud_pdf","order_sync","design_vault","asset_library","option_analytics","config_snapshots"],
  "package": { "name": "Cloud Standard", "slug": "cloud-standard-monthly" },
  "license": { "expires_at": "2027-06-09T00:00:00Z" }
}
```

How the plugin uses it (do not change these semantics):
```
cloud_license_active() == ( status === 'active'  AND  (expires_at empty OR in the future) )
can($cap)              == ( cloud_license_active()  AND  $cap ∈ caps[] )
```
Consequences:
- A free/connected store → return `status:"free"`, `caps:[]` → everything stays free/local (correct).
- An expired store → `status:"expired"` → `cloud_license_active()` false → features lock, but local
  tools and data are untouched (the plugin never destroys local data).
- **Do not** return caps for a `free`/`expired` status expecting them to unlock — they won't
  (`cloud_license_active()` gates first).

---

## 7. [E] Endpoints already in use (for reference — unchanged)

| Endpoint | Used when | Notes |
|----------|-----------|-------|
| `POST /api/v1/license/activate` | manual key entry (Advanced) | body `{license_key, business_id}`; on success plugin re-runs `/license/status` |
| `GET /api/v1/license/packages` | Account & Plan page open | live plan list + price (the in-plugin matrix has a baked-in fallback) |
| `GET /api/v1/plugin/overview` | Overview page | aggregate counts |
| `POST /api/v1/update-orders` | order sync (when `order_sync` cap + consent) | see `SPEC_FREEMIUM_V1_1` §4.0 |

---

## 8. After backend ships — the remaining plugin step (F2)

Once **D** is live (caps issued), the plugin's `can()` starts returning true for licensed stores.
The final plugin task **F2** then wraps the real cloud entry points so free/expired stores get an
inline upsell instead of a silent fail. Pattern (already supported by `cloud_locked_error()`):

```php
// e.g. class-export-pdf.php
if ( ! SPBWC_License_Manager::can( 'cloud_pdf' ) ) {
    return SPBWC_License_Manager::cloud_locked_error( 'cloud_pdf' ); // 'spbwc_cloud_locked' + cap
}
// …existing render…
```
The UI maps `spbwc_cloud_locked` → an "Unlock →" prompt linking to
`SPBWC_Cloud_Activation::checkout_url( $plan, "$cap:$object_id" )` (the `ctx` round-trips through §2).

> **F2 must ship after D**, not before — gating `cloud_pdf` while the server still returns empty caps
> would lock the feature for stores currently using it. (This is why F2 is intentionally deferred.)

---

## 9. Test checklist (backend ↔ plugin handshake)

1. **Happy path**: Choose B2B → /connect → pay → return with token+state → exchange 200 with caps →
   plugin notice "activated" → `/license/status` returns same caps → Account page shows B2B as Current,
   caps ✓.
2. **State mismatch / replay**: return with a wrong `state`, or hit the exchange token twice →
   `token_invalid_or_used` / state fail → plugin shows "link expired", **no** license written.
3. **Site binding**: exchange with a `site_url` different from the token's bound site → `409
   site_mismatch`.
4. **Expiry**: set a store `status:"expired"` on `/license/status` → plugin locks caps, local tools
   keep working.
5. **caps authority**: change a store's `caps` server-side (e.g. add `invoice_pdf`) → after Sync,
   `can('invoice_pdf')` flips true with no plugin change.

---

## 10. Open questions for backend

| ID | Question |
|----|----------|
| O-7 | Stripe-or-other proration on Standard→B2B mid-cycle: does the next `/license/status` simply reflect the new caps? (plugin just re-syncs) |
| Q-1 | Is `caps` echoed in `exchange` response (instant unlock) or only via `/license/status` (one extra Sync)? Recommend: both. |
| Q-2 | Token TTL/issuance: confirm 10-min single-use is acceptable for your checkout latency. |
| Q-3 | `X-STORLY` header on exchange — do you want it as the site-correlation check, or rely solely on `site_url` + token binding? |
