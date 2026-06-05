# B2B Client (Company Accounts) — User Flow & UX/UI Spec

> **Status:** Draft for review — converted from Printcart Cloud B2B Procurement v1 + Printcart Stores v2.5/v3.0 admin & customer mockups.
> **Date:** 2026-06-04
> **Owner:** David / Netbase
> **Source material:** `cloud-b2b-procurement-v1.md`, `printcart-store-wp-admin-v3.0.1.html`, `printcart-store-user-account-v2.5.1.html`, `designer-marketplace-ux-context.md`, `*-ai-b2b-ux-patterns.md`.
> **Related Storelly specs:** [[SPEC_QUOTE_USER_FLOW_UX]], [[SPEC_CUSTOM_ORDER]], [[SPEC_PRICING_OPTION_ASSIGNMENT]], [[SPEC_LINKED_PRODUCT_UX]].

---

## 0. The architectural reframe (read this first)

Printcart's B2B model in the source spec is a **multi-tenant cloud platform**: a self-built identity layer (`users`, `brand_accounts`, `print_stores`), checkout rendered from the cloud via **iframe**, and order/asset **sync over webhooks** to many host platforms (WP/Wix/Shopify).

**Storelly is the opposite shape** and we keep our shape:

| Printcart Cloud | Storelly (this spec) |
|---|---|
| Self-built identity (`users` table) | **WordPress users** (WC customers) |
| `brand_accounts` cloud entity | **CPT `spbwc_company`** on the same site |
| Iframe checkout from `app.printcart.com` | **Native WooCommerce cart + checkout** |
| Cross-store webhook sync, GMV metering | **None** — single site, no phone-home |
| Cloud approval engine | **Reuse `spbwc_quote` state machine** (already shipped M1–M7) |
| Tier billing automation | Out of scope — WooCommerce handles money |

**Rule:** we convert the *concepts* (company account, tier/per-client pricing, reorder, team approval), never the cloud plumbing. No external service is introduced, so **no new "External services" readme entry and no opt-in gate** are required by this feature. (Compliance ref: `CLAUDE.md` rules 6, 8.)

The AI-centric UX patterns from the source (preflight pill, quota meters, "Powered by Printcart AI") **do not apply** — Storelly has no AI engine. We reuse only the non-AI patterns: **Brand Page Header (Pattern 9)** and **B2B-vs-B2C price display (Pattern 7)**.

---

## 1. Purpose & scope

Give merchants a way to turn ordinary WooCommerce customers into **B2B company accounts** with their own branded store space, negotiated pricing, multi-seat teams with internal approval, and fast reordering of past designs.

Five features (this spec covers all; phasing in §11):

1. **Upgrade a regular customer → B2B** with their own company/store profile.
2. **Assign specific products to a company** with that company's own B2B price.
3. **B2B pricing schemes** (tiers) created once and assigned to companies.
4. **B2B reorder** — one-click repeat of a past design + specs.
5. **B2B team procurement** — company team with roles, spending limits, and an approval gate before an order is placed.

### Confirmed decisions (2026-06-04)
- **D1 — Company model:** new CPT **`spbwc_company`** + user-meta link. (Enables teams.)
- **D2 — Pricing:** **both** configurable tier % ladder **and** per-company per-product price overrides.
- **D3 — Approval engine:** **reuse the `spbwc_quote` CPT state machine** for the internal procurement-approval gate.
- **D4 — Release:** full spec, phased milestones, **Free vs Pro split marked** for wp.org compliance.

---

## 2. Actors & roles

Two scopes: **merchant** (store owner, in wp-admin) and **company** (the B2B client, in My-Account).

### 2.1 Merchant-side (wp-admin)
Uses existing caps: `spbwc_manage_product_builder` (fallback `manage_woocommerce`). No new roles for the merchant.

### 2.2 Company-side roles (stored per user in user meta `_spbwc_company_role`)

| Role | Capabilities |
|---|---|
| `owner` | Full company admin: profile, billing, invite/remove members, set limits, approve any order, manage company pricing display. One per company (the upgraded customer). |
| `admin` | Same as owner except cannot delete company / transfer ownership. |
| `approver` | Review & approve/reject team orders in the Approval Queue. No member management. |
| `requester` / `designer` | Create designs & submit orders for approval; cannot self-approve above their limit. (Single combined role in v1; "designer" label shown if marketplace designer too.) |
| `viewer` | Read-only: sees company orders, brand store, reorders. Cannot submit. |

Taxonomy is kept identical to the source mockups (Owner/Admin/Approver/Designer/Viewer) so the UX maps 1:1.

---

## 3. Data model (Woo-native)

No cloud tables. One CPT, one helper table for price rules, and user/post meta.

### 3.1 CPT `spbwc_company` (headless, `show_ui=false`)
Registered like `spbwc_quote` (storage-only; all UI is custom). `post_title` = company name, `post_author` = the owner user.

Post meta (prefix `_spbwc_company_`):

| Meta key | Type | Notes |
|---|---|---|
| `_spbwc_company_status` | string | `pending`, `active`, `suspended`, `incomplete_profile` |
| `_spbwc_company_tier` | string | slug into the tier scheme (§3.4); empty = no tier |
| `_spbwc_company_slug` | string | URL slug for the brand store space (unique) |
| `_spbwc_company_logo_id` | int | attachment ID (square, ≥400×400) |
| `_spbwc_company_banner_id` | int | attachment ID (~1920×400) |
| `_spbwc_company_brand_primary` | string | hex; powers Brand Page Header (Pattern 9) |
| `_spbwc_company_brand_secondary` | string | hex |
| `_spbwc_company_tagline` | string | |
| `_spbwc_company_profile` | array | legal name, DBA, business type, industry, employees, year founded, tax/VAT id, resale cert attachment id |
| `_spbwc_company_addresses` | array | billing + shipping (reuse WC address shape) |
| `_spbwc_company_contact` | array | primary contact name, title, business email, phone, website, socials |
| `_spbwc_company_payment_terms` | string | `prepaid`, `net15`, `net30`, `custom` (display/label only in v1 — see §7) |
| `_spbwc_company_credit_limit` | decimal | informational in v1 |
| `_spbwc_company_seats` | int | max team size (default 5, a filterable default — not a paywall; see §11) |
| `_spbwc_company_approval_threshold` | decimal | orders above this need approval (default 0 = always for requesters) |
| `_spbwc_company_allowed_products` | array | product IDs in the company's "pre-approved" Brand Store (empty = all) |

### 3.2 User ↔ company link (user meta)

| User meta | Notes |
|---|---|
| `_spbwc_company_id` | the `spbwc_company` post ID this user belongs to |
| `_spbwc_company_role` | role slug (§2.2) |
| `_spbwc_company_spend_limit_order` | per-order limit (decimal, nullable) |
| `_spbwc_company_spend_limit_month` | monthly cap (decimal, nullable) |
| `_spbwc_company_invited_by` | user ID |
| `_spbwc_company_invite_status` | `pending`, `active`, `removed` |

A user belongs to **at most one** company in v1 (the source allows many; deferred).

### 3.3 Invitations
Reuse a single pending-token pattern. Store on the company as an array `_spbwc_company_invites` of `{ email, role, token_hash, expires_at, status }` (7-day expiry, single-use) — mirrors `brand_invitations` without a table. Two invite kinds:
- **Merchant → customer "Upgrade to B2B"** (creates the company).
- **Owner/Admin → teammate** (adds a seat).

### 3.4 Pricing — tier schemes (`wp_options`)
Tiers are a configurable list, **not hardcoded** (CLAUDE.md rule 10). Option `spbwc_b2b_tiers`:

```php
[
  'tier_a' => [ 'label' => 'Tier A', 'discount_pct' => 10, 'min_order' => 100,
                'terms' => 'net15', 'free_ship_over' => 150 ],
  'tier_b' => [ 'label' => 'Tier B', 'discount_pct' => 15, ... ],
  // ... merchant-editable; VIP etc. added by merchant
]
```

A company is assigned a tier via `_spbwc_company_tier`. Quantity breaks **stack** on top of tier discount (reuse the existing `quantity_breaks` engine).

### 3.5 Pricing — per-company product overrides (helper table)
`{$wpdb->prefix}spbwc_b2b_price_rules` — the per-corporate binding from Printcart's `pricing-rule-detail`:

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| company_id | bigint | FK → `spbwc_company` post |
| product_id | bigint | bound WC product (or variation) |
| override_type | varchar(10) | `pct` (extra % off), `fixed` (fixed unit price) |
| value | decimal(12,4) | percent or unit price |
| min_qty | int | nullable |
| valid_until | datetime | nullable |
| status | varchar(10) | `active`, `disabled` |
| created_by / created | … | audit |

Cascade (highest wins): **per-company fixed** > per-company pct > tier % > retail. Quantity breaks apply after.

### 3.6 Procurement approval = an `spbwc_quote` (D3 — reuse)
When a requester submits an order that needs approval, we create an **internal `spbwc_quote`** (not the merchant-facing quote) with:
- `post_author` = requester, new meta `_spbwc_quote_company_id`, `_spbwc_quote_kind = 'procurement'`.
- Reuse existing statuses: `STATUS_REVIEW` (awaiting approval) → `STATUS_ACCEPTED` (approved) → `STATUS_CONVERTED` (→ WC order) | `STATUS_DECLINED` (rejected).
- Reuse the timeline (`add_timeline_event`) for the requester↔approver comment thread.
- On approve → convert to a real WC order using the existing quote→order conversion.

This is the single biggest code reuse: the approval queue is a filtered view of `spbwc_quote` where `_spbwc_quote_kind = 'procurement'`.

---

## 4. Feature 1 — Upgrade customer → B2B + brand store profile

### 4.1 Merchant flow (wp-admin)
Mirrors Printcart `customer-detail` → `modal-invite-b2b` → `b2b-stores` → `modal-approve-store`.

```
WP Admin → Storelly → Customers (or WooCommerce customer)
  ↓ open a customer → "Upgrade to B2B" button
Modal "Invite to upgrade to B2B store":
  - Customer summary (orders, total spent)
  - Proposed company name * (prefill from billing company / order history)
  - Industry (select)
  - Proposed tier (radio cards from spbwc_b2b_tiers; mark one Recommended)
  - Initial team seats (number, default per plan)
  - Welcome bundle (checkboxes): create empty brand profile · assign starter option-sets · onboarding note
  - Invitation message (textarea)
  [Cancel] [Save draft] [Send invitation →]
  ↓ send
  - Creates spbwc_company (status=pending/incomplete_profile)
  - Sets owner user_id, role=owner, tier
  - Emails the customer an accept link (WC email, no external service)
```

Merchant management surface: **Storelly → B2B Companies** page (new), tabs `All / Active / Pending applications / Invited / Incomplete profile` (counts on tabs — admin-workflows rule 1). Row actions: Approve / Review / View store / Members / Orders / Edit. Uses the **review-then-decide** pattern (admin-workflows Type 1) and **invitation send** (Type 7).

`modal-approve-store` equivalent for self-applied companies: set tier, payment-terms label, credit limit (informational), seats, internal note → **Approve & activate**.

### 4.2 Company flow (My-Account) — "Brand Store" profile
New My-Account endpoint **`brand-store`** (registered like `saved-designs`/`quotes`). Edit screen mirrors Printcart's 5-step `brand-store-edit`, but **inline accordion, not a wizard tab** (lighter):

1. **Branding** — store name, slug (`/store/<slug>`), logo upload, banner upload, tagline, brand colors (primary/secondary), description.
2. **Corporate profile** — legal name, DBA, business type, industry, employees, year founded, tax/VAT id, resale certificate upload.
3. **Address** — billing + shipping (reuse WC address fields; "same as billing" checkbox).
4. **Contact** — primary contact, title, business email, phone, website, socials.
5. **Pre-approved products / team / approval** — summary cards linking to Feature 2 & 5 screens.

Every company-side page renders the **Brand Page Header (Pattern 9)**: gradient using `_spbwc_company_brand_primary/secondary`, logo, eyebrow "Company · Section", optional stats row. Falls back to Storelly default brand color when unset.

**Public `/store/<slug>` layout (redesigned).** The public storefront (`SPBWC_B2B_Storefront::render_store()`) uses a richer, token-driven **banner-forward hero** distinct from the shared company-page header:
- **Banner** — when `_spbwc_company_banner_id` is set it renders as a full-bleed image layer (`.spbwc-store__banner`) under a brand-gradient readability veil (`.spbwc-store__hero-veil`); with no banner the hero is the brand gradient + decorative blob.
- **Logo** — `_spbwc_company_logo_id` in a white rounded plate; when no logo is uploaded a **brand-tinted monogram** (1–2 initials of the company name) fills the plate so the hero never looks empty.
- **Identity** — eyebrow "Brand Store", title, tagline; **description** sits below the hero.
- **Badge row** (`.spbwc-store__badges`) — brand-safe cards *assigned to the store*: **tier name only** (never the discount %), industry (from the profile), catalogue size, team size. Commercial terms (discount %, payment terms, approval thresholds) and internal status are **intentionally not exposed** on this public page.
- **Catalogue** — section head with live item count + enhanced product cards (4∶3 thumb, Sale flag, brand-coloured price, "View product" CTA).

All colours/spacing/radius/shadow use the storefront design tokens (`--nbd-mb-*` / `--nbd-color-*`); the company brand colours are injected as `--spbwc-brand-primary/secondary` so the whole page re-tints. `dashicons` is enqueued by `SPBWC_B2B_Assets::storefront()` for the badge glyphs.

**States:** not-a-company (no endpoint shown) → invited (accept banner) → pending approval ("We'll review shortly") → active (full brand store) → incomplete profile (nudge to finish). Mirrors the source's tier-pill upgrade handshake.

---

## 5. Feature 2 — Per-company products + per-company price

Converts Printcart `pricing-rule-detail` "Printing Options binding" + `modal-pricing-rule-edit`.

### 5.1 Merchant flow
```
B2B Companies → open a company → "Pricing & products" tab
  Rule header: company name, tier baseline pill, "Overrides Tier X"
  KPI strip: avg discount, orders using rule, bound products, revenue (from WC)
  ─ Rule definition: rule name, base discount (pct over tier) OR fixed, min order, valid through
  ─ Bound products table:
      Product | Base price | Tier rate | This rule | Status
      (row) Window Decal | $30.60 | $29.34 (-4.1%) | …       | ✓ Applied
      (row) Banner 6×3   | …      | …             | $45.00  | Manual override
      [+ Bind product]  [Recalculate]
  [Save changes]
```
- "Bind product" = insert row into `spbwc_b2b_price_rules` (company_id + product_id).
- "This rule" cell editable → `override_type` pct/fixed + `value`.
- Status pills: `Applied`, `Manual override`, `Disabled`. (We drop the cloud "Synced/Stale" states — there's nothing to sync to.)
- The company's **Brand Store "pre-approved products"** list (`_spbwc_company_allowed_products`) is the allow-list; bound priced products auto-add to it.

### 5.2 Where price applies (engine)
Hook order (see [[SPEC_PRICING_OPTION_ASSIGNMENT]] + `class-frontend-options.php`):
1. `woocommerce_product_get_price` / `…get_sale_price` — apply company override or tier % for the **base product price** when the buyer is a company member (resolve `_spbwc_company_id`).
2. `woocommerce_add_cart_item` (existing priority 1) — recompute with Storelly option surcharges + quantity breaks on top of the B2B-adjusted base.
3. Product visibility: support "B2B only" products (hidden from B2C) and "Brand Store locked" (only visible inside the company's allow-list) — gate via `woocommerce_product_is_visible` + catalog query filter.

**Price display (Pattern 7):** on product pages for company members, show their B2B price prominently with a small "Your company price" note; optionally a struck retail price. Never show another company's price.

---

## 6. Feature 3 — Pricing schemes (tiers) + assignment

Converts Printcart `pricing` page + `modal-new-pricing-rule`.

### 6.1 Merchant flow — **Storelly → B2B Pricing**
Three stacked cards, matching the source:
1. **Tier discount ladder** — table `Tier | Discount % | Min order | Payment terms | Free shipping | Companies | Edit`. `+ Add tier`. Edits `spbwc_b2b_tiers`. "Companies" count = companies on that tier.
2. **Quantity-based discounts** — surfaced read-only here, but they reuse the existing `quantity_breaks` engine; link to the option editor. (No new engine — memory: quantity_breaks already has cart engine.)
3. **Special rules** — promo / product-specific overrides. v1: **defer** complex promo codes to a later release; show the section but ship only per-company overrides (Feature 2) + tier ladder.

### 6.2 Assigning a tier to a company
Three entry points, all writing `_spbwc_company_tier`:
- Invite-to-B2B modal `Proposed tier`.
- Approve-company modal `Approve as tier`.
- Company detail → "Change tier" (with optional reason in timeline).

Configuration-save pattern (admin-workflows Type 6): sticky Save, "unsaved changes" indicator, inline validation, toast.

---

## 7. Feature 4 — B2B Reorder

Converts Printcart `reorders` + `modal-quick-reorder` + `modal-reorder-placed`. **No recurring/subscription** (confirmed absent in source).

### 7.1 Company flow — My-Account **`reorders`** endpoint
```
Reorders (Brand Page Header + stats: reorderable items, categories)
  Toolbar: search + category filter chips
  Grid of reorder cards:
    [thumbnail · "×N ordered" badge]
    Title · specs line · "Last ordered <date>"
    [history icon] [Edit design] [Reorder $XX.XX ▸ openQuickReorder]
```
Reorder card data comes from the buyer's past **order line items that carry `pcpb_meta`** (Storelly designs) — we already restore these via `order_again_cart_item_data()` and clone the design folder copy-on-write. Reorders list = dedup of the user's purchased Storelly designs (+ company orders for owner/admin).

### 7.2 Quick reorder modal
```
Quick reorder
  product hero · green pill "Design & files saved · ready to print"
  step dots: 1 Quantity → 2 Shipping → 3 Confirm
  Quantity stepper + preset chips ("×3 same as last", "×5 save 8%" from quantity_breaks)
  Shipping-to: company default address [Change]
  Cost summary (subtotal/shipping/tax/total) at B2B price
  Payment line: shows terms label
  [Cancel] [↻ Place order · $TOTAL]
  ↓ confirm → clones saved folder → WC()->cart->add_to_cart() → checkout (or direct order)
modal-reorder-placed: ✓ order id / total / ETA · [Continue] [View order →]
```
Engine: extends `class-saved-designs.php` `load_design()` (clone + reconstruct `pcpb_meta` + B2B price). "No setup fees on reorders" = skip any one-time option surcharge flagged as setup (config later).

---

## 8. Feature 5 — Team procurement (roles, limits, approval)

Converts Printcart `teams` / `team-member-detail` / `approval` / `approval-detail`. Approval engine = **`spbwc_quote` kind=procurement** (§3.6).

### 8.1 Company flow — My-Account **`team`** endpoint
```
Teams (header [Invite member])
  stats: Members N / seats · Pending invites
  filter: role
  member rows: avatar · name (owner "YOU") · email · role badge · spend used · kebab(Edit/Remove)
  ↓ open member → Team Member Detail
    tabs: Permissions · Spending · Orders
    Permissions: role select (Owner/Admin/Approver/Requester/Viewer) + custom toggles
      (create designs · submit for approval (cannot self-approve) · access brand store ·
       approve ≤ threshold · view activity · invite members)
    Spending: per-order limit · monthly limit · used → [Adjust limits]
```
Invite teammate = add to `_spbwc_company_invites` (role) + WC email accept link. Seat limit enforced against `_spbwc_company_seats` (a filterable default cap, not a paywall — §11).

### 8.2 Procurement & approval gate
```
Requester configures a Storelly design, clicks Add to cart / Place order
  ↓ gate check: order total > requester per-order limit  OR > company approval threshold?
    NO  → normal WooCommerce checkout (auto-approved)
    YES → create internal spbwc_quote (kind=procurement, status=REVIEW)
          requester sees "Submitted for approval" in My-Account
Approver/Owner → My-Account "Approval Queue" endpoint
  cards: thumbnail · title · specs · "Submitted Xh ago · by <requester>" · amount
         [View details] [Approve] [Reject]
  ↓ View details → Approval Detail
    design preview + Comments/history thread (quote timeline)
    right: request specs · cost (with tier discount) · requester budget usage
    [Reject] [Approve order]
  ↓ Approve → quote → WC order (existing convert) → production
  ↓ Reject → status DECLINED, requester notified, can revise
```

### 8.3 Merchant-side counterpart (wp-admin)
- Company detail → **Members** tab: roster `Member | Role | Spend limit | Used | Last active`, `+ Invite member`.
- Company detail → **Permissions** tab: company-level toggles — "Require approval over $X", "Lock Brand Store templates", "Members share library", default requester permission (`submit only / self-approve < $X / full`), `Max team size`.
- Company **Activity** timeline shows procurement events ("Approved <member>'s order REQ-… for $X").

---

## 9. State models (consolidated)

**Company:** `invited → pending → active ⇄ suspended`; `active → incomplete_profile` (nudge) → `active`.

**Team seat:** `pending → active → removed`.

**Procurement order (spbwc_quote kind=procurement):**
`REVIEW (awaiting) → ACCEPTED → CONVERTED (WC order)` | `REVIEW → DECLINED → (revise) REVIEW`.

**Price resolution (per cart line):**
`retail → tier % → per-company pct → per-company fixed` (last non-null wins) → `+ Storelly option surcharge → − quantity break`.

---

## 10. Security, i18n, compliance (must-keep)

- Every form/AJAX/REST: **nonce + `current_user_can()`** (merchant) or company-role check (company-side). A company member may only read/write **their own** company's data — enforce `_spbwc_company_id` scoping on every query (the Storelly analogue of cloud tenant scoping).
- Sanitize all input (`sanitize_*` + `wp_unslash`), escape all output (`esc_*`). Tax id, addresses, hex colors validated.
- New SQL on `spbwc_b2b_price_rules` uses `$wpdb->prepare()`. Assets via `wp_enqueue_*`.
- Text domain `storelly-product-builder-for-woocommerce`; no variables in `__()`. New strings → POT regen + i18n skill.
- Prefix discipline: **`spbwc_`** everywhere (post meta `_spbwc_`, legacy cart meta `_pcpb_` only where it already exists). No new `pcpb_`.
- **No external service, no phone-home** — feature is fully local. Nothing new for readme "External services".
- **Impact report (cart/checkout/pricing)** required before coding §11 milestones touching the cart — they change `woocommerce_product_get_price` and add a cart price layer; verify they don't break B2C, quote flow, custom-order PDF, or HPOS order sync.

---

## 11. Milestones & tier

See `docs/DEV_MILESTONES_B2B_CLIENT.md` for the working checklist.

**Tier (revised 2026-06-04):** the confirmed freemium model is *everything local = Free (unlimited),
only features that call app.storelly.com are Paid* (see the freemium spec / `[[project_freemium_local_vs_cloud]]`).
This B2B suite is **100% local** — no cloud calls — so **all milestones ship Free**. The first draft's
"Pro" markers on M4/M5 are dropped; they remain heavier engineering but free. (The earlier "mark Pro"
choice predates surfacing this hard freemium constraint — flagged to the user.)

| Milestone | Feature | Why first / risk |
|---|---|---|
| **M1 — Company core + upgrade + public store** | F1: CPT, user link, upgrade, Brand Store profile + public `/store/<slug>`, B2B Companies hub | **Done.** Account model; no cart changes. |
| **M2 — Tier pricing** | F3: tier ladder option + assignment + price filter + B2B/B2C display | Core B2B value; reuses quantity_breaks. **Cart impact report first.** |
| **M3 — Reorder** | F4: reorders endpoint + quick-reorder modal | Pure reuse of saved-designs/order-again; high value, low risk. |
| **M4 — Per-company price overrides** | F2: `spbwc_b2b_price_rules` + binding UI + cascade | Per-client negotiated pricing; heavier admin. **Cart impact report first.** |
| **M5 — Team procurement** | F5: teams, roles, limits, approval queue (reuse quote engine) | Multi-seat + approval; most surface area. |
| **M6 — Compliance** | POT regen, Plugin Check 0-error, readme, capability audit, RTL | Release gate (CLAUDE.md). |

---

## 12. Decisions (confirmed 2026-06-04) & remaining questions

**Confirmed:**
1. **Payment terms (Net-15/30) = display labels only.** No deferred-payment/credit accounting; WooCommerce + the gateway handle real money. (Implemented in M1 as a label select.)
2. **One user → one company in v1.** Enforced in `SPBWC_Company::create()` (dup guard).
3. **Brand Store has its own public URL** `/store/<slug>` (pretty permalinks) with a `?spbwc_store=<slug>` fallback for plain-permalink sites. **Shipped in M1.**
4. **"Upgrade to B2B" = WC Users-list row action** + the Storelly **B2B Companies** hub. **Shipped in M1.**
5. **Default seat cap = 5** (`SPBWC_Company::DEFAULT_SEATS`, filterable via `spbwc_company_default_seats`) — a sensible configurable default, **not** a freemium paywall (B2B is fully free under the Local=Free model).
6. **Public store is brand-safe (confirmed 2026-06-05).** The `/store/<slug>` page is fully public (no login gate), so its badge row surfaces only non-sensitive facts — tier **name**, industry, catalogue size, team size. Discount %, payment terms, approval thresholds and internal status are never rendered there. Banner-forward hero with monogram logo-fallback shipped the same day.

**Remaining (later milestones):**
- **Quantity-break "save %" chips in reorder (M3)** — pull live from the product's `quantity_breaks`. *(Recommend: live.)*
- **Team invite accept page (M5)** — magic-link accept vs in-account accept for teammates.

---

## 13. What we deliberately drop from the source

- Cloud identity layer, `app.printcart.com`, iframe checkout, `embed-url`, `postMessage` bridge.
- Webhooks, GMV metering, multi-store `order_visibility` / preview-only.
- All AI tooling (preflight/upscale/bg-remove, quotas, "Powered by Printcart AI").
- Wix/Shopify adapters, white-label, subdomain-per-brand.
- Multi-step approval rule builder (v1 = single approver, like the source v1).

These are either cloud-only or out of Storelly's product surface; none are needed to deliver the five requested features on a single WooCommerce site.
