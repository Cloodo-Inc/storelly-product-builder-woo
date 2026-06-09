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

### M2 — Menu-count cache — ✅ DONE
- `SPBWC_Request_Quote::get_awaiting_count()` caches the awaiting-quote count per
  user (60s transient); invalidated via `spbwc_quote_status_changed`. `false !==
  $cached` so a cached 0 is a hit, not a re-query.
- `SPBWC_B2B_Procurement::get_pending_count()` caches the pending-approval count
  per company (60s); invalidated on submit + on approve/reject. Full WP_Post[]
  fetch kept only for the queue render.
- Verified: wp eval shows transient set + invalidation works; account menu filter
  renders all endpoints with correct cached "Approvals (1)" badge, logout last,
  no fatals. PHP lint clean.

### M3 — Token base unification — ✅ DONE
- Found the Designer Store (`my-store`) dashboard was **unstyled**: markup
  (`.nbdl-nav-tab`, `.nbdl-dashboard`, `.nbdl-form`, `.nbdl-table-wrapper`…)
  existed but NO stylesheet defined it — raw theme defaults, off-system.
- `launcher.css` + `launcher-rtl.css`: brand navy `#404762` → `var(--nbd-mb-primary,
  #404762)` (fallback preserves look if tokens absent) — 20 occurrences each.
- `nbd_launcher` now depends on `spbwc-tokens-storefront` (registered with the
  shared handle/URL so WP de-dupes) → tokens load on the my-store page, so the
  popup + dashboard follow the design system.
- New `launcher-store.css` (+ `-rtl`): tokenised chrome for the designer dashboard
  — tabs, stat cards, forms, buttons, withdraw tables/pills, notification. Purely
  additive (was unstyled) ⇒ zero regression risk. Enqueued on `is_account_page()`.
- Acceptance: changing a `--nbd-mb-*` token now restyles the Designer Store popup
  AND dashboard. PHP lint clean. Browser visual verify deferred to post-merge.

### M4 — Account shell + template extraction — ✅ DONE (infra + first adopter)
- New `SPBWC_Account_Shell` (`includes/class-account-shell.php`): `open($args)` /
  `close()` emit one consistent header (title, subtitle, actions slot, breadcrumb)
  + body wrapper, and auto-enqueue the tokenised `account-shell.css`.
- Theme-overridable templates `templates/account/shell-header.php` +
  `shell-footer.php`, loaded via `wc_get_template( …, 'storelly/', plugin/templates )`
  → override at `yourtheme/storelly/account/*.php`.
- `account-shell.css` (+ `-rtl`): tokenised `.spbwc-account*` chrome.
- Adopted on **Saved designs** (first adopter; falls back to a plain `<h2>` if the
  shell class is absent, so it degrades safely).
- **Opt-in by design:** Quotes / Designer Store / B2B pages adopt the shell
  incrementally; un-adopted pages render unchanged. B2B pages were just rebuilt on
  the shared component library (see [[SPEC_B2B_UX_REDESIGN]]) so they adopt at
  merge to avoid clobbering that work.
- Acceptance: shell renders shared chrome + theme override resolves; PHP lint
  clean. Cross-page visual parity verified at merge (browser deferred).

### M5 — Central menu registry — ✅ DONE (normalizer approach)
- `SPBWC_Account_Menu` (`includes/class-account-menu.php`) runs ONE late
  (`priority 9999`) pass over the finished `woocommerce_account_menu_items` array:
  pulls the known Storelly endpoints into a stable grouped order
  (Designs → Quotes → B2B → Designer) after the core WooCommerce items, then pins
  `customer-logout` last. Adds a `spbwc_account_menu_items` filter for extensions.
- Chose a normalizer over rewriting all 7 per-class filters: it's authoritative
  regardless of load order, needs no edits to the freshly-rebuilt B2B classes
  (zero merge conflict), and duplicate keys are impossible by construction.
- Acceptance: verified — core items keep order, Storelly items grouped in canonical
  order, logout always last, no duplicates. PHP lint clean.

### M6 — Performance build — ✅ DONE (minify + skeleton)
- Minified the User-Menu storefront CSS with clean-css (preserves `calc()` spacing
  + custom-property fallbacks): `launcher`, `launcher-store`, `account-shell` (+ all
  `-rtl`) → `.min.css` (≈22–33% smaller; launcher 19.1K→14.6K).
- `SPBWC_Account_Shell::css_url()` prefers `.min.css` when present and `SCRIPT_DEBUG`
  is off, falling back to source so a missing build never breaks a page. Wired into
  the account-shell + launcher enqueues.
- Reproducible build script `tools/build-usermenu-css.mjs` (npx clean-css, no
  committed install) so the `.min` files can be regenerated.
- Skeleton placeholder for the Saved-designs gallery (`account-shell.css`):
  `aspect-ratio` reserves the square (no layout shift) + a short 3× shimmer that
  settles without JS; honours `prefers-reduced-motion`.
- **Deferred to merge (B2B territory):** bundling the 4 B2B storefront CSS files
  into one request lives in `class-b2b-assets.php`, which the concurrent B2B
  redesign is rewriting — do it at merge to avoid clobbering. Reorders gallery
  skeleton likewise (B2B-owned render).
- Acceptance: minified assets valid + loaded when present; gallery reserves space
  + shimmers before thumbs. Browser/Lighthouse verify at merge.

### M7 — Compliance
- Plugin Check 0 error; POT regen for new strings; readme externals unchanged.

---

## 3b. UX/UI improvement pass (G1–G6, approved 2026-06-05)

A second, user-approved pass on top of M1–M6. Done on this branch for the pages
this plugin owns (Saved designs, Quotes, Designer Store); the B2B-owned pages
(Reorders, Approvals, Team, Brand Store) are flagged for the merge pass so the
concurrent B2B redesign is not clobbered.

- **G5 perf (✅):** `launcher.css`/`launcher.js` + dashboard CSS now load **only on
  the `my-store` endpoint** (`SPBWC_Marketplace::is_my_store_endpoint()`) instead of
  every account page. Minified `launcher.js` (43.9K→19.1K, −56%) and
  `quote-storefront.js` (6.9K→3.7K) via esbuild; `SPBWC_Account_Shell::js_url()`
  prefers `.min.js`. Build script extended to JS.
- **G1 empty states (✅ mine):** `SPBWC_Account_Shell::empty_state()` helper +
  `.spbwc-account__empty` CSS; friendly icon + title + sub + primary CTA on Saved
  designs ("Browse products") and Quotes ("Request a quote"). Designer-store designs
  + Reorders/Approvals deferred to merge.
- **G2 sub-descriptions (✅ mine):** page subtitles via the shell on Quotes
  ("Track your quote requests…") + Designer Store ("Manage your designs, track sales
  and request payouts."). Withdraw min-amount already shown.
- **G3 action/status (✅):** view-quote already had primary Accept / ghost Request-
  changes / danger Decline + a validity countdown; added a `--urgent` countdown
  state (≤3 days → danger colour). Approval-queue hierarchy deferred to merge (B2B).
- **G4 mobile (✅ mine):** styled the previously-bare withdraw toggle nav; ≥44px
  touch targets + non-wrapping scrollable tables on small screens; account-shell
  header already responsive. B2B table data-labels deferred to merge.
- **G6 Designer Store (✅):** wrapped the store in the shared shell (title + subtitle
  above its own tab nav); toggle nav + balance now tokenised.

**Deferred to merge (B2B territory):** empty states for Reorders/Approvals;
approval-queue action hierarchy; B2B table→card data-labels; B2B 4-file CSS bundle.

---

## 4. Out of scope
- Buyer designer canvas (`app-product-builder.css`) — separate editor.
- `spbwc_option_builder_variable` blob on product pages — tracked elsewhere.

---

## 5. Milestone W2-MA — My Account UX/UI polish (Wave 2, item 3)

**Status:** IN PROGRESS (2026-06-09) · part of `SPEC_ADMIN_UX_POLISH_W2.md`

### Done (2026-06-09)
- **🔴 Root-cause fix — shell + menu infra were never loaded.**
  `includes/class-account-shell.php` and `includes/class-account-menu.php` were
  built (M4/M5) but **never `require`d** by the main plugin file, so every adopter's
  `class_exists( 'SPBWC_Account_Shell' )` guard returned `false` and ALL account
  pages silently fell back to their bare `<h2>` — the unified portal, empty states,
  and menu normalizer were dead code. Added both `require_once`s before the endpoint
  classes (`storelly-product-builder-for-woocommerce.php`). This single fix activates
  the shell across Saved-designs, Reorders, Team, Approvals, Quotes **and** makes the
  M5 menu normalizer run (verified: core items keep order → Storelly group →
  logout last). Confirmed via server-side render: shell chrome present, empty-state
  helper outputs CTA, no double-`<h2>`, no fatals.
- **Quotes shell adoption** — `SPBWC_Request_Quote::render_quotes_endpoint()` now
  renders inside `SPBWC_Account_Shell` (title "My Quotes" + subtitle "Track your
  quote requests…"), with a real `empty_state()` (📝 + "No quotes yet" + "Browse
  products" CTA) replacing the bare `<p>`, and a tokenised `.spbwc-rfq-empty`
  surface for the per-filter "no quotes in this view" message. Closes the §5
  follow-up. Degrades to `<h2>` via the `$use_shell` guard.
- **Shell adoption** — `Reorders`, `Approval Queue`, and `Team` now render inside
  `SPBWC_Account_Shell` (title + subtitle), matching Saved-designs. Each degrades
  to its old `<h2>` if the shell class is ever absent (`$use_shell` guard).
- **Empty states** — Reorders and Approvals now use `SPBWC_Account_Shell::empty_state()`
  (dashicon + headline + supporting line; Reorders adds a "Browse products" CTA).
  Saved-designs already used it (G1).
- **Mobile table→card** — the Team roster table collapses to stacked cards below
  600px via `data-label` attributes on each `<td>` (visually-hidden `<thead>`); the
  approval decision buttons stack full-width.
- **Touch targets** — `@media (pointer: coarse)` gives ≥44px height to the team
  row/invite controls, reorder qty/button, and approval buttons.
- **RTL** — converted the remaining physical properties in `b2b.css`
  (`margin-left`→`margin-inline-start`, `text-align:left`→`start`, absolute
  `left`→`inset-inline-start`, `padding-left`→`padding-inline-start`); the new
  polish block uses logical properties throughout, mirrored into `b2b-rtl.css`
  (also back-filled the missing Account-Credit + W2-MA blocks there).
- **Brand Store** intentionally keeps its branded `render_header` (logo + completion
  meter) rather than the generic shell — that immersive header is the page's point.
- **Quotes** endpoint (`class-request-quote.php`) is out of this task's allowed file
  set, so it still renders its own `<h2>` + bare empty `<p>` — flag for a follow-up.
- **`my-store`/Designer Store** (`SPBWC_Marketplace`/launcher) is no longer present
  in the codebase (only a `.claude/_backups` copy), so it was not in scope.

### Remaining
- ✅ ~~Adopt the shell on the **Quotes** endpoint + a real empty-state~~ — DONE
  (see root-cause fix + Quotes shell adoption above, 2026-06-09).
- Browser verify: shell parity across endpoints, mobile stacked Team table, RTL.
  (Server-side render verified; live browser pass pending — shared Chrome was
  locked by another session at the time of this change.)
- The B2B storefront sheets (`quote-storefront`/`custom-order`/`b2b`) are already on
  one token system; the optional single-request bundle (perf, M6 "deferred") is still
  open but no longer blocks visual consistency.

### Vấn đề
7 endpoint My-Account (quotes / saved-designs / reorders / brand-store / team / approval / my-store)
chưa nhất quán shell / card / empty-state / mobile / RTL. Một phần đã polish (G-series §3), một phần
"deferred to merge (B2B territory)".

### Yêu cầu
1. **Shell thống nhất**: mọi endpoint render trong `SPBWC_Account_Shell` (title + subtitle + tab nav),
   không endpoint nào "bare". Token hóa qua launcher.css (build `tools/build-usermenu-css.mjs`).
2. **Empty states** chuẩn cho Reorders / Approvals / Saved-designs (icon + message + CTA về store).
3. **Card/list nhất quán**: cùng radius/spacing/badge token; bảng → card với `data-label` trên mobile.
4. **Mobile**: touch target ≥44px, bảng scroll ngang không vỡ, toggle nav styled.
5. **RTL**: logical properties toàn bộ.
6. Gom **B2B 4-file CSS bundle** về cùng hệ token (đóng "deferred to merge").

### Acceptance
- 7 endpoint cùng ngôn ngữ thị giác; không bare; empty-state đủ; mobile + RTL pass.
- Không vỡ flow Accept-quote→order, withdraw, reorder hiện có.

### Files
`includes/class-account-shell.php`, `class-account-menu.php`, `class-saved-designs.php`,
`includes/b2b/class-b2b-storefront.php`, view My-Account, launcher.css (+ `tools/build-usermenu-css.mjs`).
