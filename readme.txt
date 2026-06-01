=== Storelly Product Builder for WooCommerce ===
Contributors: storelly
Donate link: https://storelly.com/
Tags: product builder, product customize, product customizer, woocommerce custom product
Requires at least: 4.7
Tested up to: 7.0
Stable tag: 1.3.4
Version: 1.3.4
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Storelly Product Builder allows customers to configure and personalize products. Ideal for customizable or made-to-order items.

== Description ==

Storelly Product Builder for WooCommerce is a visual product customization tool that allows customers to build products step-by-step on the front end of your website.

Customers can select different attributes such as size, color, material, text, layers, and other specifications to create a personalized product. Once finished, the customized product is added to the WooCommerce cart and processed like any normal order.

This plugin is especially useful for businesses offering:
- Customized or made-to-order products  
- Print-on-demand services  
- Layer-based product configuration  
- Digital or physical product personalization  

### Key Features

- **Front-end product builder:** Customers can design and customize products visually.
- **WooCommerce integration:** Compatible with the latest WooCommerce versions.
- **Easy to use:** Simple interface suitable for both store owners and customers.
- **Free version limitation:** The free version allows creating or modifying up to **five customizable products**. You may upgrade to the premium version for unlimited usage:
https://storelly.com/product-builder

### Languages

Storelly Product Builder ships with translations for **15 languages** out of the box and follows your WordPress Site Language automatically — no extra setup required:

- **Extended coverage (200+ strings):** Vietnamese (vi)
- **Menu labels + core admin verbs (~30 strings):** French (fr_FR), German (de_DE), Spanish (es_ES), Portuguese-Brazil (pt_BR), Italian (it_IT), Japanese (ja), Chinese-Simplified (zh_CN), Russian (ru_RU), Arabic (ar), Dutch (nl_NL), Polish (pl_PL), Turkish (tr_TR), Swedish (sv_SE), Indonesian (id_ID)

**RTL languages** (Arabic, Hebrew) automatically render with right-to-left layout — 18 companion RTL stylesheets ship with the plugin.

**Want to help translate?** Contribute on translate.wordpress.org once the plugin lands there, or send your `.po` file to support@storelly.com.

== Frequently Asked Questions ==

= How can I contact support? =
You can reach the Storelly team via email: support@storelly.com

= How do I use this plugin in my language? =

Go to **Settings → General → Site Language**, pick your language, and save. The plugin admin sidebar, settings pages and Visual Builder will switch to that language immediately. No restart, no cache clearing, no extra plugin needed.

Supported languages: Vietnamese, French, German, Spanish, Portuguese (Brazil), Italian, Japanese, Chinese (Simplified), Russian, Arabic, Dutch, Polish, Turkish, Swedish, Indonesian. If your language is not in WordPress's installed list, install it from the same Settings → General page (WordPress will download it).

= Can each WP user have a different plugin language? =

Yes. Go to **Users → Your Profile → Language** and pick a per-user language different from the site default — useful when the storefront serves customers in one language but the admin reads another.

= My language is missing or incomplete — how do I improve it? =

Two paths:

1. **Quick (just you):** Translate the `.pot` file in `wp-content/plugins/storelly-product-builder-for-woocommerce/languages/` using a tool like Poedit, save as `storelly-product-builder-for-woocommerce-{your-locale}.mo`, and drop it in the same folder.
2. **Permanent (helps everyone):** Submit your translation to the WordPress.org GlotPress project once the plugin is listed there, or email `.po` files to support@storelly.com — we bundle community translations in the next release.

= Does RTL (Arabic, Hebrew) work? =

Yes. The plugin ships 18 companion `*-rtl.css` files that WordPress core auto-loads when `is_rtl()` returns true. Switch your site to Arabic / Hebrew and the entire plugin UI mirrors right-to-left.

= What third-party resources does this plugin use? =

This plugin includes or depends on the following open-source libraries:

- Animate.css — MIT License (https://github.com/animate-css/animate.css)
- normalize.css v8.0.1 — MIT License (https://github.com/necolas/normalize.css)
- Snap.svg 0.3.0 — Apache License 2.0 (https://github.com/adobe-webplatform/Snap.svg)
- FPDI — MIT License (http://www.setasign.com/products/fpdi/about/)
- fontfaceobserver.js — BSD License (https://github.com/bramstein/fontfaceobserver)
- spectrum.js — MIT License (https://github.com/bgrins/spectrum)
- fabric.js — MIT License (https://github.com/fabricjs/fabric.js)
- SweetAlert — MIT License (https://github.com/t4t5/sweetalert)

== External services ==

This plugin connects to the following external services:

- **Cloud2Print PDF generation API (`https://api.cloud2print.net`)**  
  - **What it is used for**: Used to generate print‑ready PDF files from customers' product designs created with the builder.  
  - **What data is sent and when**: When a store admin or automated process exports a design to PDF, the plugin builds a temporary HTML representation of the design (including product artwork, layout information and font usage) and sends a request to the Cloud2Print API, which then fetches that HTML from your site in order to render and return the PDF file.  
  - **Service owner and policies**: This service is provided by Cloud2Print. Please review Cloud2Print's policies:
    - Privacy Policy: http://cloud2print.net/privacy-policy
    - Terms of Service: http://cloud2print.net/terms-of-service

- **Storelly Dashboard API (`https://app.storelly.com/public`)**  
  - **What it is used for**: Used to register your Storelly account from inside WooCommerce and to synchronise WooCommerce order information with the Storelly Dashboard.  
  - **What data is sent and when**:  
    - On first activation/initialisation, the plugin can create or connect a Storelly account by sending your store owner details (name, email address, billing address fields, time zone and WooCommerce API keys) to the Storelly Dashboard API.  
    - When an order is placed or processed in WooCommerce, the plugin can send order data (order totals, discount amounts, product and variation identifiers, quantities, unit prices and links to the generated design PDF files) to the Storelly Dashboard API so that orders can be tracked and managed there.  
  - **Service owner and policies**: This service is operated by Storelly. Please review policies:
    - Terms of Service: https://app.storelly.com/terms
    - Privacy Policy: https://app.storelly.com/privacy

- **Storelly demo product data (`https://app.storelly.com/product-data/data/data.json`)**
  - **What it is used for**: Used by the admin "Global Import" / demo product importer screen to fetch a sample catalogue (`data.json`) so the store owner can preview and import demo products into WooCommerce.
  - **What data is sent and when**: When a logged-in administrator opens the Global Import page or clicks "Import demo products", the plugin issues an anonymous `GET` request to the URL above. No site URL, user account information, order data or PII is sent in the request body; the only identifying information is the standard HTTP headers (User-Agent, IP) that any outbound HTTP request includes.
  - **Service owner and policies**: This service is operated by Storelly. Please review policies:
    - Terms of Service: https://app.storelly.com/terms
    - Privacy Policy: https://app.storelly.com/privacy

- **Vue.js via unpkg CDN (`https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js`)**
  - **What it is used for**: The Vue.js 3 runtime is loaded from the public unpkg CDN to power the admin "Global Import" screen (`includes/class-global-import-admin.php`).
  - **What data is sent and when**: When an administrator opens the Global Import admin page, their browser requests the Vue.js script from unpkg. This is a standard anonymous browser asset request — no user account information, order data, or PII is sent by the plugin. Only the request headers normally included by the browser (User-Agent, IP, Referer) are visible to the CDN.
  - **Service owner and policies**: unpkg is a public open-source CDN operated by Cloudflare on behalf of the unpkg project. Please review policies:
    - unpkg: https://unpkg.com/
    - Vue.js (project home): https://vuejs.org/

- **Google Fonts API (`https://fonts.googleapis.com`)**
  - **What it is used for**: Used to load custom web fonts for the admin interface styling.  
  - **What data is sent and when**: When admin users access the plugin settings pages, their browser automatically requests font files (Poppins font family) from Google's CDN. This is a standard browser request that may include the user's IP address and browser information as part of normal HTTP headers.  
  - **Service owner and policies**: This service is provided by Google LLC. Please review Google Fonts policies:
    - Privacy Policy: https://policies.google.com/privacy
    - Terms of Service: https://policies.google.com/terms

**Note about local file operations**: The plugin reads and writes design configuration files (config.json, design_output.json, used_font.json) to your server's local file system in the WordPress uploads directory. These are not external service calls.

== Screenshots ==

1. Create new option screen for product builder fields
2. Google Fonts manager for selecting admin fonts
3. Storelly settings page with API keys and sync options

== Changelog ==
= 1.3.4 =
* Customizer V3 polish: the "Add to cart" CTA now renders with white text on the blue gradient even when the active theme applies its own button colour. Sale-price markup is normalized inside the customizer so on-sale products no longer render two stacked prices in the topbar / summary. The "YOUR PRICE" row is now a tinted hero block so customers can see the live total at a glance. The vertical tab-nav "coming soon" indicator is now a small dot instead of a clipped text badge.

= 1.3.3 =
* Customizer V3: aligned the modal colours, spacing, and shadows with the official `_tokens.css` design system (`--st-*` / `--nbd-st-*`). The self-contained `--spbwc-c-*` tokens are now aliases that resolve to the official tokens — so when site owners theme `_tokens.css`, the customizer follows automatically.

= 1.3.2 =
* Customizer V3 — major UX overhaul: new 4-zone layout (top product header, vertical tab nav, accordion options panel, canvas, right summary column). Tabs are future-proof — placeholder tabs for "Design with AI", "Templates", and "Help & FAQ" are already wired up.
* Live grand total in the Summary column: base product price + every picked option upcharge recompute in real time as customers change selections. The "Add to cart" CTA shows the live total inline.
* Per-component reset link inside each accordion item, plus a global "Reset all" button in both the topbar and the Summary column.
* Progress meter (e.g. "2 / 5 configured") so customers can see how far through the build they are.
* The two earlier layouts remain available as fallback (`views/product-builder/wrapper-v2-1.php` and `wrapper-legacy.php`) via the `spbwc_use_legacy_customizer` filter or the `SPBWC_USE_LEGACY_CUSTOMIZER` constant.

= 1.3.1 =
* Customizer (designer modal): rebuilt with a Cloodo-style 3-column layout — the parts list now stays visible on the left while options appear on the right, so customers no longer lose context when picking a part. Each option choice now shows its upcharge price (or "Free") as a coloured pill, formatted with the store currency.
* Customizer footer reworked: the confusing "Done" button is now a primary "Save & continue" CTA next to a "Cancel" affordance.
* Compatibility/rollback: the legacy single-pane customizer is preserved at `views/product-builder/wrapper-legacy.php` and can be re-enabled via the `spbwc_use_legacy_customizer` filter or the `SPBWC_USE_LEGACY_CUSTOMIZER` constant.

= 1.3.0 =
* Template Library: WYSIWYG live preview — the "Preview" tab now renders the real Cloodo storefront (same option-builder template, same storefront CSS/JS) inside a sandboxed iframe, so any change to the buyer-facing UI flows through automatically. No parallel mockup, no drift.
* Template Library preview UX: debounced "Sample base price" with currency-symbol affordance and per-merchant localStorage persistence; subtle in-place "Updating…" pill instead of a full-overlay flash on reloads; friendly error card with Retry on failed loads; iframe auto-grows to content height via a postMessage bridge; live "YOUR TOTAL" surfaces in the dialog subtitle ("est. $X").
* Template Library preview: new "Preview against product" picker — pick any WooCommerce product and the preview uses its real price as the base so you can see exactly what applying this template to that product would look like.
* Template Library Fields tab: replaced the flat 5-column table with a scannable card list (numbered title, Type/Required pills, description, attribute chips with overflow). About tab: 2-column metadata grid with monospace slug + version, dedicated description block, and an amber callout for the pricing-source caveat.
* Storefront option fields restyled (Cloodo): each field is now a soft card with a strong-dark title, dark "chosen value", and the price delta in a tinted brand pill. Dropdowns fill the card width with a clearer chevron and hover/focus states.
* Compliance: removed the previous mockup's "Save X%" quantity-break label that wasn't backed by any pricing engine.
* Smoke tests: tools/smoke-template-preview-render.php exercises the preview endpoint's cap + nonce + slug-lookup gates so future refactors can't silently break it.

= 1.2.7 =
* Compatibility: replace hardcoded Asia/Ho_Chi_Minh timezone with site-configured wp_timezone_string().
* Compliance: declare unpkg.com (Vue.js CDN) and Storelly demo-data endpoint in External services.
* Designer Marketplace module is bundled but disabled by default; enable via spbwc_marketplace_enabled option.

= 1.2.6 =
* Add Category-Based Options & Enhance Import Reliability

= 1.2.5 =
* Fixed Builder Options Export: Resolved an issue where export would hang indefinitely.

= 1.2.4 =
* Security improvements: Fixed nonce verification patterns, improved file upload sanitization.

= 1.2.3 =
* Enhance Storelly Settings Page with Professional Styling and API Sync.

= 1.2.2 =
* Enhance file handling and security checks.

= 1.1.2 =
* Security Updates: Nonce Verification and User Permissions.

= 1.1.1 =
* Enhance Settings Handling, Template Rendering, and Style CSS in Plugin.

= 1.1.0 =
* Enhanced Security, Caching, and Code Quality Across the Plugin.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==
= 1.2.7 =
Replaces hardcoded timezone with the site's configured timezone and declares additional external services in readme.

= 1.0.0 =
First stable public version.