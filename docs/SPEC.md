# Storelly Product Builder for WooCommerce — Technical Specification

> **Plugin**: Storelly Product Builder for WooCommerce
> **Version**: 1.2.6
> **Requires**: WordPress ≥ 4.7, WooCommerce ≥ 6.0.0, PHP ≥ 7.0
> **Text Domain**: `storelly-product-builder-for-woocommerce`
> **Prefix**: `spbwc_` / `SPBWC_`
> **License**: GPL v2 or later

Scope: architecture, domain model, AJAX/REST surface, external integrations, security, lifecycle, and on-disk storage layout. Audience: maintainers and integrators.

---

## 1. Overview

Storelly Product Builder is a WooCommerce extension that adds a **visual, step-by-step product customization experience** to product pages. Customers select attributes (size, color, material, text, layers, uploaded artwork…) and the personalized configuration is attached to the WooCommerce cart and order. The plugin also:

- Renders pricing options (dropdown/radio/swatch/input/label/advanced-dropdown/xlabel) on the product page.
- Persists customer "designs" as a folder of SVG + JSON files in `wp-uploads/`.
- Generates print-ready PDFs through a remote Cloud2Print service.
- Syncs orders and licensing with the Storelly Dashboard (`app.storelly.com`).
- Offers product import/export (native JSON + legacy PrintCart adapter).
- Implements a "Request a Quote" workflow with custom WC order statuses.

**Monetization**: local-first freemium. The full builder, pricing options, quotes and custom orders are free with **no product limit**; paid **Storelly Cloud** plans gate only features that call `app.storelly.com` (print-ready PDF, order sync, dashboard analytics, hosted marketplace). See `docs/SPEC_FREEMIUM.md`.

---

## 2. Bootstrap & Architecture

### 2.1 Entry Point — `storelly-product-builder-for-woocommerce.php`

Responsibilities:

1. Define constants (paths, URLs, slugs, API base, version) — `SPBWC_PB_*` family.
2. Register activation hook `spbwc_plugin_activation` — verifies WooCommerce is active; aborts with `wp_die` otherwise; then calls `SPBWC_Storelly_Product_Builder_Backend::spbwc_plugin_activation()`.
3. `require_once` every class in `includes/` (see §2.2).
4. Register a second activation hook to generate Storelly API keys (`SPBWC_Storelly_Product_Builder_API::spbwc_generate_key`).
5. Declare HPOS (custom order tables) compatibility via `FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)`.
6. Provide `spbwc_sanitize_recursive($data)` — recursively applies `sanitize_text_field` to arrays/strings (used on POST payloads).
7. Instantiate `SPBWC_Storelly_Product_Builder_Backend` and call `spbwc_init()` to dispatch admin/frontend wiring.

### 2.2 Module Map (`includes/`)

All files below live in `includes/`. The bootstrap eagerly `require_once`'s most of them; `class-download-image.php` is loaded lazily inside `class-admin-options.php:216` when the AJAX image-download flow runs.

| File | Class | Role |
|------|-------|------|
| `class-product-builder-backend.php` | `SPBWC_Storelly_Product_Builder_Backend` | Top-level dispatcher: detect admin vs frontend, instantiate the right controllers. |
| `class-product-builder-frontend.php` | `SPBWC_Storelly_Product_Builder_Frontend` | Render builder on product page; AJAX save/upload of customer designs. |
| `class-admin-options.php` | `SPBWC_Storelly_Product_Builder_Admin_Options` | Admin menus, settings/options/products/orders/quotes/license pages, custom table creation. |
| `class-frontend-options.php` | `SPBWC_Storelly_Product_Builder_Frontend_Options` | Render pricing-option fields on product page, validate add-to-cart, attach design meta to cart item and order line item, price recalculation. |
| `class-productbuilder-api.php` | `SPBWC_Storelly_Product_Builder_API` | Storelly Dashboard sync — register account, sync orders, generate WC REST keys. |
| `class-http.php` | `SPBWC_HTTP` | Thin wrapper around `wp_remote_*` with `X-STORLY` auth header. |
| `class-license-manager.php` | `SPBWC_License_Manager` | License status cache, activation, tier limit enforcement. |
| `class-export-pdf.php` | `SPBWC_Export_PDF` | Build HTML representation of a design and call Cloud2Print API. |
| `class-download-image.php` | `SPBWC_Download_Image` | Fetch and store remote images locally. |
| `class-image.php` | `SPBWC_Image` | Image helpers (thumbs, attachments). |
| `class-io.php` | `SPBWC_IO` | File system helpers (mkdir, list, cleanup). |
| `class-install.php` | `SPBWC_Install` | Versioned install/upgrade hook. |
| `class-script-hook.php` | `SPBWC_Script_Hook` | Enqueue JS/CSS (Fabric.js, Spectrum, FontFaceObserver, SweetAlert, plugin assets). |
| `class-request-quote.php` | `SPBWC_Request_Quote` | "Request a Quote" flow: custom order statuses, frontend form, account endpoint. |
| `class-global-import.php` | `SPBWC_Global_Import` | Native JSON import/export of products + options. |
| `class-global-import-admin.php` | `SPBWC_Global_Import_Admin` | Admin UI for global import. |
| `class-global-import-controller.php` | `SPBWC_Global_Import_Controller` | AJAX/REST endpoints for global import workflow. |
| `class-printcart-import-adapter.php` | `SPBWC_PrintCart_Import_Adapter` | Map legacy PrintCart/`nbdesigner_*` data into SPBWC schema. |
| `class-printcart-import-schema.php` | `SPBWC_PrintCart_Import_Schema` | Create legacy `wp_nbdesigner_*` tables when needed. |
| `class-product-exporter.php` | `SPBWC_Product_Exporter` | Export a single product reference (settings + options + featured image) as a ZIP. |
| `class-util.php` | `SPBWC_Util` | Template loader, admin notices, WPML page resolution, misc helpers. |

### 2.3 Constants

Defined in the bootstrap file:

- `SPBWC_PB_VERSION` = `1.2.6`, `SPBWC_PB_NUMBER_VERSION` = `126`
- Paths: `SPBWC_PB_PLUGIN_URL/DIR`, `SPBWC_PB_DATA_DIR/URL` (`wp-uploads/storelly-product-builder`), `SPBWC_PB_CUSTOMER_DIR/URL` (designs), `SPBWC_PB_UPLOAD_DIR/URL`, `SPBWC_PB_FONT_DIR/URL`
- Assets: `SPBWC_PB_ASSETS_*`, `SPBWC_PB_JS_URL`, `SPBWC_PB_CSS_URL`, `SPBWC_PB_DATA_CONFIG_*`
- API: `SPBWC_API_URL` = `https://app.storelly.com`
- Feature flag: `SPBWC_ENABLE_NONCE` = `TRUE`
- Menu slugs: `SPBWC_PB_OPTIONS_SLUG`, `_BUILDER_SLUG`, `_PRODUCTS_SLUG`, `_ORDERS_SLUG`, `_QUOTES_SLUG`, `_LICENSE_SLUG`, `_OVERVIEW_SLUG`

---

## 3. Domain Model

### 3.1 Entities

| Entity | Storage | Notes |
|--------|---------|-------|
| **Pricing Option** | Custom table `wp_storelly_product_builder_options` | A reusable group of customizable fields linked to one or more products / categories. |
| **Field** | JSON serialized in the `fields` column of a Pricing Option | Typed (dropdown / radio / swatch / input / advanced-dropdown / label / xlabel) with attributes, price, validation. |
| **Product** | Standard `wp_posts` (`product`) | Linked to a Pricing Option via post meta `_nbdesigner_option`. |
| **Customer Design** | Folder under `wp-uploads/storelly-product-builder/designs/{folder_key}/` | Files: `config.json`, `design_output.json`, `used_font.json`, `frame_{n}_svg.svg`, generated PDFs in `customer-pdfs/`. |
| **Cart/Order Item** | WC native + line-item meta | Meta keys: `_pcpb_folder`, `_pcpb_options`, `_pcpb_field`, `_pcpb_option_price`, `_pcpb_original_price` (see §5.3). |
| **Quote** | WC order with custom statuses (`wc-quote-pending`, `wc-quote-accepted`, …) | Created via Request-Quote form. |
| **License** | `wp_options` → `spbwc_license_data` | Cached tier, limits, sync timestamp. |
| **API Keys** | `wp_options` → `spbwc_connect_api_keys` | `consumer_key`, `consumer_secret` (WC REST), `unauth_token` (Storelly), `business_id`. |

### 3.2 Custom Table — `wp_storelly_product_builder_options`

Created via `dbDelta` in `class-admin-options.php:347` whenever `SPBWC_PB_VERSION` differs from the stored `spbwc_version_plugin` option. Columns:

- `id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `title` TEXT NOT NULL
- `published` TINYINT(1) NOT NULL DEFAULT 1
- `product_ids` TEXT NULL — serialized array of WC product IDs
- `apply_for` VARCHAR(10) NOT NULL DEFAULT `'p'` — scope (`p` = products, `c` = categories)
- `product_cats` TEXT NULL — serialized array of category term IDs
- `created`, `modified` DATETIME NOT NULL
- `created_by`, `modified_by` BIGINT(20) NULL — user IDs
- `fields` LONGTEXT — serialized JSON of field definitions
- `builder` TEXT NULL — builder configuration blob

Legacy compatibility: `class-printcart-import-schema.php` may create `wp_nbdesigner_options`, `wp_nbdesigner_templates`, `wp_nbdesigner_mydesigns`, `wp_nbdesigner_user_designs` if a PrintCart import is run.

### 3.3 Design Folder Layout

```
wp-uploads/storelly-product-builder/
├── designs/{folder_key}/
│   ├── config.json              # frame/page layout
│   ├── design_output.json       # DPI, dimensions, render state
│   ├── used_font.json           # font list (for PDF rendering)
│   ├── frame_{n}_svg.svg        # per-frame SVG artwork
│   ├── thumbnail.png            # preview
│   └── customer-pdfs/           # PDFs returned by Cloud2Print
├── uploads/                     # customer-uploaded raw assets
└── fonts/                       # custom-installed fonts
```

`{folder_key}` is derived from an MD5 hash bound to the cart/build session and persisted as the `_pcpb_folder` order-item meta value.

---

## 4. Admin UI

### 4.1 Menu Structure

Top-level menu **Storelly Product Builder** with submenus:

| Slug constant | Page | View file |
|---------------|------|-----------|
| `SPBWC_PB_OVERVIEW_SLUG` | Overview / dashboard | `views/overview.php` |
| `SPBWC_PB_OPTIONS_SLUG` | Settings | `views/menu-settings.php`, `views/manager-fonts.php` |
| `SPBWC_PB_BUILDER_SLUG` | Pricing Options | `views/options/options-list-table.php`, `views/options/edit-option.php` |
| `SPBWC_PB_PRODUCTS_SLUG` | Products (linkage) | `views/product-builder/index.php` |
| `SPBWC_PB_ORDERS_SLUG` | Orders w/ designs | `views/box-order-metadata.php` |
| `SPBWC_PB_QUOTES_SLUG` | Quotes | (handled by `class-request-quote.php`) |
| `SPBWC_PB_LICENSE_SLUG` | License | `views/license.php` |

### 4.2 Meta Boxes

- **Product editor**: pricing-option selector (assigns a Pricing Option to the product, written to `_nbdesigner_option`).
- **Order editor**: design preview + download (`views/box-order-metadata.php`).

### 4.3 Field Editor

`views/options/edit-option.php` composes the editor from partials in `views/options/templates/`:

- Field body: `title`, `description`, `enabled`, `published`, `required`, `data_type`, `input_type`, `price`, `price_type`, `attributes`, `input_option`, `text_option`, `upload_option`.
- Field templates by type: `nbpb_text.php`, `nbpb_com.php`, `nbpb_image.php`.

### 4.4 Capabilities

- `manage_woocommerce` — settings pages.
- `spbwc_manage_product_builder` — custom cap, granted to administrators on activation.
- `upload_files` — for media-related AJAX.
- `edit_user` — for API key generation.

---

## 5. Frontend Product Builder

End-to-end flow on a designable WooCommerce product page: the plugin (1) renders the pricing-option fields and, where applicable, the Fabric.js design canvas; (2) captures the customer's selections and uploads; (3) persists a *design folder* on disk; (4) attaches a reference to that folder plus the option payload to the cart line item; (5) materialises the same data as order-item meta at checkout.

### 5.1 Rendering

Hook: `woocommerce_before_single_product`. If the product is linked to a Pricing Option (`_nbdesigner_option` meta), the plugin renders:

1. Pricing-option fields via `templates/single-product/option-builder.php` → per-type partials in `templates/single-product/options-builder/`:
   - `dropdown.php`, `radio.php`, `swatch.php`, `input.php`, `advanced-dropdown.php`, `label.php`, `xlabel.php`, `field-header.php`.
2. The full visual builder canvas (Fabric.js) when the product is configured as a "designable" item — markup from `views/product-builder/`.

### 5.2 Field Types

| Type | Behavior |
|------|----------|
| `dropdown` | `<select>` with options; each option may carry a price modifier and a product image swap (`change_image_product`). |
| `radio` | Radio inputs with optional color/swatch preview. |
| `swatch` | Color or texture swatch picker. |
| `input` | Free-text input; supports validation (email, regex) and per-character or fixed surcharge. |
| `advanced-dropdown` | Dropdown with conditional logic (show/hide other fields). |
| `label` | Read-only label. |
| `xlabel` | Extended label with rich content. |

Field properties (per `fields` JSON entry):

- `general`: `type`, `title`, `attributes[]`, `required`, `enabled`, `price`, `price_type` (`fixed` / `percentage`).
- `appearance`: styling, `change_image_product` (attachment ID swap on selection).
- `validation`: regex, email, min/max.

### 5.3 Cart & Order Integration

`class-frontend-options.php` wires:

- `woocommerce_add_to_cart_validation` → verify nonce `spbwc_add_to_cart_nonce`, required fields.
- `woocommerce_add_cart_item_data` → attach selected option payload + design folder to the cart item.
- `woocommerce_get_item_data` → display selections in cart/checkout.
- `woocommerce_checkout_create_order_line_item` → persist the order-item meta keys (`class-frontend-options.php:947-951`):
  - `_pcpb_folder` — design folder key.
  - `_pcpb_options` — serialized selected field values (`wp_slash`-escaped JSON).
  - `_pcpb_field` — option/field id for the line item.
  - `_pcpb_option_price` — surcharge applied by the options.
  - `_pcpb_original_price` — base product price before surcharges.
- `woocommerce_cart_calculate_fees` → apply price modifiers (fixed/percentage).
- `woocommerce_order_item_meta_end` and `woocommerce_spbwc_admin_order_item_thumbnail` → render design thumbnail in order views.

### 5.4 Saving a Design

AJAX `wp_ajax_(nopriv_)spbwc_save_product_builder_design`:

1. Verify nonce `spbwc_save_design_action`.
2. Accept multipart files: `design` (preview), `config` (config.json), `used_font`, `design_output`, `frame_{n}` (per view in config.json).
3. Validate each upload via `spbwc_sanitize_file_upload()` (see §11.4) — MIME + content checks for JSON / SVG / image.
4. Write to `SPBWC_PB_CUSTOMER_DIR/{key}/`.
5. Generate a thumbnail preview.
6. Return the folder key, which the frontend stores so it can be sent on add-to-cart.

Customer asset upload: AJAX `spbwc_customer_upload` — same nonce, restricted MIME types (`image/jpeg`, `image/png`, `image/gif`, `image/svg+xml`), processed with `wp_handle_sideload`.

### 5.5 JavaScript Architecture

Frontend scripts are enqueued in `class-script-hook.php`. Key modules under `static/js/`:

- `app-product-builder.js` — Fabric.js canvas editor (SVG layers, fonts, export).
- `option-builder.js` — renders and reacts to Pricing Option field changes on the product page; drives price recalculation.
- `storelly-general.js` — shared frontend utilities.
- `admin-options.js` — admin Pricing Option editor (field CRUD, conditional logic).
- `global-import-app.js` — global import workflow UI.
- `manager-fonts.js` — admin font manager.

Vendor libraries are split across `static/libs/` (Fabric.js, Snap.svg, FontFaceObserver, SweetAlert, builderproductag) and `static/js/` (Spectrum.js, jQuery TipTip).

---

## 6. AJAX & REST Surface

### 6.1 Frontend AJAX (logged-out callable)

| Action | Nonce | Purpose |
|--------|-------|---------|
| `spbwc_save_product_builder_design` | `spbwc_save_design_action` | Save design files. |
| `spbwc_customer_upload` | `spbwc_save_design_action` | Upload customer asset. |
| `spbwc_submit_quote` | `spbwc_submit_quote_action` | Submit quote form. |

### 6.2 Admin AJAX

| Action | Required cap |
|--------|--------------|
| `spbwc_download_option_image` | `manage_woocommerce` |
| `spbwc_get_media_full_size_url` | `upload_files` |
| `spbwc_add_google_font` | `manage_woocommerce` |
| `spbwc_download_order_designs` | `manage_woocommerce` |
| `spbwc_license_activate` | `manage_woocommerce` |
| `spbwc_license_sync` | `manage_woocommerce` |
| `spbwc_export_product_reference` | `manage_woocommerce` |
| `spbwc_global_import_upload` | `manage_woocommerce` |
| `spbwc_global_import_list` | `manage_woocommerce` |
| `spbwc_global_import_row_ids` | `manage_woocommerce` |
| `spbwc_global_import_run` | `manage_woocommerce` |
| `spbwc_global_import_log` | `manage_woocommerce` |

### 6.3 REST API

`class-global-import.php` registers REST routes for the global import workflow: upload, list manifests, trigger import, fetch logs.

### 6.4 Hooks for Integrators

The plugin exposes its own actions and filters so themes/other plugins can extend behavior.

**Filters** (in `class-frontend-options.php`):

| Filter | Purpose |
|--------|---------|
| `storelly_adjusted_price` | Override the price applied to a customized cart item. |
| `storelly_need_change_cart_item_price` | Decide whether to overwrite the cart item price. |
| `storelly_cart_item_thumbnail` | Replace the cart/order item thumbnail with a design preview. |
| `storelly_cart_item_name` | Override the cart item display name. |
| `storelly_show_edit_option_link_in_cart` | Show/hide the "Edit options" link in the cart. |
| `storelly_redirect_url` | Override the post-add-to-cart redirect URL. |
| `spbwc_locate_template` | Template override resolver — themes may ship overrides in `pc-product-builder/` under the theme. |

**Actions**:

| Action | Fired when |
|--------|-----------|
| `spbwc_pb_menu` | Inside the admin menu registration — add custom submenus. |
| `spbwc_before_save_product_builder_design` | Just before customer design files are written. |
| `spbwc_after_save_product_builder_design` | After customer design files are written. |
| `spbwc_global_import_product_saved` | After each product is created/updated during global import. |
| `spbwc_create_tables` | During install/upgrade — extension point for adding custom tables. |
| `spbwc_init_files_and_folders` | During install/upgrade — extension point for creating extra directories. |
| `spbwc_enqueue_script_custom` | After plugin assets are enqueued — extension point for additional scripts. |

---

## 7. External Integrations

### 7.1 Cloud2Print PDF API

- Base: `https://api.cloud2print.net`
- Trigger: admin export of an order's design, or post-order sync to Storelly.
- Workflow (`class-export-pdf.php:236-282`):
  1. Materialise a standalone HTML page under `{design_folder}/pdf-templates/{key}.html` containing the design SVG, Google Font `@font-face` rules for the fonts in `used_font.json`, and an optional background.
  2. Build a Cloud2Print request URL combining the URL-encoded public HTML URL with a base64-encoded JSON of page dimensions / DPI.
  3. Cloud2Print fetches the HTML over HTTPS, renders, and returns the PDF binary.
  4. Plugin writes the PDF to `{design_folder}/customer-pdfs/`.
- Cleanup of `pdf-templates/` is not automatic; entries accumulate until manually removed.
- The WP site must be reachable from the public internet for Cloud2Print to fetch the HTML.

### 7.2 Storelly Dashboard API

- Base: `https://app.storelly.com` (`SPBWC_API_URL`).
- Auth: HTTP header `X-STORLY: {unauth_token}` (`class-http.php`).
- Opt-in: only invoked when `spbwc_pb_settings[enable_api_sync] === 'yes'`.
- Endpoints used:
  - `POST /public/register` — register/connect store, returns `unauth_token`, `business_id`.
  - `POST /api/v1/update-orders` — push order data (totals, items, design PDF URLs) on new/processed orders.
  - License: `GET /api/v1/license/status`, `POST /api/v1/license/activate`.

### 7.3 Google Fonts

- Admin UI loads Poppins from `https://fonts.googleapis.com`.
- The font manager (`views/manager-fonts.php`) lets admins add fonts; list source: `storage/google-fonts-ttf.json`.

### 7.4 WooCommerce Hooks Consumed

**Frontend / cart:**
- `woocommerce_before_single_product` — render builder UI.
- `woocommerce_add_to_cart_validation` — verify nonce and required fields.
- `woocommerce_add_cart_item_data` — attach option payload to cart item.
- `woocommerce_get_item_data` — display selections in cart/checkout.
- `woocommerce_checkout_create_order_line_item` — persist order-item meta.
- `woocommerce_cart_calculate_fees` — apply option-based surcharges.
- `woocommerce_order_item_meta_end` — display design preview in cart/order views.

**Order lifecycle (for Storelly sync):**
- `woocommerce_order_status_*` — status transitions.
- `woocommerce_thankyou` — post-checkout confirmation.
- `woocommerce_new_order` — initial order creation.

**Compatibility:** HPOS (`custom_order_tables`) declared compatible at bootstrap.

### 7.5 PrintCart Import

`class-printcart-import-adapter.php` + `class-printcart-import-schema.php` accept legacy PrintCart configurations, optionally re-create `wp_nbdesigner_*` tables, download remote asset images, attach them as WP media, and map fields/options into the SPBWC schema.

---

## 8. License System

Implemented in `class-license-manager.php`. Storage: `wp_options.spbwc_license_data`.

| Tier | Pricing Options | Products | Orders/month |
|------|-----------------|----------|--------------|
| Free | 3 | 5 | 50 |
| Premium | Driven by Storelly Dashboard | … | … |

Operations:

- `SPBWC_License_Manager::get_current_license()` — returns cached license (15-minute object cache TTL), falls back to default free tier.
- `SPBWC_License_Manager::activate_key($key)` — POST `business_id` + `key` to Storelly; on success calls `sync_from_api()`.
- `SPBWC_License_Manager::sync_from_api()` — GET license status, overwrite `spbwc_license_data`.
- Enforcement: admin UI gates new Pricing Option creation when the free product limit is reached.

### 8.1 Caching Strategy

Beyond the license cache, the plugin maintains several other caches:

- Object cache `spbwc_published_options` — published Pricing Options (15-minute TTL).
- Transient `spbwc_product_builder_{product_id}` — per-product → Pricing Option linkage.
- Transient `spbwc_packages` — Storelly package catalogue (1-hour TTL).
- Transient `spbwc_overview_stats` — admin overview stats (1-hour TTL).

Whenever a Pricing Option is saved, all `_transient_spbwc_product_builder_%` rows are bulk-deleted to invalidate stale linkage caches.

---

## 9. Import / Export

### 9.1 Native Global Import (`class-global-import*.php`)

- Export bundle: JSON manifest `{ manifest: { import_id, export_date, source_file, version, site_url }, products: [...] }`.
- Storage directory: `wp-uploads/global-import-exports/` with hardened `.htaccess` and `index.php`.
- Retention: max 50 bundles, oldest pruned.
- Workflow:
  1. Upload bundle (`spbwc_global_import_upload`).
  2. List bundles (`spbwc_global_import_list`) and rows (`spbwc_global_import_row_ids`).
  3. Run import (`spbwc_global_import_run`) — creates products + linked Pricing Options.
  4. Fetch logs (`spbwc_global_import_log`).
- Logs are appended line-by-line to `wp-uploads/global-import-exports/logs/{job_id}.log` while a job runs; the AJAX log endpoint streams the latest lines back to the admin UI.

### 9.2 Product Exporter (`class-product-exporter.php`)

Per product, AJAX `spbwc_export_product_reference` produces a ZIP containing:

- `settings.txt` — serialized product meta.
- `print_options.txt` — serialized Pricing Option configuration.
- Featured image (re-uploaded).

### 9.3 PrintCart Adapter

Used for migrations from the legacy nbdesigner/PrintCart plugin family — see §7.5.

---

## 10. Request a Quote

`class-request-quote.php`:

- Registers custom WC order statuses (`wc-quote-pending`, `wc-quote-accepted`, etc.) via `register_post_status` + `wc_order_statuses` filter.
- Renders a "Get Quote" CTA + modal on the product page (first name, last name, email, phone, message, quantity). Form fields stored in option `spbwc_quote_form_fields`.
- AJAX `spbwc_submit_quote`: nonce-validated, creates a WC order with `wc-quote-pending` status.
- Adds a **Quotes** endpoint to *My Account* (account menu item + endpoint registration) listing the customer's quotes and a quote detail page.
- **Email notifications** (`class-request-quote.php:275-299`): on submission and on status change (accepted/rejected) the plugin sends plain-text emails via `wp_mail()` — one to the admin recipient configured under `spbwc_quote_settings['admin_email']` (falls back to the site's `admin_email`), and one to the customer if an address was supplied. Bodies are hard-coded; only the admin recipient is configurable.

---

## 11. Security Posture

### 11.1 Nonces (`SPBWC_ENABLE_NONCE = TRUE`)

| Action | Nonce |
|--------|-------|
| Save design | `spbwc_save_design_action` |
| Add to cart with options | `spbwc_add_to_cart_nonce` / `spbwc_add_to_cart_action` |
| Submit quote | `spbwc_submit_quote_action` |
| Edit cart item | `nbo-edit` |
| Media URL fetch | `spbwc_save_design_action` |

### 11.2 Capability Checks

`current_user_can('manage_woocommerce' | 'spbwc_manage_product_builder' | 'upload_files' | 'edit_user')` on every admin-only AJAX/REST handler.

### 11.3 Sanitization

- Global: `spbwc_sanitize_recursive()` applied to POST payloads.
- Strings: `sanitize_text_field`, `sanitize_file_name`, `sanitize_email`.
- Integers: `absint`.
- Output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- SQL: `$wpdb->prepare()`.

### 11.4 File-upload Validation

- `wp_check_filetype_and_ext()` + explicit MIME allowlist for design/customer uploads.
- `is_uploaded_file()` precondition.
- `wp_handle_sideload()` to place files.
- JSON / SVG content validation in `spbwc_sanitize_file_upload()`.
- `basename()` used on user-supplied paths to mitigate directory traversal.

### 11.5 Directory Hardening

Generated directories (designs, import bundles, uploads) include `index.php` and `.htaccess` to block listing/direct execution.

---

## 12. Tech Stack & Dependencies

### 12.1 Server

- PHP ≥ 7.0
- WordPress ≥ 4.7
- WooCommerce ≥ 6.0.0 (tested up to 6.9.4); HPOS-compatible
- MySQL / MariaDB (custom tables + WC standard tables)
- WPML-friendly (language-aware page lookups in `class-util.php`)

### 12.2 JavaScript Libraries

Loaded by `class-script-hook.php`. Vendor assets split between `static/libs/` and `static/js/`/`static/css/`:

- **Fabric.js** (`static/libs/`) — canvas-based design editor.
- **Snap.svg 0.3.0** (`static/libs/`) — SVG manipulation.
- **FontFaceObserver** (`static/libs/`) — async web-font loading detection.
- **SweetAlert** (`static/libs/`) — UI notifications.
- **builderproductag** (`static/libs/`) — internal tag helper.
- **Spectrum.js / Spectrum.css** (`static/js/`, `static/css/`) — color picker.
- **jQuery TipTip** (`static/js/`) — tooltips.
- **Animate.css**, **normalize.css** — styling.
- **FPDI** — listed in readme third-party credits; **not** currently `require`'d by any PHP file in `includes/`. PDF generation goes through Cloud2Print (see §7.1).

### 12.3 External Services

- `https://api.cloud2print.net` — HTML→PDF rendering.
- `https://app.storelly.com` — account / order sync / license.
- `https://fonts.googleapis.com` — admin font loading.

---

## 13. Settings & Options Reference

WordPress option keys created/used by the plugin:

| Option key | Purpose |
|------------|---------|
| `spbwc_pb_settings` | Main settings array (`enable_api_sync`, …). |
| `spbwc_connect_api_keys` | WC REST keys + Storelly `unauth_token`, `business_id`. |
| `spbwc_license_data` | Cached license tier + limits. |
| `spbwc_quote_form_fields` | Quote form field definitions. |
| `spbwc_quote_settings` | Quote workflow settings (admin recipient, etc.). |
| `spbwc_version_plugin` | Installed plugin version — drives schema/install upgrade checks. |
| `spbwc_number_of_decimals` | Override for price decimal places (falls back to WC setting). |
| `spbwc_hide_add_cart_until_form_filled` | Hide *Add to cart* until required options are filled. |
| `spbwc_product_builder_page_id` | Post ID of the builder landing page created on install. |
| `spbwc_global_import_sessions` | Tracks in-flight global-import job state. |

---

## 14. Lifecycle Events

### 14.1 Activation

1. Verify WooCommerce is active (else `wp_die`).
2. Create custom table `wp_storelly_product_builder_options`.
3. Create data directories (`designs/`, `uploads/`, `fonts/`) with hardened index/htaccess.
4. Grant `spbwc_manage_product_builder` capability to administrators.
5. Generate WC REST keys (`SPBWC_Storelly_Product_Builder_API::spbwc_generate_key`) and optionally register the store on Storelly.

### 14.2 Upgrade

On every admin load, `class-admin-options.php` compares `SPBWC_PB_VERSION` against the stored `spbwc_version_plugin` option. When they differ, `dbDelta` is re-run against the `wp_storelly_product_builder_options` schema (`class-admin-options.php:345-364`) and the `spbwc_create_tables` / `spbwc_init_files_and_folders` action hooks fire so other modules can ensure their own tables and data directories exist. `class-install.php` itself currently holds bootstrap helpers (page creation, capability assignment) rather than versioned migrations.

### 14.3 Order Processing

1. Customer adds customized product → cart item carries `_pcpb_folder` + `_pcpb_item_pb_key`.
2. On checkout, line-item meta is persisted.
3. Order status transitions trigger `spbwc_notify_on_new_order()` → builds payload (totals, line items, design PDFs) → `POST /api/v1/update-orders` on Storelly (only if `enable_api_sync = yes`).
4. Admin downloads design or triggers PDF export → Cloud2Print generates the PDF, which is saved under the design folder and surfaced in the admin order view.

---

## 15. Known Limitations & Notes

- The free tier hard-caps customizable products to 5 (enforced via license cache).
- Cloud2Print expects the WP site to be reachable from the public internet (it fetches the design HTML over HTTPS).
- Some legacy code paths still reference the `nbdesigner_*` schema for backward compatibility with PrintCart migrations.
- HPOS is declared compatible but custom order-item meta still uses legacy `_pcpb_*` keys.
- The plugin does **not** register custom post types, taxonomies, shortcodes, or cron events — it relies entirely on WooCommerce's native product/order entities extended with post meta and the custom Pricing Options table.

---

## 16. Glossary

- **Pricing Option** — a group of customizable fields, reusable across products.
- **Field** — one configurable input within a Pricing Option (dropdown, radio, …).
- **Design** — the customer's saved customization for one cart line item, materialised as a folder of SVG + JSON files under `wp-uploads/storelly-product-builder/designs/{folder_key}/`.
- **Folder key** — the MD5-derived identifier of a Design folder; stored as the `_pcpb_folder` order-item meta value.
- **Frame** — one view/page of a design (front, back, …).
- **`_pcpb_folder`** — order-item meta carrying the Folder key.
- **`_pcpb_options`** — order-item meta carrying the serialized selected field values.
- **Unauth Token** — opaque Storelly token used as `X-STORLY` header for dashboard sync.
- **HPOS** — WooCommerce High-Performance Order Storage (custom order tables).
