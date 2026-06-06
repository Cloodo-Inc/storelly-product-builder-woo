# Storelly Product Builder — Admin UI/UX Redesign Spec

> **Status**: DRAFT (framing locked, page specs in progress)
> **Goal**: make every admin page consistent, on-token, and well-organized —
> fixing the "two-speed" inconsistency, the 17-item flat menu, and token gaps.
> **Design language (locked)**: *Polish the existing system* — keep the
> "corporate blue + gold premium" identity already in `_tokens.css`; unify, don't
> rebrand.
> **Related**: `storelly-admin-ui.css` (component lib), `_tokens.css`,
> `SPEC_EMAIL_SYSTEM.md`, `[[project_freemium_local_vs_cloud]]`.

---

## 0. Locked decisions

| # | Decision | Choice |
|---|----------|--------|
| L1 | Aesthetic | Polish current identity (blue + gold), unify tokens — no rebrand |
| L2 | Depth | **Full** — includes information architecture / navigation / flow |
| L3 | Menu grouping | Keep ~10 submenu items + **section headings** (BUILD / SELL / CONFIGURE), not deep tab-nesting. Minimize routing churn |
| L4 | Settings consolidation | Fold System Info, About, License, Setup Wizard, **Emails** into a tabbed **Settings** page |
| L5 | Emails | A **tab in Settings** + an **Email quick-card on Overview** that deep-links to it |
| L6 | First scope wave | **Core daily-use**: Overview, Pricing Options (list), Edit Option v3, Linked Products |

Why "Local=Free / Cloud=Paid" matters here: the redesign must surface the Cloud
boundary cleanly (badges/locks on Cloud-only affordances) without crippling local
features. See `[[project_freemium_local_vs_cloud]]`.

---

## 1. The problem (audit summary)

The foundation is **better than it looks** — `storelly-admin-ui.css` already has 8+
mature components and `_tokens.css` has a full color/spacing/shadow palette. The
"ugliness" comes from three concrete sources, not from a missing design system:

1. **Two-speed inconsistency.** Some pages use the component lib (Overview,
   License, Quotes, Templates); others render **inline raw HTML** with no tokens
   and look like bare WordPress: **Custom Orders, Quote Settings, System
   Information, About**.
2. **Overloaded navigation.** 17 flat submenu items under one menu, with three
   duplicate-concept pairs (Pricing Options ↔ Visual Builder, Quote Settings ↔
   Quotes, Options Templates ↔ Pricing Options).
3. **Token gaps + hardcode.** No z-index scale (hardcoded 99999/9999/1051…), no
   line-height / letter-spacing scale; `overview.css` is 12.9% hardcoded hex,
   `storefront-options.css` 3.5%. The legacy v2 editor (1,703 LOC) is an island.

---

## 2. New information architecture

### 2.1 Menu structure — 17 → ~10 items, 3 groups

```
Storelly  ▸ (SVG icon)
│
├─ Overview
│
├── BUILD ──────────────  ← non-clickable section heading
├─ Pricing Options
├─ Visual Builder
├─ Linked Products
├─ Library              ← merge: Options Templates + Custom Fonts (2 tabs)
├─ Design Files
│
├── SELL ───────────────
├─ Custom Orders          ◦ badge: new orders
├─ Quotes                 ← Quote Settings becomes a tab inside  ◦ badge: pending
├─ B2B Companies          ◦ badge: pending approvals
├─ Marketplace            ← hidden when feature is off (gated)
│
├── CONFIGURE ──────────
└─ Settings             ← tabs: General · Cloud & License · Emails · System · About · Setup Wizard
```

### 2.2 What changes (low routing churn — per L3)

| Action | Items affected | Routing impact |
|--------|----------------|----------------|
| Fold into **Settings** tabs | System Information, About, License, Setup Wizard, **Emails** | New tab router in Settings; old slugs 301→ `…-options&tab=…` |
| Merge into **Library** | Options Templates + Custom Fonts | One page, 2 tabs |
| **Quote Settings → tab** inside Quotes | Quote Settings | Removes the duplicate concept |
| Keep standalone (no routing change) | Overview, Pricing Options, Visual Builder, Linked Products, Design Files, Custom Orders, B2B, Marketplace | none |

Result: **17 → 11** items (10 when Marketplace is gated off), in 3 clear bands.

### 2.3 Redirect map (back-compat — do not break bookmarks)

Old direct slugs that move must `wp_safe_redirect` to their new tab URL:
- `…-license` → `…-options&tab=license`
- `…/system-infor` → `…-options&tab=system`
- `…/about` → `…-options&tab=about`
- `…/global-import` (Setup Wizard) → `…-options&tab=setup`
- `…-templates` → `…-library&tab=templates`
- `…/manager-fonts` → `…-library&tab=fonts`
- Quote Settings (legacy) → `…-custom-quotes&tab=settings`

---

## 3. Menu beautification — 5 techniques

1. **Section headings** `BUILD / SELL / CONFIGURE` — register submenu items whose
   callback is empty and gate them with an always-false cap, then CSS-style them
   as uppercase, non-interactive labels (`--st-text-muted`, letter-spacing). Same
   technique WooCommerce/Yoast use to band a long submenu.
2. **SVG mask icon** — replace `images/logo.png` with an inline
   `data:image/svg+xml` mark so it recolors with WP's white/blue active states
   instead of a flat washed PNG.
3. **Standardized count badges** — one component on `--st-accent` for: new orders,
   pending quotes, pending B2B approvals. Currently ad-hoc per feature.
4. **Token underline-tabs** — in tabbed pages (Settings, Library, Quotes) replace
   WP's gray `.nav-tab` with a designed underline tab bar (13px / 600, brand
   underline) living under the page hero.
5. **Unified `.spbwc-page-hero`** on every page (title + subtitle + primary CTA +
   tab bar foot) — ends the "two-speed" look.

---

## 4. Token additions (prerequisite for "on-token")

Add to `_tokens.css` before page work so pages can consume them:

```css
/* Z-index scale (replaces hardcoded 99999/9999/1051/...) */
--st-z-base: 1; --st-z-sticky: 100; --st-z-dropdown: 500;
--st-z-overlay: 1000; --st-z-modal: 1100; --st-z-toast: 1200; --st-z-tooltip: 1300;

/* Line-height scale */
--st-leading-tight: 1.25; --st-leading-snug: 1.4;
--st-leading-normal: 1.5; --st-leading-relaxed: 1.625;

/* Letter-spacing scale */
--st-tracking-tight: -0.01em; --st-tracking-normal: 0;
--st-tracking-wide: 0.04em; --st-tracking-caps: 0.08em;
```

Then sweep `overview.css` / `storefront-options.css` to replace hardcoded hex with
existing color tokens (highest-density offenders first).

---

## 5. Per-page plan — Wave 1 (Core daily-use)

Each page below follows the same anatomy: **page hero → (optional tab bar) →
content built from `storelly-admin-ui.css` components → empty/loading states**.
Effort = relative; "inline?" flags pages with no view file today.

### 5.1 Overview  ·  `views/overview.php` (1,311 LOC, `overview.css` 12.9% hardcode)

The dashboard sets the tone — fix it first.

- **Hero**: unify to `.spbwc-page-hero` (title "Overview", store name + plan pill
  on the right, primary CTA "Create option").
- **Stat row**: standardize the 4–6 metric cards on `.spbwc-stat-card` (pricing
  options, linked products, orders, quotes, saved designs). One grid, equal
  heights, token spacing (`--nbd-space-*`).
- **Quick actions**: `.spbwc-quick-card` grid — *add the new Email quick-card here*
  (per L5) deep-linking to `Settings?tab=emails`. Also: Create option, Link a
  product, Import demo, Connect Cloud.
- **Cloud strip**: keep the Free-plan banner but on the new Cloud copy (already
  reworded in the freemium pass) — `.spbwc-notice-banner--info`.
- **Token sweep**: kill the 43 hardcoded hex in `overview.css` → map to
  `--st-brand`, `--nbd-st-text-soft`, etc. This single file is the worst offender.
- **Effort**: M. **Risk**: low (presentational).

### 5.2 Pricing Options — list  ·  `views/options/options-list-table.php` (1,058 LOC)

The most-used screen.

- **Hero + toolbar**: hero with "Pricing Options" + primary "New option"; below it
  a toolbar row (search `.spbwc-input`, status filter pills `.spbwc-pill`, bulk
  actions). Replace the current ad-hoc search box (`.spbwc-block-search`).
- **Cards vs table**: keep the AJAX grid but re-skin rows/cards on
  `.spbwc-block` + consistent row actions (Edit · Duplicate · Publish toggle ·
  Trash) as icon buttons with tooltips, not text links.
- **Empty state**: `.spbwc-empty-state` with an illustration + "Create your first
  option" CTA (today it's bare).
- **v2/v3 entry**: surface a single "Edit" that routes to v3 (Stage B default);
  demote the legacy v2 link to a small "Open classic editor" affordance.
- **Effort**: M-L. **Risk**: low-med (AJAX list markup; keep handlers intact).

### 5.3 Edit Option — v3  ·  `views/options/edit-option-v3.php` (401 LOC, `edit-option-v3.css`)

The modern editor — make it the showpiece.

- **Two-pane layout**: left = section nav (Apply / Quantity / Design tools /
  Fields), right = editor canvas. Sticky save bar at the bottom on
  `--st-z-sticky` (uses the new z token).
- **Section cards**: each `views/options/v3/section-*.php` becomes a
  `.spbwc-block` with a clear header + helper text. Token spacing throughout.
- **Field rows**: standardize `field-body/*` inputs on `.spbwc-input` + consistent
  labels/help; align price/qty/conditional controls.
- **Breadcrumb**: when entered from a product (`spbwc_return=1`), show a
  `.spbwc-page-hero` breadcrumb back to the product (ties into Linked Products UX).
- **Effort**: L. **Risk**: med (interactive editor — verify save round-trip; see
  `[[project_save_drops_subattributes]]` whitelist gotcha).

### 5.4 Linked Products  ·  `views/products.php` (504 LOC) + `_products-cards.php`

- **Hero + filter tabs**: hero "Linked Products"; the all/mapped/unmapped filter
  becomes a token tab bar (not raw links). Search on `.spbwc-input`.
- **Product cards**: re-skin `_products-cards.php` on `.spbwc-block` — thumb,
  title, mapped-option pill, row actions (Open · Swap · Unlink). Consistent with
  the option-list cards for a unified feel.
- **Empty/unmapped state**: helpful `.spbwc-empty-state` ("12 products have no
  builder — link one").
- **Metabox parity**: align the product-editor metabox (`views/options/meta-box.php`,
  `linked-product-metabox.css`) to the same tokens so the WP product screen
  matches — see `[[project_linked_product_ux_spec]]`.
- **Effort**: M. **Risk**: low.

---

## 6. Wave 2+ (later, after Wave 1 validated)

- **Inline raw pages → component lib**: Custom Orders, Quote Settings (→ Quotes
  tab), System Info, About. These are the visually worst; bring under the hero +
  component pattern. (Deferred only because they're lower-traffic than Wave 1.)
- **Settings consolidation**: build the tab router + fold License/System/About/
  Setup Wizard/**Emails** in. Emails table per `SPEC_EMAIL_SYSTEM.md` (unify the
  WC_Email vs raw `wp_mail` split).
- **Library**: merge Templates + Fonts.
- **Workspaces**: B2B, Quotes, Marketplace polish.
- **Heavy editors**: legacy v2 editor, Visual Builder.

---

## 7. Milestones

- **U0 — Tokens & menu shell**: add z-index/typography tokens (§4); implement the
  3-band menu with headings, SVG icon, badge component (§2–§3). No page content
  change yet. Ships the new navigation skeleton.
- **U1 — Wave 1 pages** (§5): Overview → Options list → Edit v3 → Linked Products.
- **U2 — Settings consolidation + Emails** (§6): tab router, redirect map (§2.3),
  Emails tab + Overview quick-card.
- **U3 — Library merge + inline raw pages** (§6).
- **U4 — Workspaces + heavy editors** (§6).

Each milestone: verify in-browser (screenshots), run `wp plugin check` (0 ship
errors), regen POT if strings changed.

---

## 8. Open items

| ID | Question | Owner |
|----|----------|-------|
| U-O1 | Section-heading technique: empty-cap submenu vs custom walker — pick the more WP-update-safe one | dev |
| U-O2 | Marketplace: live or dormant fork scaffolding (O-3 from freemium spec)? If dormant, drop from menu | maintainers |
| U-O3 | Legacy v2 editor — retire to "classic" affordance, or keep dual? affects Wave 4 effort | product |
| U-O4 | Emails: which types are WC_Email vs raw wp_mail (link-out vs manage-in-place) | dev (SPEC_EMAIL_SYSTEM) |
```
