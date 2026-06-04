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
| **M3** | **Buyer preview download (CD-2)** | `SPBWC_Buyer_Downloads` (`class-buyer-downloads.php`): "Download design preview" link per custom line item on My Account order detail (`woocommerce_order_item_meta_end`), own nonce + order-ownership check. Serves **preview PNGs only** (`{folder}/preview`), zipped, streamed then deleted — no public file left. Full `customer-pdfs/` stays admin-only. (OD-2: PNG preview chosen.) | **done** |
| **M4** | **Saved designs CPT + My Account (CD-3)** | `SPBWC_Saved_Designs` (`class-saved-designs.php`): CPT `spbwc_saved_design` (`show_ui=false`, author=buyer) + meta. Endpoint `saved-designs` + nav item (one-time rewrite flush). Save (OD-3: from order line item) = **clone folder** → CPT; Load = clone → `add_to_cart` → cart; Delete = CPT + folder. Nonce + author/order ownership on all actions. Per-user cap via `spbwc_saved_design_max` filter (OD-4: default unlimited). | **done** |
| **M5** | **Housekeeping + lifecycle** | Folder cleanup on saved-design delete (M4) + **on order permanent delete** (`woocommerce_before_delete_order` + `before_delete_post`, basename-guarded). Per-user cap enforced via `spbwc_saved_design_max` filter (OD-4). `_pcpb_pdf_status` surfaced in the admin order metabox + nonce-protected "Regenerate print PDFs" action. Guest "log in to save" shipped on cart (builder Save button itself deferred — no builder entry yet). | **done** |
| **M6** | **Compliance + tests** | Satisfied in round-3 M15: `wp plugin check` 0 real errors; Cloud2Print declared in readme external-services; POT regen (2270 msgids); both cart modes verified. | **done** |

### Round 2 — Usability (spec §Part D, confirmed 2026-06-03)

| # | Milestone | Scope | Status |
| --- | --- | --- | --- |
| **M7** | **Save from cart (P1/D1)** | "Save design" link via `woocommerce_after_cart_item_name` (cart-only, UX-1) → nonce + `cart_item_key` → read cart `pcpb_meta` → clone folder → `spbwc_saved_design` for the logged-in buyer → redirect to tab w/ notice. Guest → "Log in to save" (OD-8). Reuses `SPBWC_Saved_Designs`. | **done** |
| **M8** | **Design thumbnail on order (P2/D2)** | `render_order_thumbnail()` via `woocommerce_order_item_meta_start` on order detail + order-received; ownership-gated, plain-text-guarded, silent fallback. Shared `owned_item_preview_images()` helper. | **done** |
| **M9** | **Smart download (P3/D3)** | "View" = direct public preview URL (new tab, no handler, OD-9). "Download" = stream PNG when single image, zip when multiple; `stream_and_exit()` now content-type aware + `$delete_after` (don't delete the real PNG). | **done** |
| **M10** | **Safe delete + cart feedback (P4/D4)** | `custom-order.js` adds `confirm()` to `.spbwc-saved-designs__delete` (OD-10, enqueued on account); `wc_add_notice()` "Design added to your cart" on a **successful** Load (orphan clone cleaned if add fails). | **done** |

### Round 3 — Cart-block integration + User Account settings/stats (spec §Part E, confirmed 2026-06-03)

| # | Milestone | Scope | Status |
| --- | --- | --- | --- |
| **M11** | **Store API cart-item data (E1)** | `woocommerce_store_api_register_endpoint_data()` on cart-item schema (ns `storelly`): `is_design` / `save_url` / `preview`. `SPBWC_Saved_Designs::cart_save_url()` public helper (raw URL, no `wp_nonce_url` esc_html — avoids JSON double-encode) shared by classic link + Store API. Browser-verified WC 10.7. | **done** |
| **M12** | **Cart-block Save button — option B (E2)** | No build. `static/js/cart-block-save.js` reads `wp.data` `wc/store/cart` items, injects a token-styled "Save design" button into each design line item (index-matched, re-inject-guarded, MutationObserver), wired to `extensions.storelly.save_url`; guest → log-in link. Enqueued only when `has_block('woocommerce/cart')`. Browser-verified (button on design row only). | **done** |
| **M13** | **User Account settings tab (E3)** | New tab in `menu-settings.php` + `spbwc_settings()` handler: detect Cart mode + one-click "Switch Cart to Classic"/back (reversible, content backed up to `spbwc_cart_block_backup`); Save entry-point toggles (`save_on_cart/order/builder`) gated via `SPBWC_Saved_Designs::entry_enabled()`; nonce + `manage_woocommerce`. Browser-verified. | **done** |
| **M14** | **User activity stats on Overview (E4)** | "Customer design activity" section in `views/overview.php`: saved-design count + distinct authors, custom-design order items (HPOS-safe), preview-download counter (`spbwc_preview_download_count` + per-item `_pcpb_preview_downloads`). Browser-verified. | **done** |
| **M15** | **Compliance + tests (round 3)** | `wp plugin check` 0 real errors on round-3 files (only folder-name TextDomainMismatch noise); POT regenerated (2270 msgids, all round-3 strings); new strings static → no MissingTranslators/UnorderedPlaceholders; no new external services; both cart modes verified. `.po/.mo` locale fill left to the ongoing i18n pass. No Node build (option B). | **done** |

Dependencies: **M0 → M2, M3, M4**. M1 is independent (can run in parallel with M0/M2).
Round 2 (M7–M10) depends on M0 (clone) + M4 (saved-design storage), all shipped.
Round 3 (M11–M14) depends on M4/M7; M12 depends on M11. OD-12/13/14 to confirm during build.

Each milestone is committed locally (never pushed) after a lint/check pass.
