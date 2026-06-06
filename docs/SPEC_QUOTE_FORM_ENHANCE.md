# SPEC — Quote Form Enhancements: File Upload + Product Picker / Multi-Product Cart

**Status:** Phase 1 (QF1–QF4) + Phase 2 (QF5–QF8) BUILT + browser-verified · QF9 POT done (RTL n/a for storefront)
**Date:** 2026-06-05
**Owner:** David / Netbase
**Related specs:** `docs/SPEC_QUOTE_USER_FLOW_UX.md` (Part C data model), `docs/SPEC_FREEMIUM.md` (F0–F5), `docs/SPEC_B2B_CLIENT.md`
**Touches flow:** Quote request (storefront) + Quote admin + Quote PDF/email → **impact report in §8 is mandatory reading before coding.**

---

## 1. Problem & Goal

Today the Request-a-Quote form (`SPBWC_Request_Quote`) is **bound to one product**: the
buyer opens the modal from a single product page, enters a `quantity` plus the
form-builder text fields, and submits. Two real gaps:

1. **No product selection.** A buyer cannot pick products from the catalog or
   request a quote for several items in one go. The data layer already reserves
   `_spbwc_quote_request['items'][]` (P4.3) but there is no UI for it.
2. **No file attachment.** B2B / web-to-print buyers routinely need to attach
   artwork, a spec sheet, a logo, or a reference PDF. The form builder only
   supports `text / email / tel / textarea / select`; there is no `file` type.

### Decisions locked with the user (2026-06-05)
- **Target model = Multi-product Quote Cart**, but **ship File Upload first** as a
  standalone increment (it is independent and unblocks the most-requested B2B use case).
- **File upload = full field** (multi-file, size/extension limits, drag-drop, shown
  in admin detail + attached to email + linked in PDF).
- Both features are **100% local → Free**, no phone-home, per `docs/SPEC_FREEMIUM.md`.

### Non-goals (this spec)
- No cloud storage / CDN for attachments (local `uploads/` only).
- No virus scanning beyond extension/MIME whitelisting.
- No payment in the quote flow (unchanged — accept→order handles that).

---

## 2. Reusable building blocks (already in the codebase)

| Block | Location | Reuse for |
|---|---|---|
| Form-field schema + render loop | `includes/class-request-quote.php` `get_form_fields()`, `render_quote_popup()` L388-413 | add `file` field type |
| Server upload + merchant constraints | `includes/class-frontend-options.php` L730-802 (`wp_handle_upload`, `allow_type`, `max_size`, `spbwc_widen_upload_mimes`) | quote attachment handler |
| Safe extension whitelist filter | `spbwc_upload_file_allowed_extensions` (L733) | reuse verbatim — never accept executables |
| Upload-constraints admin template | `views/options/templates/field-body/upload_option.php` (min/max MB + `allow_type`) | model the form-builder `file` config UI |
| Quote CPT + request meta | `includes/quote/class-quote-model.php` `META_REQUEST`, `create()` | store `attachments[]` / `items[]` |
| Admin request recap | `includes/quote/class-quote-admin.php` `render_detail()` (customer request recap ~L525) | render attachment list |
| Buyer view + PDF | `class-request-quote.php` `render_view_quote_endpoint()`, `includes/quote/class-quote-pdf.php` | show attachment links |
| Quote AJAX submit | `class-request-quote.php` `ajax_submit_quote()` L434 | extend for files + multi-item |

---

## 3. PHASE 1 — File Upload field (ship first) · **Free**

### 3.1 Form Builder (admin)
Add a new field **type `file`** to the Quote Form Builder
(`spbwc_quotes_manager()` in `includes/class-admin-options.php`, options key
`spbwc_quote_form_fields`). When `type=file`, expose extra config (mirror
`upload_option.php`):

- `allow_multiple` (checkbox) — allow several files in one field.
- `max_files` (int, default 5; only when multiple).
- `max_size` (MB, per file; 0 = no merchant cap, server hard cap still applies).
- `allow_type` (comma list, e.g. `pdf,ai,png,jpg`; intersected with the safe
  whitelist server-side).

Field array (stored in `spbwc_quote_form_fields`):
```php
'artwork' => array(
  'name' => 'artwork', 'type' => 'file', 'label' => 'Upload artwork',
  'placeholder' => '', 'validation' => '', 'required' => '0', 'enabled' => '1',
  'allow_multiple' => '1', 'max_files' => '5',
  'max_size' => '10', 'allow_type' => 'pdf,ai,png,jpg,svg',
),
```
Back-compat: existing fields have no `file` keys → render exactly as before.

### 3.2 Storefront form (buyer)
In `render_quote_popup()`, when `type=file`:
- Render a **drop-zone + Browse button** (`<input type="file">`, `multiple` when
  configured, `accept` from `allow_type`).
- Show selected files as chips (name + size + remove ✕), client-side count/size
  validation against `max_files` / `max_size` with inline errors (reuse
  `.spbwc-rfq-error`).
- Progressive enhancement: no JS → plain file input still submits.

**Submission transport.** The current submit is `jQuery`/`fetch` to
`admin-ajax.php` action `spbwc_submit_quote`. Switch the request to **`FormData`**
so files ride along (keep the same action + nonce). No new endpoint.

### 3.3 Server handling — `ajax_submit_quote()`
1. Nonce + product/quote-enabled checks unchanged.
2. After text-field validation, process `$_FILES['quote_files']` per file field:
   - Resolve constraints from the field's `allow_type` / `max_size` / `max_files`.
   - **Reuse the exact safe whitelist** (`spbwc_upload_file_allowed_extensions`)
     intersected with merchant `allow_type` — copy the proven logic from
     `class-frontend-options.php`, do not invent a new list.
   - Add+remove `spbwc_widen_upload_mimes` around `wp_handle_upload()`.
   - Store into a dedicated dir (override `upload_dir` to
     `uploads/spbwc-quote-attachments/<quote-or-hash>/`), `index.html` guard +
     reuse `.htaccess`/no-exec hardening already used for option uploads.
3. Required-but-empty file field → per-field error (same error envelope as text).
4. On success append to the request payload:
```php
$request['attachments'] = array(
  array( 'field' => 'artwork', 'name' => 'logo.ai',
         'url' => '<uploads url>', 'path' => '<abs/rel path>',
         'size' => 12345, 'type' => 'application/illustrator' ),
);
```
Stored in `META_REQUEST` (no new meta key needed for Phase 1).

> **Guest uploads:** quotes allow `nopriv` submit. Files from guests go to a
> hashed folder, not a user folder. Same MIME/extension hardening applies. A
> hard server cap (`spbwc_quote_max_upload_bytes`, default 25 MB total/request)
> guards against abuse regardless of merchant config.

### 3.4 Admin detail (merchant)
In `render_detail()` customer-request recap, render an **Attachments** block:
- List each file as a download link (`target=_blank rel=noopener`), with name +
  human size. Use `esc_url`. Icon by extension.
- If a file is missing on disk, show a muted "file removed" state (don't fatal).

### 3.5 Email + PDF
- **New-request email** (`spbwc_quote_new_notification`): list attachment names +
  links so the merchant can grab them from the inbox.
- **Quote PDF** (`class-quote-pdf.php`): list attachment file names under the
  request summary (link if the PDF context allows; otherwise name only — PDFs
  can't embed arbitrary binaries safely).

### 3.6 Cleanup
- On quote trash/delete, delete its attachment folder (hook
  `before_delete_post` for `spbwc_quote`).
- Daily Action Scheduler GC: orphaned guest folders older than N days with no
  quote → delete (reuse existing AS infra noted in memory `localhost_wpcron_fix`).

### Phase 1 acceptance
- [x] `file` field configurable in Form Builder, persists, back-compat intact. *(verified: type select + config sub-row toggle)*
- [x] Buyer can attach 1..N files within limits; bad type/size rejected client+server. *(drop-zone + chips + DataTransfer; server whitelist ∩ allow_type)*
- [x] Attachments visible + downloadable in admin detail.
- [x] New-request email + PDF reference attachments.
- [x] Guest upload hardened; no executable extensions ever accepted. *(verified end-to-end as guest/nopriv — file stored in `quote-attachments/q-<hash>/`, index.html guard)*
- [x] `wp plugin check` → 0 ERROR on changed files. *(POT regen deferred to QF9)*

**Build notes (2026-06-05):**
- Browser-verified the full flow (admin Form Builder + storefront modal + guest submit). Caught & fixed a PHP 8 fatal: `wp_handle_upload()` takes `$file` **by reference** — must assign the array to a variable, can't pass a literal.
- UX polish shipped alongside (token gaps + modal): sticky modal header/footer with scrollable body; XHR **upload progress bar** when files attached; on-blur client validation (required/email/phone); guest success-state hint. Perf badge count was already cached (`get_awaiting_count()`), no change needed.
- Token gaps fixed: removed non-existent `--nbd-st-surface-2` → `--nbd-st-bg-soft`; dropped raw-hex fallbacks in new storefront rules; added `--spbwc-rfq-radius-sm` / `--spbwc-rfq-radius-full`; drop-zone `:focus-within` ring.
- **Known minor:** a PHP fatal mid-upload (now fixed) could leave an empty guarded folder; graceful errors roll back fully. Daily orphan GC (QF4 follow-up) not yet scheduled.

---

## 4. PHASE 2 — Product Picker / Multi-Product Quote Cart · **Free**

Target end-state. Built on the `items[]` slot already reserved (P4.3).

### 4.1 Admin setting — Quote mode
Add to `spbwc_quote_settings`:
- `quote_mode` = `single` (default, current behavior) | `cart` (multi-product).
- `enable_quote_page` (bool) — publish a standalone **Request a Quote** page
  (shortcode `[spbwc_quote_request]`) where the buyer builds a list from scratch.

`single` keeps today's exact UX (zero regression for shops that want it).

### 4.2 Storefront — "Quote Cart"
When `quote_mode=cart`:
- Product page gains **"Add to quote"** next to / replacing the quote button
  (respects existing D3 display modes both/replace/quote_only).
- A **quote drawer** (reuse the floating badge anchor) shows item count.
- **Quote review** view (drawer panel or the standalone page) lists line items:
  product thumb, name, chosen variation/options snapshot, **editable qty**,
  remove. Then the contact form-builder fields + (Phase 1) file upload, submit once.
- **Add item via product search**: AJAX autocomplete over the catalog. Reuse
  WooCommerce's product search (`WC_AJAX` / `wc_get_products`) — do **not** build a
  new index. Respect quote-enabled products only (or all, behind a setting).

Persistence of the in-progress cart:
- Logged-in: user meta `_spbwc_quote_cart`.
- Guest: WC session (`WC()->session`) so it survives page nav, like the cart.

### 4.3 Data model — multi-item request
```php
$request['items'] = array(
  array( 'product_id' => 123, 'name' => 'T-Shirt', 'qty' => 50,
         'variation_id' => 0, 'options' => array(/* snapshot */) ),
  array( 'product_id' => 0,  'name' => 'Custom banner', 'qty' => 2,
         'note' => 'free-text item' ),
);
```
- `ajax_submit_quote()` branches on `quote_mode`: in `cart` mode it validates the
  items array and **pre-seeds one quote line per item** via `SPBWC_Quote::set_lines()`
  (today it seeds a single line). Single mode path unchanged.
- `_spbwc_quote_product_id` kept for the primary/first product (back-compat with
  reporting + existing admin code).
- Free-text items (`product_id=0`) allowed so a buyer can request something not in
  the catalog.

### 4.4 Admin detail
- Request recap already renders `items[]` (P4.3 stub at `class-quote-admin.php`
  ~L806). Promote it to a proper table; the pricing-reply line editor stays the
  source of truth for prices (merchant can edit the seeded lines).

### Phase 2 acceptance
- [x] `quote_mode` toggle (Quote Settings ▸ Get Quote); `single` = current behavior (default). *(verified: cart mode shows Add-to-quote + FAB + bucket modal, single popup suppressed; single mode unchanged)*
- [x] Add-to-quote + drawer + product search picker; qty edit + remove. *(bucket existed P4.3; QF6 added `spbwc_bucket_search` via `wc_get_products`, `spbwc_bucket_setqty`, qty steppers)*
- [x] Standalone `[spbwc_quote_request]` page builds a quote with no source product. *(shortcode renders intro + opens the site-wide cart modal; verified via do_shortcode)*
- [x] Guest cart persists via WC session. *(existing `SPBWC_Quote_Bucket` session store; add verified count:1)*
- [x] Multi-item request seeds N pricing lines; admin recap shows all items. *(existing `set_lines` + recap items[] loop)*
- [x] Carries Phase-1 file upload through unchanged. *(bucket form renders the `file` field + reuses the shared public `handle_quote_uploads()`; bucket.js gained drop-zone/chips + FormData submit)*
- [x] POT regenerated with the new strings. *(`wp plugin check` clean on Phase-1 files; Phase-2 self-audit clean)*

**Phase 2 build notes (2026-06-05):**
- The quote bucket (`includes/quote/class-quote-bucket.php` + `quote-bucket.js` + `.spbwc-bucket-*` CSS) already existed from P4.3 (collect from product pages → submit multi-line quote). Phase 2 wired it behind the `quote_mode` setting, added a product-search picker + qty steppers + file-upload carry-through, and a standalone shortcode.
- Gating: `SPBWC_Request_Quote::quote_mode()` (single|cart) + `quote_page_enabled()`. In cart mode the single per-product popup/button are suppressed; the bucket (`SPBWC_Quote_Bucket::cart_enabled()`) renders instead. Single mode = zero change.
- `handle_quote_uploads()` made public so the bucket reuses the exact hardened upload path.

### QF9
- [x] POT regen (`wp i18n make-pot`) — new strings present.
- [x] Orphan-attachment GC: daily `spbwc_quote_attachment_gc` event deletes unreferenced `quote-attachments/` folders older than `spbwc_quote_attachment_retention_days` (30).
- RTL: storefront CSS is direction-neutral (no `*-rtl` variant); admin config-row CSS lands with the pending admin-CSS batch. Full RTL regen belongs to the next release i18n pass.

---

## 5. UX / UI detail

- **Modal vs page.** Single mode keeps the modal. Cart mode: drawer for
  add/review on product/shop pages, plus a full page for the standalone flow.
- **Drop-zone**: dashed border, "Drag files here or browse", live chips, per-file
  progress, remove ✕. Matches existing `.spbwc-rfq-*` tokens
  (`_tokens-storefront.css` / `quote-storefront.css`).
- **Empty states**: empty quote cart → "No items yet. Browse products to add."
- **Errors**: inline per field (existing pattern) + a top alert (`.spbwc-rfq-alert`).
- **A11y**: file input labelled; drawer is a `dialog` with focus trap (reuse modal
  a11y already implemented); chips are removable via keyboard.
- **Mobile**: drawer becomes a bottom-sheet (consistent with designer canvas v2.5).

---

## 6. Files to touch (estimate)

**Phase 1**
- `includes/class-request-quote.php` — render `file` field, `FormData` submit, server upload, attachment in payload + email/PDF hooks.
- `includes/class-admin-options.php` — Form Builder `file` type + config UI.
- `includes/quote/class-quote-admin.php` — attachment block in recap.
- `includes/quote/class-quote-pdf.php` — attachment names.
- `static/js/quote-storefront.js` + `static/css/quote-storefront.css` — drop-zone UX.
- (maybe) small shared upload helper extracted from `class-frontend-options.php` to avoid copy-paste.

**Phase 2**
- Above + `quote_mode`/`enable_quote_page` settings, drawer markup, product-search AJAX, WC-session cart, shortcode, multi-item submit branch.

---

## 7. Milestones

| ID | Scope | Tier |
|---|---|---|
| **QF1** | Form Builder `file` type + config persistence | Free |
| **QF2** | Storefront drop-zone + `FormData` submit + server upload (hardened) | Free |
| **QF3** | Admin recap attachments + email + PDF references | Free |
| **QF4** | Cleanup/GC on delete + guest folder GC | Free |
| **QF5** | `quote_mode` setting + add-to-quote + drawer | Free |
| **QF6** | Product-search picker + qty/remove + WC-session/user-meta cart | Free |
| **QF7** | Standalone `[spbwc_quote_request]` page + free-text items | Free |
| **QF8** | Multi-item submit → N pricing lines + admin recap table | Free |
| **QF9** | Compliance: plugin check 0 ERROR, POT regen, readme | — |

Phase 1 = QF1–QF4. Phase 2 = QF5–QF8. QF9 closes each phase.

---

## 8. Impact report (mandatory per CLAUDE.md — large flow)

- **WooCommerce flow:** none of cart/checkout/order is touched in Phase 1. Phase 2
  adds an *independent* quote cart (WC session namespace `_spbwc_quote_cart`) that
  never mixes with the real WC cart; accept→order conversion path is unchanged.
- **Quote sync / B2B:** request payload only **gains** keys (`attachments[]`,
  richer `items[]`); existing readers that ignore unknown keys keep working.
  `_spbwc_quote_product_id` retained for back-compat.
- **Single-product mode stays default** → existing installs see no behavior change
  until a merchant opts into `cart`/`file`.
- **Security surface (new):** file uploads from `nopriv`. Mitigations: reuse proven
  whitelist + MIME widening scoped around the single `wp_handle_upload` call, hard
  per-request byte cap, no-exec upload dir, GC of orphans. This is the main risk —
  review QF2 carefully.
- **Compliance:** local-only, Free, no external service. No readme "external
  services" change. Advertised features must exist before mention (per CLAUDE.md).
- **Perf:** product-search AJAX reuses Woo's query; cart in session = negligible.

---

## 9. Open questions for build time
1. Product search scope: **quote-enabled products only**, or the whole catalog
   with a per-product fallback? (Lean: setting, default = quote-enabled only.)
2. Allow free-text/non-catalog items in cart mode from day one, or Phase 2.1?
3. PDF: link attachments (clickable) or names only? (Lean: names + clickable when
   the PDF renderer supports links.)
4. Guest attachment retention window for GC (default 30 days?).
