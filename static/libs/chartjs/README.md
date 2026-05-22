# Chart.js bundle

Marketplace Report dashboard charts depend on Chart.js v4.x.

This directory ships a **placeholder** `Chart.min.js` (a JS comment, no
runtime code) because the build container's outbound network policy
blocks downloads from CDNs at build time.

## To enable the charts

1. Download `chart.umd.min.js` from a Chart.js v4.x release:
   - https://github.com/chartjs/Chart.js/releases (tag `v4.4.0`)
   - Or `npm install chart.js@4.4.0` and copy `node_modules/chart.js/dist/chart.umd.min.js`
2. Replace `static/libs/chartjs/Chart.min.js` with the downloaded file.
3. Keep the filename as `Chart.min.js` (capital C) — `SPBWC_Marketplace_Admin::enqueue_assets()` registers exactly that path.
4. Bump `static/libs/chartjs/README.md` to record the actual version + source URL of what was bundled.

## License

Chart.js is **MIT-licensed**, GPL-compatible. When you bundle the real
library, declare it under "External services / bundled libraries" in
`readme.txt` (or in the WordPress.org plugin Description) if reviewers
flag the addition.

## Without Chart.js

The marketplace admin remains fully functional without Chart.js — only
the two report-dashboard charts (designs over time, sales over time)
silently skip rendering. The four stat cards still display.
