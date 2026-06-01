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
| **M4** | **Buyer My-Account** | `/my-account/quotes/` + `view-quote` read CPT. Priced detail: validity countdown, line-item table, terms, action bar **Accept / Decline / Request changes** (reason + ask taxonomies). Accept → spawn NEW WC order (OD-1), link both ways, capture PO, → `converted`, route to Pay. Terminal-state locking. | todo |
| **M5** | **D3 display modes** | Per-product setting + global default (Off / Both / Replace / Quote-only). Woo wiring: `is_purchasable`, `get_price_html`, single + shop/archive loop, `add_to_cart_validation` guard, price-schema suppression. | todo |
| **M6** | **Emails + lifecycle** | `WC_Email` subclasses: created / sent / accepted / declined / converted. Action Scheduler: expiry sweep (`sent→expired`) + Day 5/10/13 reminders. | todo |
| **M7** | **Migration + cleanup** | One-time migrate existing `spbwc-quote-*` WC orders → CPT (AS batch); keep old statuses read-only. Overview widget → `wp_count_posts`. Remove orphan `wp_storelly_quote_requests` ref + `$recent_quotes_l`. Fix/remove Form Builder reorder + `select` type. | todo |
| **M8** | **Compliance + tests** | `wp plugin check` 0 errors; readme external-services/features; POT regen; test matrix from spec §20. | todo |

Each milestone is committed locally (never pushed) after a lint/check pass.
