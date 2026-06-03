# Quote — P3 feature specs (P3.9, P3.10, P3.12)

> Build-ready specs for the three open P3 items in
> [SPEC_QUOTE_UX_BACKLOG.md](SPEC_QUOTE_UX_BACKLOG.md). Grounded in the shipped B2B quote code
> (CPT `spbwc_quote`, `SPBWC_Quote` model, `SPBWC_Quote_Admin` workspace, `SPBWC_Request_Quote`
> storefront/My-Account, `class-quote-email-types.php`). Conventions: `spbwc_` prefix, text domain
> `storelly-product-builder-for-woocommerce`, nonce + capability on every action, design tokens,
> enqueue assets, Action Scheduler for cron. Effort: S ≈ <½ day · M ≈ ~1 day · L ≈ multi-day.

---

## P3.9 — Revision diff on counter-offers · M

### Why / what it solves
Negotiation can run several rounds. Today, when the merchant sends a **counter-offer** from a
`negotiating` quote, `SPBWC_Quote_Admin::maybe_handle_actions()` calls `save_reply()` (which
**overwrites** `_spbwc_quote_lines` / `_spbwc_quote_totals` / terms) and bumps
`_spbwc_quote_revision`. **The previous version is lost** — neither merchant nor buyer can see what
changed between v1 and v2. The `spbwc-q-superseded` status is registered but never used. P3.9 adds a
version history and a visible **diff** (added / removed / changed lines, old → new total) on both
sides.

### Current state (grounded)
- Meta `_spbwc_quote_revision` (int) bumped on counter — `class-quote-admin.php` ~line 159.
- `save_reply()` overwrites lines/totals/terms — `class-quote-admin.php`.
- `_spbwc_quote_change_request` (buyer asks) already rendered in admin via `render_change_request()`.
- No snapshot of prior versions; `STATUS_SUPERSEDED` unused.

### Data model
- New meta `_spbwc_quote_versions` = ordered array of immutable snapshots:
  ```php
  [ [ 'revision' => 1, 'lines' => [...], 'totals' => [...], 'valid_until' => 'Y-m-d',
      'payment_terms' => 'prepay', 'customer_note' => '…', 'sent_at' => 1717200000 ], … ]
  ```
  One entry pushed **each time a quote is sent/counter-sent** (i.e. on `→ sent`), capturing the
  state that was sent. The newest entry == the current live quote.
- Keep `_spbwc_quote_revision` as the count (== `count(_spbwc_quote_versions)`).

### Where it changes
- `SPBWC_Quote` model: add `push_version($id)` (snapshot current lines/totals/terms into the array)
  and `get_versions($id)` / `diff_versions($prev, $curr)`.
- `SPBWC_Quote_Admin::maybe_handle_actions()` — on `send`/`counter`, call `SPBWC_Quote::push_version()`
  **after** `save_reply()` but **before** the `→ sent` transition, so the snapshot reflects what was
  sent. (For the first send this stores v1; for a counter it stores v2 etc.)

### Diff algorithm (`diff_versions`)
- Match lines across versions by a stable key: `label` (case-insensitive, trimmed). Optional future:
  a hidden line id.
- Produce: `added[]` (in new, not old), `removed[]` (in old, not new), `changed[]` (same label,
  different qty or unit_price — record old/new), `unchanged[]`. Plus `total_old` / `total_new`.

### UX — admin (revising)
- On the detail of a `negotiating` quote (already shows the change-request card from P1.1), add a
  **"Previous quote (v{n−1})"** collapsible card showing the prior version's line table read-only, so
  the merchant prices the revision with full context.

### UX — buyer (receiving a revision)
- On `view-quote`, when `revision ≥ 2`, show a **"What changed since the last quote"** card above the
  line table: a compact list — `+ Added: …`, `− Removed: …`, `~ Changed: Foam board qty 120 → 100,
  unit $8.50 → $8.00`, and **Total $1,125 → $980**. Uses the same `.spbwc-rfq-*` tokens (pills:
  added=ok, removed=danger, changed=warn).

### Emails
- The `spbwc_quote_sent` email (revision ≥ 2) gains a one-line "This is a revised quote (v{n})" note +
  the old→new total. Optional: a short change list.

### Acceptance criteria
- Sending/counter-sending pushes an immutable snapshot; `_spbwc_quote_versions` grows by one per send.
- Buyer view on v≥2 shows added/removed/changed lines and old→new total, derived from the last two
  snapshots.
- Admin detail on `negotiating` shows the previous version read-only.
- Accepting still locks the **current** (latest) version; older snapshots stay read-only history.

### Edge cases
- v1 (first send): no diff card (nothing to compare) — show normal view.
- Identical re-send (no changes): diff card shows "No line changes; updated terms/validity" if only
  terms changed, else hide.
- Label collisions (two lines same label): fall back to index-based matching for that label group.

---

## P3.10 — Fuller Accept modal · S–M

### Why / what it solves
Today buyer **Accept** is a bare inline form (optional PO + "Accept & create order") in
`SPBWC_Request_Quote::render_buyer_actions()`. For a B2B commitment that spawns a payable order, the
buyer should explicitly confirm terms. P3.10 turns Accept into a clear confirmation step.

### Current state (grounded)
- `render_buyer_actions()` renders 3 cards (Accept / Request changes / Decline). Accept = PO text +
  submit `spbwc_quote_buyer_action=accept`.
- `handle_quote_action()` accept branch → `set_status(accepted)` → `spawn_order_from_quote($id,$po)`
  → `set_status(converted)` → emails → redirect to Pay.
- Quote terms already store `_spbwc_quote_payment_terms` (currently `prepay` only; deposit is v2).

### UX — the Accept confirmation
Replace the bare Accept card with a confirmation that shows, before the submit button:
1. **Order summary line**: grand total (and, when `payment_terms` is a deposit split in a future
   release, the deposit due now — read from terms; for now show full total).
2. **PO number** (optional) — existing.
3. **Terms checkbox (required)**: "I agree to the quote terms and [Terms & Conditions]." The link
   target is a new setting `spbwc_quote_settings['terms_url']` (Quote Settings → Get Quote tab); if
   empty, render plain text without a link. Submit is blocked (JS + server) until checked.
4. **Opt-in checkbox (optional)**: "Email me production / order updates."
5. Submit: **"Accept & create order"**.

### Server (`handle_quote_action` accept branch)
- Require `tos_accepted` present; if missing, redirect back with an error notice (`msg=tos`).
- Store on accept: `_spbwc_quote_tos_accepted_at` (timestamp), `_spbwc_quote_updates_optin`
  ('yes'/'no'). Carry the opt-in onto the spawned order as meta `_spbwc_order_updates_optin` so the
  store can honour it.
- Everything else unchanged (spawn order, converted, emails, Pay redirect).

### Settings
- Quote Settings → Get Quote tab: add **"Terms & Conditions URL"** (`terms_url`, `esc_url_raw`) to
  the existing `spbwc_quote_settings` save block.

### Acceptance criteria
- Accept cannot be submitted without ticking the ToS box (client + server enforced).
- ToS timestamp + updates opt-in stored on the quote; opt-in mirrored on the order.
- ToS label links to the configured Terms URL when set, plain text otherwise.
- Existing accept→order→Pay flow and emails still work.

### Edge cases
- No Terms URL configured → checkbox still required, label is plain text.
- Re-submitting accept on an already-`converted` quote → still guarded by the `sent`-only status
  check in `handle_quote_action()`.

### Effort note
S if we keep payment-method selection on the WooCommerce Pay page (recommended — WC already lists
gateways there). Adding gateway selection inside the modal is M+ and not needed for v1.

---

## P3.12 — Quote templates / quick-reply · M

### Why / what it solves
Merchants re-type the same lines (setup fee, rush, freight, volume discount) and terms on every
quote. P3.12 lets them **save a pricing reply as a reusable template** and **load** it into the
line-item builder with one click.

### Current state (grounded)
- Admin pricing reply (`SPBWC_Quote_Admin::render_detail()` + `save_reply()`) is fully manual.
- The detail already has a JS line-item builder (add/remove rows, live totals) and a Save / Send bar.

### Data model
- A lightweight CPT `spbwc_quote_template` (or a single option `spbwc_quote_templates` keyed by id —
  CPT preferred for scalability + author tracking). Each template stores:
  ```php
  '_spbwc_qt_lines'  => [ ['label','desc','qty','unit_price'], … ],
  '_spbwc_qt_terms'  => [ 'valid_days' => 14, 'payment_terms' => 'prepay', 'note' => '…' ],
  ```
  `post_title` = template name. Status: `publish`. Capability: `manage_woocommerce`.

### Where it changes
- New class `SPBWC_Quote_Template` (model: CRUD + `get_all()`), wired in the loader.
- `SPBWC_Quote_Admin::render_detail()` pricing-reply card:
  - **Load template** select (lists templates) + "Apply" → JS fills the line-item rows + terms from a
    localized data array (no reload), using the existing builder's row template.
  - **Save as template** button → opens a small inline name field → AJAX `spbwc_save_quote_template`
    (nonce + cap) that snapshots the current builder rows + terms into a new template.
- New AJAX handlers (admin-ajax): `spbwc_save_quote_template`, `spbwc_delete_quote_template` — nonce
  (`spbwc_quote_template`) + `current_user_can('manage_woocommerce')`.
- Management UI: a **"Templates"** tab on the **Quote Settings** page (list + rename + delete), reusing
  `.spbwc-admin-table` / `.spbwc-block` tokens. (Minimal v1: create from the detail, delete from the
  settings list.)

### UX flow
1. Merchant prices a quote, clicks **Save as template**, names it ("Banner — standard + rush").
2. On the next quote, picks it from **Load template → Apply**; rows + validity + terms populate; the
   merchant tweaks qty/price and sends.
3. Templates managed under Quote Settings → Templates.

### Acceptance criteria
- Save captures the current builder rows + terms into a named `spbwc_quote_template`.
- Load populates the builder (rows + validity preset + payment terms + note) via JS without a reload;
  live totals recompute.
- Delete/rename from the Quote Settings → Templates list.
- All template AJAX is nonce + `manage_woocommerce` gated; output escaped, input sanitized.

### Edge cases
- Empty template name → reject (notice).
- Loading a template does **not** auto-send — merchant reviews first.
- Deleting a template never affects already-sent quotes (templates are copy-on-apply, not linked).

### Effort note
M. The CPT + 2 AJAX endpoints + the load/save JS in the existing builder are the bulk; the settings
Templates tab is a small list table.

---

## Suggested build order
1. **P3.10** (S–M) — cheapest, closes a real B2B trust gap (explicit Accept).
2. **P3.9** (M) — needs the version snapshot model; high value for multi-round negotiation.
3. **P3.12** (M) — merchant time-saver; independent of the others.

All three are self-contained in the quote module (no changes to the concurrently-edited
`class-admin-options.php` except the P3.10 Terms-URL setting and the P3.12 Templates tab, which can
land once that file settles).
