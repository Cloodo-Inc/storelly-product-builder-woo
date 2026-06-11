# Cloud Activation API — Backend Gap & Handoff

> **Audience**: Storelly Dashboard backend team (`Storelly-dashboad-v6`).
> **Date**: 2026-06-10.
> **Source of contract**: the WooCommerce plugin spec
> [`SPEC_CLOUD_ACTIVATION_API.md`](https://github.com/Cloodo-Inc/storelly-product-builder-woo/blob/main/docs/SPEC_CLOUD_ACTIVATION_API.md)
> (in the `storelly-product-builder-woo` repo).
> **Plugin code of record**: `includes/class-license-manager.php`,
> `includes/class-cloud-activation.php`, `includes/class-productbuilder-api.php`.

This document is a **review of the live gap** between what the WooCommerce plugin already sends/expects
and what this backend actually returns. The plugin side is **fully implemented and conforms to the
spec**; everything below is backend work. **No plugin change is needed** to close these gaps.

The base URL is `https://app.storelly.com` (plugin constant `SPBWC_API_URL`). All authenticated calls
carry header `X-STORLY: <unauth_token>`, matched server-side against `business.x_token` by the
`UnAuthToken` middleware (`Modules/RestApi/Http/Middleware/UnAuthToken.php`).

---

## 1. TL;DR — status of the 7 contract endpoints

| # | Endpoint | Status | One-liner |
|---|----------|--------|-----------|
| A | `GET /connect` | ❌ **Missing** | No hosted checkout web route — purchase flow can't start. |
| B | one-time activation token | ❌ **Missing** | Not minted on return from `/connect`. |
| C | `POST /api/v1/license/exchange` | ❌ **Missing** | No controller method — auto-activation loop is dead. |
| D | `GET /api/v1/license/status` | ⚠️ **Wrong contract** | Returns 200 but **no `caps[]`**, `status` never `"active"`, `slug` is `pkg-{id}`. |
| E1 | `POST /api/v1/license/activate` | ⚠️ **403 stub** | Returns "manage from Dashboard" — manual key fallback also dead. |
| E2 | `GET /api/v1/license/packages` | ✅ **Working** | Returns package list. |
| E3 | `GET /api/v1/plugin/overview` | ✅ **Working** | Returns aggregate counts. |
| F | `POST /api/v1/update-orders` | ⚠️ **Path mismatch + empty** | Backend route is `update-order` (singular); handler `asyncOrder` is empty. |
| — | `POST /api/v1/register` | ✅ **Working** | Store registration / consent. |

**Net effect:** there is currently **no path** for a store to go `free → active` through the plugin —
both the automatic loop (`/connect` → `exchange`) and the manual path (`activate`) are broken. And even
if a license were written, the wrong `status`/missing `caps` in `/license/status` would keep
`can($cap)` returning `false`, so nothing would unlock.

---

## 2. The 3 contract bugs in `GET /api/v1/license/status` (highest priority)

This endpoint already returns HTTP 200, so it *looks* healthy — but its **shape is wrong** in three
ways that silently keep every store locked.

### Bug 1 — `status` is never `"active"` → paying customers stay locked

The plugin's gate (`includes/class-license-manager.php:321-330`) is:

```php
public static function cloud_license_active() {
    $lic = self::get_current_license();
    if ( 'active' !== ( $lic['status'] ?? 'free' ) ) { return false; }  // <-- requires literally "active"
    ...
}
```

Backend `LicenseController@getStatus` (`app/Http/Controllers/LicenseController.php:140`) returns:

```php
'status' => $package ? 'pkg-'.$package->id : 'expired',   // e.g. "pkg-3" — never "active"
```

So a real, paid subscription resolves to `status: "pkg-3"`, the plugin sees `!== 'active'`, and
`cloud_license_active()` is `false`. **Fix:** top-level `status` must be one of exactly
`free | active | expired` (lowercase). Map `Subscription.status === 'approved'` + not expired →
`"active"`; past `end_date` → `"expired"`; no subscription → `"free"`.

### Bug 2 — no `caps[]` → `can($cap)` is always false (spec item D)

The plugin reads caps from either the top level or `package.caps`
(`includes/class-license-manager.php:108-114`):

```php
if ( isset( $resp['caps'] ) && is_array( $resp['caps'] ) ) {
    $raw_caps = $resp['caps'];
} elseif ( isset( $package['caps'] ) && is_array( $package['caps'] ) ) {
    $raw_caps = $package['caps'];
}
```

`getStatus` returns `'features' => []` / a list of human strings (`:135`) — **never a `caps[]` array**.
There is also no capability column on the `Package` model (only `product_count`, `invoice_count`,
`user_count`, `location_count`, `custom_permissions`). So `caps` is always empty → `can()` always
false → **every cloud feature stays locked even for paying stores**. This is the single most important
fix: **caps are the actual unlock mechanism.**

**Fix:** add a top-level `caps` array (or `package.caps`) of capability keys (see §6 for the canonical
9 keys). Caps are **server-issued and authoritative** — the plugin never derives them from the plan.

### Bug 3 — `plan_slug` uses `pkg-{id}` instead of canonical slugs

The plugin normalizes the slug to a family for UI highlighting
(`includes/class-license-manager.php:354-363`): it matches the prefixes `cloud-b2b*` and
`cloud-standard*`. Backend returns `'slug' => 'pkg-'.$package->id` (`:133`), which matches neither, so
`plan_family()` always returns `'free'` and the Account & Plan matrix highlights the wrong tier.

**Fix:** return a top-level `plan_slug` (or `package.slug`) using the canonical vocabulary in §6,
e.g. `cloud-standard-monthly`, `cloud-b2b-annual`.

### Recommended `GET /api/v1/license/status` response (from spec §6)

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

Semantics the plugin enforces (do **not** change):

```
cloud_license_active() == ( status === 'active'  AND  (expires_at empty OR in the future) )
can($cap)              == ( cloud_license_active()  AND  $cap ∈ caps[] )
```

Notes: a free/connected store → `status:"free"`, `caps:[]`. An expired store → `status:"expired"`
(features lock, local data untouched). Do **not** return caps for `free`/`expired` expecting them to
unlock — `cloud_license_active()` gates first.

---

## 3. Missing endpoints — the closed-loop purchase flow

These three pieces (spec §2–§4) implement "buy → auto-unlock → land back on the control". They can ship
after the §2 fixes; the plugin **fails safe** without them (shows "enter key manually / click Sync").

### [A] `GET /connect` — hosted checkout entry (web route)

The plugin redirects the admin's browser here (`includes/class-cloud-activation.php:61-94`). Backend
owns the page entirely. Query params the plugin sends (all present, URL-encoded):

| Param | Example | Meaning |
|-------|---------|---------|
| `intent` | `checkout` | always `checkout` in v1 |
| `plan` | `cloud-b2b-monthly` | requested plan slug (§6) |
| `site_url` | `https://shop.example.com` | bind the purchase to this site |
| `admin_email` | `owner@example.com` | prefill account email (not authoritative) |
| `return_url` | `https://shop…/wp-admin/admin.php?page=…-license` | where to send the browser back |
| `state` | `f3a9…` (32 chars) | opaque single-use nonce — **echo back unchanged** |
| `ctx` | `cloud_pdf:1234` | optional upsell context (`cap:object_id`); echo back if present |

Backend behavior: let user create account / log in → run checkout for `plan` on your gateway (card
**never** touches WP) → on success mint the token [B] and redirect:

```
<return_url>?spbwc_activation_token=<token>&state=<same state>&ctx=<same ctx>
```

On cancel/abandon → redirect to `return_url` with **no** token. **Security:** `return_url` is
merchant-supplied — validate it is the **same host as `site_url`** before redirecting (open-redirect
guard).

### [B] One-time activation token

Single-use, **TTL 10 minutes**, opaque (≥ 32 bytes entropy), bound server-side to
`{ site_url, plan, account/subscription }` at issue time. After a successful exchange (or TTL) it must
be rejected.

### [C] `POST /api/v1/license/exchange`

Server-to-server call the plugin makes on return (`includes/class-cloud-activation.php:139-170`).

**Request** — `application/x-www-form-urlencoded`, header `X-STORLY: <unauth_token>`:

```
token=<one-time token>
site_url=https://shop.example.com
```

**Success (HTTP 200, JSON):**

```json
{
  "license_key": "SPB-XXXX-XXXX",
  "status": "active",
  "plan_slug": "cloud-b2b-monthly",
  "expires_at": "2027-06-09T00:00:00Z",
  "caps": ["cloud_pdf","order_sync","design_vault","asset_library","option_analytics","config_snapshots","invoice_pdf","email_trigger","analytics_b2b"]
}
```

The plugin treats the response as success if **any** of `license_key` / `caps` / `status` is present
and non-empty, then stores `license_key` and calls `/license/status` for the authoritative shape.
Returning only `{license_key}` is acceptable, but **returning `caps` here too gives an instant unlock**.

**Errors:** `400 {"error":"token_invalid_or_used"}`, `409 {"error":"site_mismatch"}` (token bound to a
different `site_url`), `5xx/429` → plugin shows a generic notice and suggests manual key / Sync.
**Must enforce:** single-use + 10-min TTL; reject `site_url` ≠ bound value; server-to-server only.

---

## 4. `POST /api/v1/update-orders` — path mismatch + empty handler

The plugin posts order sync to **`/api/v1/update-orders` (plural)** with `X-STORLY`
(`includes/class-productbuilder-api.php:280-337`). The backend route is **`update-order` (singular)**
(`Modules/RestApi/Routes/api.php:27-30`), and its handler `WoocommereController@asyncOrder` is an empty
stub. Result: **404 + silent no-op** — order sync never lands.

**Fix:** add a `POST /api/v1/update-orders` route (or alias the existing singular one) and implement
`asyncOrder`. The plugin sends (fire-and-forget, response ignored):

```jsonc
{
  "is_quotation": 0,
  "status": "final",
  "final_total": 123.45,
  "contact_id": 1,
  "is_direct_sale": 1,
  "products": [
    { "product_id": 0, "variation_id": 0, "unit_price": 0, "unit_price_inc_tax": 0,
      "quantity": 0, "item_tax": 0, "enable_stock": 0, "product_type": "", "tax_id": 0 }
  ],
  "tax_rate_id": "",
  "shipping_documents": [],
  "discount_type": "fixed",
  "discount_amount": 0,
  "payment": [ { "amount": 0, "is_return": 0, "method": "cash" } ],
  "price_group": 0
}
```

This call only fires when the store has consented (`enable_api_sync = yes`) and (per spec) the
`order_sync` cap is granted.

---

## 5. `POST /api/v1/license/activate` — 403 stub blocks the manual fallback

`LicenseController@activate` (`app/Http/Controllers/LicenseController.php:148-154`) currently always
returns `403 {"success": false, "msg": "Please manage your subscription directly from your Storelly
Dashboard."}`. This is the plugin's **manual key path** (Advanced screen): body
`{license_key, business_id}`; on success the plugin re-runs `/license/status`.

**Decision needed:**
- **Option A (recommended):** implement it — look up the license/subscription for `business_id`,
  validate `license_key`, attach, return `{success: true, msg, package}`. This restores the only
  manual recovery path for stores where the auto-loop fails or the gateway is offline.
- **Option B:** keep it disabled — then the **only** way to activate is the closed loop (§3), so
  `/connect` + `exchange` become hard blockers, not optional. Document this clearly for support.

Either way, today both paths are down, so at least one must ship.

---

## 6. Canonical vocabulary the plugin understands

### Plan slugs (`plan_slug`)

```
free
cloud-standard-monthly   cloud-standard-annual
cloud-b2b-monthly        cloud-b2b-annual
```

Family mapping (display only, `plan_family`): `cloud-b2b*` → **b2b**, `cloud-standard*` →
**standard**, anything else → **free**.

### Capability keys (`caps[]`) — the authoritative unlock list

These exact strings drive `can($cap)` (`includes/class-license-manager.php` `caps_catalog()`). Unknown
strings are ignored (no fatal), so new caps can be added before a plugin release.

| Cap key | Unlocks | Tier (recommended) |
|---------|---------|--------------------|
| `cloud_pdf` | Print-ready PDF rendering | Standard, B2B |
| `order_sync` | Order → Dashboard sync | Standard, B2B |
| `design_vault` | Customer design cloud backup | Standard, B2B |
| `asset_library` | In-canvas cliparts/fonts | Standard, B2B |
| `option_analytics` | Per-option revenue analytics | Standard, B2B |
| `config_snapshots` | Pricing config version history | Standard, B2B |
| `invoice_pdf` | Branded invoice/statement PDFs | B2B only |
| `email_trigger` | Scheduled B2B emails | B2B only |
| `analytics_b2b` | B2B account analytics (aging, DSO) | B2B only |

---

## 7. Suggested data mapping (backend internals → contract)

The dashboard currently models entitlement as `Package` + `Subscription`
(`Modules/Superadmin/Entities/`). To satisfy the contract without re-architecting:

- **`status`**: `Subscription::active_subscription(business_id)` exists & not past `end_date` →
  `"active"`; exists but past `end_date` → `"expired"`; none → `"free"`.
- **`plan_slug`**: add a canonical slug per package (new column, or a `package_id → slug` map). Avoid
  exposing `pkg-{id}`.
- **`caps[]`**: derive from the package — e.g. a `capabilities` JSON column on `Package`, or map
  `custom_permissions['woocommerce_module']` + tier to the §6 keys. Caps are the source of truth, so
  this can be changed server-side (promos, add-ons) without a plugin release.
- **`expires_at`**: `Subscription.end_date` (ISO 8601 preferred).

---

## 8. Test checklist (backend ↔ plugin handshake — spec §9)

1. **Happy path**: choose B2B → `/connect` → pay → return with `token`+`state` → `exchange` 200 with
   caps → plugin shows "activated" → `/license/status` returns same caps → Account page shows B2B as
   Current.
2. **Replay / state mismatch**: wrong `state`, or hit the exchange token twice →
   `token_invalid_or_used`; **no** license written.
3. **Site binding**: `exchange` with a `site_url` different from the token's bound site → `409
   site_mismatch`.
4. **Expiry**: set a store `status:"expired"` → plugin locks caps, local tools keep working.
5. **caps authority**: change a store's `caps` server-side (e.g. add `invoice_pdf`) → after Sync,
   `can('invoice_pdf')` flips true with no plugin change.

---

## 9. References

- Plugin contract (authoritative): `storelly-product-builder-woo/docs/SPEC_CLOUD_ACTIVATION_API.md`.
- Related: `SPEC_FREEMIUM_V1_1.md` (§1 caps, §4 order sync, §6 connect-back),
  `SPEC_ACCOUNT_PLAN_UX.md` (§5, §9.2), `SPEC_M5_CLOUD_CONSENT.md` (register idempotent-by-uuid).
- Backend files touched by this contract: `app/Http/Controllers/LicenseController.php`,
  `Modules/RestApi/Routes/api.php`, `Modules/RestApi/Http/Controllers/WoocommereController.php`,
  `Modules/RestApi/Http/Middleware/UnAuthToken.php`, `Modules/Superadmin/Entities/{Package,Subscription}.php`,
  plus a new `routes/web.php` entry for `/connect`.
