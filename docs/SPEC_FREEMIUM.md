# Storelly Product Builder — Freemium & Cloud Monetization Spec

> **Status**: DRAFT (strategy locked, implementation pending)
> **Author**: maintainers
> **Supersedes**: the implicit "free = up to 5 products" model in `SPEC.md §1`,
> `class-license-manager.php`, `readme.txt:32`, and the Overview/License UI copy.
> **Related**: `SPEC_M5_CLOUD_CONSENT.md`, `SPEC_ONBOARDING_ACTIVATION.md`,
> `wp-org-compliance-audit-plan.md`.

---

## 1. The one-line model: **Local = Free, Cloud = Paid**

Everything that runs **entirely on the merchant's own server** is free, forever,
with **no quantity limits**. Everything that **calls `app.storelly.com`** (and
therefore costs us real marginal money — PDF rendering, sync, storage, hosted
catalogue) requires a **paid Cloud license**.

This single boundary is the whole strategy. It is:

- **wordpress.org-safe** — zero crippleware. No local feature is disabled to
  extort an upgrade. Paid features are paid because they consume a *real paid
  service*, exactly the Jetpack / Akismet / WooCommerce-Shipping model the
  guidelines explicitly bless.
- **Cheap to ship** — no Pro feature has to be refactored out of the wp.org zip
  into a separate add-on. Quote B2B, Custom Order, the quantity-break engine,
  conditional logic — all stay **free and local**.
- **Honest** — the value metric (Cloud usage) tracks our actual cost, so pricing
  is defensible to both the reviewer and the customer.

### 1.1 Locked decisions (do not relitigate without sign-off)

| # | Decision | Choice |
|---|----------|--------|
| D1 | Primary value metric | **Feature + Cloud boundary**, not product count |
| D2 | Pro-local features (Quote, Custom Order, engines) | **Stay free in the wp.org build** — monetize only Cloud |
| D3 | Cloud tier shape | **Paid-only, no permanent cloud free-tier** |
| D4 | Compliance debt (5-product claim, locked-templates copy, external-services gaps) | **Must be cleaned regardless** of the above |

### 1.2 What this kills

- The `max_products` / `max_orders` / `max_pricing_options` **quota model**
  becomes irrelevant to gating. (Kept only if the Cloud server still wants to
  report usage for analytics — see §4.4.)
- The "free version allows … up to **five customizable products**" promise in
  `readme.txt` and `SPEC.md §1`.
- The "Premium templates locked" / "Community support only" benefit copy that
  describes a gate that does not exist in code.

---

## 2. The Local / Cloud boundary (feature ledger)

Authoritative classification of every significant capability. **Free** =
ships in the wp.org build, runs without any network call to Storelly.
**Cloud** = requires the paid Cloud license + an active connection.

### 2.1 FREE — local, unlimited

| Capability | Where | Notes |
|-----------|-------|-------|
| Visual product builder / Customizer V3 (canvas, swatch, text, image upload) | `class-product-builder-frontend.php`, `static/js/app-product-builder.js` | The flagship experience is free. |
| All pricing-option field types (dropdown/radio/swatch/input/label/advanced/xlabel) | `class-frontend-options.php` | Including `price[0]` surcharges. |
| **Quantity-break / tiered pricing engine** | cart engine (505cf4b) + V3 mirror | Free. (Note: `price_breaks` / per-option `price[1..3]` / `depend_quantity` still have no engine — see `SPEC.md`; out of scope here.) |
| Conditional / dependent fields | options model | Free. |
| **Quote B2B** (CPT `spbwc_quote`, accept→order, WC emails, expiry) | `includes/quote/` | Free, **except** Quote PDF export if it routes through Cloud2Print — see §2.3. |
| **Custom Order** (admin designs on behalf of customer, COW folders) | Custom Order module | Free, **except** the print-ready PDF step (Cloud). |
| Design persistence + reorder (local SVG+JSON folders in uploads) | `class-io.php`, frontend save | Free. No cross-device. |
| Product import/export (native JSON + PrintCart adapter) | import/export controllers | Free. The *bundled* demo data is local; the *remote* demo fetch is Cloud-adjacent (§2.3). |
| Setup Wizard › Import Woo Variations | `SPBWC_Onboarding`, `SPEC_SETUP_WIZARD_WOO_SEED.md` | Free — pure local Woo read. |
| Multilingual (15 locales), RTL | `languages/` | Free. |

### 2.2 CLOUD — paid license required

| Capability | Entry point(s) | Current gate | Target gate |
|-----------|----------------|--------------|-------------|
| **Cloud2Print print-ready PDF** (the crown jewel for print shops) | `class-export-pdf.php`, `class-order-pdf.php`, `quote/class-quote-pdf.php` | `enable_cloud2print_api` flag (consent only) | consent flag **AND** `cloud_license_active` |
| **Order sync → Dashboard** | `class-productbuilder-api.php` (already gated by `enable_api_sync`) | `enable_api_sync` flag (consent only) | flag **AND** `cloud_license_active` |
| **Dashboard analytics / Overview stats** | `SPBWC_License_Manager::get_overview_stats()` → `/api/v1/plugin/overview` | none beyond being on the page | `cloud_license_active` |
| **Hosted template marketplace** (download premium templates) | `includes/marketplace/**`, `marketplace-bridge.php` | varies | `cloud_license_active` |
| **Designer marketplace / launcher** (designers, designs, withdraws) | `includes/launcher/**` | varies | `cloud_license_active` (audit — may be dormant fork scaffolding) |
| Cloud design storage / cross-device backup | (future / partial) | — | `cloud_license_active` |
| Remote demo-catalogue import | `class-demo-seeder.php`, `class-global-import-controller.php` → `/product-data/data/data.json` | admin action | **Free** (anonymous GET, no PII) — keep free, just declare in readme |

> **Open audit item**: the `launcher/` + `marketplace/` subsystems are large and
> may be inherited fork scaffolding (cf. memory `project_fork_from_cmsmart`).
> Before wiring them to the Cloud gate, confirm they are live and intended for
> this product. If dormant, exclude from the gate (and consider removal for
> wp.org hygiene) rather than advertising them.

### 2.3 Boundary edge cases (decide explicitly)

1. **Quote PDF / Order PDF**: the *workflow* is free; the *print-ready PDF* is
   Cloud. Free users get the quote/order, plus a **local fallback** (browser
   print / basic FPDI PDF already bundled — `FPDI` is in `readme.txt:79`) so the
   feature is never dead — just not the high-fidelity Cloud render.
2. **Remote demo import**: stays **free** (it is an anonymous read that helps
   onboarding, no marginal cost worth gating). Must remain declared under
   External services.
3. **License status/packages calls** themselves are Cloud calls but must work for
   *free* users (that is how they discover/buy). Never gate the buy path.

---

## 3. Funnel without a cloud free-tier (D3 consequence)

D3 says **no permanent cloud free-tier**. That removes the "try 10 free PDFs"
on-ramp, so the funnel must come from elsewhere or top-of-funnel collapses:

- **Time-boxed Cloud trial** (recommended): a one-time **14-day full-Cloud
  trial** issued by `app.storelly.com` on first connect. Full features, hard
  stop at expiry, then read-only + upsell. This is *not* a free-tier (it ends),
  so it respects D3 while still letting merchants taste the value.
- **Local high-fidelity preview, Cloud to finalize**: free users can *build and
  preview* a design fully; the Cloud gate bites only at **export / print-ready
  PDF / sync** — i.e. at fulfillment, the moment of real intent.
- **Watermarked local PDF** as the free fallback (see §2.3.1) — proves quality,
  nudges to Cloud for the clean file.

> **Needs confirmation**: whether the Storelly Dashboard can issue a 14-day
> trial entitlement. If not, fall back to "demo/preview only" funnel. Recorded
> as open item O-1.

---

## 4. Gating architecture

### 4.1 One capability gate, one source of truth

Add a single, cached predicate that every Cloud entry point consults. Do **not**
scatter `enable_*` flag checks; route them through this:

```php
// SPBWC_License_Manager
public static function cloud_license_active(): bool {
    $lic = self::get_current_license();           // already cached
    if ( in_array( $lic['status'], array( 'active', 'trial' ), true ) ) {
        if ( empty( $lic['expires_at'] ) ) return true;          // lifetime
        return strtotime( $lic['expires_at'] ) > time();         // not expired
    }
    return false;
}

public static function can( string $cap ): bool {
    // $cap in { 'cloud_pdf', 'order_sync', 'analytics', 'marketplace' }
    return self::cloud_license_active();   // single tier today; map per-cap later
}
```

### 4.2 Two-condition rule at every Cloud call

A Cloud action fires **only if** `consent flag == yes` **AND**
`cloud_license_active()`. Consent (`SPBWC_Cloud_Connect`) and entitlement
(license) are orthogonal:

- consent without license → connected but **upsell**, no Cloud action.
- license without consent → must still pass the GDPR consent gate first.

Touch points to wrap (from the §2.2 table):
`class-export-pdf.php`, `class-order-pdf.php`, `quote/class-quote-pdf.php`,
`class-productbuilder-api.php` (sync), `get_overview_stats()`, marketplace
bridge. Each, on a blocked call, returns a typed `WP_Error('spbwc_cloud_locked')`
that the UI renders as an **upsell-by-intent** prompt (§5), never a silent fail.

### 4.3 License data model change

`get_current_license()` / `sync_from_api()` (`class-license-manager.php`) shift
from quota fields to **entitlement** fields:

```
status        : 'free' | 'trial' | 'active' | 'expired'
plan_slug     : e.g. 'cloud-monthly' | 'cloud-annual' | 'cloud-lifetime'
expires_at    : ISO | null(lifetime)
caps          : ['cloud_pdf','order_sync','analytics','marketplace']  // future per-cap
trial_ends_at : ISO | null
```

Keep `max_*` fields **only** if Dashboard analytics still wants to display usage;
they no longer gate anything locally.

### 4.4 What stays exactly as-is

- `SPBWC_Cloud_Connect` consent flow (it is already the wp.org-compliance
  linchpin — see its header doc). We only **add** the license check downstream;
  we do **not** make connecting itself require payment (a free user must be able
  to connect to start a trial / see the buy screen).
- No phone-home on activation/page-load. License `sync_from_api()` stays
  admin-triggered (Sync button / activate). Confirmed compliant in audit.

---

## 5. Upsell, re-pointed to "moment of intent"

Current `SPBWC_Upsell_Notice` (M7) is good infrastructure (contextual,
dismissible, 30-day snooze) but fires on the **wrong trigger** (product count).
Re-point it:

- **Remove** the count-based trigger entirely.
- **Fire on intent**: when a free/expired user *invokes a Cloud action* — clicks
  "Export PDF", "Sync to Dashboard", "Download premium template" — intercept the
  `spbwc_cloud_locked` error and show an **inline** unlock prompt at that exact
  control (not a top-of-page banner). Highest-converting moment.
- **Trial nudge**: if the store has never started its trial, the prompt leads
  with "Start your 14-day Cloud trial" rather than "Buy".
- Keep snooze/dismiss semantics so it never becomes a nag.

---

## 6. Compliance cleanup backlog (D4 — do regardless)

These are correctness/compliance fixes the audit already surfaced; they are
**independent** of the freemium rollout and should land first.

| ID | Fix | Files |
|----|-----|-------|
| C1 | Remove the "up to five customizable products" claim; reword free/paid framing to Local/Cloud | `readme.txt:32`, `SPEC.md §1` |
| C2 | Remove/replace "Premium templates locked", "Community support only" copy that describes a non-existent gate | `views/overview.php` (benefit list ~57-67, banner ~457-546), `views/license.php` |
| C3 | Declare the license + overview endpoints under **External services** (`/api/v1/license/{status,activate,packages}`, `/api/v1/plugin/overview`) — what data, when | `readme.txt:85+` |
| C4 | Reword the upsell/limit messaging away from implied hard caps | `class-upsell-notice.php` |
| C5 | Re-run `wp plugin check` → 0 errors; verify version triple (readme/header/tag) before release | — |

---

## 7. Impact / non-regression notes

- **No WooCommerce flow breaks**: free users keep cart→checkout→order intact
  (all local). Only *fulfillment extras* (print-ready PDF, Dashboard sync) gate.
- **Existing connected stores**: on rollout, a connected store with no license
  becomes "consented but unlicensed" → Cloud actions show upsell. Provide a grace
  window / migration notice so live print shops are not cut off without warning
  (open item O-2: grace period length + comms).
- **Downgrade/expiry**: on expiry, Cloud actions revert to free fallback
  (local PDF / no sync). Local data and designs are **never** destroyed.
- **Quote/Custom-Order stay free** → no admin-facing feature suddenly disappears,
  avoiding the #1 source of 1-star "they took my feature" reviews.

---

## 8. Open items

| ID | Question | Owner |
|----|----------|-------|
| O-1 | Can `app.storelly.com` issue a one-time 14-day trial entitlement? | backend |
| O-2 | Grace period + comms for already-connected unlicensed stores | product |
| O-3 | Are `launcher/` + `marketplace/` subsystems live or dormant fork scaffolding? Gate, or remove? | maintainers |
| O-4 | Quote/Order PDF: ship a watermarked local FPDI fallback, or browser-print only? | product |
| O-5 | Pricing: monthly/annual + lifetime split; price points per plan | business |

---

## 9. Milestones (proposed)

- **F0 — Compliance cleanup** (C1–C5). Ships independently, immediately.
- **F1 — Entitlement model**: license data shape (§4.3) + `cloud_license_active()`
  + `can()`; Dashboard issues `status/plan/expires_at`.
- **F2 — Gate the touch points** (§4.2): wrap PDF/sync/analytics/marketplace,
  return typed `spbwc_cloud_locked`.
- **F3 — Upsell-by-intent** (§5): re-point `SPBWC_Upsell_Notice`, inline prompts,
  trial CTA.
- **F4 — Funnel**: trial issuance (O-1) + local fallback PDF (O-4).
- **F5 — Expiry/grace** (O-2) + Dashboard plan UI parity.
```
