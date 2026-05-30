# SPEC — Single-Source Pricing Option Assignment

> **Status**: Locked design, pre-implementation.
> **Scope**: How a WooCommerce product is mapped to a Storelly pricing option, both at write time (Product edit page builder + Template Apply menu) and at render time (storefront / metabox / cart).
> **Touches render path**: yes — `spbwc_get_product_option()` is the single resolver used by storefront, admin metabox, and cart. Backward compatibility for legacy data is required (see §7).

---

## 1. Problem

A single product can currently be claimed by **two or more published pricing-option rows simultaneously**, which the renderer silently disambiguates by "highest-id wins". The merchant gets no warning, the losing option keeps claiming the product, and behavior can flap when transient cache state changes.

Two flows independently attach a product to an option, both writing to the same table `wp_storelly_product_builder_options`:

1. **Product edit page → builder save** (`spbwc_save_option`, [includes/class-admin-options.php:1901](../includes/class-admin-options.php#L1901)): writes/updates an option row with `product_ids = serialize([...])` from `$_POST['product_ids']` ([:1913](../includes/class-admin-options.php#L1913)).
2. **Template Apply menu** (`SPBWC_Template_Applier::apply`, [includes/templates/class-template-applier.php:40](../includes/templates/class-template-applier.php#L40)): **inserts a brand-new row** with `product_ids` = scope ([:91](../includes/templates/class-template-applier.php#L91)). Additionally per product:
   - sets transient `spbwc_product_builder_<pid>` as a hard 1-hour pointer override ([:115](../includes/templates/class-template-applier.php#L115)),
   - writes `update_post_meta(pid, '_spbwc_option_id', $new_id)` — currently **never read by any code path**,
   - writes `update_post_meta(pid, '_storelly_pb_enable', 1)`.

The resolver `spbwc_get_product_option()` ([:2748](../includes/class-admin-options.php#L2748)) then:

1. Reads transient. If hit → returns it.
2. Else scans every published row, collects all matches into `$_options` (both `apply_for='p'` and `apply_for='c'` go into the **same bucket**), `array_reverse()`, takes `[0]` → highest id wins ([:2781](../includes/class-admin-options.php#L2781)).
3. Caches result in transient.

### 1.1 Conflict scenarios actually reachable today

- **Silent double-claim**: product already has an option built via the builder (id 15). Merchant applies a template (id 20) to it. Now both row 15 and row 20 list the product in `product_ids`. Frontend shows id 20; row 15 is orphan-but-still-published and still appears under that product in the option list table ([includes/options/fields-list-table.php:496](../includes/options/fields-list-table.php#L496)).
- **Category option overrides product-specific**: a `'c'` option created later (higher id) silently overrides a `'p'` option created earlier, because the resolver mixes both tiers in one bucket.
- **Transient vs table-scan disagreement**: the applier's hard transient ([:115](../includes/templates/class-template-applier.php#L115)) pins one answer; any subsequent save/unpublish bulk-flushes every `spbwc_product_builder_%` transient ([:108-114](../includes/class-admin-options.php#L108)); the next read recomputes via "highest id wins". Two different answers can be served minutes apart for the same product.
- **`_spbwc_option_id` is dead meta**: the applier writes it, but no read path consults it, so it suggests a 1:1 mapping that the resolver ignores.

---

## 2. Authoritative rules

1. **Per-product uniqueness at render time**: a product renders exactly one pricing option at any moment.
2. **An option may target many products** (a hand-picked list of product IDs, possibly spanning categories) **OR all products of one category**, but not both at the same time on the same row — matches existing `apply_for ∈ {'p','c'}` semantics.
3. **Resolution precedence**: explicit product-level (`'p'`) > category (`'c'`). Within the same tier, **newest (higher `id`) wins** as deterministic tiebreaker.
4. **Apply-over-existing for `'p'` is a move, not a duplicate**: applying a new `'p'` option to a product that is already in another `'p'` option's `product_ids` requires explicit merchant confirmation; on confirm, the product is removed from the old option's `product_ids` (the old option keeps existing for its other products).
5. **Category overlap is resolved silently by precedence + tiebreaker**, not by confirmation dialogs — apply-time confirmation for category overlaps would be too noisy on large catalogs.
6. **`apply_for='a'` is out of scope**. The whitelist at [class-template-applier.php:51](../includes/templates/class-template-applier.php#L51) accepts it but the resolver does not handle it. Leave as-is; do not advertise.

---

## 3. Locked design

### 3.1 Source of truth

- **Product-level mapping**: post meta `_spbwc_option_id` is promoted to the **authoritative pointer**. When present and the referenced option is `published=1`, it IS the product's product-level option.
- **Category mapping**: stays implicit — derived at read time by intersecting the product's `product_cat` terms with each published option's `product_cats` column. No per-product meta is written for category coverage.
- **Transients**: kept only as a **derived cache** of the resolver result. The applier no longer writes them as hard overrides.

### 3.2 Resolver order (`spbwc_get_product_option`)

```
1. read transient cache               → if hit, return
2. read _spbwc_option_id              → if set AND option exists AND published, return it
3. scan published options             → split into two buckets by apply_for
   - 'p' bucket: rows whose product_ids contains $product_id
   - 'c' bucket: rows whose product_cats intersects $product_id's terms
4. if 'p' bucket non-empty            → pick highest id from 'p'
   else if 'c' bucket non-empty       → pick highest id from 'c'
   else                               → no option
5. cache result in transient, return
```

### 3.3 Write-time invariants

- **Builder save** (`spbwc_save_option`, `apply_for='p'`): after a successful insert/update with new `product_ids`:
  - For each `pid` in the new `product_ids`: `update_post_meta(pid, '_spbwc_option_id', $id)`.
  - For each `pid` in `previous_product_ids \ new_product_ids`: if `_spbwc_option_id == $id`, delete it (the product is no longer pointed at by this option).
  - Existing transient flush is retained.
- **Template apply** (`apply($slug, $apply_for, $scope_ids, $custom_title, $force = false)`):
  - For `apply_for='p'`, before insert: collect every `pid` in `$scope_ids` that already has a product-level assignment (pointer set, or appears in another published `'p'` row).
  - If any conflict exists and `$force` is false: return `{ success:false, conflict:true, conflicts:[...], message }` and do NOT insert. UI shows confirmation dialog.
  - If `$force` is true OR no conflict: insert the new row, then for each `pid`:
    - Remove `pid` from the **old** option's `product_ids` (move semantics) and flush that option's cache.
    - Replace `set_transient(...)` with `delete_transient(...)` so the resolver recomputes.
    - `update_post_meta(pid, '_spbwc_option_id', $new_id)` (now authoritative).
    - Keep `update_post_meta(pid, '_storelly_pb_enable', 1)`.
- For `apply_for='c'`: unchanged — insert the row, flush per-product transients for products in the affected categories (best-effort by flushing the global transient prefix, already done elsewhere). No confirmation dialog.

### 3.4 AJAX contract (`spbwc_template_apply`)

Request adds optional `force` ∈ {`0`,`1`}, default `0`.

Response shapes:

- **Success (no conflict, applied)**: `wp_send_json_success({ success:true, option_id, message, edit_url })`. Unchanged from today.
- **Conflict (apply_for='p', force=0, conflicts found)**: `wp_send_json_success({ success:false, conflict:true, conflicts:[{ product_id, product_title, current_option_id, current_option_title }, ...], message })`. JS shows confirm dialog listing each conflict; on confirm, re-POSTs with `force=1`.
- **Error**: `wp_send_json_error({ message }, status)`. Unchanged.

Note: conflict is delivered via `wp_send_json_success` (HTTP 200) with `success:false, conflict:true` so the JS client doesn't have to special-case a `wp_send_json_error` body for a non-error UX flow.

### 3.5 Metabox UI (`views/options/meta-box.php`)

- Continue rendering the existing "Mapped printing option: `<title>`" line, sourced from the new resolver (so it always reflects the rendered option).
- Defensive: if the scan would still find **more than one** published `'p'` row claiming this product (legacy data), render a `notice notice-warning` line listing the extra option titles with deep links — so the merchant can clean them up. No new UI to change the assignment from the metabox itself (deferred).

---

## 4. Data model changes

- **No schema change.** Existing columns are sufficient.
- **`_spbwc_option_id` (post meta)** — promoted from dead artifact to authoritative pointer for the product → product-level option mapping. Always an integer option id, or absent.
- **No new post meta keys.**
- **No new transient keys.** The existing `spbwc_product_builder_<pid>` transient remains, but is now strictly a derived cache (resolver result), never a hard override.

---

## 5. Implementation plan

| # | File | Change |
|---|------|--------|
| 1 | [includes/class-admin-options.php:2748](../includes/class-admin-options.php#L2748) (`spbwc_get_product_option`) | Pointer-first resolution. Split scan into `'p'`/`'c'` buckets. Picks newest within `'p'`, falls back to newest within `'c'`. |
| 2 | [includes/class-admin-options.php:1901](../includes/class-admin-options.php#L1901) (`spbwc_save_option`) | After successful save with `apply_for='p'`: sync `_spbwc_option_id` for new product_ids, delete it for removed product_ids where it equals `$id`. |
| 3 | [includes/templates/class-template-applier.php:40](../includes/templates/class-template-applier.php#L40) (`apply`) | Add `$force=false` param. Pre-check conflicts for `'p'`; on conflict without force return structured response. On apply: move product out of old `'p'` option's product_ids, replace `set_transient` with `delete_transient`, keep `_spbwc_option_id` write (now authoritative). |
| 4 | [includes/templates/class-template-ajax.php:70](../includes/templates/class-template-ajax.php#L70) (`ajax_apply`) | Read `$_POST['force']`, pass into `apply()`. Surface `conflict` payload via `wp_send_json_success`. |
| 5 | JS template library | Catch `conflict:true` from the apply AJAX, render confirm dialog listing conflicts, re-POST with `force=1`. (File to be located during implementation — likely `static/js/admin/template-library*.js`.) |
| 6 | [views/options/meta-box.php](../views/options/meta-box.php) | Render warning notice if scan finds >1 published `'p'` row still claiming this product. |

Implementation order: 1 → 2 → 3 → 4 → 5 → 6. Step 6 is defensive and may be deferred if needed.

---

## 6. Backward compatibility

- **Legacy data (no pointer set)**: resolver step 2 misses, step 3 runs. With the new `'p'` > `'c'` precedence the answer can DIFFER from today only when both a `'p'` row and a `'c'` row currently match the same product — today the newer one wins regardless; after the change the `'p'` row always wins. This is intentional and aligns with the explicit-over-implicit principle (Rule 3); it does not affect products covered by only one tier.
- **Pointer drift**: if `_spbwc_option_id` references an unpublished or deleted option, validation in step 2 fails and the resolver transparently falls through to step 3. No self-heal is performed automatically — a stale pointer simply becomes a no-op.
- **No data migration runs on activation/upgrade.** Pointers materialize naturally as merchants save options or apply templates. Legacy products keep working via the scan fallback.
- **Transient invalidation**: existing bulk-flush behavior in `spbwc_flush_option_caches` ([:100](../includes/class-admin-options.php#L100)) is retained. Removing the applier's `set_transient` hard-override eliminates one source of drift; it does not require any compensating change.

---

## 7. Test plan

Manual smoke (the four cases this spec is designed to fix):

1. **Builder save round-trip**: create option A with product_ids = [P1, P2], save. Verify `_spbwc_option_id` is now `A.id` on both P1 and P2. Remove P2 from product_ids, save. Verify `_spbwc_option_id` is deleted from P2 and still `A.id` on P1.
2. **Apply template — no conflict**: apply template T to product P3 (which has no prior option). Verify a new row B is inserted, P3's `_spbwc_option_id == B.id`, transient is cleared (not hard-set). Storefront renders B on P3.
3. **Apply template — conflict + confirm**: apply template T to P1 (which is already in A's product_ids). First call (no `force`): AJAX returns `conflict:true` with P1 listed. UI shows confirm. Second call with `force=1`: new row C inserted, P1 removed from A.product_ids, A still has P2, P1's `_spbwc_option_id == C.id`, storefront renders C on P1, P2 still renders A.
4. **Category precedence**: create option D with `apply_for='c'` covering category X. P4 is in X. Now apply option E directly to P4 (`'p'`). Storefront renders E (product-level beats category) even though D may have a higher id.
5. **Legacy fallback**: simulate legacy data by deleting `_spbwc_option_id` from P1 manually; verify the resolver still finds A via scan fallback and storefront still renders A.
6. **Defensive metabox warning**: simulate legacy data by adding P5 to both A.product_ids and C.product_ids manually; verify the product edit page metabox shows the warning listing the extra option.

Automated:
- `wp plugin check storelly-product-builder-for-woocommerce` → **0 ERROR** before commit (project rule, [CLAUDE.md](../CLAUDE.md)).
- No PHPCS regressions in touched files.

---

## 8. Out of scope

- Changing the metabox into an editable "select which option applies" UI. The metabox stays read-only for this iteration.
- Wiring `apply_for='a'` ("all products") into the resolver.
- Per-option priority / explicit ordering. Tiebreaker remains "newest id wins" within a tier.
- Bulk migration of legacy rows where the same product is in multiple `'p'` rows. Defensive metabox warning surfaces it; merchant cleans up.
- Order/cart-side impact: no change. Cart items are already pinned to the option id at line-item time via `_pcpb_*` meta; this spec only changes resolution for newly-visited product pages.

---

## 9. Glossary

- **Pointer**: post meta `_spbwc_option_id` on a product post, an integer option id.
- **Product-level option** (`apply_for='p'`): a row that explicitly lists product IDs in `product_ids`.
- **Category-level option** (`apply_for='c'`): a row that lists category term IDs in `product_cats` and is matched dynamically against a product's terms.
- **Move semantics**: when applying option B to a product previously in option A's `product_ids`, the product is **removed from A.product_ids** rather than left in both.
- **Tier**: `'p'` is the product-level tier; `'c'` is the category-level tier. Precedence is strictly `'p'` > `'c'`.
