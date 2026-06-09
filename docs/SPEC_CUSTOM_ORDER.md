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

## Part D — Usability improvements (round 2)

> Status: **confirmed 2026-06-03**, spec-first. Scope: P1 (cart-only) + P2 + P3 + P4.
> Builds on the shipped M0–M4 + UX (commits 42bd5b1, c86437b, c864726). NOT yet built.

Goal: remove the main friction points found after M0–M4 so the feature is easy to use, not just
functional. Grounded in current behaviour:
- Saving a design currently requires placing an order first (`render_save_link()` only fires on
  `woocommerce_order_item_meta_end` for an order).
- The storefront order items table renders **no image** by default → the buyer never sees the
  design they ordered.
- The preview download always returns a `.zip`, even for a single-view design.
- Delete is a one-click destructive POST; Load gives no "added" feedback.

### D1 — Save a design from the cart (P1)
- **Decision UX-1: cart line item only** (no change to the Angular builder this round).
- Render a **"Save design"** link via `woocommerce_after_cart_item_name` (next to the existing
  "Edit options" from `cart_item_name()`, `class-frontend-options.php:1268`).
- Handler: nonce + carries the `cart_item_key` → read that cart item's `pcpb_meta`
  (`pcpb` folder, `field`, `options`, `original_price`, `option_price`, product/variation) →
  **clone folder** (`spbwc_clone_design_folder`) → create `spbwc_saved_design` for the logged-in
  user (reuse `SPBWC_Saved_Designs` storage) → redirect to the Saved designs tab with the notice.
- Guest (not logged in): render a **"Log in to save"** link to My Account instead (OD-8).
- Net effect: design-now / buy-later works without an order.

### D2 — Show the buyer's design on the order (P2)
- Render the design `preview/` image as a thumbnail via `woocommerce_order_item_meta_start`
  (no template override; `_meta_end` already proven to fire for the chips). Ownership-gated.
- Surfaces: My Account order detail + order-received page. Falls back silently if no preview.

### D3 — Smarter preview download (P3)
- Add a **"View"** action = direct link to the preview image URL, opens in a new tab. Preview
  PNGs already live under public `uploads/.../designs/{folder}/preview` so no handler is needed
  (OD-9).
- **"Download"**: if exactly one preview image → stream the **PNG** as an attachment; if more
  than one → zip (current behaviour). Keeps the stream-then-delete pattern for the zip path.

### D4 — Safe delete + add-to-cart feedback (P4)
- **Delete confirm**: a small enqueued `custom-order.js` adds a `confirm()` guard on
  `.spbwc-saved-designs__delete` submit (progressive enhancement; the server still nonce-checks).
- **Load feedback**: on a successful Load, call `wc_add_notice()` "Design added to your cart"
  before redirecting to the cart (server-side; no JS needed).

### ⚠ Known limitation — Cart block (browser-verified 2026-06-03)
M7's "Save design" link (and the plugin's existing "Edit options" link) hook
`woocommerce_after_cart_item_name` / the `woocommerce_cart_item_name` filter, which only fire on
the **classic `[woocommerce_cart]` shortcode**. The WooCommerce **Cart block** (default on this
store, `wp:woocommerce/cart`) renders via the Store API and does **not** fire these classic
hooks, so the links don't appear there. Verified: switching the Cart page to the classic
shortcode makes M7 work end-to-end (save → clone → CPT → tab with notice + thumbnail card). The
cart-save handler also had to move to `wp_loaded` priority 50 + `wc_load_cart()` so the cart is
populated when the handler reads it (fixed, commit 55d26d4).

---

## Part E — Cart-block integration + User Account settings & activity stats

> Status: **confirmed 2026-06-03**, spec-first. Decisions: Settings live in a new **"User Account"
> tab** of the existing Settings page (`menu-settings.php`); **user activity stats go on the
> Overview** page; Cart-block Save button uses the **official slot-fill + JS build** (A).

### E1 — Store API data extension (PHP, shared by block + classic)
Expose per-cart-item data so the Cart block (React/Store API) can render the Save button:
- On `woocommerce_blocks_loaded`, call `woocommerce_store_api_register_endpoint_data()` for the
  **cart item** schema, namespace `storelly`, returning per item:
  `is_design` (bool), `save_url` (nonce URL), `preview` (image URL).
- Refactor the nonce-URL builder out of `render_cart_save_link()` into a public
  `SPBWC_Saved_Designs::cart_save_url( $cart_item_key )` so both the classic link and the Store
  API share one source. Handler stays `handle_cart_save()` (unchanged).
- New thin class `SPBWC_Cart_Store_API` (or a method on Saved_Designs) registers the extension.

### E2 — Cart-block Save button (option B: wp.data, no build) — **chosen 2026-06-03**
No Node toolchain. A single vanilla JS file `static/js/cart-block-save.js`, enqueued only on the
Cart block page (`has_block('woocommerce/cart')`), that:
- reads the official data store `wp.data.select('wc/store/cart').getCartData().items`;
- for each item where `item.extensions.storelly.is_design` is true (fed by E1), injects a
  token-styled **"Save design"** button into that line item's row (matched to
  `.wc-block-cart-items__row` by index, guarded against re-injection with a data flag) wired to
  `item.extensions.storelly.save_url`; guests get a "Log in to save" link (`spbwcCartSave.loginUrl`);
- re-runs on `wp.data.subscribe()` (cart updates) and a `MutationObserver` (block re-render).
Best-effort enhancement: if the block markup changes and matching fails, no button renders (graceful)
— the order-detail, classic-cart, and (future) builder entry points remain the reliable paths.
Rejected alternative (A): official `@woocommerce/blocks-checkout` slot-fill + `@wordpress/scripts`
build — more future-proof but adds a ~500 MB dev `node_modules` toolchain; not worth it for one button.

### E3 — "User Account" settings tab (Classic Cart + entry points)
Add a tab to `views/menu-settings.php` + handler in `spbwc_settings()`:
- **Cart compatibility**: detect & show current Cart mode (scan the Cart page for
  `wp:woocommerce/cart` vs the `[woocommerce_cart]` shortcode). A **"Switch Cart to Classic"**
  action replaces the Cart page content with the shortcode (stores the previous content in an
  option for one-click **Undo**). Inline explanation of block vs classic + why the Save link needs
  one of: classic cart, or the E1/E2 block integration.
- **Save entry points**: toggles `save_on_cart` / `save_on_order` / `save_on_builder` (default on)
  — the render methods check these.
- Nonce + `manage_woocommerce` on all actions.

### E4 — User activity stats on Overview
Add a "Customer design activity" section to `views/overview.php`:
- Saved designs total (`wp_count_posts('spbwc_saved_design')`) + distinct authors.
- Design re-orders (orders carrying `_pcpb_folder`, HPOS-safe `wc_get_orders`).
- Preview downloads — add a counter (increment order-item meta `_pcpb_preview_downloads` +
  a global option in `SPBWC_Buyer_Downloads::maybe_handle_download`).
- Top customized / saved products; a short "recent activity" list.
- Read-only, computed on load (cache if slow — OD-14).

### Open decisions (Part E)
- **OD-12 Build tooling:** commit `build/` artifacts + `src/` + `package.json` (wp.org needs the
  shipped JS). Confirm Node/`@wordpress/scripts` toolchain is acceptable in this repo.
- **OD-13 Classic-cart switch:** mutate the Cart page content (reversible, proposed) vs only show
  copy-paste instructions.
- **OD-14 Stats storage:** compute on the fly (proposed) vs cache in a transient/option.

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
- **OD-8 Guest save (D1):** show "Log in to save" (proposed) vs hide the save action for guests.
- **OD-9 "View" link (D3):** exposes the low-res preview image URL publicly (preview already
  lives under public uploads). *Recommendation: acceptable for preview.*
- **OD-10 Delete confirm (D4):** JS `confirm()` (proposed, simplest) vs inline two-step (no-JS).
- **OD-11 Cart block support:** the cart links only render on the classic cart shortcode. Ship a
  Store-API / Cart-block integration vs document "use the classic cart" as a requirement (the
  existing Edit-options link shares this constraint). *Recommendation: document now; scope a block
  integration as a separate milestone if the store keeps the Cart block.*

---

## Compliance checklist (carried from CLAUDE.md)

- [ ] Every new PHP file `if ( ! defined( 'ABSPATH' ) ) exit;`.
- [ ] All input sanitised (`sanitize_*` + `wp_unslash`); all output escaped (`esc_*`).
- [ ] Nonce + `current_user_can()` / ownership on every new action & endpoint.
- [ ] Single `spbwc_` prefix; text domain `storelly-product-builder-for-woocommerce`.
- [ ] Cloud2Print declared under readme "External services"; gated by explicit opt-in.
- [ ] Any advertised limit (e.g. saved-design cap) actually enforced in code.
- [ ] `wp plugin check` → 0 errors; POT regen for new strings.

---

## Milestone W2-SAMPLE — Custom Order Sample seeder (Wave 2, item 9)

**Status:** DRAFT (2026-06-09) · part of `SPEC_ADMIN_UX_POLISH_W2.md`

### Vấn đề
Đã có sample seeder cho demo product (`SPBWC_Demo_Seeder`), B2B (`SPBWC_B2B_Sample`), Quote
(`SPBWC_Quote_Sample`) — nhưng **chưa có** cho Custom Order. Merchant mới không có dữ liệu Custom Order mẫu
để xem flow COW folder / proof / detail.

### Yêu cầu
1. Tạo `includes/class-custom-order-sample.php` (`SPBWC_Custom_Order_Sample`) mirror pattern
   `SPBWC_Demo_Seeder` / `SPBWC_B2B_Sample`:
   - Seed 1 custom order mẫu + **COW design folder** qua `SPBWC_Storelly_IO::spbwc_clone_design_folder`
     (mỗi instance là bản clone, không share — đúng nguyên tắc COW).
   - Tag mọi entity tạo ra bằng `_spbwc_is_sample` để Undo/Remove sạch.
   - Bundle asset trong `storage/` (local file read + media sideload, **không** `wp_remote_*`).
2. **Local-only, nonce + cap**: chạy chỉ khi merchant bấm (Welcome card / Setup Wizard "Add Custom Order
   sample"); admin-post/AJAX có nonce + `current_user_can`.
3. **Undo/cleanup**: nút "Remove sample" gỡ order + folder + attachments theo tag.
4. Thêm card vào Welcome / Setup Wizard (mirror demo card UX).

### Acceptance
- Bấm "Add Custom Order sample" → có 1 custom order mẫu + COW folder hiển thị đúng ở Custom Order detail.
- Remove sample → sạch, không rác data, không ảnh hưởng order thật.
- Không phone-home; Plugin Check 0 error.

### Files
`includes/class-custom-order-sample.php` (mới), `includes/class-custom-order-detail.php`,
`includes/class-io.php`, asset bundle `storage/…`, view Welcome / `views/setup-wizard/landing.php`.
