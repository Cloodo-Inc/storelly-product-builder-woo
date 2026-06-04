# Dev Milestones — B2B Client (Company Accounts)

> Source of truth: [SPEC_B2B_CLIENT.md](SPEC_B2B_CLIENT.md).
> Confirmed decisions (2026-06-04): CPT `spbwc_company` + user-meta link (D1) ·
> both tier % ladder AND per-company per-product overrides (D2) ·
> procurement approval reuses the `spbwc_quote` state machine (D3) ·
> full 5-feature spec, phased, Free/Pro marked (D4).
>
> Rules: every new PHP file `ABSPATH`-guarded; `spbwc_` prefix only (no new `pcpb_`);
> text domain `storelly-product-builder-for-woocommerce`; nonce + capability/company-role on
> every action; `_spbwc_company_id` scoping on every query; enqueue assets; `$wpdb->prepare()`
> on the new price-rules table; no external service / no phone-home; Action Scheduler for any cron;
> `wp plugin check` 0 errors per ship. Impact report before any milestone that touches the cart.

> **Free/Pro note (revised 2026-06-04):** the confirmed freemium model is *everything local = Free
> (unlimited), only features that call app.storelly.com are Paid* — see the freemium spec. This B2B
> suite is **100% local**, so all milestones are **Free**. The "Pro" markers from the first draft are
> dropped; M4/M5 stay heavier engineering but remain free. (Raised with the user 2026-06-04.)

| # | Milestone | Feature | Tier | Scope | Status |
| --- | --- | --- | --- | --- | --- |
| **M1** | **Company core + upgrade + public store** | F1 | Free | Register CPT `spbwc_company` (headless) + `SPBWC_Company` model (create, status, meta accessors, slug, user link, invites, timeline, seats). Merchant "Upgrade to B2B" row action on Users + upgrade form + **B2B Companies** admin hub (status tabs/counts, detail, approve/suspend/save). My-Account `brand-store` endpoint + 4-section profile edit (branding/corporate/address/contact) with Brand Page Header + logo/banner upload. **Public `/store/<slug>` storefront** (pretty + query-var fallback, 404 gating, brand header + pre-approved grid). Owner upgrade email. No cart changes. | **done (2026-06-04)** |
| **M2** | **Tier pricing** | F3 | Free | `spbwc_b2b_tiers` option + **B2B Pricing** admin page (tier ladder CRUD, config-save). Assign tier on company detail (`_spbwc_company_tier` + timeline). `SPBWC_B2B_Pricing`: discount via `woocommerce_before_calculate_totals` pri 20 (builder = discount base only from immutable pcpb_meta; regular = fresh catalog price → no compounding) + `woocommerce_get_price_html` "Your company price" (Pattern 7) + conditional b2b.css enqueue. **Zero edits** to class-frontend-options.php. Impact report done. Verified: 15% tier → cart 999.99→849.99, B2C no-op, display note. | **done (2026-06-04)** |
| **M3** | **Reorder** | F4 | Free | My-Account `reorders` endpoint (cards from past `pcpb_meta` order lines, dedup) + quick-reorder modal (qty presets from quantity_breaks, address, B2B price, place order) + reorder-placed success. Extends `class-saved-designs.php` `load_design()` clone path + `order_again_cart_item_data()`. | todo |
| **M4** | **Per-company price overrides** | F2 | Free | Table `{$prefix}spbwc_b2b_price_rules` (+ dbDelta/uninstall). Company detail → "Pricing & products" tab: bound-products table, bind/unbind, pct/fixed override, manual override, allow-list sync to `_spbwc_company_allowed_products`. Cascade in price filter (fixed > pct > tier > retail). Product visibility "B2B only" / "Brand Store locked". | todo |
| **M5** | **Team procurement** | F5 | Free | My-Account `team` endpoint (roster, role badges, seats) + member detail (permissions matrix, spending limits) + invite teammate. Approval gate: requester submit over limit/threshold → internal `spbwc_quote` (`_spbwc_quote_kind='procurement'`, `_spbwc_quote_company_id`). My-Account `approval` queue + approval-detail (preview, timeline thread, approve/reject). Approve → reuse quote→WC-order convert. Merchant Members/Permissions/Activity tabs on company detail. | todo |
| **M6** | **Compliance + tests** | — | both | `wp plugin check` 0 errors; capability/role audit; `_spbwc_company_id` scoping audit; POT regen (new strings) + RTL; readme (no external services to add); Free/Pro gate (`spbwc_b2b_is_pro()`) degrades gracefully; test matrix from spec §9 state models. | todo |

Each milestone is committed locally (never pushed) after a lint / `wp plugin check` pass.

## Sequencing notes
- **M1 → M3 are Free and cart-safe** (M3 reuses existing add-to-cart) → ship first for fast value.
- **M2 and M4 change pricing** → each needs an impact report (B2C, quote flow, custom-order PDF, HPOS) before code.
- **M5 is the heaviest** but reuses the shipped `spbwc_quote` engine, so most cost is UI + role plumbing, not a new state machine.
- Open questions in spec §12 (payment-terms = labels only, one-company-per-user, brand-store URL, upgrade entry point, seat cap) should be confirmed before M1 code.
