# Storelly — Freemium & Cloud Monetization Spec **v1.1**

> **Status**: DRAFT for review — extends, does not replace, `SPEC_FREEMIUM.md` (v1.0)
> **Date**: 2026-06-07
> **Owner**: David / Netbase
> **Mode**: UPDATE (major scope additions → new version file per spec-writer convention)
> **Supersedes**: §2.2 marketplace row of v1.0; closes open item **O-5**; adds **O-6..O-9**
> **Related**: `SPEC_FREEMIUM.md`, `SPEC_M5_CLOUD_CONSENT.md`, `SPEC_B2B_CLIENT.md`,
> `SPEC_B2B_ACCOUNT_CREDIT.md`, `SPEC_TEMPLATE_PREVIEW.md`, `wp-org-compliance-audit-plan.md`

---

## 0. What v1.1 adds (executive summary)

v1.0 locked the strategy: **Local = Free, Cloud = Paid** (D1–D4 unchanged, not relitigated here).
v1.1 turns that boundary into a sellable product line:

| # | Addition | Closes |
|---|----------|--------|
| A | Template marketplace **Free/Premium split** + preview + versioned updates | v1.0 §2.2 gap |
| B | **Two-plan model**: Cloud Standard **$49/mo** · Cloud B2B **$99/mo** (+annual) via `caps[]` | **O-5** |
| C | **B2B service layer** (invoice PDF, dunning, statements, B2B analytics) — the reason B2B costs $99 | new |
| D | **High-frequency cloud services** on Builder/Options: Design Vault, Asset Library, Option Analytics, Config Snapshots | new |
| E | **In-plugin Plans page** + **connect-back activation flow** (checkout → token → auto-license) | new, adds O-6 |

Locked decisions carried forward verbatim: **D1** (feature+cloud boundary, not product count),
**D2** (Quote/Custom Order/engines stay free local), **D3** (no permanent cloud free-tier;
14-day trial only), **D4** (compliance cleanup C1–C5 ships first, independently).

### 0.1 New locked decisions (v1.1)

| # | Decision | Choice |
|---|----------|--------|
| D5 | Plan shape | **Two plans**: `cloud-standard` / `cloud-b2b`, monthly + annual. No per-feature à-la-carte in v1. |
| D6 | Pricing | Standard **$49/mo or $490/yr**; B2B **$99/mo or $990/yr** (annual = 2 months free). Lifetime: **not offered** in v1 (revisit post-launch). |
| D7 | Payment processing | **Hosted checkout on `app.storelly.com` (Stripe Checkout)**. Never collect card data inside wp-admin. |
| D8 | Activation | **Connect-back flow** (redirect → one-time token → auto-license) is the primary path; manual license key entry remains as fallback. |
| D9 | Template monetization | Free templates downloadable without license (anonymous GET, like demo import); Premium templates + **template updates** require `cloud_license_active`. Browsing/preview is free for everyone. |
| D10 | B2B engine vs B2B services | All B2B **engines stay free local** (per D2, incl. ledger M1–M5). The $99 plan sells only **server-side services** that consume the local data. |

### 0.2 Code-audit results — assumptions resolved during spec authoring

The v1.0/v1.1 entitlement layer is **not yet implemented in code**. Audited on 2026-06-07:

| Item | Finding | Consequence for this spec |
|------|---------|---------------------------|
| **A5** — does `order_sync` payload already carry per-line selected options? | **NO.** `SPBWC_Productbuilder_Api::spbwc_run_order_sync()` (`includes/class-productbuilder-api.php:303-335`) sends per-item only `product_id, variation_id, unit_price, quantity, product_type` + design PDF URLs in `shipping_documents`. No structured pricing-option selections. | **§4.3 Option Analytics MUST extend the sync payload** (per-line `options[]`). It does **not** "ride existing sync for free". F8 now depends on a payload change in/after F2. Flagged in §4.3 + §10. |
| **Entitlement shape** — current `get_current_license()` | Returns `status, package_name, package_slug, expires_at, max_products, max_orders, max_pricing_options, features, synced_at` (`includes/class-license-manager.php:104-114`). **No `caps[]`, no `plan_slug`, no `cloud_license_active()`, no `can()`.** `sync_from_api()` reads `package.slug` / `package.features`, not `caps`. | **F1.1 must add** `caps[]` + `plan_slug` to the data shape and map them from the server response in `sync_from_api()`. `cloud_license_active()` (v1.0 §4.1) and `can($cap)` (§1.3) are **net-new**, not edits. |
| **A6** — can `SPBWC_Cloud_Connect` consent be invoked inline post-activation without refactor? | **Plausible.** `SPBWC_Cloud_Connect` exposes `init / ajax_connect / connect() / is_connected()` (`includes/class-cloud-connect.php`) — a self-contained AJAX connect flow with an `is_connected()` predicate. The consent *render* entry point for §6.1 step 7 must be confirmed against the M5 consent screen, but no structural blocker found. | Keep A6 **open but low-risk**; §6.1 step 7 wiring confirmed during F6. |

---

## 1. Entitlement model v1.1 — plans and caps

### 1.1 License data shape (extends v1.0 §4.3 — field semantics unchanged)

```
status        : 'free' | 'trial' | 'active' | 'expired'
plan_slug     : 'cloud-standard-monthly' | 'cloud-standard-annual'
              | 'cloud-b2b-monthly'      | 'cloud-b2b-annual'
expires_at    : ISO 8601 | null
trial_ends_at : ISO 8601 | null
caps          : string[]            // authoritative, server-issued — see 1.2
quota         : { design_vault_mb: int, used_mb: int }   // soft display only, server enforces
```

> **Migration note (from audited code):** the existing option (`SPBWC_License_Manager::OPTION_KEY`)
> stores `package_slug` + `max_*` + `features`. F1.1 adds `plan_slug`, `caps`, `trial_ends_at`,
> `quota`. Keep `max_*`/`features` readable for one release (no fatal on missing keys) but they
> **no longer gate anything** (v1.0 §1.2). `sync_from_api()` must map a server-issued `caps[]`
> array; if absent, fall back to `features` → empty caps (= Free).

### 1.2 Caps matrix (single source of truth)

| Cap | Free | Trial | Standard $49 | B2B $99 | Service it gates |
|---|:---:|:---:|:---:|:---:|---|
| `cloud_pdf` | ❌ | ✅ | ✅ | ✅ | Cloud2Print print-ready PDF (v1.0 §2.2) |
| `order_sync` | ❌ | ✅ | ✅ | ✅ | Order → Dashboard sync (v1.0 §2.2) |
| `marketplace_premium` | ❌ | ✅ | ✅ | ✅ | Premium template download + updates (§2) |
| `design_vault` | ❌ | ✅ | ✅ 5 GB | ✅ 20 GB | Customer design backup/cross-device (§4.1) |
| `asset_library` | ❌ | ✅ | ✅ | ✅ | In-canvas hosted cliparts/fonts (§4.2) |
| `option_analytics` | ❌ | ✅ | ✅ | ✅ | Per-option revenue analytics (§4.3) |
| `config_snapshots` | ❌ | ✅ | ✅ 30 versions | ✅ 100 versions | Option-set version history (§4.4) |
| `analytics_b2b` | ❌ | ✅ | ❌ | ✅ | Account analytics, aging, DSO (§3.3) |
| `invoice_pdf` | ❌ | ✅ | ❌ | ✅ | Branded invoice/quote-doc render (§3.1) |
| `dunning` | ❌ | ✅ | ❌ | ✅ | Scheduled payment reminders (§3.2) |
| `statements` | ❌ | ✅ | ❌ | ✅ | Monthly company statements (§3.2) |

Trial = full B2B caps for 14 days (D3-compatible: hard stop, one per site+account).

### 1.3 Gate predicate (replaces v1.0 §4.1 stub `can()`)

```php
// SPBWC_License_Manager
public static function can( string $cap ): bool {
    if ( ! self::cloud_license_active() ) return false;     // unchanged from v1.0
    $lic  = self::get_current_license();
    $caps = isset( $lic['caps'] ) && is_array( $lic['caps'] ) ? $lic['caps'] : array();
    return in_array( $cap, $caps, true );
}
```

Rules (unchanged in spirit from v1.0 §4.2):
- Every cloud touch point calls `can('<cap>')` **AND** the relevant consent flag. Never scatter `plan_slug` checks — caps only.
- Blocked call returns `WP_Error('spbwc_cloud_locked', '', array( 'cap' => $cap ) )`; UI renders the cap-specific upsell (§6.3). Never silent-fail.
- Caps are **server-issued**; the plugin never derives caps from `plan_slug` locally (forward-compat for plan changes without plugin release).
- Unknown caps in the array are ignored (older plugin + newer server = no fatal).

### 1.4 Permission model (admin side)

| Action | Capability required |
|---|---|
| View Plans page, prices | `manage_options` |
| Start trial / checkout redirect | `manage_options` |
| Receive activation token & store license | `manage_options` + valid `state` nonce |
| Deactivate / change license | `manage_options` |
| Invoke gated cloud actions (export PDF…) | per existing feature permissions; cap check is additive |

---

## 2. (A) Template Marketplace — Free/Premium split

Amends v1.0 §2.2 row "Hosted template marketplace": split into four behaviors.

### 2.1 Behavior matrix

| Behavior | Gate | Notes |
|---|---|---|
| Browse catalogue (list, search, filter by industry) | **None** (works pre-connect) | This is the sales surface — must never be behind anything |
| Preview: screenshots + **live demo link** per template | **None** | Demo products hosted on `demo.storelly.com`; link opens new tab — no remote code in admin |
| Download **Free** template | **None** (anonymous GET) | Same compliance posture as remote demo import (v1.0 §2.3.2). Declare in External services. |
| Download **Premium** template | `can('marketplace_premium')` + consent | |
| Receive **template updates** (free or premium template) | `can('marketplace_premium')` | The recurring-value mechanism — see 2.3 |

### 2.2 Catalogue API contract

```
GET https://app.storelly.com/api/v1/templates?industry=&type=free|premium&page=1
→ 200 {
    "templates": [{
      "id": "tpl_mug_classic", "name": "...", "industry": "mug",
      "type": "free" | "premium",
      "version": "1.3.0",
      "preview": { "screenshots": ["https://cdn..."], "demo_url": "https://demo.storelly.com/..." },
      "summary": "...", "options_count": 14, "updated_at": "ISO"
    }],
    "total": 42, "page": 1, "per_page": 20
  }
→ 4xx/5xx → plugin shows cached catalogue (transient, TTL 12h) + retry notice.

GET  /api/v1/templates/{id}/download                     // free templates — anonymous
POST /api/v1/templates/{id}/download                     // premium — body: { license_key, site_url }
→ 200 { "package": { ...preset JSON schema (per ICP Catalog)... }, "version": "1.3.0" }
→ 402 { "error": "license_required" }   → plugin maps to spbwc_cloud_locked(marketplace_premium)
→ 403 { "error": "license_invalid_or_expired" }
```

### 2.3 Versioned updates (recurring value)

- Plugin stores `_spbwc_template_source = { id, version }` on every imported template/preset.
- Weekly admin-triggered (or on marketplace page open) check: `GET /api/v1/templates/updates`
  with body of installed `{id, version}` pairs → list of available updates + changelogs.
- "Update available" badge on the marketplace page; applying an update requires
  `can('marketplace_premium')` **even for free templates** (update stream is the paid service;
  the original free download stays free).
- Applying an update **never overwrites merchant edits silently**: show diff summary
  (option groups added/changed) → merchant confirms → snapshot current config first
  (reuses §4.4 Config Snapshots if licensed, else local JSON export to uploads).

### 2.4 Validation & limits

- Template package JSON ≤ **2 MB**; reject larger with explicit error.
- Imported preset must pass existing import validator (same path as PrintCart adapter import).
- Catalogue page size 20, max 100 per request.

---

## 3. (C) B2B service layer — what $99/mo actually buys

Context: per D2/D10, the entire local B2B suite (company CPT, tier pricing, wallet/net-terms/
rebate ledger `spbwc_b2b_ledger` M1–M5, approval flow) is **free**. The B2B plan sells
server-side services consuming that local data. No local B2B feature is removed or gated.

### 3.1 Branded document render — `invoice_pdf`

- Reuses the Cloud2Print render pipeline with **document templates** (invoice, quote-doc,
  credit statement) instead of print files.
- Entry points: order admin ("Generate invoice PDF"), quote detail ("Quote PDF" — replaces
  the v1.0 §2.3.1 question for B2B plan; Standard plan still gets the local FPDI/watermark
  fallback per O-4), company Account-credit tab ("Statement PDF").

```
POST /api/v1/render/document     { license_key, site_url, type: "invoice"|"quote"|"statement",
                                   payload: { ...normalized order/quote/ledger data... },
                                   branding: { logo_url, colors, footer_text } }
→ 200 { "pdf_url": "https://cdn... (signed, TTL 24h)", "render_id": "..." }
→ 402/403 as §2.2 · → 422 { "error": "payload_invalid", "fields": [...] }
```

- Payload is **derived, not raw DB**: amounts, line items, company name/address, due_date.
  No WP credentials, no customer password hashes, no unrelated PII. Field list frozen in the
  External-services readme entry (extends C3).

### 3.2 Dunning & statements — `dunning`, `statements`

- **Sync up**: when `order_sync` + B2B plan active, the existing order-sync payload is
  extended with ledger events (charge/payment/due_date per company). Server owns the schedule.
- **Dunning schedule (server-side cron)**: reminder emails at **due−3d, due, due+7d, due+30d**
  (merchant-configurable on Dashboard; sensible defaults). Escalation copy per stage.
- **Statements**: monthly job renders per-company statement PDF (§3.1) + emails to company
  owner; merchant gets a digest.
- Emails are sent **by the Storelly server** (deliverability + scheduling reliability —
  the reason this is a service, not WP-cron). From-name = store name; reply-to = merchant email.
- Kill-switch per company ("exclude from dunning") synced as a flag from the plugin
  (company detail → Account credit tab).

### 3.3 B2B analytics — `analytics_b2b`

- Dashboard views computed server-side from synced data: top accounts by revenue,
  outstanding & aging buckets (0/30/60/90 — mirrors local `get_aging()`), DSO trend,
  churn-risk (no orders in N days vs account average), per-company LTV.
- Plugin side: a read-only summary card on the Overview page via the existing
  `get_overview_stats()` channel — **no new plugin endpoint**.
- Revenue framing requirement (UX principle): the headline metric is
  **"Outstanding collected this month: $X"** and **"Reminders sent: N → est. hours saved: H"**
  (H = N × 10 min default, merchant-adjustable) — not vanity counts.

---

## 4. (D) High-frequency cloud services — Builder & Pricing Options

Design rule: engines/preview stay local (D2; real-time perf). Cloud bites only where there is
**storage, hosted content, or computation on synced data** — real marginal cost.

### 4.1 Design Vault — `design_vault`

**Job**: every customer design save is mirrored to cloud → cross-device resume, survives
site migration/uploads cleanup, reorder forever.

Flow (extends existing local save in `class-io.php` — local save is unchanged & remains
source of truth for free users):

```
Customer saves design (frontend)
→ local SVG+JSON write (unchanged)
→ if can('design_vault') AND consent: async POST (non-blocking, after local success)

POST /api/v1/vault/designs   { license_key, site_url, design_id, product_id,
                               customer_ref (hashed WP user id or guest token),
                               svg: <string ≤ 4 MB>, state_json: <string ≤ 1 MB> }
→ 201 { "remote_id": "...", "bytes": 412300, "quota": { "used_mb": 1240, "limit_mb": 5120 } }
→ 402/403 as §2.2 · → 413 { "error": "design_too_large" } · → 507 { "error": "quota_exceeded" }

GET  /api/v1/vault/designs?customer_ref=...        // list for resume/reorder UI
GET  /api/v1/vault/designs/{remote_id}             // fetch SVG+state
DELETE /api/v1/vault/designs/{remote_id}           // GDPR erasure — see 4.1.1
```

Rules:
- **Never block the customer**: vault POST is fire-and-forget with 1 retry (queued via
  Action Scheduler if WC's is available, else WP-cron single event). Failure = silent
  local-only, surfaced only in an admin sync-health widget.
- Quota: Standard 5 GB / B2B 20 GB. At ≥90%: admin notice (dismissible). At 100%: saves
  continue **local-only** + upsell; never an error to the customer (no dead ends).
- On license expiry: stored designs remain **readable/downloadable for 90 days** (grace,
  aligns O-2 philosophy "local data never destroyed" → cloud data never held hostage),
  then archived. No new uploads while expired.

#### 4.1.1 Privacy specifics (gates GDPR sign-off — assumption A3)
- `customer_ref` is a salted hash; no email/name leaves the site for vault purposes.
- Hook into WP personal-data eraser: erasing a user locally triggers DELETE of their
  vault designs. Declare all of §4 endpoints in External services (extends C3).

### 4.2 In-canvas Asset Library — `asset_library`

- New "Assets" panel in the Builder text/image tabs: search + browse cliparts, shapes,
  industry graphics, premium fonts — streamed from Storelly CDN.
- **Compliance-critical**: plugin loads **data + raster/SVG assets only**; all panel
  JS/CSS ships inside the plugin bundle (Guideline #8 — no remote executable code).
  SVGs are sanitized on the server before publication AND on import (existing sanitizer path).
- Free users: panel visible with **6 sample assets** bundled locally in the plugin zip
  (genuinely usable, not crippleware) + "Browse 500+ in Cloud" entry → upsell-by-intent.
- Inserted asset files are **copied into the design** (local SVG), so designs never break
  if the subscription lapses. Fonts: licensed webfonts are cached locally per site while
  cap is active; designs using a lapsed premium font fall back to closest bundled font
  with an admin warning (never a broken render).

```
GET /api/v1/assets?type=clipart|shape|font&industry=&q=&page=1   // requires license for full list
→ 200 { "assets": [{ "id","type","name","preview_url","file_url(signed)","license":"storelly-content" }], ... }
```

- Content production dependency: launch requires ≥ **150 assets across the 5 Tier-1
  industries** (Business Cards, T-shirts, Stickers, Mugs, Banners) — owner: content team
  (assumption A4).

### 4.3 Option Performance Analytics — `option_analytics`

> **⚠️ A5 RESOLVED (code audit 2026-06-07): the current `order_sync` payload does NOT contain
> per-line selected options.** `spbwc_run_order_sync()` (`includes/class-productbuilder-api.php:303-335`)
> sends only `product_id, variation_id, unit_price, quantity, product_type` + design PDF URLs.
> Therefore this feature **requires a payload extension** (NOT zero-data-path as the brainstorm
> assumed). Add a per-line `options[]` array (group key, value, surcharge, qty-break tier) derived
> from the order-item meta written by the builder, behind the existing `order_sync` consent + cap.
> This makes F8 depend on a payload change landing in/after F2 (see §10).

- Dashboard views: revenue per option group/value, attach rate, surcharge contribution,
  quantity-break tier distribution, abandonment proxy (synced carts optional — **out of
  scope v1**, orders only).
- Plugin surfaces one card on Overview ("Top earning option: Rush delivery — $840 this
  month") via `get_overview_stats()` — consistent with §3.3 framing.

### 4.4 Config Snapshots — `config_snapshots`

**Job**: safety net for the highest-anxiety merchant action (editing live pricing config).

- On every option-set save in admin: if cap active, push a snapshot (reuses the existing
  native JSON export serializer — no new format).

```
POST /api/v1/snapshots        { license_key, site_url, scope: "option_set", scope_id,
                                label (auto: "Before edit 2026-06-06 14:02"), json: ≤ 2 MB }
→ 201 { "snapshot_id", "count": 17, "limit": 30 }      // ring buffer: oldest auto-pruned
GET  /api/v1/snapshots?scope_id=...                     // history list
POST /api/v1/snapshots/{id}/restore-fetch               → 200 { json }   // plugin imports via existing validator
```

- Restore = **import through the existing import pipeline** (same validation), preceded by
  an automatic snapshot of the current state ("restore is also undoable").
- Limits: Standard 30 / B2B 100 snapshots per site (ring buffer). Snapshot ≤ 2 MB.
- Free users: the existing manual JSON export remains, with a one-line hint
  "Cloud keeps automatic version history" (dismissible, inline on the export row only).

---

## 5. (B) Pricing & plan presentation — closes O-5

### 5.1 Price list (D6)

| Plan | Monthly | Annual (2 months free) | Trial |
|---|---|---|---|
| Cloud Standard | **$49/mo** | **$490/yr** | 14-day full-B2B trial, no card (O-1) |
| Cloud B2B | **$99/mo** | **$990/yr** | same single trial |

- Defensibility: Standard ≈ replaces per-incident PDF/file-prep labor + premium content;
  B2B ≈ replaces B2BKing-class stack + bookkeeping hours (statement/dunning automation).
  Dashboard must keep proving this via §3.3/§4.3 revenue framing.
- Upgrade Standard→B2B: prorated by Stripe, server-side; plugin only refreshes caps.
- **No lifetime in v1** (D6). Launch promo (e.g. founding discount) is a Dashboard-side
  coupon concern — out of plugin scope.

### 5.2 In-plugin Plans page (`Storelly → Plans & Pricing`)

- New submenu, rendered from plugin-bundled assets. Pricing data fetched from
  `GET /api/v1/license/packages` **on page open only** (no background polling), cached in a
  transient (TTL 12h), with **static fallback table baked into the plugin** if API unreachable
  (prices may be stale → show "see storelly.com for current pricing" line under the table).
- Content: 2 plan cards + caps comparison (driven by §1.2 matrix), trial CTA primary,
  link "Already purchased? Enter license key" (fallback path D8).
- Compliance posture: page lives only under the Storelly menu; no admin-wide notices added
  by this feature (existing `SPBWC_Upsell_Notice` rules per v1.0 §5 unchanged).

---

## 6. (E) Activation flow — connect-back

### 6.1 Happy path (primary, D8)

```
 1. Admin on Plans page clicks [Start free trial] or [Choose Standard/B2B]
 2. Plugin generates state = wp_create_nonce-backed random (32 bytes), stores in
    transient 'spbwc_activation_state' (TTL 15 min, single use)
 3. Browser redirect →
    https://app.storelly.com/connect?intent=trial|checkout&plan=cloud-b2b-monthly
      &site_url={urlencoded}&admin_email={prefill}&return_url={admin_url}&state={state}
 4. On app.storelly.com:
      trial    → account create/login (email) → trial entitlement issued (O-1)
      checkout → Stripe Checkout (hosted; card never touches wp-admin — D7)
 5. Redirect back → {return_url}?spbwc_activation_token={one-time, TTL 10 min}&state={state}
 6. Plugin: verify state (matches transient, then delete transient) →
    server-to-server POST /api/v1/license/exchange { token, site_url }
    → 200 { license_key, status, plan_slug, expires_at, trial_ends_at, caps[], quota }
    → persist via existing sync_from_api() shape (§1.1)
 7. If cloud consent not yet given → render SPBWC_Cloud_Connect consent screen inline NOW
    (license without consent performs no cloud action — v1.0 §4.2 rule intact; A6 wiring confirmed in F6)
 8. Success screen: "Cloud activated — {plan}" + context-return CTA (§6.3)
```

### 6.2 API contract — token exchange

```
POST https://app.storelly.com/api/v1/license/exchange
  { "token": "<one-time>", "site_url": "https://shop.example.com" }
→ 200 { "license_key":"…","status":"trial|active","plan_slug":"…","expires_at":"…",
        "trial_ends_at":"…","caps":[…],"quota":{…} }
→ 400 { "error":"token_invalid_or_used" }     → UI: "Link expired — restart from Plans page"
→ 409 { "error":"site_mismatch" }             → UI: explain site_url binding + support link
→ 429 / 5xx                                    → UI: retry button (token still valid until TTL)
```

Security rules: token single-use + 10-min TTL; exchange is server-to-server (never AJAX from
browser with the token in JS-accessible storage beyond the immediate request); license bound
to `site_url` (transfer = Dashboard action, 3/yr policy carried from plan doc); `state`
verified before any token use (CSRF); all of this under `manage_options` only (§1.4).

### 6.3 Context-return (upsell-by-intent completion)

- When an upsell prompt (v1.0 §5) triggers the Plans redirect, it appends
  `&ctx={cap}:{object_id}` which round-trips through the flow.
- Success screen primary CTA = "Back to your order #1234 — Export PDF is now unlocked",
  deep-linking to the originating screen. The blocked control re-checks `can()` live.

### 6.4 Edge cases (minimum set)

1. **User abandons checkout** → returns manually → Plans page unchanged; stale state
   transient expires harmlessly.
2. **Double-click on CTA** → second redirect overwrites the state transient; only the
   latest state validates (first window's return fails safe with "restart" message).
3. **Return URL hit twice / token replay** → 400 token_invalid_or_used → restart message;
   no partial license state persisted.
4. **Network fail during exchange** → token kept (≤ TTL), explicit Retry; after TTL,
   restart-from-Plans message. Never a white screen.
5. **Multisite / staging clones**: exchange binds to `site_url` at purchase; clone gets
   409 site_mismatch → manual key path + transfer instructions.
6. **Agency bought on web first** (no redirect context) → manual key entry path; same
   `sync_from_api()` persistence; consent screen still enforced before first cloud action.
7. **Expiry mid-session**: a gated action after `expires_at` returns `spbwc_cloud_locked`
   with "renew" variant copy; per v1.0 §7 all local data/designs untouched; Vault grace per §4.1.

---

## 7. Compliance addendum (extends C1–C5; all D4-class, ship with F0/F1)

| ID | Item |
|----|------|
| C6 | External-services readme: add §2/§3/§4/§6 endpoints with exact data sent & when (templates, render/document, vault, assets, snapshots, exchange). One subsection per service. |
| C7 | Reconfirm: **no remote JS/CSS** in admin or frontend from any new feature (assets are data files only; demo links open externally). |
| C8 | Plans page & all new prompts: dismissible, in-plugin-pages only; re-run `wp plugin check` after F3/F6. |
| C9 | Privacy: personal-data exporter/eraser coverage for Vault (`customer_ref` mapping); privacy-policy suggestion text updated. |
| C10 | Free-tier integrity check: free sample assets usable standalone; free template download requires no account; quote/order PDF local fallback present (O-4) — zero crippleware posture preserved. |

---

## 8. Test scenarios (acceptance, per spec-writer gate)

1. **Happy activation (trial)**: free site → Plans → trial → returns → caps=B2B set →
   blocked "Export PDF" control now succeeds without page reload of context (≤ 2 clicks
   from success screen back to the order).
2. **Happy purchase (Standard)**: checkout → exchange → `can('invoice_pdf') === false`,
   `can('cloud_pdf') === true` — caps, not plan names, drive every gate.
3. **Validation**: premium template download with expired license → 403 mapped to inline
   upsell at the Download button; free template download with **no** license succeeds.
4. **Permission**: `shop_manager` without `manage_options` sees no Plans menu, cannot hit
   exchange handler (direct POST → 403).
5. **Edge — token replay** (§6.4.3) and **state mismatch** (CSRF attempt) both fail safe,
   no license written, error logged once (no log flooding).
6. **Edge — vault quota full**: customer save still succeeds locally; no frontend error;
   admin sees quota notice; upsell only in admin.
7. **Downgrade/expiry**: B2B → expired: dunning stops server-side; invoices fall back to
   local FPDI (watermark per O-4 decision); ledger/local B2B fully functional (D2 proof).
8. **Update path**: imported premium template v1.2 → marketplace shows update v1.3 →
   apply → pre-update snapshot exists → merchant edits preserved per §2.3 confirm step.
9. **Option analytics payload**: place an order on a builder product with ≥2 priced options →
   sync payload (§4.3) carries per-line `options[]` with surcharge + qty-break tier (regression
   guard for the A5 payload extension).

---

## 9. Out of scope (v1.1)

- Per-feature à-la-carte purchasing; usage-metered billing (credits)
- Cart-abandonment sync for option analytics (§4.3 — orders only)
- ERP/accounting connectors (QuickBooks/Xero) — placeholder cap names reserved, Year-2
- Designer marketplace / launcher monetization (pending O-3 audit)
- AI services (file pre-check, auto-quote) — deliberately deferred per product decision
- Lifetime plans; reseller/agency multi-license dashboard
- Asset Library user-uploaded/shared assets (Storelly-curated only in v1)

---

## 10. Milestones (extends v1.0 §9 — F0–F5 unchanged)

| MS | Scope | Depends on |
|----|-------|-----------|
| **F1.1** | Caps + `plan_slug` + `trial_ends_at` + `quota` in entitlement model; `sync_from_api()` maps server `caps[]`; `can($cap)` per §1.3 (lands inside F1) | F1 |
| **F6** | Plans page + connect-back activation (§5.2, §6) | F1.1, O-1, O-6 |
| **F7** | Marketplace split + versioned updates (§2) | F1.1, O-3 |
| **F8** | Option Analytics (§4.3) — **requires `order_sync` payload extension (A5)**, no longer free-riding | F2 **+ payload change** |
| **F9** | Design Vault (§4.1) | F1.1, A3 |
| **F10** | B2B service layer (§3) | F2, backend cron/email infra |
| **F11** | Asset Library (§4.2) | content production A4 |

Suggested order: **F0 → F1(+F1.1) → F2 → F3 → F6 → F8 → F9 → F7 → F10 → F11.**

> **Sequencing note (post-audit):** F8 was originally pitched as "cheapest, rides existing sync".
> Because A5 came back negative, F8 now carries a payload change. It is still cheap (one array on
> an existing POST) but is **no longer zero-risk** — schedule the payload extension as the first
> task of F8 and gate the new field behind the same `order_sync` consent.

---

## 11. Open items (new; O-1..O-4 remain open per v1.0)

| ID | Question | Owner |
|----|----------|-------|
| O-5 | ~~Pricing/plan split~~ → **CLOSED by D5/D6** | — |
| O-6 | Dashboard support for connect-back: `/connect` intent screen + one-time token issuance + `/license/exchange` | backend |
| O-7 | Stripe proration behavior on Standard→B2B mid-cycle confirmed? | backend |
| O-8 | Server email infra for dunning/statements (domain, DKIM, per-merchant from-name policy) | backend |
| O-9 | Vault retention legalese: 90-day post-expiry grace wording in ToS | product/legal |

## 12. ⚠️ Assumptions — confirm before dev start

| # | Assumption | Status / Confirm with |
|---|-----------|-----------------------|
| A1 | `app.storelly.com` can issue **no-card 14-day trial** entitlements (= O-1) | OPEN — @backend |
| A2 | Stripe Checkout (hosted) is the processor; PayPal not required at launch | OPEN — @david |
| A3 | Vault privacy model (salted `customer_ref`, eraser hook, 90-day grace) passes GDPR review | OPEN — @legal |
| A4 | Content team can produce ≥150 launch assets for Tier-1 industries before F11 | OPEN — @david |
| A5 | ~~order_sync payload already includes per-line selected options~~ | **RESOLVED — NEGATIVE (2026-06-07).** Payload lacks options; F8 must extend it (§4.3, §10) |
| A6 | Existing `SPBWC_Cloud_Connect` consent screen can be invoked inline post-activation without refactor | LOW-RISK — connect AJAX flow + `is_connected()` exist; render entry point confirmed in F6 |
