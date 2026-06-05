# SPEC — Quote Import & Sync (seed Storelly quotes from existing sources)

> Status: DRAFT (direction chosen 2026-06-04). Goal: when a merchant installs Storelly,
> their existing quote/enquiry data turns into `spbwc_quote` records immediately — by
> importing from whatever they already run, and (opt-in) staying in sync afterwards.
> Empty stores get a clearly-labelled sample quote so the workspace is never blank.

Related: `SPEC_QUOTE_USER_FLOW_UX.md` (the quote model), `class-quote-migrator.php` (M7 — the
legacy WC-order→CPT batch we extend), `SPEC_SETUP_WIZARD_WOO_SEED.md` + onboarding (where the
import step lives), demo-seeder (sample seed lands here).

## 1. Why

Storelly's quote workspace is empty on day 1. Merchants who already take quotes do it through:
a dedicated quote plugin, plain WooCommerce draft orders, or a "Get a quote" contact form. All of
those hold quote-shaped data we can read **locally** (no phone-home) and convert. Importing it =
instant value + a migration path off the old tool, and ongoing sync makes Storelly a *superset* so
the merchant can keep their existing capture form while Storelly owns the quote lifecycle.

## 2. Source landscape (storage facts → mapper inputs)

| # | Source | Detection | Storage | Map → quote |
|---|--------|-----------|---------|-------------|
| 1 | **WooCommerce draft/pending orders** | always (Woo active) | WC orders (HPOS `wc_orders` or `wp_posts`) at `draft`/`pending`/`on-hold`, unpaid | order → quote: items→lines, billing→contact, order total→totals |
| 2 | **YITH WooCommerce Request a Quote** | `class_exists('YITH_Request_Quote')` | quote = **WC order** with custom YITH statuses; quote fields stored as **order post meta** (meta_key = field name) | reuse migrator: `wc_get_orders` by YITH statuses |
| 3 | **Quotes for WooCommerce** (WisdmLabs, free) | plugin active | order-based, custom `wc-quote-…` status + per-item meta | order adapter variant |
| 4 | **B2BKing** | `class_exists('B2bking')` | quote "Conversations" (CPT) + default fields name/email/message + custom fields | conversation → quote |
| 5 | **Addify / ELEX / WebToffee RAQ** | plugin active | CPT `quote` or order-based (per plugin) | per-plugin adapter |
| 6 | **CF7 + Flamingo** | `post_type_exists('flamingo_inbound')` | CPT `flamingo_inbound`; field values in postmeta `_field_*` | flamingo msg → quote (field-mapping) |
| 7 | **WPForms** (Pro) | table `{$wpdb->prefix}wpforms_entries` | entries table, `fields` column = JSON | entry → quote (field-mapping) |
| 8 | **Gravity Forms** | table `{$wpdb->prefix}gf_entry` | `gf_entry` + `gf_entry_meta` (2.3+) | entry → quote (field-mapping) |
| 9 | **Fluent Forms** | table `{$wpdb->prefix}fluentform_submissions` | submissions, `response` = JSON | submission → quote (field-mapping) |
| 10 | **Forminator** | table `{$wpdb->prefix}frmt_form_entry` | entry + entry_meta | entry → quote (field-mapping) |
| 11 | **Ninja Forms** | `post_type_exists('nf_sub')` / NF tables | CPT `nf_sub` (legacy) or custom tables (3.x) | submission → quote (field-mapping) |

> ⚠ Exact status slugs / CPT names / meta keys for #2–#5, #11 MUST be verified against a live
> install (or the plugin source) before shipping each adapter — the table above is the research
> baseline, not a contract. Order-based sources (1,2,3) are HPOS-safe via `wc_get_orders`.

Ecosystem precedent: cross-plugin entry importers are normal (e.g. GravityImport migrates CF7 →
Gravity Forms), so merchants expect a "scan & import" step.

## 3. Architecture

### 3.1 Adapter interface
```
interface SPBWC_Quote_Source_Adapter {
    public function id(): string;            // 'woo_orders', 'yith_raq', 'cf7_flamingo', …
    public function label(): string;         // human name for the import UI
    public function is_available(): bool;     // source plugin/data present?
    public function count_importable(): int;  // how many records we could import (minus already-imported)
    public function fetch_batch( int $offset, int $limit ): array;   // raw rows
    public function map_to_quote( array $row ): array;               // → request payload for SPBWC_Quote::create()
    public function source_ref( array $row ): string;                // stable id for dedupe
    public function supports_sync(): bool;    // can we hook live submissions?
    public function register_sync(): void;    // attach listener (M5)
}
```
A registry (`SPBWC_Quote_Import`) collects adapters via a filter `spbwc_quote_source_adapters` so
3rd parties / future adapters plug in without touching core.

### 3.2 Dedupe
Every imported quote stores `_spbwc_imported_from` = `"{adapter_id}:{source_ref}"`. `count_importable()`
and the batch skip rows whose ref already exists (meta query). Re-running import is idempotent — same
guarantee as the M7 migrator.

### 3.3 Batch engine
Reuse the M7 pattern: Action Scheduler single actions, N rows/run, progress option, HPOS-safe. One
queue per adapter so a slow/large source doesn't block others. WP-Cron is disabled locally → AS only.

### 3.4 Field mapping (generic forms #6–#11)
Form sources have arbitrary fields, so the import UI shows a **mapping step**: pick the form, then map
its fields → quote fields (`first_name, last_name, email, phone, company, product, qty, message`).
Mapping saved per-source in option `spbwc_quote_import_maps`. Order/CPT sources (1–5) need no mapping.

## 4. Import wizard (one-time)

Entry points: a card in the Setup Wizard (after the Woo-seed step) **and** a "Import quotes" button
on the Quotes workspace toolbar (always available, not just at onboarding).

Flow:
1. **Scan** — run `is_available()`+`count_importable()` across adapters → list "Found N quotes in
   <source>" rows, each with an Import button (or "Map fields & import" for form sources).
2. **Map** (form sources only) — field-mapping table; remember mapping.
3. **Import** — enqueue AS batch; show live progress (reuse migrator progress UI); on done show
   "Imported N quotes" + link to the workspace, already filterable by a `source` column/badge.
4. Imported quotes land as status `spbwc-q-new` (or `review`) so the merchant triages them like any
   inbound quote. The original source data is left untouched (non-destructive).

## 5. Ongoing sync (opt-in, M5)

A per-source toggle "Also capture new <source> submissions as quotes". When on, `register_sync()`
attaches the source's completion hook and mirrors each new submission into a `spbwc_quote` using the
saved field-mapping:
- YITH RAQ: order-created hook → quote.
- CF7: `wpcf7_mail_sent` (+ Flamingo) → quote.
- WPForms: `wpforms_process_complete`.
- Gravity Forms: `gform_after_submission`.
- Fluent Forms: `fluentform/submission_inserted`.
- Forminator: `forminator_form_after_save_entry`.
Each mirrored quote carries `_spbwc_imported_from` for dedupe so a later bulk import won't duplicate
a live-captured one. Opt-in only; nothing intercepts the merchant's existing form behaviour.

## 6. Quote-early-on-install (empty state)

Three layers, evaluated at activation / first Quotes-screen visit:
1. **Sources detected** → wizard offers import (real quotes).
2. **No quote plugin, but Woo has draft/pending orders** → "Turn N existing orders into quotes"
   one-click (the #1 adapter, scoped to existing drafts). Highest leverage — every store has drafts.
3. **Nothing** → seed **1 sample quote** (via the demo-seeder), clearly badged "Sample" and
   one-click removable, so the workspace shows the populated UI instead of a blank slate.

## 7. Compliance (wp.org)

- 100% local: every adapter reads the local DB only. **No phone-home, no external service** to declare.
- Sample seed must be labelled "Sample" + deletable; never count toward any paid limit.
- Non-destructive: import never edits/deletes the source records.
- Prefix `spbwc_`; nonce + `manage_woocommerce` on every import/sync admin action; sanitise/escape;
  `$wpdb->prepare()` for the form-table reads; text domain `storelly-product-builder-for-woocommerce`.

## 8. Milestones

- **M1 — Import framework + Woo-orders adapter.** Adapter interface + registry + dedupe meta + AS
  batch + Quotes-toolbar "Import quotes" screen (scan/count/import/progress). Woo draft/pending-order
  adapter (universal, no external unknowns). *Foundation; build first.*
- **M2 — Order-based quote plugins. DONE (commit b9d43a8).** Quotes for WooCommerce adapter verified
  live: a QFW quote = WC order with `_qwc_quote=1` + `_quote_status` (quote-pending/sent/complete/paid);
  import the open ones (pending/sent). The universal Woo-orders adapter now excludes `_qwc_quote` so a
  QFW order (also "pending") is owned by one source. YITH adapter: the **free** plugin keeps quotes in
  session+email (nothing persisted) so it's inert; the adapter gates on the YITH **Premium** class and
  resolves the open `wc-ywraq-*` statuses dynamically — needs validation on a live YITH Premium install.
- **M3 — Contact-form sources + field-mapping UI. DONE (commit 0b90317).** `SPBWC_Quote_Form_Adapter`
  base (forms() + entries + saved mapping; auto_map heuristic; single "name" split to first/last;
  canonical-ref dedupe). Mapping store on the controller (option `spbwc_quote_import_maps`, get/save +
  imported_refs). Import tab renders a per-form mapping editor (quote field → form field select,
  pre-filled) + "Save mapping & import". Concrete adapters: Flamingo (CF7 — flamingo_inbound CPT +
  channel taxonomy + _field_*/_from_* meta) and Fluent Forms (fluentform_forms + fluentform_submissions
  response JSON), both gated on the source plugin. Verified via a stub form adapter (auto-map, mapping
  gate, import, name-split, dedupe, UI render). ⚠ The CF7/Fluent plugins fatal on the dev box's
  pre-release WP, so the concrete adapters were verified by schema + stub, not against the live plugins —
  validate on a supported WP. Remaining form plugins (WPForms/GF/Forminator/Ninja) follow the same
  pattern (M3b).
- **M3b — more contact forms. DONE (commit b1ee6e7).** WPForms (wpforms CPT + entry handler), Gravity
  Forms (GFAPI, name sub-inputs recombined), Forminator (Forminator_API + entry model), Ninja Forms
  (Ninja_Forms() API + nf_sub CPT). Shared `flatten_value()` on the form base. All gate on their source
  plugin (registry shows 9 adapters; 8 off on a clean store). Written against each plugin's documented
  API — validate live on a supported WP; the shared form pipeline is already stub-verified (M3).
- **M4 — Other B2B/quote plugins.** B2BKing / Addify / ELEX adapters.
- **M5 — Ongoing sync.** Opt-in live listeners for every wired source + saved mappings + dedupe.
- **M6 — Sample seed / empty-state.** Integrate the 3-layer onboarding (import / convert-drafts /
  sample) with the demo-seeder + Quotes empty-state.
- **M7 — Compliance & docs.** Plugin Check, POT, readme, this spec → as-built.

## 9. Open decisions (resolved 2026-06-04)
- Sources in scope: **all 4 categories** (orders, quote plugins, B2B plugins, contact forms).
- Mechanism: **import + ongoing sync** (sync opt-in per source).
- Empty state: **seed a Sample quote**.
Still to confirm during build: imported-quote landing status (`new` vs `review`); whether to expose a
`source` column in the workspace list; per-adapter exact storage (verify live).
