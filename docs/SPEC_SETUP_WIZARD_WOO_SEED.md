# Setup Wizard — Import Woo Variations (one-time seeder)

**Status:** Shipped (2026-05-30)
**Owner:** David / Netbase JSC
**Companion code:** `includes/setup-wizard/`, `views/setup-wizard/`, `static/js/woo-seed-app.js`, `static/css/woo-seed.css`

---

## 1. Purpose

Convert an existing WooCommerce store's variable products into Storelly
pricing options in a single pass, so a merchant who already configured Woo
attributes/variations can adopt Storelly without rebuilding everything by
hand.

**One-time, NOT a live sync.** Re-running is safe — products already linked
to a Storelly option are skipped.

## 2. Why this shape (decisions log)

| Decision | Choice | Why |
|---|---|---|
| Where to put it | Inside existing Setup Wizard menu as a second card alongside "Import Sample Products" | Setup Wizard is the natural onboarding hub; reuses the existing menu slot, no new top-level item |
| Granularity | One Storelly option set per product | Mirrors Woo's per-product variation reality; sharing across products is impossible because attribute sets differ |
| Default display type | Swatch (`'s'`) | Looks best on storefront; merchant can swap per field after import |
| Variation images | Imported by default | Critical for buyer experience on multi-variation products |
| Multi-attr price rule | Average across variations (with explicit ±delta warning) | Fast, "good enough" for most stores; alternative (leave empty) would defeat the bulk import |
| Non-variation attributes | Skipped by default | They are product specs, not buyer choices |
| Conflict policy | Skip products already linked to a Storelly option | Never overwrite a hand-built option set without explicit consent |
| Stock / SKU per variation | Not migrated (acknowledged loss) | Storelly options have no stock model; merchant either co-exists with Woo variations or disables them later |
| Idempotency | After completion, persist `spbwc_woo_seed_last = { job_id, timestamp, count }` | Lets landing card show "Last run: …" and lets re-runs be obvious |
| Undo | Every created row tagged `template_slug = woo_seed_<job_id>`; Undo deletes all rows with that slug + clears product link meta | Lets merchant bulk-revert if the first pass looks wrong |
| Atomicity | Per-product (each product is its own transaction) | Avoids a stuck job; per-product errors don't abort the batch |

## 3. Where the feature lives

| Menu | Storelly Builder → **Setup Wizard** (existing slot) |
|---|---|
| URL | `wp-admin/admin.php?page=storelly-product-builder-for-woocommerce-options/global-import` |
| Default tab | `?tab=landing` (or no tab) — landing with 2 cards |
| Sub-tabs | `?tab=sample` (existing Import Sample Products, untouched) · `?tab=woo` (new) |
| Routing | `SPBWC_Storelly_PB_Admin_Options::spbwc_global_import()` reads `$_GET['tab']` and `include`s the matching view |
| Backward-compat fallback | If new view files are missing, falls through to legacy `SPBWC_Global_Import_Admin::render_global_import_page()` |

## 4. File map

```
includes/setup-wizard/
  ├─ class-woo-seed-scanner.php     read-only scan; eligibility summary + preview
  ├─ class-woo-seed-mapper.php      variations → descriptor-shape field set
  └─ class-woo-seed-controller.php  AJAX (scan/run/log/undo) + asset enqueue
views/setup-wizard/
  ├─ landing.php                    2-card wizard hub (page-hero + quick-grid)
  └─ woo-seed.php                   wizard shell — JS mounts at #spbwc-woo-seed-app
static/js/woo-seed-app.js           wizard state machine + AJAX + progress bar
static/css/woo-seed.css             wizard-specific styles, all values via CSS vars
```

**Bootstrap:** `storelly-product-builder-for-woocommerce.php` requires the
three classes and calls `SPBWC_Woo_Seed_Controller::instance()->init()`.

## 5. Architecture

```
┌─ landing.php ─┐                  ┌─ scanner.php ─────────────────────┐
│ Sample card   │                  │ scan() iterates published         │
│ Woo card  →───┼──Open──→ Step 1 ─┤   products, buckets eligible /    │
└───────────────┘                  │   linked / simple, aggregates     │
                                   │   attribute types, picks top-5    │
                                   │   preview rows, returns + caches  │
                                   │   in a 5-minute transient.        │
                                   └───────────────────────────────────┘
                                                ↓
   Step 2 (rules form, defaults pre-checked)    │
                                                ↓
   Step 3 (confirm + ack checkbox)              │
                                                ↓
                                   ┌─ controller.php ──────────────────┐
                                   │ ajax_run() loop (batches of 15):  │
                                   │   for each eligible pid           │
                                   │     - re-check link conflict      │
                                   │     - mapper.build_option_set()   │
                                   │     - INSERT into options table   │
                                   │     - update_post_meta meta       │
                                   │     - flush product transient     │
                                   │   persist state in transient      │
                                   │   keyed by job_id (1 hr TTL).     │
                                   └───────────────────────────────────┘
                                                ↓
   Done state ─ Undo button ─→     ajax_undo() walks template_slug,
                                   deletes rows + clears meta links.
```

## 6. Data model

### Storelly option row (`wp_storelly_product_builder_options`)

Created per product, populated as follows:

| Column | Value |
|---|---|
| `title` | `<product name> (Woo seed)` |
| `published` | `1` |
| `product_ids` | `serialize([ <product_id> ])` |
| `apply_for` | `'p'` |
| `product_cats` | `serialize([])` |
| `created` / `modified` | `current_time('mysql')` |
| `created_by` / `modified_by` | current user id |
| `fields` | `serialize($descriptor)` — full descriptor-shape envelope |
| `template_slug` | `woo_seed_<job_id>` — drives Undo |
| `template_version` | `'1.0.0'` |

### Post meta updates (per imported product)

| Meta key | Value | Notes |
|---|---|---|
| `_spbwc_option_id` | row id of the inserted option set | what frontend resolves |
| `_storelly_pb_enable` | `1` | activates the builder on the product |
| `_transient_spbwc_product_builder_<pid>` | deleted | forces frontend re-resolution |

### Job state transient

Key: `spbwc_woo_seed_job_<job_id>` (TTL: 1 hour)

```
{
  job_id, created, rules,
  eligible_ids[],
  cursor, processed, skipped, errors,
  status: running | done,
  log: string[] (capped at 200)
}
```

### Last-seed marker

WP option `spbwc_woo_seed_last`:

```
{ job_id, timestamp, count }
```

Shown on landing card as "Last run: YYYY-MM-DD HH:MM — N products". Cleared
on Undo only if the marker points at the job being undone.

## 7. Field descriptor shape

The mapper emits the SAME descriptor shape as the bundled print templates
(see `storage/print-templates/templates/business-cards.json` for a real
example). Each field has:

```
id            "fw<product_id>_<idx>"
general
  title                {value: <attribute label>, …}
  data_type            value='m'   (multiple options)
  price_type           value='f'   (fixed surcharge)
  depend_qty           value='y'   (multiply by cart qty)
  depend_quantity      value='n'   (no quantity breaks)
  attributes
    options[]          [ {name, preview_type, image, color, price[delta], selected?}, … ]
appearance
  display_type         value='s' (swatch — overridable per import)
  change_image_product value='y' if any option has an image, else 'n'
```

Top-level envelope: `version: "120"`, `quantity_enable: 'n'`,
`quantity_breaks: [{ val: '1', dis: '', default: 'on' }]` — single tier so
each option's `price[]` is a 1-element array.

## 8. Pricing math

```
baseline = min(variation_prices)
For attribute A, value v:
  matches = variations where A == v (wildcards "Any" skipped)
  price(v) =
    if single-attribute product : matches[0].price
    if multi-attribute, rule=avg: average(matches.price)
    if multi-attribute, rule=empty: ''  (left blank for merchant)
  delta(v) = round(price(v) - baseline, 2)
  if delta(v) ≤ 0: emit '' (clamped — avoid confusing negative surcharges)
```

**Lossy case:** multi-attribute products where prices have interactions
(e.g. "Red costs more only in Large") cannot be tracked exactly with
independent per-attribute deltas. UI surfaces this in Step 2 ("Affects N
products. Final total may differ ±a few units").

## 9. AJAX contract

All 4 endpoints share:
- Action prefix: `spbwc_woo_seed_*`
- Method: POST
- Nonce action: `spbwc_woo_seed` (sent as `nonce` field)
- Capability: `manage_options`

| Action | Inputs | Output (success.data) |
|---|---|---|
| `spbwc_woo_seed_scan` | — | `{ eligible, linked, simple, attribute_types[], total_variations, with_image, multi_attr, preview_top[], last_seed }` |
| `spbwc_woo_seed_run` | `job_id` (empty = create), `rules{}` | `{ job_id, status, processed, skipped, errors, total, progress, log[] }` |
| `spbwc_woo_seed_log` | `job_id` | same shape as `run` (just polls) |
| `spbwc_woo_seed_undo` | `job_id` | `{ deleted, unlinked }` |

## 10. UI conventions

All wizard markup uses the shared admin-ui component library:

| Use case | Class |
|---|---|
| Page header band | `.spbwc-page-hero` + `__title` / `__subtitle` / `__eyebrow` |
| 2-card landing | `.spbwc-quick-grid` + `.spbwc-quick-card` + `__head` / `__icon` / `__title` / `__desc` / `__footer` |
| Wizard step card | `.spbwc-block` + custom `.spbwc-ws-block-body` for inner padding |
| Stat numbers | `.spbwc-ws-stat` + `--success` / `--warning` / `--mute` (mirrors `.spbwc-stat-card` family) |
| Action buttons | `.spbwc-cta-btn.spbwc-cta-btn--solid` / `--ghost` / `--sm` |
| Inline warnings | `.spbwc-notice-banner.spbwc-notice-banner--warn` |

All colors, spacing, typography, radius, shadow values flow through the
design tokens declared in `static/css/_tokens.css`
(`--st-*`, `--nbd-st-*`, `--nbd-space-*`, `--text-*`, `--nbd-radius-*`,
`--shadow-*`). **No hardcoded colors or pixel paddings in views or JS.**

Tokens + admin-ui are enqueued on BOTH the landing page and the Woo tab so
both sub-screens render consistently:

```php
// SPBWC_Woo_Seed_Controller::enqueue_assets()
//   on landing or tab=woo  → wp_enqueue_style('spbwc-tokens' + 'spbwc-admin-ui')
//   on tab=woo only        → also enqueue 'spbwc-woo-seed' + 'spbwc-woo-seed-app'
//   on tab=sample          → bail (legacy Sample Products keeps its own asset stack)
```

## 11. Known limitations (documented tradeoffs)

| Limitation | Mitigation / merchant action |
|---|---|
| Stock + SKU per variation NOT migrated | Merchant either keeps Woo variations as source of truth for stock, or accepts the loss. Surfaced as a ⚠ on Step 3 + on the ack checkbox |
| Swatch colors / images from third-party swatch plugins (CartFlows, YITH, Emran) are NOT read | Only Woo core attribute data + variation thumbnails. Merchant fills swatch colors after import |
| Multi-attr price math is lossy when prices have interactions | Avg rule surfaces estimated delta; UI flags affected product count on Step 2 |
| One option set per product = one row per product in the options table | Acceptable — mirrors Woo's per-product reality; database scale is fine for thousands of products |
| Resolution at frontend picks ONE option set per product ([class-admin-options.php:2748](../includes/class-admin-options.php#L2748)) | Seeded option becomes the active one; merchant cannot stack multiple Storelly groups on one product without rewriting `spbwc_get_product_option` |
| Double-UI: Woo variation form still renders alongside Storelly options on `variable` products | Out of scope here. Future: a "hide native variation form" toggle in Storelly settings |
| Bundled `Step %1$d of %2$d` format strings use WordPress positional placeholders | JS `sprintf` supports both `%s/%d` (sequential) AND `%N$s/%N$d` (positional) — see `static/js/woo-seed-app.js` |

## 12. wp.org compliance checklist (applied)

- `if ( ! defined( 'ABSPATH' ) ) exit;` at top of every PHP file
- All AJAX: `current_user_can('manage_options')` + `wp_verify_nonce`
- Input: `wp_unslash` + `sanitize_*`; PHP output: `esc_*`; JS output: in-house `esc()` HTML-encoder
- Prefix `spbwc_` for everything: classes, functions, hooks, option keys, meta keys, transient keys, AJAX actions
- Text domain `storelly-product-builder-for-woocommerce` everywhere
- SQL with values uses `$wpdb->prepare()`; INSERT/DELETE use `$wpdb->insert/$wpdb->delete` with format arrays
- `serialize()` used for `product_ids` / `product_cats` / `fields` to match existing schema; `maybe_unserialize` on read
- Assets via `wp_register_style/script` + `wp_enqueue_*` (no inline `<script>` / `<style>` blocks)
- No phone-home; no hardcoded locale or timezone

## 13. Test coverage

Manual end-to-end verified in docker on 2026-05-30:

| Test | Result |
|---|---|
| PHP lint on all new + modified files | 0 errors |
| Class load in WP context | All 3 classes resolved |
| AJAX hooks registered | scan / run / log / undo all wired |
| Scanner against real catalog (zero variables) | eligible=0, linked=N, simple=N as expected |
| Mapper on a 3-variation fixture (Size: Small=$10, Medium=$14, Large=$20) | Deltas: Small='' (clamped 0), Medium='4', Large='10'; default selected on first option; descriptor shape valid |
| Chrome E2E: walk wizard → import 1 fixture → verify DB row + meta → click Undo → verify cleanup | All pass — 9 screenshots in `tools/_chrome-shots/` |
| Design-tokens visual QA pass | Landing + Step 1/2/3 + Done render with the corporate blue hero, `.spbwc-quick-card` chrome, `.spbwc-ws-stat` color-coded borders |

## 14. Future work (NOT shipped)

- Read swatch colors / images from popular swatch plugins (CartFlows Variation Swatches, YITH WCAS, Emran's WC Variation Swatches) when detected
- Optional "hide native Woo variation form" toggle in Storelly settings
- Per-product preview/diff before bulk apply (currently only stat-level preview)
- CLI subcommand `wp storelly woo-seed run --rules=…` for headless onboarding
- Multi-group stacking (>1 Storelly group per product) — requires rewriting `spbwc_get_product_option` to merge instead of picking the first match
