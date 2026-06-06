#!/usr/bin/env node
/**
 * Build minified User-Menu storefront CSS (User Menu M6).
 *
 * Regenerates `*.min.css` next to each source file using clean-css, which safely
 * preserves calc() spacing and CSS custom-property fallbacks. The PHP enqueues
 * (SPBWC_Account_Shell::css_url) prefer the .min build when present and
 * SCRIPT_DEBUG is off, falling back to source otherwise.
 *
 * Usage (from the plugin root):
 *   node tools/build-usermenu-css.mjs
 *
 * Requires Node 18+. clean-css-cli is fetched on demand via npx, so no install
 * step is committed. To minify additional sheets, add their base names to FILES.
 */

import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const CSS_DIR = join(dirname(fileURLToPath(import.meta.url)), '..', 'static', 'css');

// Base names (no extension) of the User-Menu sheets to minify.
const FILES = [
  'launcher',
  'launcher-rtl',
  'launcher-store',
  'launcher-store-rtl',
  'account-shell',
  'account-shell-rtl',
];

const JS_DIR = join(dirname(fileURLToPath(import.meta.url)), '..', 'static', 'js');

// Base names (no extension) of the User-Menu scripts to minify (User Menu G5).
const JS_FILES = [
  'launcher',
  'quote-storefront',
];

let built = 0;
for (const name of FILES) {
  const src = join(CSS_DIR, `${name}.css`);
  const out = join(CSS_DIR, `${name}.min.css`);
  if (!existsSync(src)) {
    console.warn(`skip (missing): ${name}.css`);
    continue;
  }
  execFileSync('npx', ['--yes', 'clean-css-cli@5', '-o', out, src], { stdio: 'inherit' });
  console.log(`min: ${name}.css -> ${name}.min.css`);
  built++;
}
for (const name of JS_FILES) {
  const src = join(JS_DIR, `${name}.js`);
  const out = join(JS_DIR, `${name}.min.js`);
  if (!existsSync(src)) {
    console.warn(`skip (missing): ${name}.js`);
    continue;
  }
  execFileSync('npx', ['--yes', 'esbuild@0.21', src, '--minify', `--outfile=${out}`], { stdio: 'inherit' });
  console.log(`min: ${name}.js -> ${name}.min.js`);
  built++;
}
console.log(`Done. ${built} file(s) minified.`);
