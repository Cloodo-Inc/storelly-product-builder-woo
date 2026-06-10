# SPEC — Shared Dialog & Toast System (`spbwcDialog`)

Status: **shipped**. Owner: admin/storefront UX.
Source: [`static/js/spbwc-dialog.js`](../static/js/spbwc-dialog.js),
[`static/css/spbwc-dialog.css`](../static/css/spbwc-dialog.css),
loader [`includes/class-dialog.php`](../includes/class-dialog.php).

## Why

The plugin used the native browser `window.alert / confirm / prompt` popups (the
"<site> says" chrome) in ~30 places across admin and storefront. They are
unstyled, off-brand, block the main thread, and cannot be themed or tokenised.
This system replaces them with one token-driven, accessible component.

## Component API

`window.spbwcDialog` (dependency-free, promise-based):

| Method | Returns | Notes |
| --- | --- | --- |
| `alert(opts)` | `Promise<void>` | resolves on OK / Esc |
| `confirm(opts)` | `Promise<boolean>` | `true` = confirmed |
| `prompt(opts)` | `Promise<string\|null>` | `null` = cancelled |
| `toast(opts)` | `void` | non-blocking corner notice (auto-dismiss) |
| `wireConfirms(root)` | `void` | (re)scan `[data-spbwc-confirm]` in dynamic markup |

`opts` is a string (used as the message) **or** an object:
`{ title, message, okText, cancelText, tone, defaultValue, placeholder, required, icon, duration }`.

- confirm/alert `tone`: `default | danger | success | warning`
- toast `tone`: `info | success | error | warning`

Implementation: native `<dialog>` + `showModal()` (top-layer, focus trap, Esc)
with a non-native fallback path; backdrop-click and Esc resolve as cancel.
Reduced-motion respected. RTL-safe via CSS logical properties.

### Migration contract

Every call site keeps a **native fallback** so behaviour degrades gracefully if
the module is somehow absent:

```js
var ask = window.spbwcDialog
  ? window.spbwcDialog.confirm({ message: msg, tone: 'danger', okText: ok })
  : Promise.resolve(window.confirm(msg));
ask.then(function (ok) { if (!ok) { return; } /* …post-confirm work… */ });
```

- Blocking `confirm()` → the synchronous boolean branch is refactored into the
  promise `.then`. Inside AngularJS (`app-product-builder.js`, `admin-options.js`)
  the continuation calls `$scope.$applyAsync()` because it resolves outside the
  digest.
- Informational `alert()` after AJAX → non-blocking `toast()`.
- A success message that *gated a redirect* uses blocking `alert()` (not toast),
  then redirects in `.then`.

### Progressive-enhancement form/link confirms

For server-rendered destructive actions that must also work without JS, the
element keeps its inline `onsubmit/onclick="return confirm(...)"` (no-JS
fallback) **and** carries data attributes:

```html
<form … onsubmit="return confirm('…')"
      data-spbwc-confirm="…" data-spbwc-confirm-title="…"
      data-spbwc-confirm-ok="…" data-spbwc-confirm-tone="danger">
```

On load `spbwcDialog` strips the inline handler (so it never double-prompts) and
shows the styled confirm; `data-spbwc-confirm` is escaped with `esc_attr()`.

## Enqueue

`SPBWC_Dialog::init()` (loaded from the main plugin file):

- registers `spbwc-dialog` script + style on both `admin_enqueue_scripts` and
  `wp_enqueue_scripts` at priority 1 (so other handles may depend on it),
- **admin**: enqueues both on every admin page (tiny footprint; the utility is
  used by inline scripts on Storelly *and* WooCommerce-native screens),
- **storefront**: buyer-facing handles declare `spbwc-dialog` as a dependency
  (`product-builder`, `spbwc-option-builder`, `spbwc-custom-order`); the
  stylesheet is auto-paired via `maybe_pair_style()`,
- localises `spbwcDialogI18n` (OK / Cancel / default labels).

The stylesheet ships **token fallbacks** (`var(--st-brand, #1d4ed8)` …) so it
renders correctly even where `_tokens.css` is not loaded (storefront).

## Migrated call sites

Admin: `quote/class-quote-admin.php` (Save-as-template **prompt**, delete
**confirm**, AJAX **toasts**), `overview.php` (demo / Cloud / Woo-prepare /
sync), `license.php`, `menu-settings.php`, `class-product-exporter.php`,
`admin-options.js`, `manager-fonts.js`, `woo-seed-app.js`,
`linked-product-metabox.js`. Form/link confirms (data-attr): setup-wizard
landing, custom-order-sample, b2b-team, visual-builder list, edit-option.

Storefront: `app-product-builder.js` (designer — file validation, layer
delete), `option-builder.js` (upload validation), `storelly-general.js`,
`custom-order.js` (saved-design delete), `template-library.js` (apply-conflict).

## Tokens used

`--st-brand` / `--st-brand-pressed`, `--nbd-color-danger/-soft`,
`--nbd-color-success/-warning`, `--nbd-st-bg`, `--nbd-radius-lg`,
`--shadow-xl/-lg`, `--st-z-overlay/-modal/-toast`, spacing + typography scale.
No hardcoded colours/spacing in component CSS beyond token fallbacks.
