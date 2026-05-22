# Chart.js bundle

Marketplace Report dashboard charts depend on Chart.js v4.

## What ships here

| File | Origin | Notes |
|---|---|---|
| `chart.umd.js` | npm package `chart.js@4.4.0` (registry tarball, `dist/chart.umd.js`) | UMD bundle, **unminified**. Chart.js v4 dropped the pre-minified file from its npm package — bundlers minify on demand. We ship the readable UMD on purpose so wp.org reviewers can audit the source. |
| `LICENSE.md` | npm package `chart.js@4.4.0` | MIT license text. Copied verbatim. |

`SPBWC_Marketplace_Admin::enqueue_assets()` registers exactly the path
`SPBWC_PB_ASSETS_URL . 'libs/chartjs/chart.umd.js'` and the script is
loaded as a dependency of `spbwc-marketplace-admin` only on the
marketplace admin page.

## License

Chart.js is **MIT-licensed**, GPL-compatible. The library is bundled
with the plugin source for use on the marketplace admin Report tab;
no outbound request is made.

## Upgrading

To bump Chart.js (e.g. v4.5.0):

```bash
curl -sSL https://registry.npmjs.org/chart.js/-/chart.js-4.5.0.tgz -o /tmp/chartjs.tgz
mkdir -p /tmp/chartjs-extract && tar -xzf /tmp/chartjs.tgz -C /tmp/chartjs-extract
cp /tmp/chartjs-extract/package/dist/chart.umd.js static/libs/chartjs/chart.umd.js
cp /tmp/chartjs-extract/package/LICENSE.md       static/libs/chartjs/LICENSE.md
```

Then bump the registered version inside `wp_register_script(
'spbwc-marketplace-chartjs', …, '4.5.0', true )` in
`includes/marketplace/admin/class-marketplace-admin.php`.

## Without Chart.js

The marketplace admin remains fully functional without Chart.js — only
the two report-dashboard charts (designs over time, sales over time)
silently skip rendering because `static/js/marketplace-admin.js` guards
on `typeof window.Chart === 'undefined'`. The four stat cards still
display.
