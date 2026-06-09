# Storelly — Account, License & Premium-Activation UX Spec

> **Status**: DRAFT for review
> **Date**: 2026-06-09
> **Owner**: David / Netbase
> **Goal**: Make the Storelly *account + plan + premium-activation* experience one coherent,
> easy-to-understand flow, and turn the upgrade/payment path into a **closed loop** (pay → auto-unlock)
> instead of the current "open a new tab and hope" path.
> **Related**: `SPEC_FREEMIUM_V1_1.md` (caps model §1, connect-back §6), `SPEC_M5_CLOUD_CONSENT.md`,
> `SPEC_ADMIN_UI_REDESIGN.md`, `SPEC_ONBOARDING_ACTIVATION.md`
> **Audited code (2026-06-09)**: `views/license.php`, `views/menu-settings.php` (tabs
> *integration* + *user-account*), `includes/class-cloud-connect.php`, `includes/class-license-manager.php`,
> `includes/class-productbuilder-api.php`.

---

## 1. Current state — what exists today (audit)

The merchant's "account / plan / cloud" experience is **split across two admin pages and three
overlapping concepts** that the UI never unifies.

### 1.1 Page A — *License & Plans* (`views/license.php`, own menu)

- Hero with **"Upgrade Now"** → opens `app.storelly.com/subscription` **in a new tab** (`license.php:47`).
- **Current-plan card**: `status`, `package_name`, `expires_at`, `synced_at` + **"Sync License"**
  button (AJAX `spbwc_license_sync`, reloads page) (`:58–126`).
- **Available Plans grid** from `$packages`, each card showing **`max_products` / `max_orders` /
  `max_pricing_options`** quota rows (`:178–197`) and a CTA that is, again, a **new-tab link to
  `/subscription`** (`:204–216`).
- **"Manage License Subscriptions"** card → Storelly Dashboard (`:228–243`). Help section (`:245–268`).

### 1.2 Page B — *Settings* (`views/menu-settings.php`), tabs `integration` + `user-account`

**Integration tab** stacks three cards that all touch the *same* connection/consent state:
1. **"Storelly Account"** card (M5.9, `:660–779`) — connect / disconnect / link-by-Store-ID via
   `SPBWC_Cloud_Connect` AJAX. Shows account username, store URL, and an **"Active features"** row
   that reads the raw flags `enable_cloud2print_api` / `enable_api_sync` (`:716–725`).
2. **"Storelly Integration"** card (`:781–839`) — radio **Yes/No** for `enable_cloud2print_api`
   and `enable_api_sync`. **These are the very same flags** the Account card flips.
3. **"API Keys"** card (`:841–969`) — raw SID / Secret / Unauth Token / Username / Log + "Login to
   Storelly" / "Create first product".

**"User Account" tab** (`:973–1052`) — **misnamed**: it is *Cart compatibility* (classic vs block
cart) + *Save-design entry points* (show/hide the "Save design" link). Nothing to do with the
merchant's Storelly account.

### 1.3 The connect + activation mechanics

- `SPBWC_Cloud_Connect::connect()` (`class-cloud-connect.php:76`) generates WC REST keys, registers
  the store (sends store UUID + admin email), and on success **flips both `enable_api_sync` and
  `enable_cloud2print_api` to `yes`** + logs GDPR consent. It **auto-creates a Storelly account and
  emails the login** (`class-productbuilder-api.php:98–115`).
- `is_connected()` is defined purely as **`enable_api_sync === 'yes'`** (`:162–167`).
- **Payment is a bare external link.** There is **no connect-back, no token exchange**. After buying
  on `app.storelly.com`, the only way the plugin learns about it is the merchant manually clicking
  **"Sync License"**.
- The license model is still **quota-based** (`get_current_license()` →
  `max_products/max_orders/max_pricing_options/features`, `class-license-manager.php:104–114`).
  No `caps[]`, no `can()`.

---

## 2. Problems (prioritized) — why this is hard to understand

| # | Problem | Impact |
|---|---------|--------|
| **P1** | **Three concepts, no unifying model.** "Connection" (Account card), "Entitlement" (License page), and "Consent toggles" (Integration radios) are three surfaces for what the merchant experiences as *one* thing: *my Storelly account & what I pay for*. | Merchant must learn connect ≠ license ≠ feature-toggle. Highest cognitive cost. |
| **P2** | **Two pages.** Account/connect lives in Settings → Integration; plan/upgrade lives on a separate License page. Neither links the other clearly. | Constant back-and-forth; no single "home". |
| **P3** | **"User Account" tab is mislabeled** (it is cart + save-design settings). | A merchant looking for their account/plan clicks it and is lost. |
| **P4** | **Triple control of the same flags.** Account card "Enable Cloud", Integration radios, and API-Keys all mutate `enable_api_sync` / `enable_cloud2print_api`. A merchant can create contradictory state (Account says *Connected*, then flips the Integration radio to *No* → badge says *Not connected* while account data still shows). | Confusing, bug-prone, support load. |
| **P5** | **Payment is an open loop.** Upgrade = new tab → pay → *manually come back* → *manually Sync License*. The moment of payment success is disconnected from the plugin unlocking. | High drop-off at the exact point of revenue. |
| **P6** | **"Active features" will lie once caps land.** Connecting (free) turns the PDF/sync flags on, but with no license those cloud calls should be `spbwc_cloud_locked` (per `SPEC_FREEMIUM_V1_1` §1.3). The card shows "Cloud PDF ✓" for a user who actually can't use it. | Broken trust; the headline status is wrong. |
| **P7** | **Plan grid advertises quotas that no longer exist** (`max_products` etc.). | Misleading + the wp.org "5 products" compliance debt (C1). |
| **P8** | **Upgrade CTAs scattered, all dump to the same external page.** No upsell-by-intent (unlock at the blocked control). | Weak, undirected funnel. |
| **P9** | **Manual "Sync License" is the only refresh.** | Stale plan state; one more chore. |

---

## 3. Target model — collapse three concepts into one

**One mental model:** *Storelly Cloud account*. It has exactly two independent facts the merchant
needs to understand, and we present them as **one status, two dimensions**:

```
            CONNECTED?  (free account link — consent to talk to app.storelly.com)
                 ┌─────────────────────────┬─────────────────────────┐
 LICENSED?       │        no                │         yes             │
 ┌───────────────┼─────────────────────────┼─────────────────────────┤
 │ Free          │ S0  Not connected        │ S1  Connected · Free     │
 │               │     → "Connect (free)"   │     → "Upgrade to unlock" │
 ├───────────────┼─────────────────────────┼─────────────────────────┤
 │ Active        │ (not reachable —         │ S2  Connected · {Plan}   │
 │ (Std/B2B)     │  activation connects too)│     → caps active        │
 ├───────────────┼─────────────────────────┼─────────────────────────┤
 │ Expired       │ —                        │ S3  Connected · Expired  │
 │               │                          │     → "Renew"            │
 └───────────────┴─────────────────────────┴─────────────────────────┘
```

Rules that make it understandable:
- **One control owns connection** (Connect / Disconnect). The Integration Yes/No radios and the
  raw API-Keys card are **removed from the primary flow** (API keys demoted to a collapsed
  "Advanced — link manually" disclosure). No more three-way contradiction (fixes P4).
- **"What's unlocked" is driven by `can($cap)`, never by the raw flags** (fixes P6). A connected
  free user sees Cloud features as **🔒 locked**, not "✓ active".
- **Connection is free and framed as such.** Licensing is the paid step. Activation (§5) does both
  in one pass, so the merchant never has to reason about the order.

---

## 4. Target information architecture

### 4.1 One home: **Storelly → Account & Plan**

Merge License & Plans (Page A) **and** the "Storelly Account" connect card (Page B / Integration)
into a single page that answers, top to bottom:

1. **Status header** — *Are you connected? What plan? Until when?* (the S0–S3 state, one badge).
2. **What you get** — the caps matrix (Free / Standard $49 / B2B $99), current plan highlighted,
   each row ✓ or 🔒 (from `SPEC_FREEMIUM_V1_1` §1.2; **replaces the quota grid**, fixes P7/C1).
3. **Primary action** — state-dependent single CTA (§4.3).
4. **Advanced (collapsed)** — manual license key, link-by-Store-ID, API keys, connection log,
   "Sync/Refresh now", Disconnect, privacy link.

### 4.2 Fix the Settings tabs

- **Rename the "User Account" tab → "Storefront"** (or fold its two cards — *Cart compatibility*,
  *Save-design entry points* — into the existing **Cart & Order** tab). Frees the word "Account"
  for the merchant's actual account (fixes P3).
- **Remove the duplicate "Storelly Integration" radio card** from the Integration tab; connection +
  feature state now live on the Account & Plan page. The Integration tab keeps only genuinely
  separate third-party integration settings (if any) or is removed.
- **Demote "API Keys"** to the Advanced disclosure on the Account & Plan page.

> Net: from **2 pages × 3 cards × 7 tabs** of overlapping account surfaces → **1 page** for
> account/plan, with advanced controls one click away.

### 4.3 The single status card (per state)

| State | Badge | Headline | Primary CTA | Secondary |
|-------|-------|----------|-------------|-----------|
| **S0** Not connected | grey "Not connected" | "Everything runs **free** in your WordPress. Connect to add Cloud features." | **Connect (free)** | Choose a plan ↓ · Advanced |
| **S1** Connected · Free | green "Connected" | "Connected as {email}. Cloud features are **locked** — upgrade to unlock." | **Choose Standard / B2B** | Manage account · Advanced |
| **S2** Connected · {Plan} | brand "{Plan} active" | "{Plan} active until {date}. {N} Cloud features unlocked." | **Manage subscription** | Refresh · Advanced |
| **S3** Connected · Expired | amber "Expired" | "Your plan expired {date}. Local features still work; Cloud is paused." | **Renew** | Refresh · Advanced |

Caps list under the headline always renders from `can()` — ✓ unlocked / 🔒 locked-with-upsell.

---

## 5. The premium-activation / payment flow (the core optimization)

### 5.1 Today vs target (step count)

**Today (open loop, ≈8 context switches across 2 pages + external):**
```
License page → read → Upgrade (new tab) → app.storelly.com → pay → (manually return)
→ Settings → Integration → Enable Cloud → (return) → License page → Sync License → maybe unlocked
```

**Target (closed loop, 2 clicks, one page, one external hop, auto-applied):**
```
Click locked feature  OR  one "Upgrade" CTA
   → connect-back redirect (carries site_url, plan, return_url, state, ctx)
   → app.storelly.com: account + payment (gateway is Storelly's concern — SPEC_FREEMIUM_V1_1 D7)
   → redirect back with one-time token
   → plugin server-to-server exchange → license auto-applied (caps[])
   → land on the exact originating screen, cap now ✓ unlocked
```

This is exactly `SPEC_FREEMIUM_V1_1` §6 (connect-back). This spec adopts it as the **only** primary
path and adds the UX wrapping below. Manual "Sync License" / license-key entry stay as **Advanced
fallbacks**, not the main road (fixes P5, P9).

### 5.2 One flow connects *and* licenses

If the merchant is **S0 (not connected)** and clicks **Choose B2B**, the connect-back round-trip
returns both the account link *and* the license in the single `/license/exchange` response — the
plugin persists caps and, if cloud consent was not yet given, renders the consent screen inline
(`SPEC_FREEMIUM_V1_1` §6.1 step 7). The merchant never has to "connect first, then buy". Connection
becomes an implementation detail of activation, not a separate chore (fixes P1/P2 at the flow level).

### 5.3 Upsell-by-intent (where the funnel actually fires)

- Any blocked Cloud action (Export PDF, B2B invoice, scheduled email "trigger time", premium asset)
  returns `WP_Error('spbwc_cloud_locked', …, ['cap'=>X])`.
- The UI renders an **inline unlock prompt at that exact control** — "Print-ready PDF needs a Cloud
  plan. **Unlock →**" — which enters the §5.1 flow with `ctx={cap}:{object_id}`.
- On success the merchant is deep-linked **back to that control**, which re-checks `can()` live and
  proceeds. This replaces the scattered, undirected "Upgrade Now" buttons (fixes P8).

### 5.4 Post-activation truth

After exchange, the Account & Plan status card re-renders from the freshly persisted `caps[]` (no
manual refresh). The "what's unlocked" list flips the relevant rows to ✓. A toast confirms
"{Plan} activated — {feature} unlocked".

### 5.5 Edge cases (inherit `SPEC_FREEMIUM_V1_1` §6.4)

Abandon checkout, double-click, token replay, network fail mid-exchange, multisite/clone
`site_mismatch`, agency-bought-on-web (manual key), expiry mid-session → all handled per the
freemium spec; surface each with a clear inline message, never a white screen.

---

## 6. Layout sketch — Account & Plan page (S1 example)

```
┌─ Storelly · Account & Plan ───────────────────────────────────────────────┐
│  [● Connected]   acme-prints@example.com   ·   Store: acme.example.com      │
│  Plan: Free      Cloud features are locked.                  [ Choose plan ]│
├────────────────────────────────────────────────────────────────────────────┤
│  What you get                          Free   Standard $49   B2B $99         │
│   Local builder, options, quotes,       ✓        ✓             ✓             │
│   custom order, engines (unlimited)                                          │
│   Cloud print-ready PDF                 🔒       ✓             ✓     [Unlock]│
│   Order → Dashboard sync                🔒       ✓             ✓             │
│   Design Vault / Asset Library          🔒       ✓             ✓             │
│   Option analytics / Config snapshots   🔒       ✓             ✓             │
│   B2B: invoice PDF · email triggers ·   🔒       —             ✓     [Unlock]│
│        statements · account analytics                                        │
│                                          [ Choose Standard ] [ Choose B2B ]  │
├────────────────────────────────────────────────────────────────────────────┤
│  ▸ Advanced (manual license key · link by Store ID · API keys · log ·        │
│              refresh · disconnect · privacy)                                  │
└────────────────────────────────────────────────────────────────────────────┘
```

- Token-first (radius/spacing/badge per `static/css/_tokens.css`), RTL-safe, no hardcoded colors
  (per `storelly-finish-task` design-token rule).
- The matrix is **driven by the `caps[]` definition** (single source of truth), not hand-written
  per plan — so adding a cap updates the page automatically.

---

## 7. Microcopy (trust + clarity)

- **One-line model, top of page:** *"Everything runs free inside your WordPress. Cloud features —
  print-ready PDF and B2B services — run on app.storelly.com and need a plan."*
- **Connect (free):** *"Creates your free Storelly account and links this store. We share your admin
  email, store URL and a store ID. Nothing is sent until you click. [Privacy]"* (reuse existing
  consent copy, `menu-settings.php:746`).
- **Locked feature:** *"{Feature} needs a Cloud plan. Unlock →"* (never "you've hit a limit" — there
  are no quotas).
- **Never advertise a cap that isn't real** (compliance C1/C10; e.g. drop "up to 5 products").

---

## 8. Compliance & correctness notes

- **Caps, not quotas:** the plan matrix must render from `caps[]` (`SPEC_FREEMIUM_V1_1` §1.2); delete
  `max_products/max_orders/max_pricing_options` from the plan cards (fixes P7, closes C1).
- **`can()` drives "unlocked", flags drive "consent".** The status card must distinguish *connected*
  (consent on) from *unlocked* (cap present) so S1 never claims a feature is active (fixes P6).
- **No phone-home on page load.** Pricing/packages fetch is on page open only, cached transient
  (TTL 12h) + static fallback baked in (per `SPEC_FREEMIUM_V1_1` §5.2). Exchange is server-to-server,
  `manage_options` only.
- **External services readme:** the `/connect`, `/license/exchange`, `/license/packages` endpoints
  declared with data sent & when (extends C3/C6).
- Re-run `wp plugin check` after the IA change (tab rename, card removal touch many strings/POT).

---

## 9. Milestones

| MS | Scope | Depends on |
|----|-------|-----------|
| **U1** | ✅ **DONE (2026-06-09)** — IA fix, pure refactor, no backend. See §9.1 for what shipped. | — |
| **U2** | ✅ **DONE (2026-06-09)** — unified **Account & Plan** page (status card S0–S3 + caps matrix replacing the quota grid). See §9.2. | F1.1, U1 |
| **U3** | ✅ **DONE plugin-side (2026-06-09)** — connect-back flow built (`SPBWC_Cloud_Activation`); **dark until backend O-6** (`/connect` + token + `/license/exchange`), falls back to manual key / Sync. See §9.2. | backend O-6 |
| **U4** | 🟡 **PARTIAL (2026-06-09)** — `can()` gate + `cloud_locked_error()` helper + caps-matrix lock/upsell display shipped. **Control-level inline prompts on PDF/sync need F2** (wrapping the real cloud calls). See §9.2. | F2, U3 |

Suggested order: **U1 → U2 → U3 → U4** (U1 ships value immediately and is low-risk; U2+ need the
entitlement layer from the freemium spec).

### 9.1 U1 — what shipped (2026-06-09)

Files: `views/menu-settings.php`, `views/license.php`, `static/css/menu-setting.css`.

- **Renamed the mislabeled tab.** Settings → "User Account" tab → **"Storefront"** (label, icon,
  key `user-account`→`storefront`, whitelist, panel id, nav + cart-mode form action). Contents
  (Cart compatibility + Save-design entry points) unchanged — they were never about the account.
  Fixes **P3**.
- **Consolidated the connection surface (Integration tab).** Instead of three competing cards:
  - The **"Storelly Account"** card stays the single connect/disconnect home. Removed its
    read-only **"Active features"** row (it duplicated the toggles below — fixes the **P4**
    "status shown in two places" half) and added two clear CTAs in the connected state:
    **"Compare plans & upgrade"** (→ License page, bridges **P2**) and **"Open Storelly dashboard"**.
  - The old **"Storelly Integration"** radio card → reframed as **"Cloud features"** with plain-language
    labels ("Generate print-ready PDFs", "Sync orders to your Storelly dashboard") + a hint shown
    when not connected ("Connect your account above first — these only take effect once connected").
    (Kept the toggles rather than deleting, so no function is lost pre-caps; the read-only status
    duplication is what was removed.)
  - **"API Keys"** raw card → demoted into a collapsed **`<details class="spbwc-advanced">`** disclosure
    ("Advanced — link manually with API keys, login & connection log"), token-styled in
    `menu-setting.css`. Fixes **P4** (raw keys no longer compete with the primary connect button).
- **Clearer CTAs/labels** throughout; connect button "Enable Cloud" → **"Connect to Storelly — free"**.
- **Copy/compliance:** License page "Free plan — **limited features**" → "Free plan — **all local
  features included, no limits**" (kills the crippleware framing; aligns C1). *(Full caps-matrix
  rewrite of the plan grid + page merge is U2.)*
- **Verified (Chrome, isolated session):** Integration + Storefront tabs render, console clean, no PHP
  notices. `wp plugin check` full run deferred to the U2 batch / next release gate.

> **Deviation from the original U1 wording:** the spec said "remove the duplicate Integration radio
> card". In practice the *duplication* was the read-only status row on the Account card, not the
> toggles themselves — so the toggles were kept (reframed as "Cloud features") and the duplicate
> status row removed. Net effect (one connection home, no contradictory surfaces) is as intended.

### 9.2 U2–U4 — what shipped (2026-06-09)

Foundation first — **F1.1** (entitlement caps) landed in `includes/class-license-manager.php`:
`get_current_license()`/`sync_from_api()` now carry `plan_slug` + server-issued `caps[]`; new
`cloud_license_active()`, `can($cap)`, `plan_family()`, `plan_matrix()`, `caps_catalog()`,
`cap_label()`, `cloud_locked_error()`. Free defaults no longer claim quotas ("Up to 5 products" →
honest local-free framing; closes **C1** in the license data). Caps stay empty until the server
grants them, so `can()` is safe-false today (no false "unlocked").

- **U2 — unified Account & Plan page** (`views/license.php` rewritten; controller passes
  `$connect_nonce`/`$connected`; matrix CSS in `license.css`):
  - **Status card** drives the **S0–S3** model from `connected × cloud_license_active()` — one badge,
    one headline, one primary CTA per state (Connect free / Choose a plan / Manage subscription / Renew).
  - **Caps comparison matrix** (Free / Standard $49 / B2B $99) from `caps_catalog()` × `plan_matrix()`,
    current plan column highlighted, ✓/— per cell. Replaces the misleading `max_products` quota grid.
  - **Connection** (connect/disconnect, reusing `SPBWC_Cloud_Connect` AJAX) + manual license key + Sync
    moved into a collapsed **Advanced** disclosure. One home; one connection control.
- **U3 — connect-back activation** (`includes/class-cloud-activation.php`, registered in the loader):
  `checkout_url()` builds a nonce'd admin-post link; `handle_checkout()` mints a single-use `state`
  (15-min transient) and redirects to `app.storelly.com/connect`; `maybe_handle_return()` verifies
  `state` (`hash_equals`, CSRF), exchanges the one-time token server-to-server at
  `/api/v1/license/exchange`, persists the plan, and shows a clean admin notice. **Backend O-6 not
  live yet**, so the exchange fails gracefully → manual key / Sync fallback (never a dead end). The
  "Choose" CTAs in the matrix are the connect-back entry points. External endpoints declared in
  readme (C6).
- **U4 — upsell-by-intent (partial):** the `can()` gate + `cloud_locked_error()` (`spbwc_cloud_locked`
  WP_Error with `cap`) are ready, and the Account-page matrix shows locked vs included. **Wiring the
  inline prompt onto the real blocked controls (Export PDF, sync, etc.) is F2** — that means touching
  the PDF/order flows, deliberately deferred until the entitlement server is live to avoid gating a
  flow that would otherwise work.

**Verified (Chrome, isolated session 9315):** Account & Plan renders in state **S1** (connected/free) —
badge "CONNECTED", correct headline, caps matrix with Free=Current + Standard $49 + B2B $99 rows,
"Choose" CTAs carrying the `admin-post.php?action=spbwc_cloud_checkout&plan=…` nonce'd connect-back
URL. Console clean, no PHP notices. All changed PHP `php -l` clean.

> **Backend to light up the full loop (hand-off):** **O-6** `/connect` intent screen + one-time token
> + `/api/v1/license/exchange` returning `{ license_key, status, plan_slug, expires_at, caps[] }`;
> server must issue `caps[]` on `/license/status` for `can()` to unlock anything. **F2** then wires the
> inline upsell onto the actual cloud controls.

### 9.3 U2 polish — S0 connect flow, Sync clarity, Advanced redesign (2026-06-09)

Refinement of the shipped page from direct UX review. Files: `views/license.php`,
`static/css/license.css`, `includes/class-admin-options.php` (one display flag).

- **S0 = single-CTA connect gate.** The hero now leads with one prominent **"Connect to Storelly — free"**
  CTA + a quiet "See plans" link; the meaningless top-level Sync button is removed from every state.
- **Connect has real progress + a celebration moment** (fixes the "is it hung?" gap):
  - The Connect button gets a spinner (`.spbwc-btn-upgrade.is-loading`, which was previously undefined —
    only `.spbwc-btn-sync` had one) + label swap to "Connecting…" + an inline status line
    ("Creating your free account & linking this store…").
  - On success the card swaps to an in-card **celebration panel** (animated ✓ + "🎉 Connected!") for ~1.5s,
    then redirects with `?spbwc_welcome=1` so a **one-time welcome banner** greets the merchant on the
    connected (S1) view. Banner is dismissible and strips the flag from the URL (`history.replaceState`)
    so a refresh won't re-show it. Replaces the bare `location.reload()`.
- **"Sync" → "Refresh plan status"**, moved entirely into **Advanced** (it only means anything once
  connected): a labelled item with a clear description + "Last synced" meta. Shown only when connected.
- **Advanced redesigned.** The cramped reused `.spbwc-setting-row` 3-col grid is replaced by a clean
  `.spbwc-adv-grid` of `.spbwc-adv-item` mini-blocks (icon-titled head + description + right-aligned
  control), default **collapsed** in all states. Manual license key input now token-sized, not inline.
- **Design-token cleanup:** removed the 3 inline `style=""` attributes (status-card margin, license-key
  input width, help-section margin) into `license.css` utilities; all new rules use `_tokens.css` vars,
  responsive < 782px, RTL-aware grid (`justify-self:end`).

> **Not yet browser-verified:** the local Chrome instance was bound to the devtools-mcp profile at
> review time (single-instance lock), so the visual smoke test (S0 progress → celebration → welcome
> banner, Advanced layout) is pending. All changed PHP `php -l` clean; all referenced CSS tokens
> confirmed present in `_tokens.css`.

---

## 10. Open items

| ID | Question | Owner |
|----|----------|-------|
| UX-1 | Keep "License & Plans" as a top-level menu item renamed **Account & Plan**, or move it under a single Storelly hub? (ties into `SPEC_ADMIN_UI_REDESIGN` menu IA). | product |
| UX-2 | Where do the old "User Account" tab contents land — own "Storefront" tab or merged into "Cart & Order"? | product |
| UX-3 | Should "Connect (free)" remain a distinct affordance in S0, or only ever happen implicitly via "Choose plan"? (Distinct connect aids the free-dashboard/order-sync funnel; implicit-only is simpler.) | product |
| UX-4 | Connect-back backend readiness (`/connect` + token + `/license/exchange`) — same as `SPEC_FREEMIUM_V1_1` O-6. | backend |
| UX-5 | During the transition (before F1.1 caps land), does U1 ship alone with the current quota copy removed, or wait for U2? Recommend: **U1 now** with copy cleaned (C1), matrix in U2. | product |
```
