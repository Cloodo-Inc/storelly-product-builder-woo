# Development Milestones — Product Builder V2 (Wizard + Designer Studio)

> **Reference plan:** `C:\Users\admin\.claude\plans\cho-t-i-plan-merry-acorn.md`
>
> **Constraint:** KHÔNG sửa code logic pricing option. KHÔNG tích hợp canvas
> designer. Schema `options.*` giữ nguyên — wizard và classic editor save cùng
> data source.
>
> **Targeting plugin version:** v2.0.0 (major bump khi wizard default)

---

## Milestone Overview

| Code | Name | Effort | Depends on | Status |
|------|------|--------|------------|--------|
| **M0** | Token audit fixes & design system hardening | 2-3d | — | ⏳ Pending approval |
| **M1** | Wizard skeleton + Step 2 (Pricing fields) | 5-7d | M0 (parallel OK) | ⏳ Pending approval |
| **M2** | Wizard Step 1 (Template gallery) + Global Template wire | 3-4d | M1 | ⏳ Pending |
| **M3** | Wizard Step 3 (Buyer preview via real iframe) | 3-4d | M1 | ⏳ Pending |
| **M4** | Wizard Step 4 (Apply) + Done summary | 2-3d | M1 | ⏳ Pending |
| **M5** | Default-link flip + classic editor banner | 1d | M1-M4 | ⏳ Pending |
| **M6** | Designer Studio skeleton (theme + layout defaults) | 4-5d | M1-M5 | ⏳ Pending |
| **M7** | Designer Studio per-option override + mobile UX tab | 3-4d | M6 | ⏳ Pending |
| **M8** | Buyer-side improvements (PR riêng) | 5-7d | M6 (data contract) | ⏳ Pending |
| **M9** | Classic editor deprecation banner & retirement plan | 1d | M1-M5 stable 2 releases | ⏳ Future |

**Estimated total:** ~30-40 effective dev days, broken into 9 milestones, đa số có
thể chạy parallel hoặc song song.

**Release plan:**
- v1.3.0 — M0 (token fixes), no UX change visible
- v1.4.0 — M1 (wizard available behind opt-in link)
- v1.5.0 — M2 + M3 + M4 (wizard complete, still opt-in)
- v1.6.0 — M5 (default flip), M6 (Designer Studio beta)
- v1.7.0 — M7 + M8 (Studio complete + buyer improvements)
- v2.0.0 — M9 (classic retired)

---

## M0 — Token Audit Fixes & Design System Hardening

**Code:** `m0-tokens`  
**Effort:** 2-3 dev days  
**Risk:** Low (CSS-only, no logic)  
**Can ship independently:** ✅ Yes (no user-facing flow change)

### Goals

1. Fix undefined design token causing broken stepper UX
2. Eliminate hardcoded hex colors trong CSS/PHP để chuẩn bị cho theme override
   (foundation cho Designer Studio M6)
3. Document semantic intent của token alias

### Deliverables

| File | Change |
|------|--------|
| `static/css/_tokens.css` | Add `--nbd-st-border-strong: #8c8f94;` (hoặc value phù hợp). Add comment documenting `--st-brand-soft` ≡ `--st-pill-featured-bg` semantic intent. Add `--nbd-color-danger-soft: #fef2f2;` cho error highlight. |
| `static/css/admin-options-v2.css` | Replace 5 chip icon hex (line 328-332) bằng token (mở rộng pill family hoặc dùng tokens hiện có). Replace `#fef2f2 !important` bằng `var(--nbd-color-danger-soft)`. Remove dead fallback `#b32d2e` ở line 2231. |
| `views/options/edit-option.php` | Move inline hex `style="background: #e0e7ff; color: #3730a3;"` line 504 sang CSS class `.v2-onboarding__card-icon--upload`. |
| `views/options/templates/nbpb_com.php`, `nbpb_text.php` | Gom 7 inline `text-align` thành CSS class `.v2-table-cell--center` / `--left`. |
| `views/options/field.php` | Move inline `cursor:pointer` line 12 sang CSS. |

### Acceptance Criteria

- [ ] `Grep` for `var\(--nbd-st-border-strong` không còn fallback `,` syntax — token đã defined
- [ ] `Grep` for `#[0-9a-fA-F]{3,6}` trong `admin-options-v2.css` chỉ còn `#fff` (acceptable) hoặc mockup colors (preview phone frame)
- [ ] `Grep` for inline `style=".*#"` trong `views/options/edit-option.php` returns 0
- [ ] Stepper dot active phân biệt rõ với inactive (visual smoke test)
- [ ] Plugin Check `wp plugin check ...` → 0 ERROR
- [ ] No visual regression trên admin Edit Option page (manual smoke test)

### Test plan

- Manual: load `wp-admin → SPBWC → Edit option` → check 3 radio cards (Sections / Matrix / Stepper) trong cả active và inactive state
- Manual: DevTools inspect stepper dot active → computed background ≠ inactive dot
- Manual: Onboarding cards (empty option) → upload card icon vẫn hiển thị màu indigo
- Automated: `wp plugin check storelly-product-builder-for-woocommerce`

---

## M1 — Wizard Skeleton + Step 2 (Pricing Fields)

**Code:** `m1-wizard-skeleton`  
**Effort:** 5-7 dev days  
**Risk:** Medium (new route, but reuses existing AngularJS controller)  
**Can ship independently:** ✅ Yes (opt-in via direct URL; classic editor unchanged)

### Goals

1. Tạo wizard chrome mobile-first với 4 step container (Step 1, 3, 4 placeholder)
2. Step 2 = relayout Builder tab cũ → compact field cards + accordion sub-sections
3. List page có button "Try new editor (beta)" → wizard URL

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `includes/class-admin-options.php` | Edit | Add route handler `action=wizard` |
| `includes/class-script-hook.php` | Edit | Enqueue `admin-option-wizard.js/css` conditionally khi `action=wizard` |
| `views/options/edit-option-wizard.php` | New | Main wizard shell (4 step container + chrome) |
| `views/options/wizard/step-2-fields.php` | New | Step 2 (reuse `field.php` + 14 sub-templates) |
| `views/options/wizard/step-1-placeholder.php` | New | Placeholder "Step 1 coming in M2" |
| `views/options/wizard/step-3-placeholder.php` | New | Placeholder "Step 3 coming in M3" |
| `views/options/wizard/step-4-placeholder.php` | New | Placeholder "Step 4 coming in M4" |
| `static/js/admin-option-wizard.js` | New | Step nav, validation gate, auto-save debounce, reuse `optionCtrl` |
| `static/css/admin-option-wizard.css` | New | Mobile-first wizard chrome styles (extend tokens) |
| `views/options/index.php` (or list table) | Edit | Add small CTA "✨ Try new editor (beta)" link sang wizard, giữ Edit cũ |

### Reuse (NO changes)

- AngularJS module `optionApp` + controller `optionCtrl` (`static/js/admin-options.js`)
- All 14 field-body sub-templates (`views/options/templates/field-body/*.php`)
- Drag-drop sortable directive
- AJAX save endpoint
- All pricing math, attribute logic, conditions, bulk discount

### Acceptance Criteria

- [ ] URL `?page=spbwc-options&action=wizard&id=0` mở wizard create mode
- [ ] URL `?page=spbwc-options&action=wizard&id={existing}` mở wizard edit mode với data loaded
- [ ] Wizard chrome: top progress bar (4 step), bottom action bar (Back/Save draft/Next)
- [ ] Step 2: field palette top, field list, drag-reorder hoạt động
- [ ] Field card collapsed mặc định, expand → 3 accordion (Attributes / Conditions / Appearance)
- [ ] Save draft → option saved (cùng table, cùng schema)
- [ ] Switch sang classic editor (URL `?action=update&id=...`) → data identical
- [ ] Mobile responsive: test 375px width (iPhone SE) — không horizontal scroll, button ≥44×44
- [ ] List page button "Try new editor (beta)" link đúng wizard URL
- [ ] Classic editor URL `?action=update` **vẫn hoạt động bình thường** (chưa flip default)
- [ ] Plugin Check pass

### Test plan

- Manual end-to-end: tạo option mới qua wizard → add 3 field (1 multi-choice, 1 number, 1 upload) → save → reopen → fields đúng
- Manual switch test: tạo option qua wizard → reopen qua classic editor → save → reopen qua wizard → data identical
- Mobile: Chrome DevTools 375×667, 414×896, iPad 768×1024
- Keyboard navigation: Tab through chrome, Ctrl+S save
- A11y: focus management khi chuyển step (focus về Next button hoặc first input)
- Regression: classic editor URL không bị break

---

## M2 — Wizard Step 1 (Template Gallery) + Global Template Wire

**Code:** `m2-template-gallery`  
**Effort:** 3-4 dev days  
**Risk:** Medium (depends on Global Template endpoint reliability)  
**Depends:** M1

### Goals

1. Step 1 hiển thị template gallery dạng card grid (mobile-first)
2. Wire vào Global Template endpoint `https://app.storelly.com/product-data/data/data.json`
3. Fallback local presets khi offline
4. "Start from scratch" → skip thẳng Step 2

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `views/options/wizard/step-1-template.php` | New | Replace placeholder. Card grid + search + category tabs |
| `includes/class-template-gallery.php` | New | Fetch + cache (transient 1h) templates from endpoint |
| `static/presets/*.json` | New | 5-6 local fallback (business-card, t-shirt, mug, flyer, generic, blank) |
| `static/js/admin-option-wizard.js` | Edit | Add template selection + seed logic (overwrite vs append confirm) |
| `static/css/admin-option-wizard.css` | Edit | Template card grid responsive |

### Acceptance Criteria

- [ ] Step 1 hiển thị ≥4 template card khi online (live data)
- [ ] Click template → confirm modal nếu option đã có fields, overwrite nếu trống
- [ ] Confirm → seed `options.*` từ payload, jump Step 2
- [ ] Endpoint timeout (>20s) → fallback local presets + toast "Using local templates"
- [ ] "Start from scratch" → empty option, jump Step 2
- [ ] Edit existing option → Wizard mở Step 2 (skip Step 1)
- [ ] Search input filter cards realtime (client-side)
- [ ] Category tabs filter (Featured / Apparel / Print / Promo / Custom)
- [ ] Mobile: horizontal scroll tabs, 1-column card grid
- [ ] Tablet/Desktop: 2-3 column card grid
- [ ] Cache invalidation: admin có nút "🔄 Refresh templates"

### Test plan

- Mock endpoint trả về 0 template → graceful empty state
- Mock endpoint timeout → fallback hiện
- Apply template "Business card" → option có đúng 4 field như spec
- Apply template lên option đã có fields → confirm modal hiện
- Edit existing option → KHÔNG hiện Step 1

---

## M3 — Wizard Step 3 (Buyer Preview via Real Iframe)

**Code:** `m3-real-preview`  
**Effort:** 3-4 dev days  
**Risk:** High (cross-frame sync phức tạp)  
**Depends:** M1

### Goals

1. Step 3 embed buyer template thật trong iframe — pixel-accurate preview
2. Viewport switcher (Mobile / Tablet / Desktop)
3. Display mode picker (sections / matrix / stepper) với smart hint
4. Interaction live: admin đổi field → iframe re-render

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `views/options/wizard/step-3-preview.php` | New | Replace placeholder. Iframe container + viewport switcher + display_mode picker |
| `views/preview-iframe.php` | New | Standalone preview page (full WP template) for iframe src |
| `includes/class-preview-controller.php` | New | Route handler `?page=spbwc-preview&id={id}` |
| `static/js/admin-option-wizard.js` | Edit | postMessage sync giữa wizard và iframe |
| `static/js/preview-iframe-listener.js` | New | Iframe receives postMessage → re-render |

### Acceptance Criteria

- [ ] Step 3 hiển thị iframe với buyer template thật, không phải mockup AngularJS
- [ ] Đổi field trong Step 2 → quay Step 3 → preview updated
- [ ] Đổi display_mode → iframe re-render với layout mới
- [ ] Viewport switcher: 375 / 768 / 1024 width
- [ ] Smart hint: "7 fields detected → Stepper recommended for mobile"
- [ ] Mobile (admin trên mobile): preview mở fullscreen bottom-sheet
- [ ] Click swatch trong iframe → not navigate (preview-only, disable submit)
- [ ] Iframe loading state với skeleton

### Test plan

- Cross-browser iframe: Chrome / Firefox / Safari
- postMessage security: origin check
- Resize iframe → buyer template responsive đúng
- Display mode "stepper" hoạt động trong iframe (test Next/Prev nav)
- Mobile admin: bottom sheet slide-up smooth

---

## M4 — Wizard Step 4 (Apply) + Done Summary

**Code:** `m4-apply-done`  
**Effort:** 2-3 dev days  
**Risk:** Low (reuse Apply tab logic)  
**Depends:** M1

### Goals

1. Step 4 = Apply tab cũ relocated, category picker enhanced
2. Done summary với 3 CTA: Publish / Save draft / Open Designer Studio

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `views/options/wizard/step-4-apply.php` | New | Reuse Apply tab markup, swap category picker sang `wc-enhanced-select` |
| `views/options/wizard/done.php` | New | Summary card + 3 CTA |
| `static/js/admin-option-wizard.js` | Edit | Done state handler, Studio CTA conditional (chỉ hiện khi M6 ship) |

### Acceptance Criteria

- [ ] Step 4 product picker + category picker (searchable hierarchical)
- [ ] Count badge "✓ 42 products, 3 categories"
- [ ] Done: hiển thị option title, N fields, applied counts
- [ ] CTA "Publish" → publish + redirect list
- [ ] CTA "Save draft" → save + stay on Done
- [ ] CTA "Open Designer Studio" hidden until M6 ships, sau đó link sang `?page=spbwc-designer-studio&option_id={id}`

---

## M5 — Default-Link Flip + Classic Editor Banner

**Code:** `m5-default-flip`  
**Effort:** 1 dev day  
**Risk:** Low (UI tweak only)  
**Depends:** M1-M4 stable

### Goals

1. List page "Edit" và "+ Create" default sang wizard
2. Wizard footer link "↩ Switch to classic editor"
3. Classic editor banner "✨ Try the new wizard"

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `views/options/index.php` | Edit | Đổi default link sang `?action=wizard` |
| `views/options/options-list-table.php` | Edit | Row action "Edit" link sang wizard |
| `views/options/edit-option-wizard.php` | Edit | Add footer "Switch to classic" link |
| `views/options/edit-option.php` | Edit | Add top banner "Try new wizard" |

### Acceptance Criteria

- [ ] Default click "Edit" → wizard
- [ ] Classic editor vẫn truy cập được qua wizard footer link
- [ ] Banner ở classic dismissible (per-user WP option)

---

## M6 — Designer Studio Skeleton

**Code:** `m6-studio-skeleton`  
**Effort:** 4-5 dev days  
**Risk:** Medium (new menu, new IA)  
**Depends:** M1-M5 (Studio CTA wired)

### Goals

1. Top-level menu **🎨 Designer Studio** mới
2. 3 tab: **Theme** / **Layout default** / **Preview**
3. Iframe live preview (reuse logic từ M3)
4. Save global theme settings → WP option `spbwc_storefront_global`

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `includes/class-admin-options.php` | Edit | Register Designer Studio menu item |
| `views/designer-studio.php` | New | Main shell (mobile-first 1 col, desktop 2 col) |
| `views/designer-studio/theme.php` | New | Theme tab: color, radius, font scale |
| `views/designer-studio/layout.php` | New | Layout default: display_mode global default |
| `views/designer-studio/preview-iframe.php` | New | Iframe wrapper với viewport switcher |
| `static/js/designer-studio.js` | New | Form bindings + iframe postMessage |
| `static/css/designer-studio.css` | New | Mobile-first 3-zone layout |
| `templates/single-product/option-builder.php` | Edit | Read theme từ `spbwc_storefront_global`, apply qua CSS var inline (chỉ thêm, không sửa logic) |

### Acceptance Criteria

- [ ] Menu "🎨 Designer Studio" xuất hiện trong admin sidebar
- [ ] 3 tab: Theme / Layout / Preview
- [ ] Theme tab: color picker brand, radius dropdown, font scale slider
- [ ] Đổi brand color → save → buyer product page reload → CSS var áp dụng đúng
- [ ] Đổi display_mode default → option mới tạo dùng default đó
- [ ] Preview iframe load buyer template với theme applied
- [ ] Viewport switcher (375 / 768 / 1024)
- [ ] Mobile responsive (admin trên iPad/phone)
- [ ] Reset to default button

### Test plan

- Smoke: thay brand color global → buyer product page áp dụng đúng
- Cross-browser: Chrome / Firefox / Safari
- Mobile admin: 1-col layout, tab horizontal scroll
- Acceptance: tạo option mới sau đổi default → display_mode đúng

---

## M7 — Designer Studio Per-Option Override + Mobile UX Tab

**Code:** `m7-studio-per-option`  
**Effort:** 3-4 dev days  
**Risk:** Medium  
**Depends:** M6

### Goals

1. Scope selector: Global vs Per-option
2. Per-option override save trong `options.storefront_*` (extend schema, không sửa logic)
3. Tab "Mobile UX": sticky chip toggle, bottom-sheet behavior, micro-copy

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `views/designer-studio.php` | Edit | Add scope dropdown (Global / Option X / Option Y) |
| `views/designer-studio/mobile-ux.php` | New | Mobile UX tab |
| `views/designer-studio/micro-copy.php` | New | Custom labels (CTA text, error msg, empty state) |
| `static/js/designer-studio.js` | Edit | Per-option save logic, override precedence |
| `templates/single-product/option-builder.php` | Edit | Read per-option override first, fallback global |

### Acceptance Criteria

- [ ] Scope dropdown: "Global default" + list of options
- [ ] Per-option theme override save thành công
- [ ] Buyer page với option có override → áp dụng override
- [ ] Buyer page với option không override → áp dụng global
- [ ] Mobile UX: sticky chip toggle → buyer mobile có chip sticky
- [ ] Bottom-sheet on tap: buyer mobile tap chip → modal slide-up
- [ ] Micro-copy: đổi CTA text → buyer page đổi đúng

---

## M8 — Buyer-Side Improvements (PR Riêng)

**Code:** `m8-buyer-side`  
**Effort:** 5-7 dev days  
**Risk:** Medium (touch buyer code, PR riêng để dễ revert)  
**Depends:** M6 (data contract cho theme/layout settings)

### Goals

PR riêng cải thiện trải nghiệm buyer trên mobile, tích hợp data từ Designer Studio.

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `templates/single-product/option-builder.php` | Edit | Sticky summary chip mobile, field grouping render |
| `templates/single-product/options-builder/*.php` | Edit | Touch target ≥44px, swipe-friendly swatches |
| `static/js/app-product-builder.js` | Edit | Auto-detect display_mode khi config "auto", smart group rendering |
| `static/css/app-product-builder.css` | Edit | Mobile-first overhaul, sticky chip styles, bottom-sheet modal |

### Acceptance Criteria

- [ ] Mobile sticky chip "Paper: Matte · Qty: 100 · $42.00 · [Add to cart]"
- [ ] Tap chip → bottom-sheet modal với full summary
- [ ] Stepper mode mobile: bottom-sheet step navigator
- [ ] Field grouping: buyer thấy 3 group "Specs / Customization / Quantity" thay vì 8 field flat
- [ ] Smart display_mode auto-pick dựa trên field count
- [ ] Touch target ≥44×44px verified bằng DevTools
- [ ] Lighthouse mobile score ≥90 perf, ≥95 a11y

### Test plan

- Real device test: iPhone SE, iPhone Pro Max, iPad, Samsung Galaxy
- Lighthouse audit mobile + desktop
- A/B comparison: buyer drop-off rate sau M8 vs trước

---

## M9 — Classic Editor Deprecation & Retirement

**Code:** `m9-deprecation`  
**Effort:** 1 dev day (+ wait window)  
**Risk:** Low (UI banner only)  
**Depends:** M1-M5 stable in production ≥2 minor releases

### Goals

1. Banner đậm "This editor will be retired in v2.0"
2. Exit survey "Why do you prefer classic editor?"
3. After v2.0: classic editor read-only / removed

### Deliverables

| File | Type | Purpose |
|------|------|---------|
| `views/options/edit-option.php` | Edit | Deprecation banner + exit survey link |
| `includes/class-admin-options.php` | Edit | (v2.0) Disable classic route, hard redirect sang wizard |

### Acceptance Criteria

- [ ] Banner dismissible (per-user)
- [ ] Exit survey collects feedback (Google Form hoặc internal endpoint)
- [ ] v2.0: `?action=update` redirect sang `?action=wizard`

---

## Cross-Milestone Concerns

### Mobile-first guarantees

Mỗi milestone với UI deliverable phải pass:
- DevTools 375×667 (iPhone SE) — không horizontal scroll
- Touch target ≥44×44 cho tất cả button/chip/toggle
- Sticky bars top + bottom mobile
- Bottom-sheet modal trên mobile thay vì center modal

### Plugin Check compliance

Mỗi PR phải pass `wp plugin check storelly-product-builder-for-woocommerce` → 0 ERROR. Theo `CLAUDE.md`:
- Mọi PHP có `if ( ! defined( 'ABSPATH' ) ) exit;`
- Sanitize input, escape output
- Nonce + capability check
- Text domain `storelly-product-builder-for-woocommerce`

### i18n

Mọi string user-facing dùng `__()`, `_e()`, `_x()` với text-domain đúng. Update `.pot`
file sau M5 và M7.

### Performance budget

- Wizard JS bundle gzipped: <80 KB
- Designer Studio JS bundle gzipped: <50 KB
- Buyer template thêm CSS: <10 KB increment so với hiện tại
- Iframe preview load <2s trên 3G

### Versioning

Mỗi milestone bump minor version, plugin file header + readme `Stable tag` + git tag.
v2.0.0 chỉ khi M9 complete và classic removed.

### Branch strategy

- Mỗi milestone 1 feature branch: `feature/m{N}-{code}`
- Squash merge vào `main`
- Tag sau mỗi release
- Hotfix branches từ tag

### Risk register

| Risk | Mitigation |
|------|-----------|
| Global Template endpoint down | M2 fallback local presets |
| Iframe cross-frame sync bugs | M3 dùng postMessage với origin check + debounce |
| AngularJS scope conflict iframe vs wizard | Separate ng-app instances + isolated scope |
| Schema drift wizard ↔ classic | Acceptance test "round-trip" trong mỗi milestone |
| Mobile admin UX regress | DevTools 375px test mandatory mỗi PR |
| User reject wizard sau ship | M5 default-flip có rollback flag (admin option) |

---

## Decision points & next action

Để start M0 hoặc M1, cần user confirm:

1. **M0 hoặc M1 trước?**
   - M0 = token fixes, independent, ship sớm value
   - M1 = wizard skeleton, longer, big value
   - Đề xuất: M0 và M1 chạy parallel (2 branch riêng), M0 ship trước trong v1.3.0

2. **Open questions Section 9 trong plan** — có câu nào trả lời để mở M2 ngay?

3. **Branch convention:** `feature/m0-tokens` OK hay project dùng convention khác?

4. **Release cadence:** 1 minor/sprint hay tích lũy nhiều milestone 1 release?

5. **Bắt đầu coding khi nào?** Confirm scope cho milestone đầu tiên trong session sau.

---

**Tài liệu này KHÔNG implement gì — chỉ là roadmap. Cần user phê duyệt scope từng
milestone trước khi start coding.**
