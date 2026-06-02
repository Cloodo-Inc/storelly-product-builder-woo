# Custom Order — Design Files, Downloads, Saved Designs & Re-order/Re-edit

> Status: **As-built audit + feature spec (open decisions partly resolved 2026-06-01)**
> Feature area: "Custom Order" — print-ready PDF tied to the order, buyer file downloads,
> save-design-to-account, and re-order / re-edit for Visual-Builder products.
> Owner modules: `includes/class-frontend-options.php`, `includes/class-export-pdf.php`,
> `includes/class-productbuilder-api.php`, `includes/class-admin-options.php`,
> `includes/visual-builder/class-visual-builder-admin.php`
> Last updated: 2026-06-01

This spec documents what exists **today** with `file:line` references (Part A), maps the
**user flows** (Part B), then defines the **target design** with milestones and open
decisions (Part C). Part A is grounded in current code; Part C is explicitly NOT-yet-built.

Confirmed decisions (2026-06-01):
- **CD-1 — PDF flow:** decouple "generate order PDF" from `enable_api_sync`; render in the
  background via **Action Scheduler** (no checkout latency).
- **CD-2 — Buyer downloads:** buyer can download a **preview** (low-res PNG / watermarked PDF)
  from My Account **at any time** after ordering; the full print file stays admin-only.
- **CD-3 — Save scope:** "Save design to account" stores **the full design** — selected options
  **plus a clone of the design folder** (SVG/PNG assets), so reload is pixel-accurate.
- **CD-4 — Deliverable:** this spec + milestone file first; no code until reviewed.

---

## 1. Purpose

Give a buyer who personalises a product (Classic options or the Visual Builder canvas) a
complete post-purchase lifecycle:

1. Their design is **captured on the order** as a print-ready PDF, generated reliably and
   automatically (not gated behind an unrelated sync toggle).
2. They can **see and download a preview** of what they ordered from **My Account**.
3. They can **save a configuration to their account** and reload it later into the builder.
4. They can **re-order** a past custom item and **re-edit** the design — each producing an
   **independent** design instance (no overwrite of the original order's artwork).

Target use case: web-to-print / product personalisation where the design *is* the deliverable.

---

## 2. Where the feature lives (surfaces)

| Surface | Entry point | Code |
| --- | --- | --- |
| Storefront — add custom item to cart | builder form → `pcpb-field[...]` + nonce | `add_cart_item_data()`, `class-frontend-options.php:45,621` |
| Cart — show options + edit link | per-item meta rows + "Edit options" | `get_item_data()` `:43,1184`; `cart_item_name()` `:67,1268` |
| Cart — preview thumbnail | builder render replaces product thumb | `cart_item_thumbnail()` `:63,1252` |
| Order — persist design meta | 5 hidden meta keys on the line item | `order_line_item()` `:57,1110` |
| Order (admin) — design metabox | preview + download (png/svg/pdf/previews) | `spbwc_product_builder_design()`, `class-admin-options.php:166,175`; `views/box-order-metadata.php` |
| Order (admin) — download endpoint | AJAX `spbwc_download_order_designs` | `class-admin-options.php:3485`; JS `static/js/storelly-general.js:12` |
| PDF engine | Cloud2Print remote render | `SPBWC_Storelly_Export_PDF::spbwc_export_pdf()`, `class-export-pdf.php:34` |
| Auto-PDF on order (gated) | `woocommerce_store_api_checkout_order_processed` | `spbwc_notify_on_new_order()`, `class-productbuilder-api.php:20,180` |
| Re-order | restore builder meta into cart | `order_again_cart_item_data()` `:61,1225` |
| Builder prefill (re-order/re-edit) | `nbo_values` base64 URL param | `class-frontend-options.php:310-334,551-575` |
| Visual Builder (admin) | design authoring canvas | `includes/visual-builder/class-visual-builder-admin.php` |
| My Account pattern to mirror | Quote endpoints + nav item | `class-request-quote.php:22-25,540` |

---

## 3. Data model (as-built)

### Design folder (filesystem)
Each customised item produces a **design folder** under `SPBWC_PB_CUSTOMER_DIR/{folder}/`:
- `frame_{N}_svg.svg` — vector per product view (front/back/…)
- `{filename}.png` — raster renders
- `preview/` — preview images
- `config.json`, `design_output.json` — geometry, DPI, dimension unit (read by the PDF engine)
- `customer-pdfs/{folder}_{page}.pdf` — generated print PDFs (`class-export-pdf.php:296`)

The folder path is the linchpin: it is stored verbatim as `_pcpb_folder` and consumed by
both the admin download and the PDF engine.

### Cart item meta (session, ephemeral)
`pcpb_meta` set by `add_cart_item_data()` (`:691-695`):
- `field` — raw `$_POST['pcpb-field']` selections
- `option_price` — processed fields + prices, `cart_image`, `cart_item_fee`
- `options` — base64 option config
- `original_price`, `price`
- `pcpb` — **design folder path** (set at `:682`)

### Order line item meta (persistent)
Written by `order_line_item()` (`:1138-1142`); hidden from customer via
`woocommerce_hidden_order_itemmeta` (`class-admin-options.php:3037`):

| Meta key | Content |
| --- | --- |
| `_pcpb_option_price` | processed fields + computed prices |
| `_pcpb_field` | raw selections |
| `_pcpb_folder` | **design folder path** |
| `_pcpb_options` | serialised option config |
| `_pcpb_original_price` | base price for recompute |

### Visual Builder design (admin authoring)
- `spbwc_vb_promoted` / `spbwc_vb_excluded` (wp_options) — which pricing options are "Visuals".
- Design data lives in the option's serialised `fields` column (`nbpb_type` = `nbpb_com` /
  `nbpb_text` / `nbpb_image`, attribute swatches, per-view `pb_config`).

### PDF engine
`SPBWC_Storelly_Export_PDF::spbwc_export_pdf($folder, $include_background=false)`
(`class-export-pdf.php:34`): posts SVG+background HTML to **Cloud2Print**
(`https://api.cloud2print.net`, `:22`), downloads PDFs to `customer-pdfs/`.
**This is an external service** → must be opt-in + declared in readme "External services".

---

## 4. As-built behaviour, per case

### Case 1 — PDF tied to the order ⚠️ exists but mis-scoped
- Engine works end-to-end and is **auto-invoked** on `…checkout_order_processed`
  (`class-productbuilder-api.php:20`).
- **But** auto-generation runs only when `enable_api_sync = yes` (`:170,183`), because it is
  embedded inside the launcher sync payload, not a standalone "make the order's print file"
  step. With sync off, **no PDF is ever generated automatically**; admin must click per order.
- Synchronous Cloud2Print call inside the order-processed hook risks checkout latency/timeout.

### Case 2 — Download files ⚠️ admin-only
- Admin metabox offers 5 formats (png, png-preview, svg, pdf, pdf-preview), ZIP-packaged to
  `SPBWC_PB_DATA_DIR/download/{order}_{type}.zip` (`class-admin-options.php:3515-3566`).
- The endpoint requires `upload_files` cap + admin nonce (`:3486,3490`) → **buyers cannot use it**.
- Buyer-facing download is only advertised copy in `views/overview.php` / `views/menu-settings.php`,
  **not implemented**.

### Case 3 — Save options to account ❌ missing
- No CPT, user meta, "save design", favourites, wishlist or drafts feature exists.
- Closest reusable pattern: the **Quote B2B** CPT + My Account endpoints
  (`class-request-quote.php:540` registers `quotes` / `view-quote`; nav via
  `woocommerce_account_menu_items`).

### Case 4 — Re-order / Re-edit ⚠️ broken for Visual Builder
- `order_again_cart_item_data()` (`:1225-1246`) restores `option_price`, `field`, `options`,
  `original_price` — **but never restores `pcpb` (the design folder)**.
  → Re-ordering a Visual-Builder item loses its artwork; the new order's folder is empty, so the
  PDF engine has nothing to render.
- Even if the folder string were copied, old and new orders would point at the **same physical
  folder** → re-editing would **overwrite the original order's artwork**.
- In-cart re-edit ("Edit options", `:1268-1290` → `remove_previous_product_from_cart()` `:1013`
  → `add_to_cart()` `:1041`) works for the cart session but shares the same folder risk.

**Conclusion:** the unifying defect is folder **identity** — every reuse path must treat a design
folder as copy-on-write.

---

## Part B — User flows (target)

### B1. Buy a custom product (unchanged core)
Customise → Add to cart → checkout → order created → design meta persisted. **New:** an
Action-Scheduler job is enqueued to render the order's print PDF in the background (CD-1).

### B2. Buyer downloads a preview (new, CD-2)
My Account → Orders → order detail → per custom line item a **"Download preview"** control →
buyer-nonce + ownership-checked endpoint streams the **preview** (existing `preview/` PNG, or
Cloud2Print `pdf-preview` with watermark/low DPI). Available **any time** after the order exists.
Full print files remain admin-only.

### B3. Save a design to account (new, CD-3)
On the builder (and/or cart/order), a **"Save design"** action clones the current design folder
to a stable, user-owned copy and records a `spbwc_saved_design` entry. My Account →
**"Saved designs"** lists them with preview + **"Load into builder"** and **"Delete"**.
Loading clones again into a fresh folder and prefills the builder via `nbo_values`.

### B4. Re-order (fix, CD per Case 4)
"Order again" → for each custom line item, **clone** `_pcpb_folder` into a new folder, set it as
`pcpb_meta['pcpb']`, and restore the other meta → new cart item is a fully independent instance.

### B5. Re-edit (fix)
"Edit options" in cart, or "Edit" on a saved design / past order line → load options + **clone
folder** → builder opens prefilled → saving produces a **new** instance, never mutating the source.

---

## Part C — Target design

### C1. Decouple + queue the order PDF (CD-1)
- New standalone responsibility: **"render print files for an order"**, independent of
  `enable_api_sync`. Trigger on payment-complete / order-processed.
- Enqueue an **Action Scheduler** job per order (WP-Cron loopback is disabled locally; AS is the
  project standard — see Quote M6). Job loops items, calls `spbwc_export_pdf()` per
  `_pcpb_folder`, marks a per-item status meta (`_pcpb_pdf_status` = pending/done/failed).
- Keep `enable_api_sync` strictly for the launcher payload; PDF rendering no longer depends on it.
- Cloud2Print stays an **external service**: gate behind an explicit print-engine opt-in setting
  and declare it in readme "External services" (CLAUDE.md rule 6).

### C2. Buyer preview download (CD-2)
- New buyer endpoint (AJAX or `template_redirect` handler) with its **own nonce** + ownership
  check (`$order->get_customer_id() === get_current_user_id()` or guest order-key match).
- Serves **preview only**: reuse `{folder}/preview/*.png`; optionally Cloud2Print `pdf-preview`
  (the `$include_background=true` path, `class-admin-options.php:3528`) with watermark/low DPI.
- Surface in `woocommerce_order_item_meta_end` (or the order-details template) per custom item.
- Never expose `customer-pdfs/` full files to buyers; keep the admin endpoint untouched.

### C3. Saved designs CPT (CD-3) — mirror the Quote pattern
- CPT `spbwc_saved_design` (`show_ui=false`, `post_author` = buyer). Meta:
  `_pcpb_saved_folder`, `_pcpb_saved_field`, `_pcpb_saved_options`, `_pcpb_saved_product_id`,
  `_pcpb_saved_preview`.
- My Account endpoint `saved-designs` + nav item (mirror `add_account_endpoints()` /
  `woocommerce_account_menu_items`, `class-request-quote.php:540,22`).
- "Save" = **clone folder** → write CPT. "Load" = **clone folder again** → add to cart / open
  builder with `nbo_values` prefill. "Delete" removes the CPT + its cloned folder.
- All buyer actions: nonce + `post_author` ownership (mirror `handle_quote_action()`).

### C4. Copy-on-write folder service (foundation for C1–C3 and Case 4)
- One helper: `spbwc_clone_design_folder($src_folder): string` — deep-copies
  `SPBWC_PB_CUSTOMER_DIR/{src}` to a new unique folder id, returns the new id. (Reuse
  `SPBWC_Storelly_IO` helpers.)
- Apply in: `order_again_cart_item_data()` (also **add the missing `pcpb` restore**), the saved-
  design save/load, and in-cart re-edit.
- Folders become append-only per instance → no cross-order overwrite.

### C5. Lifecycle / housekeeping
- Disk growth: cloning multiplies folders. Add retention (e.g. prune saved-design folders on
  CPT delete; optional cap on saved designs per user). **Any advertised cap must exist in code**
  (CLAUDE.md release checklist).
- `_pcpb_pdf_status` powers an admin "regenerate" affordance for failed renders.

---

## Open decisions (to confirm before/while building)

- **OD-1 Save storage:** CPT `spbwc_saved_design` (proposed, mirrors Quote) vs lightweight user
  meta. *Recommendation: CPT.*
- **OD-2 Preview format:** reuse existing `preview/*.png` (cheapest, no external call) vs
  Cloud2Print watermarked `pdf-preview` (truer to print, costs an API call). *Recommendation:
  PNG preview first; PDF-preview as opt-in.*
- **OD-3 Save entry points:** builder only, or also cart + order detail?
- **OD-4 Per-user cap:** max saved designs per account (0 = unlimited)? Affects disk + readme copy.
- **OD-5 Guest handling:** save-to-account requires login; offer "log in to save" on the builder?
- **OD-6 PDF trigger point:** `payment_complete` vs `order_status_processing/completed` — which
  status should fire the render job?
- **OD-7 Folder retention:** when to prune cloned folders (on CPT delete only, or also on
  order deletion / trash)?

---

## Compliance checklist (carried from CLAUDE.md)

- [ ] Every new PHP file `if ( ! defined( 'ABSPATH' ) ) exit;`.
- [ ] All input sanitised (`sanitize_*` + `wp_unslash`); all output escaped (`esc_*`).
- [ ] Nonce + `current_user_can()` / ownership on every new action & endpoint.
- [ ] Single `spbwc_` prefix; text domain `storelly-product-builder-for-woocommerce`.
- [ ] Cloud2Print declared under readme "External services"; gated by explicit opt-in.
- [ ] Any advertised limit (e.g. saved-design cap) actually enforced in code.
- [ ] `wp plugin check` → 0 errors; POT regen for new strings.
