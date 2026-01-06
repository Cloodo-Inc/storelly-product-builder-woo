=== Storelly Product Builder for WooCommerce ===
Contributors: storelly
Donate link: https://storelly.com/
Tags: product builder, product customize, product customizer, woocommerce custom product
Requires at least: 4.7
Tested up to: 6.9
Stable tag: 1.1.2
Version: 1.1.2
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

== Frequently Asked Questions ==

= How can I contact support? =
You can reach the Storelly team via email: support@storelly.com

= What third-party resources does this plugin use? =

This plugin includes or depends on the following open-source libraries:

- Animate.css — MIT License  
- normalize.css v8.0.1 — MIT License  
- Snap.svg 0.3.0 — Apache License 2.0  
- FPDI — MIT License
- fontfaceobserver.js — BSD License  
- spectrum.js — MIT License  
- fabric.js — MIT License  

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

== Screenshots ==

1. Front-end product builder
2. Back-end product settings

== Changelog ==
= 1.1.2 =
* Security Updates: Nonce Verification and User Permissions.

= 1.1.1 =
* Enhance Settings Handling, Template Rendering, and Style CSS in Plugin.

= 1.1.0 =
* Enhanced Security, Caching, and Code Quality Across the Plugin.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==
First stable public version.