=== Storelly Product Builder for WooCommerce ===
Contributors: storelly
Donate link: https://storelly.com/
Tags: product builder, product customize, product customizer, woocommerce custom product
Requires at least: 4.7
Tested up to: 7.0
Stable tag: 1.6.3
Version: 1.6.3
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
- **Free & local-first:** The full product builder, pricing options, quotes and custom orders run on your own server with **no product limit**. Optional Storelly Cloud features — print‑ready PDF rendering, order sync and dashboard analytics — connect to the Storelly service and require a paid Cloud plan:
https://storelly.com/product-builder

### Languages

Storelly Product Builder ships with translations for **15 languages** out of the box and follows your WordPress Site Language automatically — no extra setup required:

- **Extended coverage (200+ strings):** Vietnamese (vi)
- **Menu labels + core admin verbs (~30 strings):** French (fr_FR), German (de_DE), Spanish (es_ES), Portuguese-Brazil (pt_BR), Italian (it_IT), Japanese (ja), Chinese-Simplified (zh_CN), Russian (ru_RU), Arabic (ar), Dutch (nl_NL), Polish (pl_PL), Turkish (tr_TR), Swedish (sv_SE), Indonesian (id_ID)

**RTL languages** (Arabic, Hebrew) automatically render with right-to-left layout — 18 companion RTL stylesheets ship with the plugin.

**Want to help translate?** Contribute on translate.wordpress.org once the plugin lands there, or send your `.po` file to support@storelly.com.

== Frequently Asked Questions ==

= How can I contact support? =
You can reach the Storelly team directly:
- Email: support@storelly.com
- WhatsApp: +84 937 869 689

(A searchable knowledge base and a support ticket system are coming soon.)

= What is "Storelly Cloud" and do I need an account? =

No account is required to use the plugin. The product builder, pricing options, quotes and custom orders all run **locally on your own WordPress site, for free, with no product limit**.

"Storelly Cloud" is a separate, optional online service (app.storelly.com). It adds print‑ready PDF rendering, order sync and a central dashboard. You only need it if you want those cloud features.

How connecting works:
- **New users:** click **"Enable Cloud"** on the plugin's Overview/Welcome screen. A Storelly account is created and connected for you automatically, right inside wp‑admin — you are not redirected to storelly.com. You then receive an email at your site admin address with your Storelly username, password and login link (please sign in and change the password).
- **Existing Storelly users:** you can instead paste your API keys (SID + Secret) on **Settings → Integration**, or paste your Store ID via "Already have a Store ID? Link it" to attach this site to your existing store.
- **Reinstalling:** the plugin keeps a stable store identifier derived from your site URL and admin email, so reconnecting re‑links to the same Storelly store instead of creating a duplicate.

Nothing is sent to Storelly until you explicitly connect, and you can disconnect at any time (Overview → Disconnect). See the "External services" section for exactly what data is shared.

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

- **Storelly Dashboard API (`https://app.storelly.com`)**
  - **What it is used for**: Used to register your Storelly account from inside WooCommerce, to synchronise WooCommerce order information with the Storelly Dashboard, and to check your Storelly Cloud plan (license status, available plans and aggregate dashboard statistics).
  - **What data is sent and when**:
    - **Only after** a store administrator explicitly opts in — by clicking "Enable Cloud" on the plugin's Welcome screen (or connecting the account on the settings page) — the plugin registers your store by sending your store owner details (name, email address, billing address fields, time zone, WooCommerce API keys) and a non-secret store identifier (a stable store ID derived from your site URL and admin email, so reinstalling re-links to the same store) to the Storelly Dashboard API. Nothing is sent before this explicit opt-in.
    - When an order is placed or processed in WooCommerce **and order sync is enabled**, the plugin sends order data (order totals, discount amounts, product and variation identifiers, quantities, unit prices and links to the generated design PDF files) to the Storelly Dashboard API so that orders can be tracked and managed there.
    - When a store administrator opens the License or Overview screen, or clicks "Sync license", the plugin requests the license endpoints (`/api/v1/license/status`, `/api/v1/license/packages`) and the overview endpoint (`/api/v1/plugin/overview`) to read the store's current plan, the list of available plans, and aggregate counts (totals of products, orders and quotes). When the administrator activates a license, the entered license key and the store's numeric business identifier are sent to `/api/v1/license/activate`. These requests are made only in response to the administrator opening those screens or clicking the relevant button.
  - **Service owner and policies**: This service is operated by Storelly. Please review policies:
    - Terms of Service: https://app.storelly.com/terms
    - Privacy Policy: https://app.storelly.com/privacy

- **Storelly demo product data (`https://app.storelly.com/product-data/data/data.json`)**
  - **What it is used for**: Used by the admin "Global Import" demo catalogue screen to fetch a larger sample catalogue (`data.json`) so the store owner can preview and import additional demo products into WooCommerce. The Welcome screen's one-click "Add demo product" uses a demo bundled inside the plugin and does NOT contact this service.
  - **What data is sent and when**: Only when a logged-in administrator opens the Global Import demo-catalogue screen does the plugin issue an anonymous `GET` request to the URL above. No site URL, user account information, order data or PII is sent in the request body; the only identifying information is the standard HTTP headers (User-Agent, IP) that any outbound HTTP request includes.
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
= 1.6.3 =
* Removed the vestigial designer-marketplace / "launcher" module entirely. It was bundled-but-disabled scaffolding inherited from the NBDesigner / pc-designer codebase (no UI entry point) and was the only place Storelly still carried that lineage's legacy globals. Deleting it permanently eliminates any possibility of a symbol collision with another NBDesigner / PC Designer–derived web-to-print plugin — superseding the 1.6.1 / 1.6.2 mitigations, which are no longer needed. Core product builder, quotes, B2B, custom orders and saved designs are unchanged.

= 1.6.2 =
* Comprehensive conflict handling for sites that also run an NBDesigner / PC Designer–derived web-to-print plugin. Storelly now reliably detects a sibling designer plugin (both by a class probe and by scanning the active-plugins list, so it works regardless of plugin load order) and cleanly disables only its own optional designer-marketplace module instead of loading code that would collide. A clear admin notice asks you to deactivate the other plugin. This fully resolves the activation fatal introduced when both were active. Storelly's core product builder, quotes and B2B features are unaffected, and the marketplace module is opt-in (off by default).

= 1.6.1 =
* Fixed a fatal error ("Cannot redeclare function") that could occur on activation when another NBDesigner / pc-designer–derived web-to-print plugin was already active. The optional designer-marketplace module now detects those legacy helpers and steps aside instead of crashing the site. (Note: Storelly is intended to replace such plugins, not run alongside them.)

= 1.6.0 =
A major feature release turning Storelly into a full B2B-capable, quote-driven WooCommerce product platform. Everything below runs locally and stays free; only the optional Storelly Cloud features (print-ready PDF, order sync, dashboard analytics) require a paid plan.

* **B2B / Wholesale (new).** Company accounts (with members and roles), a wholesale tier-pricing ladder, and an Account Credit system: prepaid wallet, net-terms credit line with an over-limit approval workflow, monthly volume rebates, partial-refund reversals and accounts-receivable aging — all on a single signed ledger. Each company gets a public, brand-safe storefront, and payment-term labels are fully customizable.
* **Request a Quote (greatly expanded).** Add a file-upload field to quote forms (multi-file, drag-and-drop, shown in admin/email/PDF). New multi-product quote cart and a standalone quote page. Import existing quotes from WooCommerce orders, contact-form plugins (Contact Form 7, WPForms, Gravity Forms, Forminator) and quote plugins (ELEX, Addify, B2BKing). Buyers manage, accept and convert quotes to orders from their account.
* **Custom Orders.** Reworked detail page: instant tabs, clearer CTAs, customer notes, one-click re-order and download-all.
* **My Account portal.** New customer endpoints — quotes, saved designs, re-orders, brand store, team, approvals and store management.
* **Email system.** All transactional mail unified on the WooCommerce email engine (designer messages, custom-order received and proof emails), plus an email log, a "send test" tool and an admin Emails dashboard.
* **Designer canvas.** Buyers can now add their own free-form text and images on the canvas alongside admin-defined components, with pricing computed authoritatively on the server.
* **Admin redesign.** Refreshed Emails / About / System / Orders / Quote screens on a shared component library, and a re-organized menu (placed under WooCommerce) grouped into Build / Sell / Configure bands with a crisp new icon.
* **Reliability & compliance.** PHP 8 compatibility fixes, batched several N+1 query paths, hardened account-endpoint rewrite flushing, and a clean Plugin Check pass (prepared queries, WP_Filesystem-friendly file ops).

= 1.5.7 =
* Onboarding & activation polish — get merchants to a live, customizable product faster and keep them:
  - **One-click demo, bundled and offline-safe.** The Welcome screen's "Add demo product" now installs a ready-made customizable product (the bag) from data bundled inside the plugin — no network call, works on a fresh or offline install. Images are sideloaded locally; the product, its option set and images are tagged so "Remove demo" cleans everything up. The previous remote sample importer remains available on the Global Import screen.
  - **Resume setup.** Skipping the Welcome guide before finishing onboarding now leaves a quiet "Show the setup guide again" link, so a stray click isn't permanent.
  - **Request-a-Quote badge on by default.** The floating site-wide quote badge now shows by default once the quote feature is enabled (merchants who turned it off stay in control); badge URL output is escaped.
  - **Review request.** A polite, dismissible review prompt now appears on the Storelly admin screens — only after at least two weeks of use AND once the builder is actually set up — with "leave a review", "maybe later" (snoozes 30 days) and "no thanks" choices.
  - **Large-catalogue safety.** The background "prepare existing products" migration is now time-boxed per request and resumes via the dashboard poll (so big catalogues can't time out a request), with a re-entrancy lock so concurrent runs never create duplicate option sets.
  - **Design-token pass.** The review and upsell admin notices moved off inline styles onto the shared design tokens, plus a polished "demo installed" state for the Welcome card.
* Customizer V3 — three follow-ups surfaced by the thorough flow test on 1.5.6:
  - **(P0) Reset all no longer leaves the canvas blank.** The previous implementation looped `selectAttribute(0)` over every component, racing 5 parallel `fabric.Image.fromURL` callbacks onto the same canvases — the last one to land "won" and could leave Fabric in a corrupted state where the base view image was no longer painted, even after closing and re-opening the modal. The only recovery was a full page reload. Reset now does that page reload itself, deterministically: it clears the localStorage build, shows the success toast, and reloads after a brief 600ms delay so the toast remains visible. The buyer lands back on the product page with a clean customizer (the canvas re-renders, the carousel transform resets to 0, all stage states are pristine). A page reload here is a fair trade — Reset all is a rare action, and the previous in-place implementation was outright broken.
  - **(P2) In-modal Reset confirmation replaces the native `window.confirm()` dialog.** The browser-native confirm was modal-blocking, unstyled, and jarring against the V3 design system. The new dialog sits inside the popup (outside the `.nbdpb-carousel` subtree so the legacy script doesn't sweep it), uses the V3 design tokens (brand-soft icon ring, danger-red CTA), supports keyboard close (Esc) and backdrop dismiss, and matches the elevated card look used by the rest of the customizer. JS `$scope.showConfirm()` returns a Promise so the caller can chain — falls back to the native `confirm()` if the host node isn't rendered (older wrapper template, smoke tests).
  - **(P3) Onboarding teach-toast no longer lingers across a Reset.** `resetAll` now explicitly hides any active `.spbwc-cust-teachtoast` before showing its own "All customizations have been reset" toast, so the buyer doesn't see two stacked toasts or a leftover "Live pricing" message that reads as if the reset triggered it.

= 1.5.6 =
* Customizer V3 — Order Summary sticky-bottom restructure so the "Add to cart" CTA is the LAST visible element pinned to the browser edge:
  - The "Free design preview — no charge until your design is locked in" trust note used to live at the very bottom of the sticky band, BELOW the Reset/Cancel row, BELOW the CTA. On shorter viewports — or when the browser added chrome (autofill bar, address bar showing again on scroll, etc.) — that trust note was the element that got clipped by the browser, so users reported "phía dưới add to cart - reset có 1 mục đang bị che bởi trình duyệt".
  - Trust note moved INTO the scrollable middle as a sibling of the price-block, so it gets natural overflow handling and is never clipped.
  - Sticky-bottom now contains only two rows: the small Reset all / Cancel link row on top, and the BIG CTA on the bottom. CTA is the LAST node in the band — nothing below it can be obscured by browser UI.
  - Modal layout fix that surfaced during the above test: legacy `.nbdpb-popup` defaults to `display: block !important`, so the V3 4-column grid (tab-nav + panel + canvas + summary) was sized to the full modal height but positioned BELOW the modal-head — it extended past the modal bottom edge by exactly the modal-head height. On laptop viewports (≈640px after admin bar + DevTools) this clipped the CTA by ~47px. V3 modal now scope-overrides to `display: flex; flex-direction: column !important` with `flex: 1 1 auto` propagated down through `.spbwc-cust-app` → `.spbwc-cust-body` → `.spbwc-cust-container`, so the grid only takes the space remaining under the modal-head and the sticky CTA pins flush to the modal bottom. V2 keeps its legacy block layout untouched.

= 1.5.5 =
* Customizer V3 — lock preset Fabric layers + more visible zoom controls:
  - For preset (`nbpb_com`) components — colour/material swatches like SIDE PANELS, INSIDE STORAGE — the Fabric image layer now ships with `selectable: false` + `evented: false`. The customer picks an option, the overlay snaps into place, and there's no way to accidentally click into the layer and reveal the layer-transform admin toolbar (Bring Forward / Send Backward / Zoom / Clear). For `nbpb_text` / `nbpb_image` (customer-supplied content) the layer stays fully interactive so the customer can still position their text or photo.
  - Zoom controls bumped: 36px tall buttons (was 32px), stronger border, larger value pill, "ZOOM" caption to the left so it reads as a real tool. Canvas toolbar row tightened from 48→56px to make room.

= 1.5.4 =
* Customizer V3 — kill the "white square overlay" bug (user screenshot of bag → option pick → solid white box covering the artwork):
  - The legacy `selectAttribute()` adds a Fabric image layer for every stage of the picked option. When admin doesn't differentiate the option per view (every option reuses the same shared placeholder image — image_id 135 on the bag), that layer renders as a literal white square covering the product photo.
  - New `$scope.isViewPassiveForComponent(component, viewIdx)` returns true when every option of the component points to the same `image_url` on that view → the view is "passive" → the option doesn't differentiate that view → hide the layer.
  - `$scope.selectAttributeAndSwitchView` now iterates all stages after the legacy `selectAttribute` runs and toggles `visible/selectable/evented = false` on the Fabric layer for passive views. Renders + discardActiveObject so the admin-tool toolbar doesn't pop up.
  - `$scope.changeStage` now calls `discardActiveObject` + clears `showAdminTool` on the new stage so view switching never bring the layer-transform toolbar into view.

= 1.5.3 =
* Customizer V3 — primary-view detection verified offline against the bag product (option_id 8). PHP unserialization of the actual `wp_storelly_product_builder_options.fields` blob shows `findPrimaryView()` returns the expected stage for every component: HANDLES → 0 (Front, tie-broken to first), SIDE PANELS → 0, MIDDLE BLOCK → 0, INSIDE STORAGE → **2 (Inside)**, STRAP FABRIC → 0. The auto-switch heuristic is correct; if the bug still reproduces, hard-reload to bust the cached 1.5.1 / 1.4.x JS and set `window.SPBWC_DEBUG_VIEW = true` in DevTools then re-open the INSIDE STORAGE accordion to see the heuristic firing in the console.

= 1.5.2 =
* Customizer V3 — two view-switch fixes from buyer feedback:
  - Picking an option whose visual change lives on a different view now auto-switches the canvas to that view, even when the admin has set a base `image_url` on every view. The old "first non-empty url" heuristic failed in that case. The new `$scope.findPrimaryView(component)` counts distinct image URLs per view across all the component's options and picks the view with the greatest variety. Toggling an accordion item open also hops to that primary view immediately so the customer is already looking at the right side before picking.
  - Hid the ghost `.nbpb-overlay` div that lived inside the design-zone — in V3 it was washing out the artwork on the Inside view of multi-view products. Repositioned the contextual `.design-admin-tool` (Bring fwd / Send back / Zoom / Clear) from top-centre overlap to bottom-left of the canvas, with proper glass backdrop + tokens, so the layer transform tools don't block the product photo.

= 1.5.1 =
* Customizer V3 — polish batch + responsive + persistence:
  - **localStorage persistence**: every option pick, text entry, and image upload is saved to a product-scoped localStorage key (`spbwc_v3_design_<oid>`). When the customer reopens the modal we restore their previous design + flash a toast "Your previous design has been restored." Reset All clears the persistence. Same pattern as the existing storefront save-build infrastructure.
  - **Toast system**: new floating bottom-right toaster (`$scope.showToast(msg, kind, duration)`) with success / warning / danger / info variants. First toast use: "All customizations have been reset." after Reset All.
  - **A11y polish**: focus rings (`:focus-visible` 2px brand outline) on every interactive surface inside `.spbwc-cust-v3`; `aria-live="polite"` on the Add-to-cart CTA so screen readers announce live price changes; `aria-pressed` on option cards.
  - **Mobile responsive**: at ≤768px the 4-column grid collapses into a vertical stack — canvas takes the top, tab nav becomes a horizontal scroll row, panel takes 45vh below, summary becomes a bottom drawer that peeks with the CTA + tap-to-expand handle. ≤480px the brand thumb hides and the CTA shrinks to 56px.
  - **Empty states**: components with zero options now render a dashed "No options available for this part" tile instead of an empty grid.
  - **Micro-interactions**: option cards lift on hover (`translateY(-1px)`), filter chips fade in via `spbwcValFade` keyframes when the filter changes, skeleton placeholder (shimmer) primitive added for future use.

= 1.5.0 =
* Customizer V3 — full UX audit + token consistency cleanup:
  - Added five new design tokens (`--spbwc-c-success-mid/deep/line`, `--spbwc-c-brand-mid/line`) so every accent colour in the customizer comes from the token system. Replaced the last five hardcoded hex values that lived in the V3 CSS — the entire customizer surface now re-themes from a single token block.
  - Audit pass confirmed every base token resolves to the Printcart Canva v2.0 palette (brand #2563eb, ink #1f2937, text #6b7280, line #e5e7eb, bg-soft #f9fafb, success #10b981, radius 12/8/6, shadow scale).
  - All major flows verified working: open modal, view thumb swap (0↔1↔2 deterministic), accordion open/close/toggle, sub-option pick + auto-view-switch, filter chips per attribute, tab swap Customize/Details/Shipping/Help, reset all with confirm, sticky-CTA summary.

= 1.4.9 =
* Customizer V3 — two view-switching bugs fixed:
  1. View thumb click sometimes left the carousel stuck — root cause was the legacy `nbdpbCarousel.itemActive()` calculating the transform from *current* (already-transformed) offsets, which produced inconsistent results going 0→1→2→0. Rewrote `$scope.changeStage(idx)` to set the carousel transform directly with `index × first-item-width`, independent of past state. Switching is now deterministic regardless of click order.
  2. Picking a sub-option whose visual change lives on a different view didn't switch the canvas to that view. New `$scope.selectAttributeAndSwitchView(optionIdx, component)` wrapper calls the existing `selectAttribute()` (so the save pipeline is untouched) and then hops the canvas to the first view where the picked option has a non-empty `image_url`. If the current view already shows the change, it stays put.

= 1.4.8 =
* Customizer V3: hide the legacy carousel `<` / `>` arrows + dot pagination inside the canvas. They were duplicating the new view-thumb strip (top-right of canvas) and overlapping the artwork — making it look like switching views broke the main image. View thumbs are now the only view-switcher affordance.

= 1.4.7 =
* Customizer V3 — direction refinement:
  - View thumbnails moved INTO the design zone (top-right corner of canvas). Each thumbnail is the view's base image; click to switch sides of the product. Replaces the previous text-pill view switcher in the canvas toolbar.
  - The "This view / All" view filter pill in the panel head is removed; per-component filter chips inside the open option grid replace it.
  - New filter chips per accordion body: when a component has 2+ parent attribute groups (e.g. SIDE PANELS = Leather / Cotton / Suede), a chip row appears above the option grid — All / Leather / Cotton / Suede. Click to filter sub-options to that parent family. Backed by a new `$scope.getAttrFilters(component)` helper.

= 1.4.6 =
* Customizer V3 — view-aware accordion + Details tab redesign:
  - When a product has 2+ views (Front / Back / Side), the Customize tab now auto-filters the accordion to show ONLY parts that visually affect the view the buyer is currently looking at. Detection runs on each component's `pb_config.views[currentStage]` — if no option has a visible asset on that view, the component is hidden in "This view" mode. A small `[This view] [All]` toggle in the panel head lets buyers flip to the unfiltered list. Defaults to "This view".
  - Details tab redesigned with a real product hero: full-width 4:3 hero image (medium_large), clickable gallery thumbs below the hero (click to swap the hero), product name + "From $X base" price line, "About this product" lede (short_description), "Full description" body, and a "Specifications" key-value grid (SKU / Category / Weight / Dimensions).

= 1.4.5 =
* Customizer V3 — three UX refinements:
  - Removed the `border-bottom` line under the panel head "Customize parts" caption per user feedback. The head now flows visually into the accordion list with just padding separation.
  - Summary column is now a 3-row layout — sticky top (product name + progress pill + track), scrollable middle (order summary card + price breakdown), sticky bottom (Add to cart CTA + Reset/Cancel + trust note). The CTA stays visible no matter how tall the price-breakdown grows; only the middle band scrolls.
  - FAQ tab redesigned: brand-tint intro banner ("Need a hand?") above the Q&A list, bordered card-per-question with a chevron that rotates on toggle, open question gets a tinted header strip + soft blue border. Five seed Q&As ship by default; the contact link at the bottom is filterable via `spbwc_customizer_contact_url`.

= 1.4.4 =
* Customizer V3: fixed accordion scroll regression — a stale duplicate `.spbwc-cust-tabpanel { overflow-y: auto }` rule was scrolling the whole tab panel (including the pinned head) and fighting the inner accordion list, so products with many options (e.g. a colour picker with 10+ swatches) had their bottom options cut off at the viewport edge. Removed the duplicate; the inner accordion list now owns the scroll exclusively, head stays pinned.
* Customizer V3: tab nav polish per buyer feedback — dropped the 3px brand-blue accent rail on the active tab's right edge. The rail had become visual noise next to the accordion cards in the panel. Active state now relies on the brand-tint bg + brand text + a 1px brand-soft border around the button. Tab nav rail also gets a slightly darker `--nbd-mb-border-strong` right border + `--nbd-mb-bg-soft` background so it reads as a distinct rail (not the same surface as the white accordion cards).

= 1.4.3 =
* Customizer V3 — scroll containment + UI elegance pass:
  - The accordion list inside the Customize tab is now the proper scroll container for the panel column. Many components (or many options inside an open step) push the whole list, which scrolls smoothly while the panel head stays pinned. The earlier nested `max-height: 360px` inner scroll was removed — single scroll container, no nested-touch fights.
  - Panel head ("CUSTOMIZE PARTS" caption) pinned with `flex: 0 0 auto`, fixed 44px height, left-aligned 12px uppercase letter-spacing 0.06em. `!important` defeats theme overrides that were nudging it.
  - Vertical tab nav buttons bumped to 72px min-height with 10×8 padding, 32px rounded icon plate (8px radius, fills with white on active), 11.5px label, 6px gap stack — more breathing room, less cramped. Active tab also gets a 3px brand-blue accent rail on the right edge (panel side) — visually "connects" the active tab to the panel.

= 1.4.2 =
* Customizer V3 — Summary blocks now have generous breathing room: column padding 20×18px, 20px gap between sections, price-block padding 14×16px, item-card padding 14px with 14px gap. No more dense glued-together rows next to the canvas.
* Spec line under the product name skips the WP default "Uncategorized" term and shows the SKU instead (or "Made to order" if no SKU). The earlier hardcoded "Custom builder" tag is gone.
* Status progress pill polished: brand-soft bg + pulsing brand dot + 11.5px 700 letter-spacing 0.01em label. Flips to emerald success palette + box-shadow ring when 100% configured. Progress track also bumped 4→6px with inset-shadow + gradient fill for visual weight.
* Canvas toolbar moved OUT of the artwork stage into a dedicated 48px row below the stage. View switcher (centre), live-preview pill (left), zoom bar (right) now sit in their own band — the product image is never hidden under floating affordances again.

= 1.4.1 =
* Customizer V3: accordion step rows now toggle — clicking an already-open step closes it (was open-only). Driven by a new `$scope.toggleAccordion(idx)` so the legacy `showAttribute` save pipeline stays untouched.
* Panel head "CUSTOMIZE PARTS" alignment fixed: no longer drifts left of the accordion items.
* Empty AI / Templates placeholder tabs replaced with real content tabs — **Details** (auto-sources `$product->get_short_description()`, `get_description()`, SKU, category, weight, dimensions) and **Shipping** (production lead time, delivery options, return policy, secure-checkout — filterable via `spbwc_customizer_shipping_html`). The "Help & FAQ" tab is kept.

= 1.4.0 =
* Customizer V3 — Summary column rewritten 1:1 with Printcart Canva v2.0 reference. New structure: product hero block at top (22px bold name + small variant line + live progress pill on the right) → ORDER SUMMARY · 1 ITEM section with thumb item card → light-gray "price-block" containing Base + every component row + Shipping + a YOUR PRICE total row inside (Printcart `.price-row.total` 15px label, 20px bold value) → big Add to cart CTA (17px label / 20px price stacked) → Reset / Cancel link row → blue trust note. Padding, gaps, fonts, weights, radii, shadows all use the Printcart `--nbd-space-*`, `--nbd-radius-*`, and `--nbd-shadow-*` scale values verbatim.
* Status "5/5 configured" affordance moved from the left panel into the Summary head. It now lives as a pill at the top-right of the product name + a 4px progress track underneath — the customer sees it together with the price they're committing to.
* Removed the heavy box-shadow on the canvas artwork. The product image now sits flush on the dotted-grid stage exactly like Printcart Canva — no "boxed thumbnail" treatment.

= 1.3.9 =
* Customizer V3 — premium polish pass to match Printcart Canva v2.0 reference HTML 1:1:
  - Canvas now sits on a 24×24 dotted grid background (`linear-gradient` cross-hatch over `--nbd-mb-bg-canvas` #f3f4f6) so the artwork "floats" with depth — no more flat white wall behind the product image.
  - Artwork is clipped to fit the stage (`max-width: calc(100% - 32px)`) with a soft `shadow-xl` lift; it never spills past the canvas border.
  - New zoom bar (`.spbwc-cust-zoom`) at the bottom-right: −/% label/+/fit, glass pill with backdrop-blur — direct port of Printcart `.zoom-bar`. Wired to a new `$scope.zoomCanvas(delta, fitReset)` that drives a CSS-transform scale 0.5–2.0 on the design-zone.
  - "Add to cart" CTA bumped to 64px min-height with branded `0 6px 16px 0 rgba(37,99,235,0.3)` shadow — matches `.summary-cta-big` from the reference. Hover lifts; active presses.
  - Topbar now has Printcart `shadow-md`, summary padding evened out to 16px on all sides, breakdown rows aligned to space-3.
  - Long option lists inside an open accordion now scroll INSIDE the step body (max-height 360px, `overscroll-behavior: contain`) — the panel head + Summary stay fixed.

= 1.3.8 =
* Customizer V3 — design tokens now ported VERBATIM from the Printcart Canva v2.0 reference HTML (`--nbd-mb-primary` #2563eb, `--nbd-mb-bg-soft` #f9fafb, `--nbd-color-success` #10b981 emerald, Printcart radius/shadow scale). The tab nav and step accordion CSS rules are direct ports of the reference `.tab-btn` and `.step-item` styles — no creative additions (dropped the white-card active tab, the panel "tongue" bridge, and the brand-tint glow ring around open steps).

= 1.3.7 =
* Customizer V3 — tab nav vs panel separation pass: the left tab rail is now a gray-soft background so the white panel reads as a distinct surface (was hard to tell apart). The active tab is a raised white card with a brand-blue accent on the left and a "tongue" bridge into the panel so it's obvious which view is open. Accordion items are now individual bordered cards (matching the Canva step-row pattern) with a brand-blue glow when open and a contained hover so the gray hover state no longer "sticks" on previously-clicked items.

= 1.3.6 =
* Customizer V3 — Canva-style polish pass: new "ORDER SUMMARY · 1 ITEM" card with product thumb + live spec readout sits at the top of the right column; YOUR PRICE total bumped to 28px bold so it reads as the conversion anchor; summary column tightened 340→300px to give more room to the central canvas. A floating "Live preview" pill and a Front/Back view switcher (only when product has ≥2 views) now sit at the bottom of the canvas — matching the Printcart Canva v2.0 reference. The save / preview loader overlay gained a premium backdrop-blur effect.

= 1.3.5 =
* Customizer V3 — pattern alignment with Printcart Canva v2.0 reference UI: step badges in the accordion are now status icons (green check when configured, empty dot when pending) instead of sequential numbers; the "YOUR PRICE" row drops the tinted panel for a clean baseline-aligned bold row that sits above a 2px separator; the panel progress bar flips to green the moment all components are configured; and a dismissable "Live pricing" teaching toast appears bottom-right of the modal on first open.

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
= 1.6.3 =
Removes the unused designer-marketplace module, permanently ending any conflict with NBDesigner / PC Designer-derived plugins. Core features unchanged.

= 1.6.2 =
Definitive fix for activation fatals when another NBDesigner / PC Designer web-to-print plugin is active. Recommended for everyone.

= 1.6.1 =
Fixes a possible activation fatal error when another NBDesigner-derived web-to-print plugin is active. Recommended for everyone on 1.6.0.

= 1.6.0 =
Major update: B2B company accounts and wholesale pricing, Account Credit (wallet, net terms, rebates), expanded Request-a-Quote with imports, redesigned admin and emails, and free-form text/image on the designer canvas. All local features remain free.

= 1.2.7 =
Replaces hardcoded timezone with the site's configured timezone and declares additional external services in readme.

= 1.0.0 =
First stable public version.