# Custom Order Detail — Storelly-native order workspace

> Status: **spec, confirmed 2026-06-05** (spec-first; not yet built).
> Goal: make the **Storelly Custom Orders → order detail** the PRIMARY place a print/POD shop
> manages a custom order — artwork, print files, options, history, customer context — so the
> WooCommerce order-edit screen is only a secondary process. Sections confirmed: all four
> proposed + extra POD-specific panels (the "Something else" the user asked for).
> Builds on: SPEC_CUSTOM_ORDER.md (M0–M15) + the HPOS-safe Custom Orders list (`spbwc_orders_manager`).

---

## 1. Routing & entry

- New view on the existing Orders page: `admin.php?page={SPBWC_PB_ORDERS_SLUG}&view={order_id}`.
  - `spbwc_orders_manager()` branches: `view` set + valid → render detail (`views/custom-order-detail.php`
    via a new `spbwc_render_order_detail( $order_id )`); else the list (current).
- List row **"View"** → this native detail (not WC edit). A secondary **"Open in WooCommerce"**
  link stays on the detail header for the full WC order screen.
- Capability: `current_user_can( 'spbwc_manage_product_builder' )` (same as the list). Back link to
  the list. Invalid/non-custom order id → notice + back.

## 2. Layout

Two columns (reuse admin tokens `--st-*` / `--nbd-st-*`, page-hero header):
- **Main (left, ~2fr):** Header summary · Design items · Order summary + addresses · History/timeline.
- **Sidebar (right, ~1fr):** Customer activity · Production checklist · Files panel.
Responsive: single column under ~1024px.

## 3. Sections

### S1 — Header summary + primary actions
- Order #, status pill, date, customer (name + email + link), grand total, **aggregate PDF status**
  (Ready / Partial / Failed / —).
- Actions: **Download print PDFs** (in-page, all design items), **Regenerate print PDFs**
  (reuses `SPBWC_Order_PDF::regenerate_url()` / handler), **Open in WooCommerce** (secondary).

### S2 — Design items (core, web2print)
Per custom line item:
- **Preview gallery** — all `preview/*.png` views (not just first), click to enlarge.
- **Option spec list** — from `_pcpb_option_price` fields (name → value + price), clean grid.
- **Print specs** — dimensions / DPI / unit / page count read from the design `config.json` /
  `design_output.json` when present.
- **Per-item actions** — Download (png / svg / pdf / pdf-preview, reusing the existing
  `spbwc_download_order_designs` AJAX + `storelly-general.js`), Regenerate, **View in designer**
  (existing `nbd_item_key` link), per-item `_pcpb_pdf_status` badge + `_pcpb_preview_downloads` count.

### S3 — Order summary + addresses
- Line items (all, incl. non-design) with qty/price, subtotal/shipping/tax/total.
- Billing + shipping address blocks; payment method; shipping method.

### S4 — Order history / timeline
- WooCommerce order notes + status changes via `wc_get_order_notes( ['order_id'=>id] )`, newest first,
  with author + date, system vs customer note styling.
- **Add internal note** form (admin-only, nonce) → `$order->add_order_note()`.

### S5 — Customer activity (sidebar)
For the order's customer (by `customer_id`; guest → email match where possible):
- Lifetime: # custom orders, total spent, last order date.
- # saved designs (CPT by author), # preview downloads (sum `_pcpb_preview_downloads`).
- List of this customer's other custom orders (link to each detail).
- HPOS-safe (`wc_get_orders( ['customer_id'=>id] )`).

### S6 — Production / fulfillment checklist (extra, sidebar)
- Lightweight per-order production state stored in order meta (`_spbwc_production_*`):
  Artwork approved · Print files ready · Sent to print · Shipped. Checkboxes (nonce) — an at-a-glance
  production tracker independent of WC order status. (Free/local; no cloud.)

### S7 — Files panel (extra, sidebar)
- All files for the order's designs: generated SVG/PNG/PDF (`customer-pdfs/`, `svg/`, root png) +
  the buyer's uploaded files (`_pcpb_field` upload values). Each: name, type, size, download.
- "Download everything (zip)" for the whole order.

## 4. Actions & data sources

| Action | Mechanism |
| --- | --- |
| In-page download (png/svg/pdf/preview) | existing `spbwc_download_order_designs` AJAX (cap `upload_files` → keep, or align to `manage_woocommerce`) + `storelly-general.js`, controls embedded in S2 |
| Regenerate PDFs | `SPBWC_Order_PDF::maybe_regenerate()` (admin_init, nonce) — already shipped |
| Add note | `$order->add_order_note()` (nonce + cap) |
| Production checkboxes | order meta write (nonce + cap) |
| Customer activity | `wc_get_orders`, `get_posts(spbwc_saved_design)`, item-meta sums |
| Print specs | read `config.json` / `design_output.json` in the design folder |

No new external services. All read/derived from existing order + design data.

## 5. Open decisions
- **OD-D1 Files panel scope:** generated print files only, or also raw buyer uploads (may be large)?
- **OD-D2 Production checklist:** fixed 4 steps (proposed) vs configurable list.
- **OD-D3 Download capability:** keep admin download at `upload_files`, or tighten to `manage_woocommerce`?
- **OD-D4 Guest customer activity:** match by billing email when `customer_id = 0`?

## 6. Milestones

| # | Milestone | Scope | Status |
| --- | --- | --- | --- |
| **D1** | **Routing + detail shell** | `view` branch in `spbwc_orders_manager`; `views/custom-order-detail.php`; header summary (S1) + back link + "Open in WooCommerce"; list "View" → native detail. | todo |
| **D2** | **Design items (S2)** | Preview gallery + option spec + print specs + per-item download/regenerate/designer + status/badges. In-page download wired. | todo |
| **D3** | **Summary + history (S3+S4)** | Order summary + addresses; notes/timeline + add-note form. | todo |
| **D4** | **Customer activity (S5)** | Sidebar lifetime + saved designs + downloads + other custom orders (HPOS-safe). | todo |
| **D5** | **Production checklist + Files panel (S6+S7)** | Production meta checkboxes; files list + download-all-zip. | todo |
| **D6** | **Polish + compliance** | Tokens, responsive, `wp plugin check` 0 errors, POT, browser-verify. | todo |

> Rules: ABSPATH guard; `spbwc_` prefix; text domain `storelly-product-builder-for-woocommerce`;
> nonce + capability on every action; escape all output; HPOS-safe (no `wp_posts` order queries);
> design tokens (no raw hex); reuse existing handlers (download/regenerate) — don't duplicate.
