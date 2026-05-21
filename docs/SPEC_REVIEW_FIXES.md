# SPEC.md Review & Fix Report

> **PR**: [#5](https://github.com/Cloodo-Inc/storelly-product-builder-woo/pull/5)
> **Branch**: `claude/storelly-product-spec-Lv9Wx`
> **Initial commit**: `243cb4b` (docs: add comprehensive technical specification)
> **Fix commit**: `ca68941` (docs: address review findings on SPEC.md)
> **Net diff of fix commit**: +130 / −36 lines

This document records every issue found while reviewing `docs/SPEC.md` against the actual codebase, and every fix that was applied (or intentionally skipped).

The review was performed by 3 parallel agents at high effort:
- **Agent 1 — Accuracy verification**: verify every factual claim against the actual code.
- **Agent 2 — Completeness**: identify topics under-documented or missing.
- **Agent 3 — Structure / quality**: review the doc as a document (consistency, terminology, redundancy, readability).

---

## 1. Accuracy Fixes (verified against code)

### 1.1 [CRITICAL] Wrong column type for `product_ids`

- **Spec said**: `product_ids` LONGTEXT
- **Code says** (`class-admin-options.php:351`): `product_ids text NULL`
- **Fix**: Rewrote §3.2 to enumerate the exact `CREATE TABLE` columns with correct types and defaults — `TEXT`, `TINYINT(1)`, `VARCHAR(10) DEFAULT 'p'`, `DATETIME`, `BIGINT(20)`, `LONGTEXT` for `fields`. Added the missing `builder TEXT NULL` column. Linked to source at `class-admin-options.php:347`.

### 1.2 [CRITICAL] Non-existent order-item meta key `_pcpb_item_pb_key`

- **Spec said**: order items store `_pcpb_folder` (design folder ID) and `_pcpb_item_pb_key` (build session key).
- **Code says** (`class-frontend-options.php:947-951`): the actual keys persisted are
  - `_pcpb_folder`
  - `_pcpb_options`
  - `_pcpb_field`
  - `_pcpb_option_price`
  - `_pcpb_original_price`
  
  No `_pcpb_item_pb_key` anywhere in the codebase.
- **Fix**: Removed every reference to `_pcpb_item_pb_key` from §3.1, §3.3, §5.3, §14.3, §15, §16. Replaced with the real meta-key list, citing the source file/line. Updated the design folder section to say the folder key is "persisted as the `_pcpb_folder` order-item meta value."

### 1.3 [CRITICAL] `class-download-image.php` misrepresented as bootstrap-required

- **Spec said**: listed in §2.2 module map alongside files require_once'd in the bootstrap.
- **Code says**: not in the bootstrap require chain (`storelly-product-builder-for-woocommerce.php` lines 68-87). It is loaded lazily inside `class-admin-options.php:216` when the AJAX image download flow runs.
- **Fix**: Added a clarifying note above the §2.2 table — "The bootstrap eagerly `require_once`'s most of them; `class-download-image.php` is loaded lazily inside `class-admin-options.php:216` when the AJAX image-download flow runs."

### 1.4 [MAJOR] Non-existent `spbwc_pb_db_version` option

- **Spec said** (§13 + §14.2): version stored in `spbwc_pb_db_version`; `class-install.php` compares it with `SPBWC_PB_NUMBER_VERSION`.
- **Code says**: this option key does not appear anywhere. Version comparison actually happens in `class-admin-options.php:345` against the **`spbwc_version_plugin`** option.
- **Fix**: Removed `spbwc_pb_db_version` from the §13 table. Rewrote §14.2 to describe the real upgrade flow — admin load compares `SPBWC_PB_VERSION` vs `spbwc_version_plugin`, re-runs `dbDelta`, fires `spbwc_create_tables` / `spbwc_init_files_and_folders` action hooks.

### 1.5 [MINOR] FPDI claimed to be used by export flow

- **Spec said**: FPDI is "used by export flow".
- **Code says**: FPDI is credited in `readme.txt` but never `require`'d or instantiated by any PHP in `includes/`. All PDF generation goes through Cloud2Print.
- **Fix**: Updated §12.2 — "listed in readme third-party credits; **not** currently `require`'d by any PHP file in `includes/`. PDF generation goes through Cloud2Print (see §7.1)."

### 1.6 [MINOR] Wrong vendor JS paths

- **Spec said**: JS libraries are "sourced under `static/libs/`".
- **Code says**: Spectrum.js / Spectrum.css live under `static/js/` and `static/css/`; jQuery TipTip is under `static/js/`. Only some vendors are in `static/libs/`.
- **Fix**: Rewrote §12.2 to split the JS library list by directory — `static/libs/` (Fabric.js, Snap.svg, FontFaceObserver, SweetAlert, builderproductag) vs `static/js/` / `static/css/` (Spectrum, TipTip).

---

## 2. Completeness Gaps Filled

### 2.1 [HIGH] Missing integrator hooks (filters & actions)

The plugin exposes ~14 custom hooks for theme/plugin integration but the spec listed none.
- **Fix**: Added new **§6.4 Hooks for Integrators** with two tables:
  - 7 filters: `storelly_adjusted_price`, `storelly_need_change_cart_item_price`, `storelly_cart_item_thumbnail`, `storelly_cart_item_name`, `storelly_show_edit_option_link_in_cart`, `storelly_redirect_url`, `spbwc_locate_template`.
  - 7 actions: `spbwc_pb_menu`, `spbwc_before_save_product_builder_design`, `spbwc_after_save_product_builder_design`, `spbwc_global_import_product_saved`, `spbwc_create_tables`, `spbwc_init_files_and_folders`, `spbwc_enqueue_script_custom`.

### 2.2 [HIGH] Request-a-Quote email workflow missing

§10 described form/statuses but omitted email notifications.
- **Fix**: Added bullet to §10 documenting `wp_mail()` notifications (admin + customer), configuration via `spbwc_quote_settings['admin_email']`, hard-coded body, source at `class-request-quote.php:275-299`.

### 2.3 [MEDIUM] PDF export workflow underspecified

§7.1 had 4 lines of generic description.
- **Fix**: Replaced with 4-step concrete workflow (HTML materialised in `pdf-templates/`, URL-encoded + base64 settings request, remote fetch, PDF written to `customer-pdfs/`). Noted no automatic cleanup and that the WP site must be publicly reachable.

### 2.4 [MEDIUM] JavaScript architecture undocumented

Spec mentioned Fabric.js but never said where JS entry points live.
- **Fix**: Added **§5.5 JavaScript Architecture** listing the main frontend/admin JS modules with their roles.

### 2.5 [MEDIUM] Caching strategy only mentioned for license

License caching was documented; broader transient/cache usage was not.
- **Fix**: Added **§8.1 Caching Strategy** documenting `spbwc_published_options` object cache, `spbwc_product_builder_{product_id}` per-product transient, `spbwc_packages` and `spbwc_overview_stats` transients (with TTLs), and the bulk transient invalidation on Pricing Option save.

### 2.6 [MEDIUM] Global-import logs not described

§9.1 mentioned "fetch logs" without saying where they live or how.
- **Fix**: Added that logs are appended line-by-line to `wp-uploads/global-import-exports/logs/{job_id}.log` and streamed via the AJAX log endpoint.

### 2.7 [MEDIUM] §13 options table incomplete

Only 5 options listed; codebase uses more.
- **Fix**: Added `spbwc_version_plugin`, `spbwc_number_of_decimals`, `spbwc_hide_add_cart_until_form_filled`, `spbwc_product_builder_page_id`, `spbwc_global_import_sessions`, `spbwc_quote_settings`. Removed the bogus `spbwc_pb_db_version`.

### 2.8 [LOW] Lack of CPT/taxonomy/shortcode/cron statement

Readers couldn't tell whether the plugin registered any of these.
- **Fix**: Added bullet to §15: "The plugin does **not** register custom post types, taxonomies, shortcodes, or cron events — it relies entirely on WooCommerce's native product/order entities extended with post meta and the custom Pricing Options table."

---

## 3. Structural / Style Fixes

### 3.1 Dead boilerplate intro

- **Before**: "This document is a comprehensive specification of the plugin: architecture, domain model, modules, APIs, integrations, security, and storage. It is intended as a reference for maintainers, integrators, and reviewers."
- **After**: "Scope: architecture, domain model, AJAX/REST surface, external integrations, security, lifecycle, and on-disk storage layout. Audience: maintainers and integrators."

### 3.2 Section 5 jumped from `##` to `###` with no intro

- **Fix**: Added an end-to-end flow paragraph (5 numbered phases) under `## 5. Frontend Product Builder` before the `### 5.1 Rendering` subsection.

### 3.3 §7.4 WC hooks crammed into 3 lines of prose

- **Fix**: Converted to three grouped bullet lists (Frontend / cart, Order lifecycle, Compatibility).

### 3.4 §6.2 collapsed action names with slashes

- **Before**: `spbwc_global_import_upload` / `_list` / `_row_ids` / `_run` / `_log`
- **After**: each action on its own row in the table (5 rows).

### 3.5 §5.4 missing cross-reference

- **Fix**: Linked `spbwc_sanitize_file_upload()` to §11.4 where the validation rules are described.

### 3.6 Terminology drift "Pricing Option (Field Group)"

- **Fix**: Dropped the parenthetical "(Field Group)" — sole occurrence — to keep terminology consistent throughout.

### 3.7 Glossary cleanup

- **Removed**: spurious "build session key" framing.
- **Added**: `Folder key`, `_pcpb_folder`, `_pcpb_options` definitions.

---

## 4. Intentionally Skipped Findings

These were flagged by the reviewers but **not** applied — recorded here for transparency.

### 4.1 Merge §6 (AJAX surface) and §11 (security) into one table
Agent 3 suggested deduplication. **Skipped**: the two tables intentionally serve different lenses — §6 is the API surface for integrators, §11.1 is a security/nonce audit view. Cross-cutting one perspective into the other would hurt both.

### 4.2 `load_plugin_textdomain` missing
Agent 2 noted no `load_plugin_textdomain()` call in `includes/`. **Skipped**: this is a plugin defect, not a spec defect. Documenting an i18n bootstrap that doesn't exist would be wrong; fixing it is out of scope for a docs PR.

### 4.3 Various micro-style tweaks (capitalisation, em dash vs hyphen, table-header backtick consistency)
**Skipped**: noise-to-value ratio too low for a 467-line doc.

### 4.4 Inline `{file paths}` vs `includes/{file paths}` in §2.2 table
**Partially addressed** via the note before the table ("All files below live in `includes/`") rather than rewriting every row.

---

## 5. Verification Summary (Agent 1 final report)

All of the following were re-verified against the code during review and confirmed correct:

- All `SPBWC_*` constants in §2.3 (paths, slugs, `SPBWC_API_URL`, version numbers).
- All view files referenced in §4.1 exist at the listed paths.
- All AJAX actions listed in §6.1 and §6.2 are registered in the code; no missing actions.
- All WooCommerce hooks sampled in §5.3 / §7.4 are actually used.
- External API endpoints (`/api/v1/update-orders`, license endpoints, Cloud2Print URL pattern) match the code.
- License tier defaults (3 Pricing Options / 5 products / 50 orders) match `class-license-manager.php`.
- Activation flow in §14.1 matches `spbwc_plugin_activation` and `SPBWC_Storelly_Product_Builder_Backend::spbwc_plugin_activation`.

---

## 6. Commits in this PR

| Commit | Description |
|--------|-------------|
| `243cb4b` | Initial spec (467 lines). |
| `ca68941` | Review findings applied (this report). |
