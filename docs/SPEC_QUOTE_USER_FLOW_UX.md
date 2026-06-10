# Request a Quote — User Flow & UX/UI Spec

> Status: **As-built audit + UX redesign proposal**
> Feature area: "Get Quote" / Request a Quote (RAQ)
> Owner module: `includes/class-request-quote.php`, Quotes admin in `includes/class-admin-options.php`
> Last updated: 2026-06-01

This spec documents the Quote feature **exactly as it exists today** (Part A), maps every
**user flow and screen** (Part B), then proposes a **UX/UI redesign** with open decisions to
resolve before building (Part C). Everything in Part A is grounded in current code with
`file:line` references; Part C is explicitly labelled as NOT-yet-built.

---

## 1. Purpose

Let a merchant turn any WooCommerce product into a "Request a Quote" product: the storefront
shows a **Get Quote** button instead of (alongside) Add-to-Cart, the buyer fills a configurable
form in a popup, and a **quote** is created as a WooCommerce order in a custom status. The buyer
can review and **Accept / Reject** the quote from My Account; the merchant manages settings, the
form, and submitted requests from a dedicated **Quotes** admin page.

Target use case: B2B / made-to-order / "price on request" products where direct checkout is not
desired.

---

## 2. Where the feature lives

| Surface | Entry point | Code |
| --- | --- | --- |
| Storefront — single product | `Get Quote` button under Add-to-Cart | `render_quote_button()` → `woocommerce_after_add_to_cart_button` (prio 25), `class-request-quote.php:27,87` |
| Storefront — popup form | Modal in footer | `render_quote_popup()` → `wp_footer`, `class-request-quote.php:28,98` |
| Storefront — submit | AJAX `spbwc_submit_quote` | `ajax_submit_quote()`, `class-request-quote.php:30,179` |
| My Account — list | `/my-account/quotes/` endpoint + nav item | `render_quotes_endpoint()`, `class-request-quote.php:23,367` |
| My Account — detail | `/my-account/view-quote/{id}/` endpoint | `render_view_quote_endpoint()`, `class-request-quote.php:24,407` |
| My Account — accept/reject | `?spbwc_quote_action=accept|reject` | `handle_quote_action()`, `class-request-quote.php:25,449` |
| Admin — Quotes page | WP admin menu → Quotes (3 tabs) | `spbwc_quotes_manager()`, `class-admin-options.php:1009` |
| Product editor | "Enable Request Quote" checkbox | meta box, `class-admin-options.php:2824,2997`; `views/options/meta-box.php:18-22` |
| Email | Admin + customer plain-text notifications | `send_quote_notification_email()` / `send_quote_customer_email()`, `class-request-quote.php:278,294` |

---

## 3. Data model (as-built)

### Options (`wp_options`)
- `spbwc_quote_settings` — `{ enable_quote: 'yes'|'no', admin_email, success_message }`.
  Global on/off switch + admin recipient + post-submit message. (`class-admin-options.php:1022-1027`)
- `spbwc_quote_form_fields` — ordered map of field descriptors (see §4). Defaults seeded by
  `spbwc_get_default_quote_form_fields()` (`class-admin-options.php:1573`) and mirrored in
  `SPBWC_Request_Quote::get_form_fields()` (`class-request-quote.php:34`).

### Product meta
- `_spbwc_enable_quote` — `'1'` or empty. Per-product opt-in. Quote button only renders when the
  **global** switch is `yes` AND this meta is `'1'` (`is_product_quote_enabled()`, `class-request-quote.php:79`).

### Quote = WooCommerce order (no custom table)
A submitted quote is a real `shop_order` created via `wc_create_order()` with a custom status.
Meta written on submit (`class-request-quote.php:247-254`):

| Meta key | Meaning | Notes |
| --- | --- | --- |
| `_spbwc_quote_request` | Buyer message text | "new" format |
| `_spbwc_quote_fields` | Full sanitized field map | "new" format |
| `_spbwc_quote_product_id` | Quoted product ID | single product only |
| `_spbwc_quote_quantity` | Requested qty | |
| `_raq_request` | Copy of field map | legacy/dual-write |
| `_raq_customer_name` | `first_name + last_name` | legacy |
| `_raq_customer_email` | Email | legacy |
| `_raq_customer_message` | Message | legacy |

> **Dual-write** to `_spbwc_*` and `_raq_*` keeps a legacy "RAQ" reader compatible. History tab
> queries either key with an `OR` meta_query (`class-admin-options.php:1318-1328`).

### Custom order statuses (`register_quote_order_statuses()`, `class-request-quote.php:304`)
- `wc-spbwc-quote-new` — New Quote Request (created on submit)
- `wc-spbwc-quote-accepted` — Accepted Quote (buyer accepted)
- `wc-spbwc-quote-rejected` — Rejected Quote (buyer rejected)

Registered into the WC status list via `wc_order_statuses` filter (`class-request-quote.php:343`).

---

## 4. Form field descriptor shape

Each entry in `spbwc_quote_form_fields` (key = field `name`):

```php
'company' => array(
  'name'        => 'company',      // sanitize_key, used as POST key & meta key
  'type'        => 'text',         // text | email | tel | textarea | select
  'label'       => 'Company',      // user-facing label
  'placeholder' => '',             // input placeholder
  'validation'  => '',             // '' | email | phone
  'required'    => '1',            // '1' | '0'
  'enabled'     => '1',            // '1' | '0' — disabled fields are not rendered/validated
)
```

Default set: `first_name` (req), `last_name` (req), `email` (req, email-validated),
`message` (textarea, optional). (`class-request-quote.php:37-74`)

**Known type gap:** the form builder offers `select` as a type but there is **no options editor**
and the storefront renderer has **no `select` branch** — a `select` field renders as a plain text
input (`class-request-quote.php:138-142`). See §11.

---

## 5. User flows

### 5.1 Storefront buyer — happy path
```
Product page (quote-enabled)
  → sees [Add to cart] + [Get Quote]
  → clicks Get Quote
  → modal opens: Quantity + configured fields
  → fills required fields → Submit Quote
  → "Sending…" → AJAX spbwc_submit_quote
  → success message shown in modal
       ├─ logged in  → redirect to /my-account/view-quote/{id}/
       └─ guest      → message + form reset (stays on page)
  → admin email + (if email captured) customer email sent
```
Server side: nonce check → product/quote-enabled check → per-field required/email/phone validation
→ `wc_create_order(status: spbwc-quote-new)` → add product + qty → write meta → calc totals → save
→ emails → JSON success with `message`, `order_id`, `redirect`. (`class-request-quote.php:179-275`)

### 5.2 Storefront buyer — validation error
Required empty / bad email / bad phone → `wp_send_json_error` with a concatenated message; modal
shows it in red, form stays open with entered values intact. (`class-request-quote.php:198-224`,
JS `class-request-quote.php:166-168`)

### 5.3 My Account — review & decide (logged-in only)
```
My Account → Quotes (nav item, before Logout)
  → table of this customer's quotes: Quote # | Date | Status | Total | View
  → View → /my-account/view-quote/{id}/
       → shows Status, Message, Items, [Accept Quote] [Reject Quote]
       → Accept → status = accepted, order note, emails, redirect back
       → Reject → status = rejected, order note, emails, redirect back
```
Ownership enforced by `customer_id === current_user_id` on both detail render and action handler
(`class-request-quote.php:415,463`). Actions are nonce-guarded
(`spbwc_quote_action_{id}`, `class-request-quote.php:459`).

### 5.4 Admin merchant
```
WP Admin → Quotes
  ├─ Tab "Get Quote"      → enable toggle, notification email, success message
  ├─ Tab "Form Builder"   → add/remove/reorder* fields, set type/label/placeholder/validation/required/enabled
  └─ Tab "Request History"→ search (name/email), paginated list, View → WC order edit screen
```
Per-product enablement happens separately in the **product editor** meta box, not on this page.
Tab switching is JS-only (no reload); settings/form saves go through an AJAX `fetch` that re-posts
the form and surfaces a toast (`class-admin-options.php:1505-1545`).
(*reorder: drag handle is rendered but sortable is **not wired** — see §11.)

---

## 6. Screen-by-screen UX/UI (as-built)

### 6.1 Storefront — Get Quote button
- Markup: `<p><button class="button alt" id="spbwc-open-quote-popup">Get Quote</button></p>`
  (`class-request-quote.php:95`). Inherits theme button styling; no plugin CSS.
- Position: after Add-to-Cart (`woocommerce_after_add_to_cart_button`, prio 25).
- **Gap:** button always sits *next to* Add-to-Cart; there is no "quote replaces purchase" mode
  (hide price / hide Add-to-Cart).

### 6.2 Storefront — Quote popup (modal)
- Fully **inline-styled** overlay (`class-request-quote.php:108-148`): fixed, `rgba(0,0,0,.6)`
  backdrop, 640px white card, `6vh` top margin, `88vh` max-height, scroll.
- Header: `Request a Quote` + an `x` text button. Message slot `#spbwc-quote-message`.
- Body: Quantity (number, min 1) then each enabled field; required marked with red `*`.
- Footer: `Submit Quote` primary button.
- Behaviour (inline jQuery, `class-request-quote.php:149-175`): open on button click, close on `x`
  or backdrop click, submit via `$.post` to `admin-ajax.php`, inline "Sending…/Success/Failed".
- **Gaps:** no ESC-to-close, no focus trap / `aria-modal`, no body scroll-lock, no product
  context (title/thumbnail/price) inside the modal, all styling inline (no design-token reuse,
  not theme-overridable), jQuery dependency assumed.

### 6.3 My Account — Quotes list
- `<h2>My Quotes</h2>` + WC-styled `shop_table`. Columns: Quote # | Date | Status | Total | View.
  Empty state: "No quotes found." (`class-request-quote.php:382-404`).
- Query: `_spbwc_quote_request EXISTS`, limit 50. (`class-request-quote.php:372-381`)
- **Gaps:** list uses only the `_spbwc_*` key (legacy `_raq_*`-only quotes won't appear here even
  though History does show them); no status filter; "Total" is usually the surcharge/0, which can
  read as misleading for a quote.

### 6.4 My Account — Quote detail
- Heading `Quote #{id}`, Status, Message, Items list, `Accept Quote` / `Reject Quote` buttons
  (`class-request-quote.php:436-446`).
- **Gaps:** Accept/Reject buttons always shown even after a decision (no terminal-state lock); no
  full submitted-field recap (only `message`); no price/quote breakdown; plain `.button` styling.

### 6.5 Admin — Quotes page (3 tabs)
- Modern design-token chrome already in place: hero header, `nav-tab` wrapper, `.spbwc-block`
  cards, `.spbwc-setting-row` layout, toast on save, empty-state component
  (`class-admin-options.php:1087-1570`). This is the **most polished** surface today.
- **Get Quote tab:** Yes/No radio, Notification Email, Success Message textarea.
- **Form Builder tab:** toolbar (new field name + Add), editable table (name/type/label/
  placeholder/validation/required/enabled + trash), Save.
- **Request History tab:** search bar (name/email), result count, table
  (Quote # | Customer | Email | Message[14 words] | Date | View→order edit), pagination (20/page).
- **Gaps:** drag-reorder not functional; History "View" leaves the plugin for the native WC order
  screen (no quote-tailored detail / reply UI); no status column or status filter in History; no
  bulk actions; no per-product enablement shortcut from this page.

---

## 7. Email (as-built)
Plain-text `wp_mail`, subject `[Storelly Quote] #{id} - {EVENT}` where EVENT ∈ NEW/ACCEPTED/REJECTED.
- Admin mail: order #, status, customer, email, message, admin edit URL (`class-request-quote.php:278-292`).
- Customer mail: greeting + status line + view-quote link (`class-request-quote.php:294-302`).
- **Gaps:** not WooCommerce-templated (no header/footer/branding), no HTML, not in WC → Emails
  settings, no per-event subject/body customization, no merchant "send revised quote/price" mail.

---

## 8. Security & i18n (as-built — keep)
- Storefront submit: nonce `spbwc_submit_quote_action`; logged-in + nopriv supported.
- Accept/Reject: nonce `spbwc_quote_action_{id}` + ownership check.
- Admin page: `current_user_can('spbwc_manage_product_builder')`; saves use `check_admin_referer`.
- Sanitization: `absint`, `sanitize_key`, `sanitize_text_field`, `sanitize_email`+`is_email`,
  `sanitize_textarea_field`; output escaped (`esc_html`/`esc_attr`/`esc_url`/`wp_kses_post`).
- All strings use text domain `storelly-product-builder-for-woocommerce` with translator comments.

---

## 9. Overview dashboard reference
`views/overview.php` reports a quote count and references a table `wp_storelly_quote_requests`
that is **never created** — it guards on table existence and falls back to order-meta counts. A
`$recent_quotes_l` variable is declared but never rendered. Treat as orphaned scaffolding; do not
advertise a quotes DB table.

---

## 10. State model (consolidated)

```
                 submit (storefront)
                        │
                        ▼
              ┌───────────────────┐
              │ spbwc-quote-new   │  ← buyer message, fields, product, qty
              └─────────┬─────────┘
            accept ┌────┴────┐ reject
                   ▼         ▼
        spbwc-quote-accepted  spbwc-quote-rejected   (both terminal today)
```
There is **no** merchant-driven transition (e.g. "quoted/priced", "sent", "expired") and **no
quote→paid-order conversion**: Accept only flips a status flag (`class-request-quote.php:467-481`).

---

## 11. Known limitations / scaffolding (documented)
1. **`select` field type is non-functional** — builder lists it, no options editor, renders as text.
2. **Form Builder reorder not wired** — drag handle present, no jQuery UI sortable bound (`class-admin-options.php:1272-1300`).
3. **Single product per quote** — no multi-item / quote-cart; one product + qty only.
4. **No merchant pricing reply** — merchant can't set a quoted price or send a revised offer; Accept/Reject act on the original order total.
5. **No quote→order conversion** — accepted quote never becomes a payable order.
6. **My-Account list vs History key mismatch** — list keys on `_spbwc_quote_request` only; History keys on either. Legacy-only quotes are invisible in My Account.
7. **Accept/Reject never lock** — buttons stay live after a terminal decision.
8. **Modal a11y/UX gaps** — no focus trap, ESC, scroll-lock, or product context; all inline CSS.
9. **Emails are bare** — not WC-templated, plain text only.
10. **No expiry / SLA / reminders**, **no bulk admin actions**, **no CSV export**.
11. **Orphaned dashboard scaffolding** — `wp_storelly_quote_requests` table + `$recent_quotes_l`.

---

## Part C — Redesign: B2B Quote with D2 (merchant pricing reply) + D3 (display mode)

> **D2 and D3 are CONFIRMED in scope.** This part is a build-ready design, benchmarked against the
> Printcart B2B mockups (`printcart-store-wp-admin-v3.0.1.html`, `printcart-store-user-account-v2.5.1.html`)
> and supporting specs. Patterns are adapted to WooCommerce-native, wp.org-compliant constraints —
> Printcart's Cloud/iframe/multi-tenant architecture is **out of scope**; only its flows, states,
> labels, and screen anatomy are borrowed. Per the team rule, the remaining **Open decisions (§18)**
> should be confirmed before coding.

### 12. The core insight (why D2 is the missing middle)
Today the buyer's **Accept / Reject** responds to *nothing priced* — the merchant never quoted a
price. In every Printcart B2B flow, "Accept" means **"I accept the merchant's quoted price."** So
D2 is not an add-on; it is the missing middle that makes the existing Accept/Reject meaningful:

```
  Buyer requests  →  [ D2: Merchant prices & sends ]  →  Buyer accepts the price  →  Pay/convert
   (have today)            (the gap)                        (have today, now real)
```

### 13. UX goals
- Make a quote a **conversation with clear next-step ownership** at every state (buyer vs merchant).
- **Protect B2C conversion** (Storelly `storelly-flows-spec.md` RULE-C1: *Get Quote is a SECONDARY
  CTA, must not cannibalize Add to Cart*). D3 defaults must not hide buy/price unless asked.
- **Quote stays unpaid, no stock decrement** until conversion (RULE-C2) — keep current behaviour.
- Reuse existing admin **design tokens** (`_tokens.css`, `.spbwc-block`, toast, empty-state);
  enqueue a real storefront stylesheet (kill inline modal CSS).
- wp.org compliance: no phone-home, nonce+cap on every action, escape/sanitize, single `spbwc_`
  prefix, text-domain discipline.

### 14. Quote data model + state machine — **CONFIRMED: dedicated CPT, full negotiation in v1**

> **Decisions locked (2026-06-01):** OD-10 → quote is a **dedicated CPT `spbwc_quote`** (NOT a WC
> order). OD-2 → the **counter-offer / negotiation loop ships in v1**. OD-1 → Accept **spawns a NEW
> WC order** linked back to the quote. OD-5 → **all four D3 display modes ship in v1**.
> Net effect: this is a **maximal-B2B v1**, materially bigger than the WC-order-reuse path.

**14.1 Data model (CPT `spbwc_quote`).** A quote is a first-class post (mirrors the Printcart
`Q-YYYY-NNNN` entity), NOT a WooCommerce order. Consequence: we **do not** get WC's native line-item
editor for free — the pricing reply (§15) needs its **own repeatable line-item store + UI**.

| Storage | Holds |
| --- | --- |
| `spbwc_quote` post (post_status = quote status) | the quote itself; `post_title` = human ID `Q-YYYY-NNNN` (OD-9) |
| `_spbwc_quote_request` meta | buyer's submitted fields (project/qty/specs/message/contact), product ref + design ref |
| `_spbwc_quote_lines` meta (repeatable) | merchant line items: `{label, desc, qty, unit_price, line_total}` |
| `_spbwc_quote_totals` meta | `{subtotal, discount, tax, total, currency}` |
| `_spbwc_quote_valid_until` / `_payment_terms` / `_customer_note` | terms card |
| `_spbwc_quote_revision` + `_spbwc_quote_parent` | revision counter + supersede link (v1, since negotiation is in) |
| `_spbwc_quote_order_id` | linked WC order once converted (OD-1) |
| WP comments on the post | activity timeline + internal notes (admin-only) |

**14.2 State machine (CPT post statuses, full loop in v1).** All statuses below are **v1**:

| Status (slug) | Meaning | Set by | Trigger |
| --- | --- | --- | --- |
| `spbwc-q-new` | Request received, no price | system | buyer submits Get Quote |
| `spbwc-q-review` | Merchant opened/claimed | merchant | admin "Mark in review" (auto on open) |
| `spbwc-q-sent` | Priced + sent, awaiting buyer | merchant | **admin "Send pricing reply" (D2)** |
| `spbwc-q-negotiating` | Buyer requested changes | buyer | buyer "Request changes" |
| `spbwc-q-superseded` | Old revision replaced by a newer one | system | on sending a revised quote (vN→vN+1) |
| `spbwc-q-accepted` | Buyer accepted the price | buyer | buyer "Accept" |
| `spbwc-q-converted` | A NEW WC order was created from this quote | system | on accept (auto-spawn order) |
| `spbwc-q-declined` | Buyer rejected (terminal) | buyer | buyer "Decline" + reason |
| `spbwc-q-expired` | `valid_until` passed (terminal) | system | Action Scheduler sweep |
| `spbwc-q-withdrawn` | Merchant cancelled offer (terminal) | merchant | admin "Withdraw" |

```
 new ─"Mark in review"─▶ review ─"Send pricing reply"(D2)─▶ sent ──────────────┐
                            ▲                                 │                  │
                            │              Request changes │  │ Accept   Decline │ (timeout)
              merchant revises (new version)               ▼  ▼          ▼       ▼
                            └──────── negotiating       accepted     declined  expired
                          (prior version → superseded)     │
                                                  auto-spawn NEW WC order
                                                           ▼
                                                       converted ──▶ buyer pays new order
```
Terminal: `converted` (success), `declined`, `expired`, `withdrawn`. `superseded` marks replaced
revisions. **Revisioning (ADR-012 "always a new version, never overwrite"):** each merchant
re-quote bumps `_spbwc_quote_revision`; the prior snapshot flips to `superseded` and stays readable.

### 15. D2 — Merchant pricing reply (detailed)

**15.1 Where it lives (admin).** Because the quote is a **CPT** (OD-10), the reply lives on the
**`spbwc_quote` edit screen** — a custom two-column layout (mockup `quote-view` admin): left =
buyer's request recap + **line-item builder** + terms; right sidebar = customer card + status
timeline (WP comments) + quick actions. The plugin already ships a polished Quotes admin (hero,
`.spbwc-block`, status tabs, KPI-ready empty-state) — extend it into a **Custom Quotes list**
(`WP_List_Table` or a `spbwc_quote` admin-list) with the mockup columns: *Quote # · Customer ·
Request summary · Est. value · Status pill · Updated · Expires · Actions*, status tabs with counts,
and bulk actions (Mark in review / Send / Mark expired / Archive). The current "Request History"
tab becomes this list.

> ⚠ **CPT cost:** no native WC line-item editor — build a **custom repeatable line-item table**
> (add/remove rows, editable unit price, live subtotal/tax/total) bound to `_spbwc_quote_lines`.

**15.2 Reply form anatomy** (from the mockup "Quote line items" builder + "Quote terms" card):
- **Line items table** — *Description · qty · unit price (editable, overrides catalog) · line total*
  + **[+ Add line item]**. Seed rows from the requested Storelly product + selected options + design
  reference. Merchant can add custom lines — mockup uses exactly **Setup & color matching · Rush
  production · Freight**.
- **Discount** — one **manual** order/line discount line (e.g. "Volume discount 8% off 50+ units").
  ⚠ NOT an auto engine — Storelly `quantity_breaks` has no engine ([[quantity_breaks_no_engine]]);
  do not auto-apply or advertise "save %".
- **Shipping / freight** — explicit line item (mockup models freight as a line, not a calc).
- **Tax** — WooCommerce computes per its tax settings; shown as a line (mockup: "Texas state tax 8.25%").
- **Validity / "Valid until"** — preset **7 / 14 / 30 / 60 days / custom date** → stored as
  `_spbwc_quote_valid_until`; drives `expired`. Default **14 days** (mockup default).
- **Payment terms** *(v1 = Pre-pay only; v2 = radio Pre-pay / 50-50 deposit / Net-30 / Custom)* →
  `_spbwc_quote_payment_terms`.
- **Customer-visible note** (terms/message shown to buyer) + **Internal note** (admin-only → native
  WC private order note).
- Buttons: **Save draft** · **Send pricing reply** (→ `spbwc-q-sent`) · **Send counter-offer**
  (when responding to a `negotiating` quote — bumps revision, supersedes prior) · **Save as template**
  (v2).
- **Document-action toolbar** (shipped) — **Preview customer view** + **Download / Print** are
  grouped as one right-aligned segmented toolbar in the detail top bar (`.spbwc-q-detailbar__tools`,
  `role="group"`), with **Back to all quotes** kept on the leading edge. They are document/output
  actions (review before sending, export the PDF), deliberately separated from the form's primary
  submit cluster (**Save draft** / **Send pricing reply**) in the action bar below. Stacks
  full-width under ~600px.

**15.2.1 Action submission — AJAX + UX (shipped):**
The action bar (Save draft / Send pricing reply / Send counter-offer / Withdraw) submits via
**AJAX** (`wp_ajax_spbwc_quote_action`, nonce `spbwc_quote_action` + `manage_woocommerce`) with **no
page reload**; the plain POST form is kept as the no-JS fallback. The shared core
`do_quote_action()` runs identically for both paths. On success the JSON response patches the **status
pill**, **activity timeline** and **editable** state in place; after Send/Withdraw the form is locked
client-side (inputs disabled, action bar → locked hint). Notifications use the tokenized
**`spbwcDialog.toast()`** (success/error, RTL-safe, auto-dismiss) — no WP core `.notice` on the JS path.
- **Sticky action bar** (`.spbwc-q-actionblock` `position:sticky; bottom`) keeps Save/Send reachable
  on a long form; falls back to static under 782px.
- **Dirty-state badge** (`.spbwc-q-savestate`): *Unsaved changes* (amber) → *Saving…/Sending…* (pulse)
  → *Saved* (green), plus a `beforeunload` guard while edits are unsaved & quote editable.
- **Confirm before Send / Withdraw** via `spbwcDialog.confirm()` — Send shows **Quote total +
  recipient email**; Withdraw is a `danger`-toned confirm. Send/counter are **guarded**: blocked with
  a warning toast when the total is **0** or a priced line is **missing a name**.
- **Default 14-day validity** auto-applied to a brand-new quote with no date set; **Ctrl/⌘+S** =
  Save draft.
- **Live Quote value** mirrored into the sidebar Overview card (`#spbwc-q-overview-total`,
  `wc_price` initial + `recalc()` live) so the total stays in view while scrolling the form.
- **Optimistic timeline**: each action prepends a muted, pulsing *“…”* entry to the Activity
  feed immediately (`--pending`), superseded by the server `activity_html` on success (removed on
  error) — the log feels instant.
- **Inline line validation**: the Send guard highlights the exact line(s) missing a name
  (`.spbwc-q-line-input--error`), scrolls to and focuses the first, and clears the mark as soon as
  the merchant edits it — not just a generic toast.

**15.3 On "Send pricing reply":**
- `_spbwc_quote_lines` written to quoted lines at quoted unit prices; `_spbwc_quote_totals` recomputed.
- Meta: `_spbwc_quote_valid_until`, `_spbwc_quote_payment_terms`, `_spbwc_quote_revision`.
- Status `new/review → spbwc-q-sent` (or, when re-quoting from `negotiating`: prior version →
  `superseded`, new version → `sent`, revision++).
- Quote comment logged ("Quote sent by {user}") = the activity timeline.
- **No payment, no stock decrement** — nothing payable exists until conversion (a WC order is only
  created on Accept, per OD-1).

**15.4 Buyer side (My Account → Quotes detail) after D2:**
Renders the **priced** quote (mockup `quote-view`): hero (project + total), **validity countdown**
("14 days remaining"), line-item table ("What's included"), totals (subtotal/discount/tax/**Total**),
terms card, status timeline, and an action bar — **Accept · Decline · Request changes** (all v1).
- **Accept** → status `accepted`; **lock the quoted snapshot**; then **spawn a NEW WC order** (OD-1)
  from `_spbwc_quote_lines` (custom line items, qty, unit prices), set `_spbwc_quote_order_id` ↔
  order's `_spbwc_source_quote` (two-way link), capture optional **PO number** → order meta; quote →
  `converted`. Route buyer to WooCommerce **"Pay for order"** / checkout for the new order.
- **Decline** → `declined` + reason (mockup taxonomy, reuse as select options): *Price too high /
  Timeline doesn't work / Going with another vendor / Project cancelled / Budget changed / Other* +
  feedback. Notify merchant.
- **Request changes** → `negotiating` + checkbox asks (mockup): *Reduce quantity / Different material /
  Different dimensions / Skip rush production / Change shipping method / Adjust payment terms (Net-30) /
  Other* + details. Re-opens the merchant reply (§15.2) for a revision round (→ new version, prior
  `superseded`).

**15.5 Emails (D2 needs proper `WC_Email` subclasses, not the current plain text):**
- `quote.created` → merchant "New quote request".
- `quote.sent` → buyer **"Your quote is ready"** — quoted total, **"Valid until {date}"**, itemized
  summary, Review/Accept CTA (My Account deep link; tokenized link supported for nopriv/email-tracked
  buyers since Storelly already allows nopriv submission).
- `quote.accepted` → order confirmation / deposit receipt.
- `quote.declined` → merchant notice.
- `quote.converted` → order confirmation.
- *(v2)* expiry reminders via Action Scheduler — mockup pattern **Day 5 "Just checking in" · Day 10
  "5 days left" · Day 13 "Last chance"**.

### 16. D3 — Display mode (detailed)
**Caveat:** Printcart does **not** implement a per-product "quote replaces add-to-cart" toggle — its
model is a parallel global RFQ funnel ("Standard size + qty < 50? Skip the quote — use our online
designer"). So D3's granular controls are **designed for Storelly**, guarded by RULE-C1.

**16.1 Option matrix** — per-product setting with a global default. **OD-5: all four modes ship in v1.**

| Mode | Add to Cart | Price shown | Quote button | Use case |
| --- | --- | --- | --- | --- |
| **Off** | Yes | Yes | No | Normal product |
| **Both** *(recommended default)* | Yes (primary) | Yes | Yes (secondary) | B2C + optional RFQ (RULE-C1) |
| **Quote replaces cart** | Hidden | Yes | Yes (primary) | "Call for order", price still informative |
| **Quote-only, hide price** | Hidden | **Hidden** | Yes (primary) | True price-on-request |

**16.2 Per-product vs global.** Global default in Storelly settings (Off / Both / Replace /
Quote-only) for all builder-enabled products + a per-product override (product meta). *(v2: rule-driven
fallback — mockup "100+ units → Custom quote" routes a buyable product to quote only at bulk qty.)*

**16.3 WooCommerce wiring for price/cart hiding** (more than CSS):
- `woocommerce_is_purchasable` → false (removes add-to-cart form natively).
- `woocommerce_get_price_html` → return quote CTA / "Price on request" in hide-price mode.
- Remove `woocommerce_template_single_add_to_cart`; inject Get Quote via `woocommerce_single_product_summary`.
- Mirror on **shop/archive** (`woocommerce_loop_add_to_cart_link`, `woocommerce_after_shop_loop_item`)
  so hidden-price products show neither price nor add-to-cart in listings.
- Gate **direct cart/checkout** server-side (`woocommerce_add_to_cart_validation`) — can't force-add via URL.
- Suppress **`product` price schema** when price is hidden (avoid misleading rich results).

**16.4 Mixed-cart concerns.** A quote is **not** a cart item, so "Both" is safe (real cart only holds
buyable products). If a multi-item quote cart is built later, keep it a **separate quote bucket**,
never mixed into the WC cart (mixing corrupts totals/coupons/shipping). Exclude hide-price products
from coupons and price-bearing upsells.

### 17. B2B extras — ranked for a wp.org WooCommerce plugin

| Feature | Rank | Rationale |
| --- | --- | --- |
| Validity / expiry date + auto-`expired` | **v1** | tiny field + one cron; highest value-to-cost |
| Customer-visible + internal notes | **v1** | internal = native WC private notes (free) |
| Per-line price override + custom lines (setup/rush/freight) | **v1** | this *is* D2; WC line items support it |
| PO number on accept | **v1** | one optional field; universally requested in B2B |
| Convert-to-order (accept → payable order) | **v1** | reuse WC "Pay for order" |
| WC-templated emails (`WC_Email`) | **v1** | replaces bare plain-text; appears in WC → Emails |
| Counter-offer / negotiation loop + revisions | **v1 (OD-2)** | `negotiating` + versioning (prior → `superseded`) — confirmed in v1 |
| Dedicated `spbwc_quote` CPT + custom line-item UI | **v1 (OD-10)** | confirmed; replaces WC-order reuse, needs own line-item store/editor |
| All four D3 display modes | **v1 (OD-5)** | Off / Both / Replace / Quote-only-hide-price all in v1 |
| Accept → spawn NEW WC order (linked) | **v1 (OD-1)** | confirmed; quote stays a quote, order minted on accept |
| Multi-item quote cart | **v2** | needs separate bucket + UI; keep single-product v1 (OD-4 default) |
| Net terms / 50-50 deposit | **v2** | Net = order without capture (doable); deposit needs partial-pay |
| Quote templates (reusable lines/terms) | **v2** | mockup "Save/Load template"; time-saver, not launch-critical |
| PDF quote | **v2** | expected B2B affordance; email-with-link suffices v1 |
| Tiered/contract pricing engine | **v2 / out** | no engine today; manual discount line only in v1 |
| Company / multi-seat accounts + roles | **out** | big surface; premium territory, not free wp.org |
| Approval chains / spending-limit routing | **out** | needs multi-seat first |
| Tax-exempt / resale cert | **out (v1)** | no mockup implements the toggle; WC tax-exempt fiddly |

> Strategy (Printcart ADR-035): the buyer's quote UX is **free**; gating belongs on advanced
> *merchant* tooling, never the buyer. For a free-on-wp.org plugin, keep the whole buyer quote flow free.

### 18. Decisions
**Confirmed 2026-06-01 (user):**
| # | Decision | **Chosen** |
| --- | --- | --- |
| **OD-1** | Accept → order mechanism | ✅ **Spawn a NEW WC order** from the quote, linked both ways |
| **OD-2** | Counter-offer / negotiation tier | ✅ **v1** (full `negotiating` + revision/supersede loop) |
| **OD-5** | D3 modes shipped in v1 | ✅ **All four** (Off / Both / Quote-replaces-cart / Quote-only-hide-price); default **Both** |
| **OD-10** | Quote storage | ✅ **Dedicated CPT `spbwc_quote`** (not a WC order) |

**Defaulted to recommendation (override anytime):**
| # | Decision | Default applied |
| --- | --- | --- |
| **OD-3** | Admin home | CPT edit screen + **Custom Quotes list-table** (replaces History tab) |
| **OD-4** | Multi-item quote cart | **Single-product per quote in v1**; multi-item bucket v2 |
| **OD-6** | Price-hiding scope | **Full suppression** (single + shop/archive/search + price schema) |
| **OD-7** | Expiry behaviour | **Auto-`expired` + lock** v1; expiry **reminder emails v1** (Action Scheduler, Day 5/10/13) |
| **OD-8** | PO / Net terms | **PO field v1**; **Net terms v2** |
| **OD-9** | Numbering | **Separate `Q-YYYY-NNNN`** (natural now that quote is a CPT) |

> ⚠ **Scope note:** OD-1 + OD-2 + OD-5 + OD-10 together = a **maximal-B2B v1** — a new CPT with its
> own line-item editor, a full negotiation/revision loop, all four storefront display modes, and
> order-spawning on accept. This is a substantial build (estimate: new CPT + admin list/detail +
> line-item repeater UI + 4-mode storefront wiring + 5 `WC_Email`s + Action Scheduler expiry/reminders
> + buyer My-Account redesign). Consider phasing internally even though all lands in "v1".

### 19. Compliance checklist (applies to any build)
- [ ] Every form/AJAX/action: nonce + `current_user_can()` (buyer transitions: ownership check).
- [ ] `ABSPATH` guard on new PHP files; sanitize input, escape output.
- [ ] Single `spbwc_` prefix on all new meta/statuses; keep legacy `_pcpb_*`/`_raq_*`/`_nbdesigner_*`
      read-paths for back-compat but write new fields as `spbwc_`.
- [ ] Text domain `storelly-product-builder-for-woocommerce`, no variables in `__()`.
- [ ] No phone-home; any external service declared in `readme.txt`.
- [ ] Storefront + modal assets via `wp_enqueue_*` (replace inline CSS/JS).
- [ ] Expiry/reminders via **Action Scheduler** (WP-Cron loopback is disabled locally — [[localhost_wpcron_fix]]).
- [ ] `wp plugin check` → 0 errors; advertised features exist in code.

### 20. Test coverage (target)
- **D3 storefront:** mode matrix (Off/Both/Replace/Quote-only × per-product/global); price+cart hidden
  on single, shop, archive, search; direct-add-to-cart URL blocked; schema price suppressed.
- **D2 admin:** reply builder line CRUD + unit-price override; validity → expiry meta; send → `sent`
  + order note + buyer email; capability/nonce gate.
- **Buyer:** ownership enforcement; priced detail render; Accept→lock+convert→payable order; Decline
  +reason; (v2) Request-changes→negotiating; terminal-state locks Accept/Decline.
- **Lifecycle:** expiry sweep flips `sent → expired`; converted order links back to quote.
- **Email:** `WC_Email` dispatch on created/sent/accepted/declined/converted.

### 21. Cleanup + migration folded into this work
- **Migrate existing quote-orders → CPT** (OD-10 changes storage): existing `spbwc-quote-*` WC orders
  (with `_spbwc_quote_*` / `_raq_*` meta) must be migrated to `spbwc_quote` CPT posts, or a one-way
  read-compat shim must surface them in the new list. Provide a one-time migration (Action Scheduler
  batch) and keep the old order statuses registered read-only for back-compat.
- Implement or delete the orphaned `wp_storelly_quote_requests` table reference + `$recent_quotes_l`
  in `views/overview.php` (the CPT makes a clean `wp_count_posts('spbwc_quote')` available).
- Wire or remove the Form Builder drag-reorder handle; implement or remove the `select` field type.
- My-Account "Quotes" now reads the **CPT** (by buyer user/email), replacing the `_spbwc_quote_request`
  meta query — fixes the old list/History key mismatch by construction.
