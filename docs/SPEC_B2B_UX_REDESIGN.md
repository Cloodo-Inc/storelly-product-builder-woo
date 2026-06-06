# B2B Pages — UI/UX Redesign Plan (per-page)

> **Status:** Plan for review (not yet implemented).
> **Date:** 2026-06-05
> **Why:** M1–M5 shipped functional B2B pages, but they render with **raw WP-admin
> classes** (`.wrap`, `.form-table`, `.wp-list-table.widefat`) and thin ad-hoc
> `.spbwc-b2b-*` CSS. They look flat next to the Overview dashboard and Quote
> workspace, which compose the plugin's **existing shared component library**.
> **Fix:** rebuild every B2B page from that library + design tokens. Almost no new
> CSS — mostly new *markup* emitting existing classes.
> **Related:** [[SPEC_B2B_CLIENT]], [[DEV_MILESTONES_B2B_CLIENT]].

---

## 0. Root cause & strategy

The plugin already ships a polished component system (catalogued below). Overview
and Quote look good because they use it; the B2B pages don't.

**Strategy:** for each B2B page, replace the markup so it emits the **existing**
component classes, and enqueue the existing component stylesheets on B2B pages.
Delete most of `b2b-admin.css` / `b2b.css`; keep only a slim B2B-specific layer
(brand-colour theming of the header, a few missing atoms).

### 0.1 Component library we reuse (canonical classes)

**Admin** (`storelly-admin-ui.css`, enqueue handle `spbwc-admin-ui`, dep `spbwc-tokens`):
- Page header: `.spbwc-page-hero` (+ `__grid/__body/__title/__subtitle/__actions`)
- Sections: `.spbwc-section` (+ `__header/__title/__subtitle`)
- KPIs: `.spbwc-stat-grid` › `.spbwc-stat-card[--brand|--success|--warning|--accent]`
- Cards: `.spbwc-block[--brand|--success|--warning|--flat]` (+ `__head/__body/__foot`)
- Rows: `.spbwc-list` › `.spbwc-list__item` (thumb + title + meta + `__item-status` + action)
- Table: `.spbwc-admin-table` (replaces `.widefat`), toolbar `.spbwc-list-toolbar` + `.spbwc-search-bar`, `.spbwc-admin-pagination`
- Forms: `.spbwc-setting-rows` › `.spbwc-setting-row` (label / control / hint grid), `.spbwc-input`, `.spbwc-radio-group` (pill toggles)
- Buttons: `.spbwc-cta-btn[--solid|--ghost|--sm]`
- Feedback: `.spbwc-notice-banner[--info|--warn|--success]`, `.spbwc-pill[--ok|--warn|--off|--draft|--neutral]`, `.spbwc-empty-state`
- Quote-detail patterns to mirror: `.spbwc-q-detail` (2-col main+320 sidebar), `.spbwc-q-kpis/.spbwc-q-kpi`, `.spbwc-q-recap` (def-list), `.spbwc-q-timeline`, `.spbwc-q-field`

**Storefront** (`quote-storefront.css` + `custom-order.css`, deps on `spbwc-tokens-storefront`):
- My-Account frame already themed (sidebar + content cards) via `custom-order.css` scoped to `body.woocommerce-account`
- KPIs: `.spbwc-rfq-stats` › `.spbwc-rfq-stat[--accent]`
- Tabs: `.spbwc-rfq-filter` › `.spbwc-rfq-filter__tab` (active = brand)
- Cards: `.spbwc-rfq-card`, `.spbwc-rfq-summary`, `.spbwc-rfq-table`, `.spbwc-rfq-banner[--ok|--danger|--info]`
- Buttons: `.spbwc-rfq-btn[--ghost|--danger]`, action chips `.spbwc-co-chip[--ghost]`
- Cards grid: `.spbwc-saved-designs__list/__item/__thumb/__body/__actions`
- Qty: `.spbwc-rfq-stepper`; fields `.spbwc-rfq-field`; pills `.spbwc-rfq-pill[--warn|--info|--ok|--danger]`
- Brand header (Pattern 9): keep current `.spbwc-store__header` (the one B2B-specific block worth keeping)

### 0.2 New atoms to add (small, shared) — `b2b.css` / `b2b-admin.css` slim layer

These don't exist yet (catalog §"Critical missing"); add once, reuse everywhere:
1. `.spbwc-tabs` / `.spbwc-tab` — detail-page tab nav (admin + storefront). ~30 lines.
2. `.spbwc-avatar` — circular initials/photo (24/32/40px). Team + approvals. ~15 lines.
3. `.spbwc-meter` — thin usage bar (spend limit / profile completion). green→amber→red by %. ~20 lines.
4. `.spbwc-role-chip` — role badge (owner/admin/approver/requester/viewer) colour-mapped. ~10 lines.
5. Brand-header theming vars already in `b2b.css` (keep).

Everything else = reuse. Net: `b2b-admin.css` shrinks to the brand-hero override + the 4 atoms; `b2b.css` keeps the brand header + atoms.

---

## 1. ADMIN — B2B Companies (hub / list)

**Route:** `admin.php?page=…-b2b-companies`
**Now:** `<h1>` + WP notices clutter + 3 plain stat divs + `.subsubsub` tabs + bare `.widefat` table. Upgrade entry is hidden in the Users-list row action — undiscoverable.

**Redesign:**
- **`.spbwc-page-hero`** — eyebrow "B2B" · title "Companies" · subtitle "Branded accounts, tiers and team procurement." · `__actions`: **`+ Upgrade a customer`** (solid) → opens a customer-picker modal (search WC customers, pick → upgrade form) + `Manage tiers` (ghost → B2B Pricing).
- **`.spbwc-stat-grid`** with 4 **`.spbwc-stat-card`**: Total (`--brand`), Active (`--success`), Pending approval (`--warning`, count badge mirrors menu), Avg team size or Tiered (`--accent`). Each with a dashicon.
- **`.spbwc-list-toolbar`** + **`.spbwc-search-bar`**: search by company/owner + the status tabs become filter pills on the right (reuse `.spbwc-radio-group` or `.subsubsub` restyled).
- Body in a **`.spbwc-block--flat`** → **`.spbwc-admin-table`**: columns Company (logo thumb + name + slug) · Owner (avatar + email) · Tier (`.spbwc-role-chip`/pill) · Team (`x / n` + mini `.spbwc-meter`) · Status (`.spbwc-pill`) · Store (View ↗) · row actions.
- **`.spbwc-empty-state`** when no companies: icon + "No B2B companies yet" + `Upgrade a customer` CTA.

**Flow win:** upgrade is now a first-class button (customer-picker modal) instead of a hidden row action. Search + filters for scale.
**New:** customer-picker modal (AJAX search `wc_get_customers`-style). Effort: **M**.

---

## 2. ADMIN — Upgrade a customer (form)

**Route:** `…-b2b-companies&action=upgrade&user=<id>` (also reachable from the hub modal).
**Now:** `<h1>` + `.form-table` with stacked inputs. Functional, flat.

**Redesign:**
- **`.spbwc-page-hero`** (compact): back link + title "Upgrade {name} to B2B" + subtitle with the customer's email & order count (context, like Printcart's invite modal).
- **`.spbwc-block--brand`** "Customer" recap card: avatar, name, email, lifetime orders/spend (`.spbwc-q-recap` def-list).
- **`.spbwc-block`** "Company setup": `.spbwc-setting-rows` — Company name (prefilled), Industry, Proposed **tier** as `.spbwc-radio-group` cards (Tier A/B/C with % shown, one "Recommended"), Team seats, Payment terms, Activate-now toggle.
- Sticky **`.spbwc-block__foot`**: `Create company` (solid) + `Cancel`.

**Flow win:** tier chosen visually at creation (radio cards, not a later step); customer context shown so the merchant knows who they're upgrading. Effort: **S**.

---

## 3. ADMIN — Company detail

**Route:** `…-b2b-companies&company=<id>`
**Now:** my new gradient hero + 3 stat tiles are OK, but below it's one long scroll of `.form-table` (settings) + `.widefat` (members) + an **ugly inline bind-row** (Product ID | %off | Value | Min qty | date | button jammed together). No structure.

**Redesign — mirror the Quote detail (`.spbwc-q-detail` 2-col):**
- **`.spbwc-page-hero`** (already brand-gradient): logo + name + `.spbwc-pill` status + meta (owner, slug) + `__actions`: `View Brand Store ↗`, status action (Approve/Suspend/Reactivate as `.spbwc-cta-btn`).
- **`.spbwc-q-kpis`** row: Team `x/n` (+meter), Tier (chip), Bound products (#), 90-day spend (from WC).
- **`.spbwc-tabs`**: **Overview · Members · Pricing & products · Activity**.
  - **Overview tab:** `.spbwc-block` "Settings" with `.spbwc-setting-rows` (tier select, seats, approval threshold, payment terms) + sticky save in `__foot`. `.spbwc-block` "Profile snapshot" (legal name, industry, tax id via `.spbwc-q-recap`) with "complete/incomplete" `.spbwc-meter`.
  - **Members tab:** ✅ *shipped & polished.* Column-aligned `.spbwc-member-list` roster: `.spbwc-block` head with title + neutral `x/n seats` badge, a muted uppercase **header row** (Member · Role · Order limit), then `.spbwc-list__item.spbwc-member` rows = `.spbwc-avatar--lg` + identity column (name + Owner chip over email, ellipsised) + fixed **Role** column (`.spbwc-role-chip`, 130px) + right-aligned **Order limit** column (value + `per order` unit, or "No limit", 120px). Fixed-width columns keep chips/amounts vertically aligned across rows (was: loose flex siblings → ragged). Spend cap is an absolute limit, so it shows as a value, not a `.spbwc-meter`. Logical-property CSS → RTL-safe; stacks under 600px. Invites stay owner-driven from My-Account (admin view read-only).
  - **Pricing & products tab:** the per-company rules as a **`.spbwc-admin-table`** (Product · Base · Override · Effective · Min qty · Valid · Remove) + a **proper bind form** as `.spbwc-setting-rows` inside a `.spbwc-block` (product autocomplete, override type radio-group, value, min qty, valid-until) — NOT the current jammed inline row.
  - **Activity tab:** `.spbwc-q-timeline` of company events (created, tier changed, approvals…).

**Flow win:** tabs replace the endless scroll; pricing bind becomes a real form; KPIs surface the account at a glance. **New:** `.spbwc-tabs`, product autocomplete field. Effort: **L**.

---

## 4. ADMIN — B2B Pricing (tier ladder)

**Route:** `…-b2b-pricing`
**Now:** `<h1>` + description + one big editable `.widefat` table with an "add" row + Save. Workable but plain and easy to mis-edit.

**Redesign:**
- **`.spbwc-page-hero`**: title "B2B Pricing" + subtitle + `__actions` `+ Add tier`.
- **`.spbwc-section`** "Tier discount ladder" → **`.spbwc-list-grid`** of **`.spbwc-block`** cards, one per tier: header = tier label + `.spbwc-pill` (companies count), body = `.spbwc-setting-rows` (discount %, min order, payment terms, free-ship), foot = Save / Remove. A trailing dashed **`.spbwc-empty-state`**-style "add tier" card.
  - *Alternative (lighter):* keep one table but restyle as `.spbwc-admin-table` with inputs as `.spbwc-input` and a clear "Add tier" affordance + per-row save feedback.
- **`.spbwc-notice-banner--info`**: "Only the discount % is charged today; min-order / terms / free-ship are labels. Quantity breaks stack on top."
- Cross-link card: "Assign a tier to a company on its detail page →".

**Flow win:** card-per-tier is far clearer than a dense table; companies-count per tier shows adoption. Effort: **M**.

---

## 5. STOREFRONT — Brand Store profile (My-Account)

**Route:** `?…&brand-store`
**Now:** the worst offender — Pattern-9 header is fine, but the body is a long stack of plain `<fieldset>`s with raw "Choose file" inputs and tiny native colour pickers. Reads as a bureaucratic form (the exact anti-pattern flagged in `designer-marketplace-ux-context.md`).

**Redesign:**
- Keep **`.spbwc-store__header`** (brand-colour gradient) + add a **`.spbwc-meter`** "Profile 60% complete" + `View public store ↗`.
- Convert the 4 fieldsets into **`.spbwc-tabs`** (Branding · Company · Address · Contact) OR `.spbwc-rfq-card` accordion sections — each a card, not a flat fieldset.
  - **Branding card:** logo + banner as **drag-drop upload zones** (styled box w/ preview thumbnail, "PNG/SVG · min 400×400"), brand colours as **swatch buttons** (not tiny native picker) with hex input, tagline, description (`.spbwc-rfq-field`).
  - **Company / Address / Contact cards:** `.spbwc-rfq-field` rows (consistent inputs, focus ring), required markers.
- **Live preview** aside (desktop): a mini card showing how the public store header looks with current logo/colour (updates the CSS vars). Reinforces the brand theming.
- Sticky save bar: "You have unsaved changes" + `Save Brand Store` (`.spbwc-rfq-btn`). Toast on success (no full reload feel).
- Pre-approved products + Team + Approval summary cards link out (cross-nav).

**Flow win:** tabbed/carded sections + real upload zones + colour swatches + live preview turn a form into a brand console. **New:** styled upload dropzone, colour swatch, live-preview JS (small). Effort: **L**.

---

## 6. STOREFRONT — Team

**Route:** `?…&team`
**Now:** plain table + cramped inline role/limit form + bare invite row.

**Redesign:**
- Mini **`.spbwc-rfq-stats`**: Members `x/n`, Pending invites, Approvers #.
- Roster as **`.spbwc-list`** of rich `.spbwc-list__item` (or `.spbwc-saved-designs__item`-style cards): **`.spbwc-avatar`** + name/email + **`.spbwc-role-chip`** + spend **`.spbwc-meter`** (`$used / $limit`) + actions (edit role/limit, remove). Owner row tagged "You".
- **Edit member** → inline expand or a `.spbwc-rfq-card` panel (role `.spbwc-radio-group`, per-order limit, monthly limit) — not the cramped inline controls.
- **Pending invitations** → `.spbwc-rfq-card` list with email + role chip + Resend/Revoke.
- **Invite teammate** → a tidy `.spbwc-rfq-card` form (email + role select + optional limit + Send), seat-limit aware.

**Flow win:** avatars + role chips + spend meters make the team legible at a glance; invite/edit become proper panels. **New:** `.spbwc-avatar`, `.spbwc-role-chip`, `.spbwc-meter`. Effort: **M**.

---

## 7. STOREFRONT — Approval Queue (+ detail)

**Route:** `?…&approval`
**Now:** a single plain card per request (total · requester · items · textarea · Approve/Reject). No preview, no requester avatar, no budget context, no due date.

**Redesign (mirror Printcart approval cards):**
- **`.spbwc-rfq-stats`**: Awaiting (#), Approved this month, Value pending ($).
- **`.spbwc-rfq-filter`** tabs: Pending / Approved / Rejected.
- Each request = **`.spbwc-rfq-card`** with: design **thumbnail** (first snapshot preview) · title + specs · requester row (**`.spbwc-avatar`** + name + `.spbwc-role-chip`) · amount (big) · "submitted Xh ago" + optional due date · **budget line** ("uses 3.5% of {requester} remaining limit", `.spbwc-meter`) · comment `.spbwc-rfq-field` · **`Approve`** (`.spbwc-rfq-btn`) / **`Reject`** (`--danger`).
- Optional **approval detail** view (click card) → full design preview + items table (`.spbwc-rfq-table`) + comment thread (`.spbwc-q-timeline`).
- `.spbwc-rfq-banner--ok` confirmation after decision.

**Flow win:** approver sees who/what/how-much-budget at a glance — real procurement review, not a bare card. **New:** reuse avatar/meter; snapshot-preview lookup. Effort: **M–L**.

---

## 8. STOREFRONT — Reorders

**Route:** `?…&reorders`
**Now:** decent card grid already, but minimal (thumb + name + date + qty + button).

**Redesign:**
- Header + **`.spbwc-rfq-stats`**: Reorderable items, Categories, Saved on reorders (optional).
- **`.spbwc-rfq-filter`** chips: All / by category + search.
- Cards reuse **`.spbwc-saved-designs__item`** look: thumb + "×N ordered" badge + name + specs + "last ordered {date}" + a **`.spbwc-rfq-stepper`** qty + preset chips ("×3 same", "×5 save 8%" from quantity_breaks) + **`Reorder $X`** (`.spbwc-rfq-btn`). Company price shown if a tier/rule applies.
- Optional quick-reorder confirmation as a small `.spbwc-rfq-banner` (or reuse the RFQ modal shell) instead of straight-to-cart.

**Flow win:** qty stepper + save-% presets + company price match the Printcart quick-reorder; consistent card system. Effort: **S–M**.

---

## 9. PUBLIC — Brand Store `/store/<slug>`

**Route:** `/store/<slug>` (or `?spbwc_store=<slug>`)
**Now:** already the best — Pattern-9 brand header + product grid + Pattern-7 price. Minor polish only.

**Redesign:**
- Header: add optional **stats row** (products available, team members) and a CTA ("Browse all →") in the header `__actions` style.
- Product grid: reuse `.spbwc-saved-designs__item` card shape for consistency; show **company price** (Pattern 7) prominently on each card; "team price" eyebrow.
- Empty state: `.spbwc-empty-state` styling.
- Respect brand banner image when set (already supported). Effort: **S**.

---

## 10. Cross-cutting UX upgrades

- **Consistent My-Account nav:** the 4 B2B tabs (Brand Store, Reorders, Team, Approvals) already sit in the themed sidebar card — keep order; add small count badges (Approvals already has one).
- **Toasts over reloads:** replace `?spbwc_*_msg=` full-page notices with a lightweight toast where possible (progressive; notices still work без JS).
- **Avatars everywhere** users appear (hub owner, members, approvals) via `.spbwc-avatar` (initials fallback from display name).
- **Empty states** for every list (companies, members, reorders, approvals, bound products) via `.spbwc-empty-state` with a relevant CTA.
- **Accessibility:** tabs `role=tablist/tab/tabpanel`; meters `role=progressbar`; upload inputs labelled; focus rings (tokens already provide `--shadow-input-focus`).
- **RTL:** generate `b2b-rtl.css` + `b2b-admin-rtl.css` (rtlcss) — folds into M6.

---

## 11. Build phases (proposed)

| Phase | Scope | Pages | Effort |
|---|---|---|---|
| **R0 — Foundation** | Enqueue `spbwc-admin-ui` on B2B admin pages + storefront component CSS on B2B account pages; add the 4 slim atoms (`.spbwc-tabs`, `.spbwc-avatar`, `.spbwc-meter`, `.spbwc-role-chip`); shrink `b2b-admin.css`/`b2b.css`. | — | S |
| **R1 — Admin hub + upgrade** | Page-hero, stat cards, toolbar, admin-table, empty state; customer-picker modal. | §1, §2 | M |
| **R2 — Company detail** | 2-col + tabs (Overview/Members/Pricing/Activity), KPIs, proper bind form, timeline. | §3 | L |
| **R3 — B2B Pricing** | Tier cards (or restyled table) + info banner. | §4 | M |
| **R4 — Brand Store** | Tabbed/carded sections, upload zones, colour swatches, live preview, completion meter. | §5 | L |
| **R5 — Team + Approvals** | Avatars, role chips, spend meters, rich approval cards (+ detail). | §6, §7 | M–L |
| **R6 — Reorders + public store + RTL** | Stepper, presets, stats; public-store polish; RTL build. | §8, §9 | S–M |

Each phase: rebuild markup in the render methods (no logic change), verify in Chrome against the Overview/Quote look, commit. No data-model or flow-logic changes — purely presentation + a few new affordances (customer-picker, tabs, live preview).

---

## 12. Out of scope / notes
- No backend/data changes — this is presentation + small JS (picker, tabs, live preview, toasts).
- Keep all nonce/capability/scoping exactly as shipped.
- Plain-permalink dev site: keep using WC endpoint URLs / `?spbwc_store=` (already permalink-aware).
- New strings → POT regen in M6.
