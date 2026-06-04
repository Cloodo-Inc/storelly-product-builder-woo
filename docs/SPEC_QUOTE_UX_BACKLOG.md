# Quote — UX / User-flow improvement backlog

> Companion to [SPEC_QUOTE_USER_FLOW_UX.md](SPEC_QUOTE_USER_FLOW_UX.md) and
> [DEV_MILESTONES_QUOTE_B2B.md](DEV_MILESTONES_QUOTE_B2B.md). The B2B v1 build (M1–M7) shipped the
> end-to-end flow (CPT, admin pricing reply, storefront → CPT, buyer My-Account, D3 display modes,
> WC_Email + expiry, legacy migration). This backlog tracks the UX/flow refinements found after the
> pages were live, prioritised P1 (fix-now) → P5 (cleanup). Effort: S ≈ <½ day, M ≈ ~1 day, L ≈ multi-day.

Legend: **GAP** = a real hole in the flow (something silently broken/missing) · **ENH** = enhancement.

---

## P1 — Real flow gaps (low effort, high value) — *being implemented now*

### P1.1 — Show the buyer's change request in the admin detail · GAP · S
When a buyer clicks **Request changes**, the asks are stored in `_spbwc_quote_change_request`
(`{asks:[], details}`) but **never rendered**. The merchant sees a `negotiating` quote with no idea
what to revise.
- **AC:** On the admin quote detail, when status is `negotiating` (or the meta exists), a
  "Customer's change request" card shows the selected asks (mapped to their labels) + the free-text
  details, above the pricing reply.

### P1.2 — Buyer acknowledgement email on submit · GAP · S
Submitting a quote only emails the **admin** (`spbwc_quote_new`). The buyer gets no confirmation.
- **AC:** A new `WC_Email` (customer recipient) fires on submit: "We've received your request
  {quote_number} — we'll get back to you with pricing." Includes the product + quantity. Appears in
  WooCommerce → Emails. Only sent when an email was captured.

### P1.3 — Pending-count badges on the menus · GAP · S
Neither the admin **Quotes** menu nor the My-Account **Quotes** item shows that something needs
attention.
- **AC (admin):** the Quotes submenu shows a count bubble of `spbwc-q-new` quotes (WP
  `awaiting-mod` style), hidden when zero.
- **AC (buyer):** the My-Account Quotes item appends `(N)` where N = the current user's quotes in
  `sent` status (awaiting their decision), hidden when zero.

### P1.4 — Actionable success state after submit · GAP · S
After submitting, the modal shows a static "Request sent" message with no next step.
- **AC:** logged-in buyers see a **"Track your quote"** CTA linking to My-Account → Quotes; guests
  see a short line inviting them to create an account to track it. No redirect (stay put), just a
  clear path forward.

---

## P2 — Quick UX/UI wins (high value)

### P2.5 — KPI cards + row urgency cues on the admin list · ENH · M
- **AC:** above the list, cards for *New requests*, *Awaiting response* (count + $ outstanding),
  *Accepted (30d)*. Per-row chips: "⚠ Respond soon" for new/old, "✓ Converted → #N" for converted.

### P2.6 — Stat cards + status filter on the buyer My-Quotes list · ENH · M
- **AC:** header cards (*Total quotes*, *Awaiting your action*, *Quoted value*, *Converted*) +
  status filter tabs; the list already has the data.

### P2.7 — Richer product context in the request modal · ENH · S
- **AC:** show price / "Price on request", a larger thumbnail, a one-line "what happens next", and a
  +/- quantity stepper.

### P2.8 — "Preview customer view" from the admin detail · ENH · S
- **AC:** a link/button on the pricing reply that opens the buyer-facing quote view in a new tab
  before sending.

---

## P3 — Negotiation & conversion completeness

### P3.9 — Revision diff on counter-offers · ENH · M
- **AC:** when re-quoting from `negotiating`, show v1 → v2 (added/removed/changed lines, old vs new
  total). Builds on the existing `_spbwc_quote_revision` / `superseded` plumbing.

### P3.10 — Fuller Accept modal · ENH · S–M
- **AC:** Accept asks for ToS confirmation, optional payment method, a "email me production updates"
  opt-in, and the PO number (already present).

### P3.11 — Buyer emails on accepted / converted · ENH · S
- **AC:** the buyer (not just the admin) gets an order-confirmation email with the Pay link when a
  quote is accepted/converted.

### P3.12 — Quote templates / quick-reply · ENH · M
- **AC:** save a set of line items + terms as a reusable template; load it into a new pricing reply.

---

## P4 — B2B depth (v2, large)
- **P4.1 PDF quote — DONE (hybrid).** `SPBWC_Quote_PDF`: a nonce-guarded local print view
  (`?spbwc_quote_print=`) the merchant/buyer prints or saves as PDF (no bundled library, no
  phone-home, always available) + a `spbwc_quote_cloud_pdf` filter so a Cloud2Print adapter can
  produce a real PDF that's attached to customer emails when `enable_cloud2print_api` is on.
  Download/Print links on the admin detail + buyer view; customer emails attach the cloud PDF when
  present. **Remaining:** the Cloud2Print *document endpoint* adapter (hook the filter, POST the
  quote HTML, save the returned PDF) — needs the endpoint contract.
- **P4.2 Net terms / 50-50 deposit — DONE.** The pricing reply has a Payment terms select
  (`SPBWC_Quote::payment_terms_options()`): Pre-pay / Net-15 / Net-30 / Net-60 / 50% deposit.
  `spawn_order_from_quote()` is terms-aware — Net-N → full order set **on-hold** with a due date
  (`_spbwc_quote_due_date` = today+N) + note (an unpaid invoice, no forced payment); 50% deposit →
  order carries a single `Deposit (50% of <total>)` fee with the balance tracked
  (`_spbwc_quote_balance`) + note; Pre-pay → pending (pay now). Buyer Accept card + converted view
  show the right messaging ("Deposit due now" / Net-N invoice). *Note: capturing the remaining
  deposit balance is left to a deposits gateway — we model the deposit as the payable amount + a
  tracked balance, no partial-payment plugin required.*
- **P4.3 Multi-item quote cart — DONE.** `SPBWC_Quote_Bucket`: a WC-session bucket (separate from
  the cart) collected via "Add to quote list" buttons; a floating "Quote list (N)" badge opens a
  review modal (adjust/remove + request form) that submits **one** `spbwc_quote` with
  `request['items']` pre-seeded as multiple line items. Vanilla-JS (`quote-bucket.js`), 3
  nonce-guarded AJAX endpoints, admin recap renders the product list. Gated on the global Get Quote
  toggle.
- **Company / multi-seat accounts + approval chains** — the company/approval piece is the
  separate B2B Client project (docs/SPEC_B2B_CLIENT.md, CPT spbwc_company). Still open.

## P5 — Cleanup (small tech debt)
- **Form Builder** `select` field type has no options editor (renders as text) — implement or remove.
- **Form Builder** drag-reorder handle is rendered but not wired.
- Split the four quote edits currently sharing `class-admin-options.php` with unrelated in-progress
  work so they can be committed cleanly (overview count, filemtime enqueue, "Quote Settings" rename,
  Request-History removal).

---

## Status
- **P1.1–P1.4:** implemented (`feat(quote): P1`).
- **P2.5 (admin KPI cards), P2.6 (buyer stat cards + status filter):** implemented (`feat(quote): P2`).
- **P2.7 (richer modal — price + qty stepper), P2.8 (admin "Preview customer view"), P3.11 (buyer
  accepted/converted email):** implemented (`feat(quote): P2.7+P2.8+P3.11`).
- **P3.9 (revision diff), P3.10 (fuller Accept modal), P3.12 (quote templates):** implemented
  (`feat(quote): P3.9/P3.10/P3.12`). Two hot-file touches pending the user's commit of those files:
  loader `require class-quote-template.php` + init, and the P3.10 Terms-URL field in
  `class-admin-options.php` (both applied in the working tree).
- **P4, P5:** open — pick per priority.
