# Dev Milestones — Custom Order (PDF · Downloads · Saved Designs · Re-order/Re-edit)

> Source of truth: [SPEC_CUSTOM_ORDER.md](SPEC_CUSTOM_ORDER.md).
> Confirmed decisions (2026-06-01): PDF decoupled from `enable_api_sync` + Action-Scheduler
> queue (CD-1) · buyer downloads **preview** any time (CD-2) · save = **full design, clone
> folder** (CD-3) · spec-first, no code until reviewed (CD-4).
>
> Rules: every new PHP file `ABSPATH`-guarded; `spbwc_` prefix; text domain
> `storelly-product-builder-for-woocommerce`; nonce + capability/ownership on every action;
> enqueue assets; Action Scheduler for cron (WP-Cron loopback disabled locally); Cloud2Print
> declared in readme "External services" + opt-in; `wp plugin check` 0 errors per ship.

| # | Milestone | Scope | Status |
| --- | --- | --- | --- |
| **M0** | **Copy-on-write folder service** | `spbwc_clone_design_folder($src): string` deep-copies `SPBWC_PB_CUSTOMER_DIR/{src}` → new unique id (reuse `SPBWC_Storelly_IO`). Skips generated dirs (`customer-pdfs`/`pdf-templates`). Basename-validated. Foundation for M2/M3/M4. → `class-io.php`. | **done** |
| **M1** | **Decouple + queue order PDF** | New `SPBWC_Order_PDF` (`class-order-pdf.php`): on `payment_complete`/`processing`/`completed` queues an Action-Scheduler job (`spbwc_generate_order_pdfs`), gated by **`enable_cloud2print_api`** (NOT `enable_api_sync`); per-order `_pcpb_pdf_scheduled` de-dupe; job loops items → `spbwc_export_pdf()` per `_pcpb_folder` → writes `_pcpb_pdf_status` (done/failed, hidden). Sync fallback when AS absent. Launcher sync untouched. | **done** |
| **M2** | **Fix re-order / re-edit (Case 4)** | `order_again_cart_item_data()`: restore the missing `pcpb` folder **and** clone it (M0) so the new cart item is independent; cart preview re-pointed to the clone. In-cart "Edit options" re-saves into the cart item's own (already-cloned) folder, so a past order is never mutated. | **done** |
| **M3** | **Buyer preview download (CD-2)** | Buyer endpoint (own nonce + order-ownership check) serving **preview only** (`{folder}/preview/*.png`; optional watermarked `pdf-preview`, OD-2). Surface per custom line item in My Account order detail (`woocommerce_order_item_meta_end`). Full `customer-pdfs/` stays admin-only. | todo |
| **M4** | **Saved designs CPT + My Account (CD-3)** | CPT `spbwc_saved_design` (`show_ui=false`, author=buyer) + meta. Endpoint `saved-designs` + nav item (mirror Quote). Save = clone folder → CPT; Load = clone → cart/builder via `nbo_values`; Delete = CPT + folder. Nonce + author ownership on all actions. Entry points per OD-3; per-user cap per OD-4. | todo |
| **M5** | **Housekeeping + lifecycle** | Folder retention on saved-design delete / order trash (OD-7); enforce advertised caps in code (OD-4); `_pcpb_pdf_status` admin surfacing; guest "log in to save" affordance (OD-5). | todo |
| **M6** | **Compliance + tests** | `wp plugin check` 0 errors; readme external-services (Cloud2Print) + feature copy matches code; POT regen; test matrix from spec §Part C. | todo |

Dependencies: **M0 → M2, M3, M4**. M1 is independent (can run in parallel with M0/M2).

Each milestone is committed locally (never pushed) after a lint/check pass.
