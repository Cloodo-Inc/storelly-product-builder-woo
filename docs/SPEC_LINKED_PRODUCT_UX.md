# SPEC — Linked Product UX (Woo edit ↔ Storelly option)

> **Status**: Implemented + smoke-tested (2026-06-03). Shared-guard ships warn-only; clone deferred (§6).
> **Known polish**: the option editor already renders its own breadcrumb, so the product-context breadcrumb added here stacks above it (redundant but harmless — only ours carries "Back to product").
> **Scope**: The admin experience of editing a WooCommerce product that is linked to a Storelly pricing option — the metabox on the product edit screen, the round-trip into the option builder, and back.
> **Home base**: WooCommerce product edit screen (Direction A). The Storelly metabox is upgraded into a control panel; the product edit page stays the place merchants start from.
> **Touches render path**: no. This spec only changes admin UI/flow and adds two write actions (swap pointer, unlink pointer). The storefront resolver `spbwc_get_product_option()` is unchanged. The `_spbwc_option_id` pointer remains the single source of truth — see [SPEC_PRICING_OPTION_ASSIGNMENT.md](SPEC_PRICING_OPTION_ASSIGNMENT.md).

---

## 1. Problem

The "linked product" flow is fragmented across three surfaces with no shared design language and inconsistent navigation:

1. **Storelly › Products** card list ([views/_products-cards.php](../views/_products-cards.php)) — "Edit product" opens Woo edit in the **same tab** (merchant leaves Storelly); "Edit Option" opens the builder in a **new tab**.
2. **Woo › Edit Product** metabox ([views/options/meta-box.php](../views/options/meta-box.php)) — raw WP form fields, the mapped option is a **read-only label**, and "Edit option" opens a **new tab**.
3. **Storelly Option Builder** — the actual field editor, reached with no breadcrumb and no way back to the originating product.

### 1.1 Concrete pain points

- **Inconsistent tab behavior** — same-tab vs new-tab mixed; no breadcrumb, no "back to product". Merchants get lost or drown in tabs.
- **Mapping is read-only at the metabox** ([meta-box.php:36-39](../views/options/meta-box.php#L36)). To change/swap/unlink the option a merchant must open the option and edit its "Apply to products" list — the relationship lives in two places.
- **Raw WP form styling** — the metabox feels like a second, unrelated plugin rather than an integrated experience.
- **Hidden shared-option risk** — one option targets many products (1 option → N products). Editing Option #8 from product 123's context silently changes products 124, 125 too. The current UI never warns. This is the highest data-integrity risk in the flow.
- **Manual round-trip** — product → option → back to product is all manual navigation.

---

## 2. Authoritative rules

1. **Woo product edit is home base.** Every "edit fields" path starts and ends there.
2. **One product → one option pointer** (`_spbwc_option_id`). The metabox edits/displays exactly that pointer. Category-tier (`'c'`) mapping is out of scope for this metabox (it stays implicit, resolver-driven).
3. **Same-tab navigation with breadcrumb.** Opening the builder from a product navigates in the same tab and always offers "← Save & back to product".
4. **Editing a shared option warns before proceeding.** No silent cross-product edits. This release ships **warn-and-confirm only** (no clone/detach — deferred, see §6).
5. **Swap and Unlink mutate only the pointer**, never delete option rows or fields. Swap repoints `_spbwc_option_id`; Unlink clears it for this product. Both flush the product's resolver transient.
6. **Security**: every new write (swap, unlink) verifies a nonce and `current_user_can('manage_woocommerce')` (or the capability already used by the option save handler — match existing).

---

## 3. Locked design — the metabox

### 3.1 Layout

```
┌─ Storelly product builder ──────────────────────────────┐
│  [✓] Enable product builder                              │
│                                                          │
│  ── when enabled & mapped ──                             │
│  ┌─ Mapped option ───────────────────────────────────┐  │
│  │  📐 Printing Options v2   #8                       │  │
│  │  6 fields · Designer ✓ · Add-to-cart + Quote      │  │
│  │  [chip Paper] [chip Size] [chip Qty] [+3]         │  │
│  │                                                    │  │
│  │  ⚠ Shared by 3 products  ⌄ (show list)            │  │
│  │                                                    │  │
│  │  [ Edit fields → ]   [ Swap ▾ ]   [ Unlink ]      │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ── when enabled & NOT mapped ──                         │
│  [ + Create option for this product ]                    │
│                                                          │
│  ┌─ Request a quote ─────────────────────────────────┐  │
│  │  [✓] Enable request quote                          │  │
│  │  Display: ( Add-to-cart + Quote ▾ )               │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

### 3.2 Data the metabox callback must compute

Extend `spbwc_meta_box()` ([includes/class-admin-options.php:2809](../includes/class-admin-options.php#L2809)) to pass to the view:

| Variable | Source | Notes |
|----------|--------|-------|
| `$option_id`, `$option_title` | existing | unchanged |
| `$field_count` | count of the option's `fields` array | reuse logic that already powers the products card `field_count` |
| `$has_designer` | scan fields for a designer/`nbpb` component | true → show "Designer ✓" |
| `$display_mode` | derived from `_spbwc_enable_quote` + `_spbwc_quote_display_mode` | human label: "Add-to-cart + Quote" / "Quote only" / "Add to cart" |
| `$field_chips` | first N field titles (N=3) + overflow count | for the chip row |
| `$shared_product_ids` | products in this option's `product_ids` **other than** the current one | drives the shared badge + count |
| `$shared_products` | id → title/edit-link for the expandable list | lazy: only titles, capped (e.g. first 20) |
| `$swap_options` | published options list `[id => title]` for the Swap dropdown | reuse `spbwc_get_cached_published_options()` |
| `$link_edit_option` | existing, but **drop `target="_blank"`** | same-tab now |

`$extra_options` (legacy multi-claim warning) stays as-is.

### 3.3 Action behaviors

- **Edit fields →** — link to the builder, **same tab**, keeps `product_id`. If the option is shared (`count($shared_product_ids) > 0`), intercept the click with a JS confirm:
  > "Option #8 is shared by **3 products**. Editing its fields affects all of them."
  > `[ Edit anyway ]` `[ Cancel ]`
  No clone path this release.
- **Swap ▾** — searchable `<select>` of `$swap_options`. On change → AJAX `spbwc_swap_product_option` (nonce + cap): set `_spbwc_option_id` to the chosen option, add the product to that option's `product_ids` if absent, flush the product transient, return new summary HTML (or reload the metabox section).
- **Unlink** — light confirm → AJAX `spbwc_unlink_product_option`: delete `_spbwc_option_id` for this product, remove the product from the option's `product_ids`, flush transient. Metabox falls back to the "Create option" CTA state.
- **Create option for this product** — same-tab link to builder `action=create&product_id=PID`.
- **Shared badge ⌄** — pure client-side disclosure of `$shared_products` (each row links to that product's edit screen, same tab).

---

## 4. Locked design — builder breadcrumb & return

When the builder edit/create screen (`SPBWC_PB_BUILDER_SLUG`, the `action=edit|create` branch near [class-admin-options.php:580-730](../includes/class-admin-options.php#L580)) receives a `product_id` query arg:

1. Render a breadcrumb above the builder: `Products › [Product title] › Option #8` where "Products" links to the Storelly products list and "[Product title]" links back to `post.php?post=PID&action=edit`.
2. Render a primary **← Save & back to product** button in the builder footer that submits the option and, on success, redirects to the product edit screen instead of the option list.
3. When no `product_id` is present, behavior is unchanged (current list-return flow).

`product_id` is already threaded into the edit URL today ([meta-box.php:87](../views/options/meta-box.php#L87), [_products-cards.php:30](../views/_products-cards.php#L30)) — this spec only makes the builder *consume* it for navigation.

---

## 5. Styling

- New admin stylesheet (token-based, matching [static/css/edit-option-v3.css](../static/css/edit-option-v3.css)) for the metabox card, chips, badge, and action row. Enqueue **only** on the product edit screen.
- Replace the raw `storelly-form-field` blocks for the mapped-option area; keep the existing enable/quote inputs but group them under a "Request a quote" sub-card.

---

## 6. Out of scope (deferred)

- **Clone / "detach a private copy"** for shared options — explicitly deferred. This release only warns. Revisit if merchants frequently need per-product divergence (a `spbwc_clone_option()` helper would be the foundation; cf. `spbwc_clone_design_folder`).
- Category-tier (`'c'`) mapping editing from the metabox.
- Direction B (Storelly-as-home) and Direction C (unified drawer) — not chosen.

---

## 7. Files touched

| File | Change |
|------|--------|
| [includes/class-admin-options.php:2809](../includes/class-admin-options.php#L2809) | `spbwc_meta_box()` computes §3.2 vars; drop `target="_blank"` on `$link_edit_option`; add AJAX handlers `spbwc_swap_product_option`, `spbwc_unlink_product_option` (nonce + cap + transient flush). |
| builder edit/create branch (`SPBWC_PB_BUILDER_SLUG`, ~[:580-730](../includes/class-admin-options.php#L580)) | breadcrumb + "Save & back to product" when `product_id` present. |
| [views/options/meta-box.php](../views/options/meta-box.php) | New card markup: summary, chips, shared badge + list, Swap/Unlink/Edit actions. |
| New `static/css/linked-product-metabox.css` | Token-based styling; enqueued on `product` edit screen only. |
| New JS (or extend admin script) | Swap select → AJAX, Unlink confirm, shared-edit interception, badge disclosure. Localize nonce. |

---

## 8. Acceptance

- [ ] Editing a non-shared option from a product navigates same-tab, shows breadcrumb, and "Save & back" returns to that product.
- [ ] Editing a shared option triggers the "shared by N" confirm before navigating.
- [ ] Swap repoints the product to another option without touching either option's fields; storefront resolves the new option.
- [ ] Unlink returns the metabox to the "Create option" CTA and the storefront shows no builder.
- [ ] Swap/Unlink reject requests with a bad/missing nonce or insufficient capability.
- [ ] Metabox visual matches Storelly design tokens; `wp plugin check` stays at 0 errors (escaping/sanitization/nonce on the new code).
