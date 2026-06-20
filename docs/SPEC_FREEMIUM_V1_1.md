# Storelly — Freemium & Cloud Monetization Spec **v1.1**

> **Status**: DRAFT for review — extends, does not replace, `SPEC_FREEMIUM.md` (v1.0)
> **Date**: 2026-06-09 (rev. after scope trim — see §0.3)
> **Owner**: David / Netbase
> **Mode**: UPDATE (major scope additions → new version file per spec-writer convention)
> **Supersedes**: §2.2 marketplace row of v1.0; closes open item **O-5**; adds **O-6..O-9**
> **Related**: `SPEC_FREEMIUM.md`, `SPEC_M5_CLOUD_CONSENT.md`, `SPEC_B2B_CLIENT.md`,
> `SPEC_B2B_ACCOUNT_CREDIT.md`, `SPEC_EMAIL_SYSTEM.md`, `SPEC_TEMPLATE_PREVIEW.md`,
> `wp-org-compliance-audit-plan.md`

---

## 0. What v1.1 adds (executive summary)

v1.0 locked the strategy: **Local = Free, Cloud = Paid** (D1–D4 unchanged, not relitigated here).
v1.1 turns that boundary into a sellable product line:

| # | Addition | Closes |
|---|----------|--------|
| A | **Two-plan model**: Cloud Standard **$49/mo** · Cloud B2B **$99/mo** (+annual) via `caps[]` | **O-5** |
| B | **B2B service layer** (invoice PDF, **email triggers / scheduled sends**, statements, B2B analytics) — the reason B2B costs $99 | new |
| C | **Cloud data backbone + high-frequency services**: Order→Dashboard sync, Design Vault, Asset Library, Option Analytics, Config Snapshots | new |
| D | **In-plugin Plans page** + **connect-back activation flow** (purchase → token → auto-license) | new, adds O-6 |
| E | **Template marketplace stays fully free** (browse / preview / download / updates) — confirmed, no premium split | tidies v1.0 §2.2 |

Locked decisions carried forward: **D1** (feature+cloud boundary, not product count),
**D2** (Quote/Custom Order/engines stay free local), **D4** (compliance cleanup C1–C5 ships first).
**D3 is amended** — see §0.3 (no trial at all now).

### 0.1 New locked decisions (v1.1)

| # | Decision | Choice |
|---|----------|--------|
| D5 | Plan shape | **Two plans**: `cloud-standard` / `cloud-b2b`, monthly + annual. No per-feature à-la-carte in v1. |
| D6 | Pricing | Standard **$49/mo or $490/yr**; B2B **$99/mo or $990/yr** (annual = 2 months free). Lifetime: **not offered** in v1 (revisit post-launch). |
| D7 | Payment processing | **Entirely on `app.storelly.com`.** The plugin is **payment-agnostic** — it never sees a gateway, card, or checkout page. Whatever gateway Storelly uses is the Storelly API's concern; the plugin only receives the resulting license through connect-back (§6). |
| D8 | Activation | **Connect-back flow** (redirect → one-time token → auto-license) is the primary path; manual license key entry remains as fallback. |
| D9 | Template marketplace | **All templates free** — browse, preview, download, and versioned updates require **no license** (anonymous GET, same posture as remote demo import). No premium tier, no `marketplace_premium` cap. Marketplace is a free acquisition surface, not a monetized one. |
| D10 | B2B engine vs B2B services | All B2B **engines stay free local** (per D2, incl. ledger M1–M5). The $99 plan sells only **server-side services** that consume the local data. |

### 0.2 Code-audit results — assumptions resolved during spec authoring

The v1.0/v1.1 entitlement layer is **not yet implemented in code**. Audited 2026-06-07:

| Item | Finding | Consequence for this spec |
|------|---------|---------------------------|
| **A5** — does the legacy order-sync payload carry per-line selected options? | **NO.** `SPBWC_Productbuilder_Api::spbwc_run_order_sync()` (`includes/class-productbuilder-api.php:303-335`) sends per-item only `product_id, variation_id, unit_price, quantity, product_type` + design PDF URLs in `shipping_documents`. No structured pricing-option selections. | `order_sync` is the **re-added backbone (§4.0)**: its `update-orders` payload is **extended once** with per-line `options[]`, and Option Analytics (§4.3) computes from that feed. So the audit's takeaway is "extend the one payload", not "build N bespoke payloads". |
| **Entitlement shape** — current `get_current_license()` | Returns `status, package_name, package_slug, expires_at, max_products, max_orders, max_pricing_options, features, synced_at` (`includes/class-license-manager.php:104-114`). **No `caps[]`, no `plan_slug`, no `cloud_license_active()`, no `can()`.** `sync_from_api()` reads `package.slug` / `package.features`, not `caps`. | **F1.1 must add** `caps[]` + `plan_slug` to the data shape and map them from the server response in `sync_from_api()`. `cloud_license_active()` (v1.0 §4.1) and `can($cap)` (§1.3) are **net-new**, not edits. |
| **A6** — can `SPBWC_Cloud_Connect` consent be invoked inline post-activation without refactor? | **Plausible.** `SPBWC_Cloud_Connect` exposes `init / ajax_connect / connect() / is_connected()` (`includes/class-cloud-connect.php`) — a self-contained AJAX connect flow with `is_connected()`. The consent *render* entry point for §6.1 step 7 must be confirmed against the M5 consent screen, but no structural blocker found. | Keep A6 **low-risk**; §6.1 step 7 wiring confirmed during F6. |

### 0.3 Scope trim (2026-06-09 — supersedes parts of the 2026-06-07 draft)

Five product calls were made after the first draft. They are now binding:

1. **No Stripe / no plugin-side payment.** Payment + gateway live entirely on the Storelly API
   (D7). All Stripe-specific wording removed; the plugin only does the connect-back token exchange.
2. **Template marketplace is fully free** (D9). The premium-template split and the
   `marketplace_premium` cap are **removed**. Versioned updates stay free.
3. **No trial.** The 14-day trial is **removed entirely** (amends v1.0 D3). `status` has no
   `trial` value, there is no `trial_ends_at`. Funnel = generous free local value + upsell-by-intent
   at the cloud touch points (no taste-the-paid-tier on-ramp). Open item O-1 is **moot/closed**.
4. **Order-sync RE-ADDED as the cloud data backbone (`order_sync` cap, Standard + B2B).**
   *(Reverses the 2026-06-08 "drop order_sync" call after a benefit analysis — see §4.0.)* Re-adding
   it is not just acceptable, it is **architecturally cheaper**: instead of each analytics/B2B
   service shipping its own bespoke payload + consent + External-services entry (the fragmentation
   the trim created), there is **one canonical order feed**, extended **once** (A5: add `options[]`;
   B2B: add ledger events). `option_analytics`, `analytics_b2b`, and `email_trigger` go back to
   *computing on already-synced data*. Merchant benefit: production handoff (print-ready PDF +
   design files travel to the Dashboard for fulfillment staff with no WP login), central/multi-device
   order management, and analytics WooCommerce can't give natively. **Condition:** benefit exists
   only if the Dashboard order surface is a live product — gate the build on the O-3 liveness audit
   (if dormant fork scaffolding, do not sell it). PII posture: derived/normalized payload, strict
   consent, single External-services declaration (C6).
5. **Email triggers become a paid B2B service (new cap `email_trigger`).** Storelly's B2B emails
   and emails carrying **file attachments** fire locally & immediately for free; the **"trigger time"
   layer** (server-driven scheduling/timing of sends, fetched/controlled from the Storelly API) must
   be **upgraded to Premium to activate**. This replaces the separate `dunning` + `statements` caps —
   both are now use-cases of `email_trigger` (§3.2).

---

## 1. Entitlement model v1.1 — plans and caps

### 1.1 License data shape (extends v1.0 §4.3 — field semantics unchanged)

```
status        : 'free' | 'active' | 'expired'
plan_slug     : 'cloud-standard-monthly' | 'cloud-standard-annual'
              | 'cloud-b2b-monthly'      | 'cloud-b2b-annual'
expires_at    : ISO 8601 | null
caps          : string[]            // authoritative, server-issued — see 1.2
quota         : { design_vault_mb: int, used_mb: int }   // soft display only, server enforces
```

> **Migration note (from audited code):** the existing option (`SPBWC_License_Manager::OPTION_KEY`)
> stores `package_slug` + `max_*` + `features`. F1.1 adds `plan_slug`, `caps`, `quota`. Keep
> `max_*`/`features` readable for one release (no fatal on missing keys) but they **no longer gate
> anything** (v1.0 §1.2). `sync_from_api()` must map a server-issued `caps[]` array; if absent, fall
> back to `features` → empty caps (= Free). There is **no `trial` status** (§0.3 item 3).

### 1.2 Caps matrix (single source of truth)

| Cap | Free | Standard $49 | B2B $99 | Service it gates |
|---|:---:|:---:|:---:|---|
| `cloud_pdf` | ❌ | ✅ | ✅ | Cloud2Print print-ready PDF (v1.0 §2.2) |
| `order_sync` | ❌ | ✅ | ✅ | **Order → Dashboard sync — the data backbone** (§4.0): production handoff + feeds all analytics |
| `design_vault` | ❌ | ✅ 5 GB | ✅ 20 GB | Customer design backup/cross-device (§4.1) |
| `asset_library` | ❌ | ✅ | ✅ | In-canvas hosted cliparts/fonts (§4.2) |
| `option_analytics` | ❌ | ✅ | ✅ | Per-option revenue analytics (§4.3) |
| `config_snapshots` | ❌ | ✅ 30 versions | ✅ 100 versions | Option-set version history (§4.4) |
| `analytics_b2b` | ❌ | ❌ | ✅ | Account analytics, aging, DSO (§3.3) |
| `invoice_pdf` | ❌ | ❌ | ✅ | Branded invoice/quote/statement doc render (§3.1) |
| `email_trigger` | ❌ | ❌ | ✅ | **Trigger-time / scheduled sends** for B2B & attachment emails (§3.2) |

> Removed vs the 2026-06-07 draft: `marketplace_premium` (deleted per §0.3 — templates all free),
> the **Trial** column, and the separate `dunning`/`statements` caps (folded into `email_trigger`).
> `order_sync` was briefly dropped then **re-added** as the backbone (§0.3 item 4, §4.0).

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
| Start checkout redirect | `manage_options` |
| Receive activation token & store license | `manage_options` + valid `state` nonce |
| Deactivate / change license | `manage_options` |
| Invoke gated cloud actions (export PDF…) | per existing feature permissions; cap check is additive |

---

## 2. Template Marketplace — fully free (D9)

The marketplace is **not** a monetized surface. It is the free acquisition channel. All four
behaviors are free, require no license, and never call a gated path.

### 2.1 Behavior matrix

| Behavior | Gate | Notes |
|---|---|---|
| Browse catalogue (list, search, filter by industry) | **None** | Sales/acquisition surface |
| Preview: screenshots + **live demo link** per template | **None** | Demo products hosted on `demo.storelly.com`; link opens new tab — no remote code in admin |
| Download any template | **None** (anonymous GET) | Same compliance posture as remote demo import (v1.0 §2.3.2). Declared in External services. |
| Receive **template updates** | **None** | Free recurring value; see 2.3 |

### 2.2 Catalogue API contract

```
GET https://app.storelly.com/api/v1/templates?industry=&page=1
→ 200 {
    "templates": [{
      "id": "tpl_mug_classic", "name": "...", "industry": "mug",
      "version": "1.3.0",
      "preview": { "screenshots": ["https://cdn..."], "demo_url": "https://demo.storelly.com/..." },
      "summary": "...", "options_count": 14, "updated_at": "ISO"
    }],
    "total": 42, "page": 1, "per_page": 20
  }
→ 4xx/5xx → plugin shows cached catalogue (transient, TTL 12h) + retry notice.

GET /api/v1/templates/{id}/download    // anonymous — all templates
→ 200 { "package": { ...preset JSON schema (per ICP Catalog)... }, "version": "1.3.0" }
```

### 2.3 Versioned updates (free recurring value)

- Plugin stores `_spbwc_template_source = { id, version }` on every imported template/preset.
- Admin-triggered (or on marketplace page open) check: `GET /api/v1/templates/updates` with body of
  installed `{id, version}` pairs → list of available updates + changelogs.
- "Update available" badge on the marketplace page; applying an update is **free**.
- Applying an update **never overwrites merchant edits silently**: show diff summary (option groups
  added/changed) → merchant confirms → snapshot current config first (reuses §4.4 Config Snapshots
  if licensed, else local JSON export to uploads).

### 2.4 Validation & limits

- Template package JSON ≤ **2 MB**; reject larger with explicit error.
- Imported preset must pass existing import validator (same path as PrintCart adapter import).
- Catalogue page size 20, max 100 per request.

---

## 3. (B) B2B service layer — what $99/mo actually buys

Context: per D2/D10, the entire local B2B suite (company CPT, tier pricing, wallet/net-terms/
rebate ledger `spbwc_b2b_ledger` M1–M5, approval flow) is **free**. The B2B plan sells server-side
services consuming that local data. No local B2B feature is removed or gated.

> **Data feed:** B2B services compute on the **`order_sync` backbone (§4.0)**, which is extended for
> B2B with **ledger events** (charge/payment/due_date per company). One canonical feed — no bespoke
> per-service payloads. Derived/normalized, never raw DB; gated by `order_sync` + each service's cap.

### 3.1 Branded document render — `invoice_pdf`

- Reuses the Cloud2Print render pipeline with **document templates** (invoice, quote-doc, credit
  statement) instead of print files.
- Entry points: order admin ("Generate invoice PDF"), quote detail ("Quote PDF" — for the B2B plan;
  Standard plan still gets the local FPDI/watermark fallback per O-4), company Account-credit tab
  ("Statement PDF").

```
POST /api/v1/render/document     { license_key, site_url, type: "invoice"|"quote"|"statement",
                                   payload: { ...normalized order/quote/ledger data... },
                                   branding: { logo_url, colors, footer_text } }
→ 200 { "pdf_url": "https://cdn... (signed, TTL 24h)", "render_id": "..." }
→ 402/403 (cloud locked) · → 422 { "error": "payload_invalid", "fields": [...] }
```

- Payload is **derived, not raw DB**: amounts, line items, company name/address, due_date. No WP
  credentials, no password hashes, no unrelated PII. Field list frozen in the External-services
  readme entry (extends C3).

### 3.2 Email triggers & scheduled sends — `email_trigger`  *(was: dunning + statements)*

Builds on the unified Storelly email system (`SPEC_EMAIL_SYSTEM.md` — all emails prefix `spbwc_`,
WC_Email-based, `spbwc_email_log` table). This is the **"Premium layer"** that spec flagged as TODO.

- **Free (local, immediate):** every Storelly email — including B2B emails and emails with **file
  attachments** — fires on its WP/Woo event, immediately, via WC_Email. No scheduling.
- **Premium (`email_trigger`):** activates the **"trigger time"** section of the email settings.
  Send *timing* is fetched from / controlled by the Storelly API: scheduled, delayed, and recurring
  triggers. Use-cases:
  - **Dunning sequences**: reminder emails at **due−3d, due, due+7d, due+30d** (merchant-configurable
    on Dashboard; sensible defaults). Escalation copy per stage.
  - **Monthly statements**: scheduled per-company statement email (+ §3.1 PDF) to company owner;
    merchant gets a digest.
  - **Follow-ups / nurture** on B2B quotes/orders.
- **Where the timing runs:** the **Storelly server** owns the schedule and (for reliability +
  deliverability) sends scheduled emails server-side — that is *why* it is a paid service rather than
  WP-cron. From-name = store name; reply-to = merchant email.
- **Plugin side:** the "Trigger time" UI block in Storelly › Emails is **locked for free/Standard**
  (shows the upsell-by-intent prompt). Without the cap, emails still send immediately (no dead end).
- Kill-switch per company ("exclude from scheduled emails") synced as a flag (company detail →
  Account credit tab).

```
GET  /api/v1/email-triggers                 // returns merchant trigger-time config (schedules)
POST /api/v1/email-triggers/events          { license_key, site_url, company_id,
                                              event: "ledger_charge"|"due_date"|"statement_due",
                                              payload: { ...derived ledger/due data... } }
→ 200 { "scheduled": true, "next_send_at": "ISO" }
→ 402/403 (cloud locked → email_trigger upsell)
```

### 3.3 B2B analytics — `analytics_b2b`

- Dashboard views computed server-side from the `order_sync` feed + B2B ledger events (§4.0): top accounts by revenue,
  outstanding & aging buckets (0/30/60/90 — mirrors local `get_aging()`), DSO trend, churn-risk (no
  orders in N days vs account average), per-company LTV.
- Plugin side: a read-only summary card on the Overview page via the existing `get_overview_stats()`
  channel — **no new plugin endpoint**.
- Revenue framing requirement (UX principle): the headline metric is **"Outstanding collected this
  month: $X"** and **"Reminders sent: N → est. hours saved: H"** (H = N × 10 min default,
  merchant-adjustable) — not vanity counts.

---

## 4. (C) Cloud data backbone + high-frequency services

Design rule: engines/preview stay local (D2; real-time perf). Cloud bites only where there is
**storage, hosted content, or computation on synced data** — real marginal cost.

### 4.0 Order → Dashboard sync — the data backbone — `order_sync`

**Why it exists (benefit, per §0.3 item 4):** one canonical order feed that (a) carries the
print-ready PDF + design files to the Storelly Dashboard for **production handoff** (fulfillment
staff work there, no WP login), (b) gives **central/multi-device order management**, and (c) is the
**single data source** that `option_analytics`, `analytics_b2b`, and `email_trigger` all compute on —
so those features stay cheap ("ride existing sync") instead of each shipping a bespoke payload.

**Built on existing code:** `SPBWC_Productbuilder_Api::spbwc_run_order_sync()` already POSTs orders
to `/api/v1/update-orders` (Action Scheduler, gated by `enable_api_sync` consent). v1.1 work =
(1) add the `cloud_license_active()` + `can('order_sync')` gate on top of the consent flag (v1.0 §4.2
two-condition rule); (2) **extend the payload once** with the data the audit found missing:

```
POST /api/v1/update-orders   { ...existing fields...,
    "products": [{ product_id, variation_id, unit_price, quantity, product_type,
                   "options": [{ group_key, value, surcharge, qty_break_tier }],   // NEW (A5)
                   "design_files": [ signed_url, ... ] }],
    "b2b": { company_id, ledger_events: [{ type, amount, due_date }] }             // NEW, B2B only
}
```

Rules:
- **Two-condition gate**: fires only if `enable_api_sync` consent **AND** `can('order_sync')`.
  A free/expired store that consented but has no license → no upload + upsell-by-intent (never silent).
- **Derived payload, declared PII**: amounts, line items, selected options, company name/address,
  due dates. No WP credentials/password hashes. Frozen field list in External-services (C6).
- **Async + non-blocking**: stays on Action Scheduler; a failed sync never blocks checkout.
- **⚠️ Liveness condition (O-3):** this only delivers merchant value if the Dashboard order surface
  is a **live product**, not dormant cmsmart fork scaffolding. Confirm O-3 before building F8/F10.
  If dormant → do not sell `order_sync`; fall back to bespoke analytics payloads.

### 4.1 Design Vault — `design_vault`

**Job**: every customer design save is mirrored to cloud → cross-device resume, survives site
migration/uploads cleanup, reorder forever.

Flow (extends existing local save in `class-io.php` — local save is unchanged & remains source of
truth for free users):

```
Customer saves design (frontend)
→ local SVG+JSON write (unchanged)
→ if can('design_vault') AND consent: async POST (non-blocking, after local success)

POST /api/v1/vault/designs   { license_key, site_url, design_id, product_id,
                               customer_ref (hashed WP user id or guest token),
                               svg: <string ≤ 4 MB>, state_json: <string ≤ 1 MB> }
→ 201 { "remote_id": "...", "bytes": 412300, "quota": { "used_mb": 1240, "limit_mb": 5120 } }
→ 402/403 (cloud locked) · → 413 { "error": "design_too_large" } · → 507 { "error": "quota_exceeded" }

GET  /api/v1/vault/designs?customer_ref=...        // list for resume/reorder UI
GET  /api/v1/vault/designs/{remote_id}             // fetch SVG+state
DELETE /api/v1/vault/designs/{remote_id}           // GDPR erasure — see 4.1.1
```

Rules:
- **Never block the customer**: vault POST is fire-and-forget with 1 retry (queued via Action
  Scheduler if WC's is available, else WP-cron single event). Failure = silent local-only, surfaced
  only in an admin sync-health widget.
- Quota: Standard 5 GB / B2B 20 GB. At ≥90%: admin notice (dismissible). At 100%: saves continue
  **local-only** + upsell; never an error to the customer (no dead ends).
- On license expiry: stored designs remain **readable/downloadable for 90 days** (grace, aligns O-2
  philosophy "local data never destroyed" → cloud data never held hostage), then archived. No new
  uploads while expired.

#### 4.1.1 Privacy specifics (gates GDPR sign-off — assumption A3)
- `customer_ref` is a salted hash; no email/name leaves the site for vault purposes.
- Hook into WP personal-data eraser: erasing a user locally triggers DELETE of their vault designs.
  Declare all of §3/§4 endpoints in External services (extends C3).

### 4.2 In-canvas Asset Library — `asset_library`

- New "Assets" panel in the Builder text/image tabs: search + browse cliparts, shapes, industry
  graphics, premium fonts — streamed from Storelly CDN.
- **Compliance-critical**: plugin loads **data + raster/SVG assets only**; all panel JS/CSS ships
  inside the plugin bundle (Guideline #8 — no remote executable code). SVGs are sanitized on the
  server before publication AND on import (existing sanitizer path).
- Free users: panel visible with **6 sample assets** bundled locally in the plugin zip (genuinely
  usable, not crippleware) + "Browse 500+ in Cloud" entry → upsell-by-intent.
- Inserted asset files are **copied into the design** (local SVG), so designs never break if the
  subscription lapses. Fonts: licensed webfonts are cached locally per site while cap is active;
  designs using a lapsed premium font fall back to closest bundled font with an admin warning (never
  a broken render).

```
GET /api/v1/assets?type=clipart|shape|font&industry=&q=&page=1   // requires license for full list
→ 200 { "assets": [{ "id","type","name","preview_url","file_url(signed)","license":"storelly-content" }], ... }
```

- Content production dependency: launch requires ≥ **150 assets across the 5 Tier-1 industries**
  (Business Cards, T-shirts, Stickers, Mugs, Banners) — owner: content team (assumption A4).

### 4.3 Option Performance Analytics — `option_analytics`

> **A5 note (code audit 2026-06-07):** the existing `order_sync` payload sends only ids/price/qty +
> PDF URLs — **no selected options**. This feature does **not** get its own payload; instead it
> **rides the `order_sync` backbone (§4.0)**, which is extended once with the per-line `options[]`
> array. `option_analytics` is therefore a server-side *view* over already-synced data and
> **requires `order_sync` to be active** (consent + cap).

- Computed server-side from the `order_sync` feed (§4.0 `options[]`): revenue per option
  group/value, attach rate, surcharge contribution, quantity-break tier distribution.
  (Cart-abandonment proxy = **out of scope v1**, orders only.)
- Plugin surfaces one card on Overview ("Top earning option: Rush delivery — $840 this month") via
  `get_overview_stats()` — consistent with §3.3 framing.

### 4.4 Config Snapshots — `config_snapshots`

**Job**: safety net for the highest-anxiety merchant action (editing live pricing config).

- On every option-set save in admin: if cap active, push a snapshot (reuses the existing native JSON
  export serializer — no new format).

```
POST /api/v1/snapshots        { license_key, site_url, scope: "option_set", scope_id,
                                label (auto: "Before edit 2026-06-06 14:02"), json: ≤ 2 MB }
→ 201 { "snapshot_id", "count": 17, "limit": 30 }      // ring buffer: oldest auto-pruned
GET  /api/v1/snapshots?scope_id=...                     // history list
POST /api/v1/snapshots/{id}/restore-fetch               → 200 { json }   // plugin imports via existing validator
```

- Restore = **import through the existing import pipeline** (same validation), preceded by an
  automatic snapshot of the current state ("restore is also undoable").
- Limits: Standard 30 / B2B 100 snapshots per site (ring buffer). Snapshot ≤ 2 MB.
- Free users: the existing manual JSON export remains, with a one-line hint "Cloud keeps automatic
  version history" (dismissible, inline on the export row only).

---

## 5. (A) Pricing & plan presentation — closes O-5

### 5.1 Price list (D6)

| Plan | Monthly | Annual (2 months free) |
|---|---|---|
| Cloud Standard | **$49/mo** | **$490/yr** |
| Cloud B2B | **$99/mo** | **$990/yr** |

- Defensibility: Standard ≈ replaces per-incident PDF/file-prep labor; B2B ≈ replaces B2BKing-class
  stack + bookkeeping hours (statement/email-trigger automation). Dashboard must keep proving this via
  §3.3/§4.3 revenue framing.
- Upgrade Standard→B2B: **proration handled server-side on `app.storelly.com`** (gateway-agnostic,
  D7); plugin only refreshes caps.
- **No lifetime, no trial in v1** (D6, §0.3). Launch promo (founding discount) is a Dashboard-side
  coupon concern — out of plugin scope.

### 5.2 In-plugin Plans page (`Storelly → Plans & Pricing`)

- New submenu, rendered from plugin-bundled assets. Pricing data fetched from
  `GET /api/v1/license/packages` **on page open only** (no background polling), cached in a transient
  (TTL 12h), with **static fallback table baked into the plugin** if API unreachable (prices may be
  stale → show "see storelly.com for current pricing" under the table).
- Content: 2 plan cards + caps comparison (driven by §1.2 matrix), **"Choose plan / Upgrade" CTA
  primary**, link "Already purchased? Enter license key" (fallback path D8).
- Compliance posture: page lives only under the Storelly menu; no admin-wide notices added by this
  feature (existing `SPBWC_Upsell_Notice` rules per v1.0 §5 unchanged).

---

## 6. (D) Activation flow — connect-back

### 6.1 Happy path (primary, D8)

```
 1. Admin on Plans page clicks [Choose Standard] / [Choose B2B]
 2. Plugin generates state = wp_create_nonce-backed random (32 bytes), stores in
    transient 'spbwc_activation_state' (TTL 15 min, single use)
 3. Browser redirect →
    https://app.storelly.com/connect?intent=checkout&plan=cloud-b2b-monthly
      &site_url={urlencoded}&admin_email={prefill}&return_url={admin_url}&state={state}
 4. On app.storelly.com: account create/login → payment on Storelly's own gateway
    (plugin is payment-agnostic; card/checkout never touch wp-admin — D7)
 5. Redirect back → {return_url}?spbwc_activation_token={one-time, TTL 10 min}&state={state}
 6. Plugin: verify state (matches transient, then delete transient) →
    server-to-server POST /api/v1/license/exchange { token, site_url }
    → 200 { license_key, status, plan_slug, expires_at, caps[], quota }
    → persist via existing sync_from_api() shape (§1.1)
 7. If cloud consent not yet given → render SPBWC_Cloud_Connect consent screen inline NOW
    (license without consent performs no cloud action — v1.0 §4.2 rule intact; A6 wiring confirmed in F6)
 8. Success screen: "Cloud activated — {plan}" + context-return CTA (§6.3)
```

### 6.2 API contract — token exchange

```
POST https://app.storelly.com/api/v1/license/exchange
  { "token": "<one-time>", "site_url": "https://shop.example.com" }
→ 200 { "license_key":"…","status":"active","plan_slug":"…","expires_at":"…","caps":[…],"quota":{…} }
→ 400 { "error":"token_invalid_or_used" }     → UI: "Link expired — restart from Plans page"
→ 409 { "error":"site_mismatch" }             → UI: explain site_url binding + support link
→ 429 / 5xx                                    → UI: retry button (token still valid until TTL)
```

Security rules: token single-use + 10-min TTL; exchange is server-to-server (never AJAX from browser
with the token in JS-accessible storage beyond the immediate request); license bound to `site_url`
(transfer = Dashboard action, 3/yr policy carried from plan doc); `state` verified before any token
use (CSRF); all of this under `manage_options` only (§1.4).

### 6.3 Context-return (upsell-by-intent completion)

- When an upsell prompt (v1.0 §5) triggers the Plans redirect, it appends `&ctx={cap}:{object_id}`
  which round-trips through the flow.
- Success screen primary CTA = "Back to your order #1234 — Export PDF is now unlocked", deep-linking
  to the originating screen. The blocked control re-checks `can()` live.

### 6.4 Edge cases (minimum set)

1. **User abandons checkout** → returns manually → Plans page unchanged; stale state transient
   expires harmlessly.
2. **Double-click on CTA** → second redirect overwrites the state transient; only the latest state
   validates (first window's return fails safe with "restart" message).
3. **Return URL hit twice / token replay** → 400 token_invalid_or_used → restart message; no partial
   license state persisted.
4. **Network fail during exchange** → token kept (≤ TTL), explicit Retry; after TTL, restart message.
   Never a white screen.
5. **Multisite / staging clones**: exchange binds to `site_url` at purchase; clone gets 409
   site_mismatch → manual key path + transfer instructions.
6. **Agency bought on web first** (no redirect context) → manual key entry path; same
   `sync_from_api()` persistence; consent screen still enforced before first cloud action.
7. **Expiry mid-session**: a gated action after `expires_at` returns `spbwc_cloud_locked` with
   "renew" variant copy; per v1.0 §7 all local data/designs untouched; Vault grace per §4.1.

---

## 7. Compliance addendum (extends C1–C5; all D4-class, ship with F0/F1)

| ID | Item |
|----|------|
| C6 | External-services readme: add §2/§3/§4/§6 endpoints with exact data sent & when (templates, order sync `update-orders` incl. options[]+design files+B2B ledger, render/document, email-triggers, vault, assets, snapshots, exchange). One subsection per service. |
| C7 | Reconfirm: **no remote JS/CSS** in admin or frontend from any new feature (assets are data files only; demo links open externally). |
| C8 | Plans page & all new prompts: dismissible, in-plugin-pages only; re-run `wp plugin check` after F3/F6. |
| C9 | Privacy: personal-data exporter/eraser coverage for Vault (`customer_ref` mapping); privacy-policy suggestion text updated. |
| C10 | Free-tier integrity check: free sample assets usable standalone; **all templates download with no account**; emails send locally with attachments for free (only trigger-time scheduling is paid); quote/order PDF local fallback present (O-4) — zero crippleware posture preserved. |

---

## 8. Test scenarios (acceptance, per spec-writer gate)

1. **Happy purchase (B2B)**: free site → Plans → Choose B2B → pay on app.storelly.com → returns →
   exchange → caps=B2B set → blocked "Export PDF" control now succeeds without a context page reload
   (≤ 2 clicks from success screen back to the order).
2. **Caps drive gates, not plan names**: Standard license → `can('cloud_pdf') === true`,
   `can('invoice_pdf') === false`, `can('email_trigger') === false`.
3. **Template free path**: download any template with **no** license succeeds; applying a template
   update with no license succeeds (no premium gate anywhere in §2).
4. **Permission**: `shop_manager` without `manage_options` sees no Plans menu, cannot hit exchange
   handler (direct POST → 403).
5. **Edge — token replay** (§6.4.3) and **state mismatch** (CSRF attempt) both fail safe, no license
   written, error logged once (no log flooding).
6. **Edge — vault quota full**: customer save still succeeds locally; no frontend error; admin sees
   quota notice; upsell only in admin.
7. **Email trigger gate**: free/Standard site → B2B email fires **immediately on its WP event** with
   attachment intact; the "Trigger time" settings block is locked → upsell. B2B site → trigger-time
   schedule is fetched from API and dunning/statement sends are scheduled server-side.
8. **Downgrade/expiry**: B2B → expired: scheduled email triggers stop server-side, emails revert to
   immediate local send; invoices fall back to local FPDI (watermark per O-4); ledger/local B2B fully
   functional (D2 proof).
9. **Order sync payload + analytics view**: with `order_sync` active, an order on a builder product
   with ≥2 priced options → the `update-orders` payload (§4.0) carries per-line `options[]` (surcharge
   + qty-break tier) and design-file URLs; Option Analytics (§4.3) renders the per-option view from it.
   With `order_sync` inactive (free) → no upload, upsell shown.

---

## 9. Out of scope (v1.1)

- Per-feature à-la-carte purchasing; usage-metered billing (credits)
- Plugin-side payment / gateway integration (Storelly API owns it entirely — D7)
- Premium template tier (all templates free — D9)
- Trial / free-tier on-ramp (removed — §0.3 item 3)
- Cart-abandonment sync for option analytics (§4.3 — orders only)
- ERP/accounting connectors (QuickBooks/Xero) — placeholder cap names reserved, Year-2
- Designer marketplace / launcher monetization (pending O-3 audit)
- AI services (file pre-check, auto-quote) — deliberately deferred
- Lifetime plans; reseller/agency multi-license dashboard
- Asset Library user-uploaded/shared assets (Storelly-curated only in v1)

---

## 10. Milestones (extends v1.0 §9 — F0–F5 unchanged)

| MS | Scope | Depends on |
|----|-------|-----------|
| **F1.1** | Caps + `plan_slug` + `quota` in entitlement model; `sync_from_api()` maps server `caps[]`; `can($cap)` per §1.3 (lands inside F1) | F1 |
| **F6** | Plans page + connect-back activation (§5.2, §6) | F1.1, O-6 |
| **F7** | Free marketplace + versioned updates (§2) — confirm/clean existing marketplace code is unlicensed | F1.1, O-3 |
| **F8** | Order sync backbone (§4.0): ✅ **gate DONE (2026-06-20, v1.7.0)** — `spbwc_run_order_sync` + `spbwc_maybe_queue_order_sync` now two-condition gated via `spbwc_order_sync_allowed()` (consent AND `can('order_sync')`). _Still pending_: extend payload with per-line `options[]` (A5) → then Option Analytics (§4.3) as a view over it | F1.1, F2, **O-3 liveness** |
| **F9** | Design Vault (§4.1) | F1.1, A3 |
| **F10** | B2B service layer (§3): `invoice_pdf`, `email_trigger`, `analytics_b2b`; B2B ledger events extend the §4.0 feed | F1.1, F8, backend cron/email infra (O-8) |
| **F11** | Asset Library (§4.2) | content production A4 |

Suggested order: **F0 → F1(+F1.1) → F2 → F3 → F6 → F8 → F9 → F7 → F10 → F11.**

> **Note:** F8 now bundles the `order_sync` gate + the one-time payload extension (the audit found no
> option data on the wire). Option Analytics and the whole B2B layer (F10) are *views* over this one
> feed — that consolidation is the benefit of re-adding order_sync (§0.3 item 4). Gate F8/F10 on the
> O-3 Dashboard-liveness check.

---

## 11. Open items (O-1 closed per §0.3; O-2/O-3/O-4 remain open per v1.0)

| ID | Question | Owner |
|----|----------|-------|
| O-1 | ~~14-day trial issuance~~ → **CLOSED — no trial (§0.3 item 3)** | — |
| O-5 | ~~Pricing/plan split~~ → **CLOSED by D5/D6** | — |
| O-6 | Dashboard support for connect-back: `/connect` intent screen + one-time token issuance + `/license/exchange` | backend |
| O-7 | Proration behavior on Standard→B2B mid-cycle (gateway-agnostic, server-side) confirmed? | backend |
| O-8 | Server infra for `email_trigger`: trigger-time schedule API + scheduled-send email (domain, DKIM, per-merchant from-name) | backend |
| O-9 | Vault retention legalese: 90-day post-expiry grace wording in ToS | product/legal |

## 12. ⚠️ Assumptions — confirm before dev start

| # | Assumption | Status / Confirm with |
|---|-----------|-----------------------|
| A1 | ~~no-card 14-day trial entitlements~~ | **MOOT — trial removed (§0.3 item 3)** |
| A2 | ~~Stripe Checkout is the processor~~ | **MOOT — payment is entirely Storelly API's concern; plugin payment-agnostic (D7)** |
| A3 | Vault privacy model (salted `customer_ref`, eraser hook, 90-day grace) passes GDPR review | OPEN — @legal |
| A4 | Content team can produce ≥150 launch assets for Tier-1 industries before F11 | OPEN — @david |
| A5 | ~~order_sync payload already includes per-line selected options~~ | **RESOLVED — NEGATIVE (2026-06-07).** No option data on the wire → the re-added `order_sync` payload is **extended once** with `options[]` (§4.0); analytics ride that feed (§4.3). |
| A6 | Existing `SPBWC_Cloud_Connect` consent screen can be invoked inline post-activation without refactor | LOW-RISK — connect AJAX flow + `is_connected()` exist; render entry point confirmed in F6 |
| A7 | Storelly API can expose a **trigger-time schedule** config + send scheduled emails server-side (gates `email_trigger`, §3.2) | OPEN — @backend (= O-8) |

---

## 13. Milestone W2-PRICE — Pricing/Plans page chuẩn hóa (Wave 2, item 5)

**Status:** DRAFT (2026-06-09) · part of `SPEC_ADMIN_UX_POLISH_W2.md`

### Vấn đề
License/Upgrade page (`views/license.php`) hiện chỉ mở `app.storelly.com/subscription` ở tab mới
(M5.8). Chưa có bảng plan chuẩn trong dashboard; chưa map entitlement `caps[]`/`can()` (mục này CHƯA code
theo audit §0). Cần "làm chuẩn" cách hiển thị gói giá ngay trong wp-admin.

### Yêu cầu
1. **Pricing table component** trong License page: hiển thị các plan (Free / Standard $49 / B2B $99 —
   theo D5/D6) dạng cột, token-first (radius/spacing/badge), highlight plan hiện tại, ribbon "Recommended".
2. **Nguồn dữ liệu plan**: fetch danh sách plan từ API (`GET /api/v1/license/packages` hoặc endpoint
   hiện có) qua **transient cache** (TTL ~12h) + **fallback hardcoded** khi offline/timeout. KHÔNG block
   render (đọc cache trước, refresh nền).
3. **Feature matrix** theo `caps[]`: mỗi plan liệt kê cap bật/tắt (cloud PDF, order_sync, design_vault,
   option analytics, config snapshots, B2B service: invoice_pdf/email_trigger/analytics_b2b) lấy từ định
   nghĩa entitlement (§1.2 caps/can — cần code `includes/class-entitlement.php` hoặc tương đương nếu chưa
   có). Lưu ý: KHÔNG còn `marketplace_premium` (đã bỏ §0.3 — marketplace free); `order_sync` đã thêm lại
   làm backbone (§4.0).
4. **CTA**: plan cao hơn → "Upgrade" (nối connect-back §6 khi backend sẵn; tạm redirect hosted);
   plan hiện tại → "Current"; thấp hơn → ẩn/disable.
5. Map đúng plan hiện tại từ `SPBWC_License_Manager::get_current_license()['status']`.

### Acceptance
- License page hiển thị bảng plan chuẩn token; offline vẫn render bằng fallback (không trắng/treo).
- Plan hiện tại được highlight đúng; feature matrix khớp `caps[]`.
- Không block render (fetch async/cache); Plugin Check 0 error; external service khai báo nếu fetch packages.

### Files
`views/license.php`, `includes/class-license-manager.php` (fetch packages + cache),
`includes/class-entitlement.php` (caps/can — nếu chưa có), CSS pricing (token), readme (external service).
