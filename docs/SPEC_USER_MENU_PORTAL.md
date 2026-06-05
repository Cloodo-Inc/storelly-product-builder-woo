# User Menu (My Account) Portal — Audit, Design Tokens & Performance Plan

> **Status:** Plan for review (M1 in progress).
> **Date:** 2026-06-05
> **Scope:** Every front-end page reachable from the WooCommerce **My Account**
> menu that this plugin adds — Quotes, Saved designs, Reorders, Brand Store,
> Team, Approvals, and the Designer Store (`my-store`).
> **Why:** Endpoints work but rest on a fragile rewrite-flush; the Designer Store
> is visually off-system (no design tokens); markup is built inline per class so
> the portal is hard to keep consistent and theme-overridable; menu items are
> assembled by 7 classes racing on the same array; menu-count queries run on
> every account page-load.
> **Related:** [[SPEC_B2B_UX_REDESIGN]] (per-page B2B markup — subsumed here),
> [[SPEC_QUOTE_USER_FLOW_UX]], [[SPEC_FREEMIUM]].

---

## 0. Inventory (source of truth)

Plugin creates **no WP pages** for these — all are WooCommerce account endpoints
(query var + `woocommerce_account_{slug}_endpoint` hook), menu items injected via
`woocommerce_account_menu_items`.

| Page | Slug | Class | File | Menu visibility | Flush |
|---|---|---|---|---|---|
| Quotes | `quotes` / `view-quote` | `SPBWC_Request_Quote` | `includes/class-request-quote.php` | quote enabled | ❌ none |
| Saved designs | `saved-designs` | `SPBWC_Saved_Designs` | `includes/class-saved-designs.php` | any logged-in | ✅ flag |
| Reorders | `reorders` | `SPBWC_B2B_Reorders` | `includes/b2b/class-b2b-reorders.php` | any logged-in | ✅ flag |
| Brand Store | `brand-store` | `SPBWC_B2B_Account` | `includes/b2b/class-b2b-account.php` | B2B member | ✅ flag |
| Team | `team` | `SPBWC_B2B_Team` | `includes/b2b/class-b2b-team.php` | B2B member | ✅ flag |
| Approvals | `approval` | `SPBWC_B2B_Procurement` | `includes/b2b/class-b2b-procurement.php` | B2B approver | ✅ flag |
| Designer Store | `my-store` | `SPBWC_Marketplace` | `includes/launcher/class.launcher.php` | designer + marketplace on | ❌ none |

---

## 1. Problem analysis

### 1.1 Activation does not provision endpoints (404 risk)

`register_activation_hook` (`storelly-product-builder-for-woocommerce.php:71`) creates
one "Product Builder" page and tables but **never flushes rewrite rules**. Endpoints
register on `init`. The "new" classes self-heal with a per-endpoint lazy-flush flag
(`class-saved-designs.php:73`); the **legacy `quotes`/`view-quote` and `my-store`
register `add_rewrite_endpoint` with no flush of their own** (`class-request-quote.php:597`,
`class.launcher.php:89`). Today they only resolve because the saved-designs flush
regenerates *all* rules — an implicit, order-dependent dependency. If saved-designs
is disabled/reordered, or a full-page cache serves the first request, `quotes`/
`my-store` 404 until **Settings → Permalinks → Save**.

### 1.2 Designer Store is off-system

`launcher.css` (~19KB) references **no design token**, rendered by standalone
`templates/launcher/store/*.php`. It is the only User Menu page visually outside the
shared `_tokens-storefront` system.

### 1.3 Inline markup, not theme-overridable

Quotes/Reorders/Brand Store/Team/Approvals build HTML inline inside PHP classes —
no `templates/` for theme override (wp.org best practice), inconsistent page chrome.

### 1.4 Menu assembly is uncoordinated

7 classes each filter `woocommerce_account_menu_items`, several `unset` and re-append
`customer-logout` (`class-request-quote.php:602`). No central order/grouping control.

### 1.5 Menu-count queries on every account load

Each render runs `get_posts` to count awaiting quotes/approvals
(`class-request-quote.php:609`) — 2–3 queries on *every* account page, every load.

---

## 2. Strategy

Three layers, additive, no behaviour change to buyer flows:

- **T1 — One token base:** every User Menu page depends on `_tokens-storefront.css`.
  Migrate `launcher.css` colours → `var(--nbd-mb-*)`. One brand change → whole portal.
- **T2 — Account shell:** shared header/breadcrumb/card wrapper + extract inline
  markup into `templates/account/*.php` (theme-overridable via `wc_get_template`).
- **T3 — Menu registry:** one place registers/orders/groups all account menu items.

Plus performance: cache counts, minify+bundle storefront CSS, skeleton loaders.

---

## 3. Milestones

### M1 — Rewrite-flush hardening (safe, do first) — ✅ DONE
- Added self-healing lazy-flush flag to `quotes`/`view-quote`
  (`spbwc_quotes_endpoint_flushed`, `class-request-quote.php`) and `my-store`
  (`spbwc_marketplace_endpoint_flushed`, `class.launcher.php`) — no longer rely on
  saved-designs flushing the whole rule set.
- `spbwc_install()` now clears all 8 endpoint flush flags so (re)activation re-arms
  the lazy flush on next `init` (`class-product-builder-backend.php`).
- Acceptance: fresh activate (no permalink re-save) → all endpoints resolve;
  ordering-independent. PHP lint clean. Browser/rewrite-dump verify pending.

### M2 — Menu-count cache
- Cache awaiting-quote / pending-approval counts in a 60s transient (or user-meta),
  invalidate on quote/procurement status change.
- Acceptance: account page-load fires 0 count queries on warm cache.

### M3 — Token base unification
- Ensure every endpoint enqueues `_tokens-storefront` first.
- Migrate `launcher.css` hardcoded colours → tokens; add `launcher-rtl` parity.
- Acceptance: changing a `--nbd-mb-*` token visibly restyles Designer Store too.

### M4 — Account shell + template extraction
- `templates/account/` : `shell.php` (header/breadcrumb/card), one partial per page.
- Route each endpoint through `wc_get_template` so themes can override.
- Reuse existing `.spbwc-rfq-*` / `.spbwc-co-*` atoms (per [[SPEC_B2B_UX_REDESIGN]]).
- Acceptance: all 7 pages share identical chrome; theme override resolves.

### M5 — Central menu registry
- One `woocommerce_account_menu_items` filter; declarative item list with order +
  group + visibility callback; remove per-class menu juggling.
- Acceptance: stable menu order; one `customer-logout` reposition; no duplicates.

### M6 — Performance build
- Minify CSS+JS for production; bundle the 4 B2B storefront files into one.
- Skeleton loaders for saved-designs / reorders galleries.
- Acceptance: B2B page = 1 CSS request; gallery shows skeleton before thumbs.

### M7 — Compliance
- Plugin Check 0 error; POT regen for new strings; readme externals unchanged.

---

## 4. Out of scope
- Buyer designer canvas (`app-product-builder.css`) — separate editor.
- `spbwc_option_builder_variable` blob on product pages — tracked elsewhere.
