# Dev Milestones — B2B Quote redesign (D2 + D3)

> Source of truth: [SPEC_QUOTE_USER_FLOW_UX.md](SPEC_QUOTE_USER_FLOW_UX.md) Part C.
> Confirmed decisions (2026-06-01): CPT `spbwc_quote` (OD-10) · negotiation loop in v1 (OD-2) ·
> Accept spawns a NEW WC order (OD-1) · all four D3 display modes in v1 (OD-5).
> Defaults: list-table admin (OD-3) · single-product/quote (OD-4) · full price suppression (OD-6) ·
> auto-expire + Day 5/10/13 reminders (OD-7) · PO v1 / Net terms v2 (OD-8) · `Q-YYYY-NNNN` (OD-9).
>
> Rules: every new PHP file `ABSPATH`-guarded; `spbwc_` prefix; text domain
> `storelly-product-builder-for-woocommerce`; nonce + capability on every action; enqueue assets;
> Action Scheduler for cron (WP-Cron loopback disabled locally); `wp plugin check` 0 errors per ship.

| # | Milestone | Scope | Status |
| --- | --- | --- | --- |
| **M1** | **CPT foundation** | Register `spbwc_quote` CPT (headless, `show_ui=false` for now) + 10 post statuses + `SPBWC_Quote` model (create, lines/totals accessors, `Q-YYYY-NNNN` numbering, validated status transitions, timeline via comments). No UI wired. | **in progress** |
| **M2** | **Admin: pricing reply + list** | Custom Quotes `WP_List_Table` (status tabs/search/bulk) + 2-col detail: request recap, **line-item builder** (custom repeater, editable unit price, live JS totals), terms (validity preset/payment/notes), Save draft / **Send pricing reply** / Send counter-offer / Withdraw. Built on design tokens. Capability (`manage_woocommerce`) + nonce. | **done** |
| **M3** | **Storefront submit → CPT** | `spbwc_submit_quote` now creates a `spbwc_quote` post (canonical request payload + pre-seeded product line) instead of a WC order. Modal redesigned: enqueued CSS/JS, `role=dialog`/focus-trap/ESC/scroll-lock, product context, per-field inline errors, success state. Nonce + nopriv kept; lightweight admin email (full `WC_Email` in M6). | **done** |
| **M4** | **Buyer My-Account** | `/my-account/quotes/` list + `view-quote` detail now read the CPT (by author). Priced detail: validity countdown, line-item table + totals, merchant note, action forms **Accept (PO) / Request changes (ask checkboxes) / Decline (reason)** — shown only on `sent`. Accept → `spawn_order_from_quote()` builds a NEW payable WC order (fees = quote lines, exact total), links both ways, stores PO, quote → `converted`, buyer routed to **Pay now**. Terminal/negotiating states show status banners (locked). | **done** |
| **M5** | **D3 display modes** | Global default (Get Quote settings) + per-product override (`_spbwc_quote_display_mode`) for Off / Both / Replace / Quote-only. Woo wiring in SPBWC_Request_Quote: `get_display_mode()`, standalone button hook, `is_purchasable`/`get_price_html`/`loop_add_to_cart_link`/`add_to_cart_validation`/`structured_data_product` filters. Verified all 4 modes in browser. | **done** |
| **M6** | **Emails + lifecycle** | Five `WC_Email` subclasses (new→admin, sent→customer, reminder→customer, accepted→admin, declined→admin) registered via `woocommerce_email_classes`/`_actions`, branded via WC header/footer templates; fired by `do_action()` from the quote flow (replaces the plain-text `wp_mail` notifiers). Action Scheduler daily sweep: `sent→expired` past validity + one-time expiry reminders at 7/3/1 days left. | **done** |
| **M7** | **Migration + cleanup** | `SPBWC_Quote_Migrator` — one-time Action Scheduler batch converts legacy quote orders → CPT (HPOS-safe `wc_get_orders` discovery by meta + broad status list, since legacy `…-accepted/-rejected` statuses were truncated to 20 chars; maps full + truncated slugs, copies line items, links + flags the order). Overview count → `wp_count_posts('spbwc_quote')` (removed the orphan `wp_storelly_quote_requests` table query); removed unused `$recent_quotes_l`. Legacy order statuses kept read-only. Verified under HPOS. | **done** |
| **M8** | **Compliance + tests** | `wp plugin check` 0 errors; readme external-services/features; POT regen; test matrix from spec §20. | todo |

Each milestone is committed locally (never pushed) after a lint/check pass.
