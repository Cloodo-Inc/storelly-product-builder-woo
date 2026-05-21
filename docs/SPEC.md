# Storelly Product Builder for WooCommerce — Technical Specification

> **Plugin**: Storelly Product Builder for WooCommerce
> **Version**: 1.2.6
> **Requires**: WordPress ≥ 4.7, WooCommerce ≥ 6.0.0, PHP ≥ 7.0
> **Text Domain**: `storelly-product-builder-for-woocommerce`
> **Prefix**: `spbwc_` / `SPBWC_`
> **License**: GPL v2 or later

This document is a comprehensive specification of the plugin: architecture, domain model, modules, APIs, integrations, security, and storage. It is intended as a reference for maintainers, integrators, and reviewers.

---

## 1. Overview

Storelly Product Builder is a WooCommerce extension that adds a **visual, step-by-step product customization experience** to product pages. Customers select attributes (size, color, material, text, layers, uploaded artwork…) and the personalized configuration is attached to the WooCommerce cart and order. The plugin also:

- Renders pricing options (dropdown/radio/swatch/input/label/advanced-dropdown/xlabel) on the product page.
- Persists customer "designs" as a folder of SVG + JSON files in `wp-uploads/`.
- Generates print-ready PDFs through a remote Cloud2Print service.
- Syncs orders and licensing with the Storelly Dashboard (`app.storelly.com`).
- Offers product import/export (native JSON + legacy PrintCart adapter).
- Implements a "Request a Quote" workflow with custom WC order statuses.

**Free tier limit**: up to 5 customizable products. Premium tiers unlock more via the Storelly Dashboard.

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
| **Pricing Option (Field Group)** | Custom table `wp_storelly_product_builder_options` | A reusable group of customizable fields linked to one or more products / categories. |
| **Field** | JSON column inside Pricing Option | Typed (dropdown / radio / swatch / input / advanced-dropdown / label / xlabel) with attributes, price, validation. |
| **Product** | Standard `wp_posts` (`product`) | Linked to a Pricing Option via post meta `_nbdesigner_option`. |
| **Customer Design** | Folder under `wp-uploads/storelly-product-builder/designs/{folder_key}/` | Files: `config.json`, `design_output.json`, `used_font.json`, `frame_{n}_svg.svg`, generated PDFs in `customer-pdfs/`. |
| **Cart/Order Item** | WC native + line-item meta | Meta keys: `_pcpb_folder` (design folder ID), `_pcpb_item_pb_key` (build session key). |
| **Quote** | WC order with custom statuses (`wc-quote-pending`, `wc-quote-accepted`, …) | Created via Request-Quote form. |
| **License** | `wp_options` → `spbwc_license_data` | Cached tier, limits, sync timestamp. |
| **API Keys** | `wp_options` → `spbwc_connect_api_keys` | `consumer_key`, `consumer_secret` (WC REST), `unauth_token` (Storelly), `business_id`. |

### 3.2 Custom Table — `wp_storelly_product_builder_options`

Created in `class-admin-options.php` on activation. Columns (typical):

- `id` BIGINT PK
- `title` VARCHAR
- `published` TINYINT
- `apply_for` VARCHAR — scope: products / categories
- `product_ids` LONGTEXT — serialized array of WC product IDs
- `product_cats` LONGTEXT — serialized array of category term IDs
- `fields` LONGTEXT — serialized JSON of field definitions
- `created`, `modified` DATETIME
- `created_by`, `modified_by` BIGINT (user IDs)

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

`{folder_key}` is derived from an MD5 hash bound to the cart/build session and stored as `_pcpb_item_pb_key`.

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
- `woocommerce_checkout_create_order_line_item` → persist `_pcpb_folder`, `_pcpb_item_pb_key`, option selections as order item meta.
- `woocommerce_cart_calculate_fees` → apply price modifiers (fixed/percentage).
- `woocommerce_order_item_meta_end` and `woocommerce_spbwc_admin_order_item_thumbnail` → render design thumbnail in order views.

### 5.4 Saving a Design

AJAX `wp_ajax_(nopriv_)spbwc_save_product_builder_design`:

1. Verify nonce `spbwc_save_design_action`.
2. Accept multipart files: `design` (preview), `config` (config.json), `used_font`, `design_output`, `frame_{n}` (per view in config.json).
3. Validate each upload via `spbwc_sanitize_file_upload()` — MIME + content checks for JSON / SVG / image.
4. Write to `SPBWC_PB_CUSTOMER_DIR/{key}/`.
5. Generate a thumbnail preview.
6. Return the folder key, which the frontend stores so it can be sent on add-to-cart.

Customer asset upload: AJAX `spbwc_customer_upload` — same nonce, restricted MIME types (`image/jpeg`, `image/png`, `image/gif`, `image/svg+xml`), processed with `wp_handle_sideload`.

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
| `spbwc_global_import_upload` / `_list` / `_row_ids` / `_run` / `_log` | `manage_woocommerce` |

### 6.3 REST API

`class-global-import.php` registers REST routes (namespace `storelly/v1` or similar) for the global import workflow: upload, list manifests, trigger import, fetch logs.

---

## 7. External Integrations

### 7.1 Cloud2Print PDF API

- Base: `https://api.cloud2print.net`
- Trigger: admin export of an order's design, or post-order sync.
- Payload: a public HTML URL (hosted on the WP site, dynamically built from the SVG + fonts + CSS for the design) plus base64-encoded settings.
- Response: a PDF stored under the design's `customer-pdfs/` folder.

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

Front: `woocommerce_before_single_product`, `woocommerce_add_to_cart_validation`, `woocommerce_add_cart_item_data`, `woocommerce_get_item_data`, `woocommerce_checkout_create_order_line_item`, `woocommerce_cart_calculate_fees`, `woocommerce_order_item_meta_end`.

Order: `woocommerce_order_status_*`, `woocommerce_thankyou`, `woocommerce_new_order` (for Storelly sync).

HPOS: declared compatible with `custom_order_tables`.

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

Loaded by `class-script-hook.php`, sourced under `static/libs/`:

- **Fabric.js** — canvas-based design editor.
- **Snap.svg 0.3.0** — SVG manipulation.
- **Spectrum.js** — color picker.
- **FontFaceObserver** — async web-font loading detection.
- **FPDI** — PDF post-processing (referenced via readme; used by export flow).
- **Animate.css**, **normalize.css** — styling.
- **SweetAlert** — UI notifications.

### 12.3 External Services

- `https://api.cloud2print.net` — HTML→PDF rendering.
- `https://app.storelly.com` — account / order sync / license.
- `https://fonts.googleapis.com` — admin font loading.

---

## 13. Settings & Options Reference

WordPress option keys created/used by the plugin:

| Option key | Purpose |
|------------|---------|
| `spbwc_pb_settings` | Main settings array (`enable_api_sync`, decimal places, …). |
| `spbwc_connect_api_keys` | WC REST keys + Storelly `unauth_token`, `business_id`. |
| `spbwc_license_data` | Cached license tier + limits. |
| `spbwc_quote_form_fields` | Quote form field definitions. |
| `spbwc_pb_db_version` | Schema version (for migrations in `class-install.php`). |

---

## 14. Lifecycle Events

### 14.1 Activation

1. Verify WooCommerce is active (else `wp_die`).
2. Create custom table `wp_storelly_product_builder_options`.
3. Create data directories (`designs/`, `uploads/`, `fonts/`) with hardened index/htaccess.
4. Grant `spbwc_manage_product_builder` capability to administrators.
5. Generate WC REST keys (`SPBWC_Storelly_Product_Builder_API::spbwc_generate_key`) and optionally register the store on Storelly.

### 14.2 Upgrade

`class-install.php` compares `SPBWC_PB_NUMBER_VERSION` against `spbwc_pb_db_version` and runs migrations as needed.

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
- HPOS is declared compatible but custom order item meta still uses the legacy keys (`_pcpb_folder`, `_pcpb_item_pb_key`).

---

## 16. Glossary

- **Pricing Option** — a group of customizable fields, reusable across products.
- **Field** — one configurable input within a Pricing Option (dropdown, radio, …).
- **Design** — the customer's saved customization for one cart line item, materialized as a folder of SVG + JSON files.
- **Frame** — one view/page of a design (front, back, …).
- **Unauth Token** — opaque Storelly token used as `X-STORLY` header for dashboard sync.
- **HPOS** — WooCommerce High-Performance Order Storage (custom order tables).
