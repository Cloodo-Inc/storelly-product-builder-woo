# SPEC — Admin UX/UI Polish, Wave 2

**Status:** DRAFT (2026-06-09) · **Owner:** David / Netbase JSC
**Scope:** Storelly Product Builder for WooCommerce (wordpress.org)
**Related specs:** `SPEC_ADMIN_UI_REDESIGN.md`, `SPEC_ONBOARDING_ACTIVATION.md`,
`SPEC_FREEMIUM_V1_1.md`, `SPEC_M5_CLOUD_CONSENT.md`, `SPEC_USER_MENU_PORTAL.md`,
`SPEC_TEMPLATE_PREVIEW.md`, `SPEC_CUSTOM_ORDER.md`

---

## 0. Context & goal

Sau đợt redesign admin (blue+gold, menu band BUILD/SELL/CONFIGURE — `SPEC_ADMIN_UI_REDESIGN.md`),
còn một loạt điểm gồ ghề UX/UI và vài lỗ hổng sản phẩm. Wave 2 dọn 13 mục. File này đặc tả **7 mục
polish cross-cutting** (A1, A2, A8, A10, A11, A12, A13). 6 mục còn lại nằm trong spec feature gốc
(xem bảng cuối §0).

### Nguyên tắc bắt buộc (mọi mục)
- Tuân thủ wordpress.org: `ABSPATH` guard, sanitize+`wp_unslash` input, `esc_*` output, nonce +
  `current_user_can()` cho mọi action/AJAX, 1 prefix `spbwc_`, text-domain literal
  `storelly-product-builder-for-woocommerce`, `$wpdb->prepare()`, `wp_enqueue_*`, **không phone-home**
  ngoài flow consent đã có.
- Token-first: dùng `static/css/_tokens.css`; không thêm `style="…"` inline / giá trị màu-spacing
  hardcode mới. RTL-safe (logical properties).
- DoD: chạy skill `storelly-finish-task` sau mỗi mục (token/UX → Chrome test session riêng → compliance
  → update spec → auto-commit local, **không push**).

### Bản đồ 13 mục
| # | Mục | Spec |
|---|-----|------|
| 1 | Overview dọn khối duplicate | **A1 (file này)** |
| 2 | Field AJAX load sau save | **A2 (file này)** |
| 3 | My Account UX/UI | `SPEC_USER_MENU_PORTAL.md` |
| 4 | Storelly account registration UX | `SPEC_M5_CLOUD_CONSENT.md` |
| 5 | Pricing/Plans page chuẩn | `SPEC_FREEMIUM_V1_1.md` |
| 6 | Template Preview chuẩn hóa | `SPEC_TEMPLATE_PREVIEW.md` |
| 7 | Test welcome wizard | `SPEC_ONBOARDING_ACTIVATION.md` |
| 8 | Languages gọn vào Settings | **A8 (file này)** |
| 9 | Custom Order Sample | `SPEC_CUSTOM_ORDER.md` |
| 10 | Unified search component | **A10 (file này)** |
| 11 | Fix header/upsell flicker | **A11 (file này)** |
| 12 | Capability warning → FAQ wizard | **A12 (file này)** |
| 13 | Bỏ JSON I/E → Save to Cloud | **A13 (file này)** |

---

## A1 — Overview: dọn khối dư thừa / duplicate

> **Status: IMPLEMENTED (2026-06-09).** Language widget gỡ khỏi `overview.php` (về Settings ▸ Languages,
> A8). "Showing local data only" banner → 1 badge nhỏ `.spbwc-page-hero__badge` trong page-hero (link Sync),
> CSS token-first trong `overview.css`. Welcome cloud-card rút gọn (xem A4/M5.9). Welcome-mode còn nguyên.
> CẦN verify browser: badge hiện đúng khi remote down, không vỡ layout hero.

### Vấn đề
`views/overview.php` (1347 dòng) chồng nhiều khối nói cùng một ý:
- `page-hero` (eyebrow "Storelly Product Builder" + title Overview) **trùng** branding với
  `license-hero` ngay dưới.
- **i18n language widget** render ở Overview *và* Settings (noisy — chính user phàn nàn). → chuyển hẳn
  về Settings (mục A8), bỏ khỏi Overview.
- Welcome `cloud-card` (welcome-mode) + Settings›Integration "About Storelly Cloud" + license-hero
  benefit "Cloud off" — 3 chỗ mô tả cloud.
- "Showing local data only" notice + license meta "No expiry — but limited feature set" lặp ý Free.

### Yêu cầu
1. **Một nguồn cho mỗi thông điệp**:
   - Branding/plan/upgrade → chỉ `license-hero`.
   - Ngôn ngữ → chỉ Settings (A8).
   - Trạng thái Cloud ngoài welcome-mode → chỉ Settings (A4). Trong welcome-mode → chỉ welcome cloud-card.
2. **Gỡ** lệnh gọi `SPBWC_I18n_Notice::render_language_widget()` khỏi `overview.php` (giữ ở Settings).
3. Gộp `page-hero` + `license-hero` về một header gọn (giữ CTA "Documentation" + "Create option group";
   plan badge + Upgrade/Sync nằm trong header thống nhất) HOẶC giữ 2 khối nhưng bỏ phần trùng eyebrow/title.
4. "Showing local data only" notice: gộp ý vào header (1 badge "Local data" + link Sync), không là banner riêng.
5. Stat-grid, design-activity, recent-activity giữ nguyên (không trùng).

### Acceptance
- Overview không còn 2 khối branding; không còn language widget; mỗi thông điệp xuất hiện ≤ 1 lần.
- Không vỡ welcome-mode (welcome cloud-card vẫn chạy khi `is_welcome_mode()`).
- 0 inline style mới; CSS trong `overview.css` dùng token.

### Files
`views/overview.php`, `includes/class-admin-options.php::spbwc_overview()`, `static/css/overview.css`.

---

## A2 — Field cần AJAX load input sau save (không reload trang)

> **Status: IMPLEMENTED — conservative (2026-06-09).** KIẾN TRÚC THỰC TẾ: field-body partials
> (`views/options/templates/field-body/*`) là **AngularJS `<script type="text/ng-template">`**, render
> client-side từ model `$scope.options.fields` — KHÔNG phải HTML fragment server-render. Field "đụng save
> mới hiện đúng" thực chất chỉ vì Save cũ làm **full page reload** (rehydrate model từ PHP). Giải pháp an
> toàn (không động `getJsonFields()` chung của classic/VB): thêm **capture-phase submit interceptor** cho
> riêng form V3 `#spbwc-po-v3-form` trong `admin-options.js` → POST cùng FormData tới handler **đã có sẵn**
> `spbwc_save_option_ajax` (nonce `spbwc_save_option_action` + cap `spbwc_manage_product_builder`/`manage_options`),
> KHÔNG reload. Model Angular giữ nguyên nên sub_attributes / conditional_depend / price_breaks /
> depend_quantity / placeholder vẫn render đúng tức thì. Serialization `jsonFields` KHÔNG đổi → **0 nguy cơ
> whitelist nuốt field** (bài học `save_drops_subattributes` an toàn). Interceptor tự skip khi có VB shell
> (`#spbwc-vb-form`) hoặc classic. Option mới: cập nhật `option_id` + URL (`action=v3&id=…`) in-place, không
> redirect. **CẦN verify browser**: (1) Save option có sub_attr/conditional/price_breaks → hiện đúng ngay
> không reload + reload tay vẫn persist; (2) tạo option MỚI rồi Save → ở lại trang, id cập nhật, save lần 2
> update đúng row; (3) classic editor + VB auto-save KHÔNG đổi hành vi; (4) lỗi mạng không mất model.

### Vấn đề
Trong options builder, một số field chỉ render đúng input/state **sau khi Save + full page reload**
(Save hiện là form submit truyền thống). Liên quan whitelist `getJsonFields`/`jsonFields` trong
`static/js/admin-options.js` (memory: Save từng nuốt `sub_attributes`, `conditional_depend`,
`placeholder`, `price_breaks`, `depend_qty/quantity` nếu thiếu whitelist).

### Yêu cầu
1. **Audit** danh sách field "đụng save mới hiện đúng": `sub_attributes`, `conditional_depend`,
   `price_breaks`, `depend_quantity/depend_qty`, `placeholder`, các field type render phụ thuộc dữ liệu
   đã lưu. Ghi danh sách vào spec khi implement.
2. Chuyển luồng Save sang **AJAX** (`wp_ajax_spbwc_option_save` hoặc handler hiện có): server lưu rồi
   **render lại fragment HTML** của field/section vừa lưu (tái dùng partial `views/options/templates/field-body/*`)
   trả về JSON; JS swap DOM in-place. Không reload trang.
3. Giữ nonce + `current_user_can()`; field key mới phải thêm vào whitelist `getJsonFields`/`jsonFields`.
4. Hiển thị trạng thái: saving spinner → saved toast (dùng toast component sẵn có nếu có), error inline.
5. Không hồi quy: dữ liệu đã lưu không bị whitelist đè mất (đúng bài học memory `save_drops_subattributes`).

### Acceptance
- Save một option có sub_attributes/conditional/price_breaks → field hiển thị đúng **ngay** không reload.
- Reload thủ công sau đó vẫn thấy đúng (đã persist).
- Không có field key nào bị nuốt khi Save.

### Files
`static/js/admin-options.js`, AJAX handler trong `includes/class-admin-options.php`,
partials `views/options/templates/field-body/*`.

---

## A8 — Plugin Languages gọn vào Settings

> **Status: IMPLEMENTED (2026-06-09).** Thêm tab `languages` (icon `dashicons-translation`) vào
> `menu-settings.php`: `'languages'` trong `$spbwc_valid_tabs`, nav link, panel `#tab-languages` gọi
> `SPBWC_I18n_Notice::render_language_widget()` (đặt ngoài form chính, tab JS show/hide như tab khác). BỎ
> lệnh gọi widget always-on ở cuối Settings + đã gỡ khỏi `overview.php` (A1). Logic `class-i18n-notice.php`
> không đổi; controller không validate tab (whitelist chỉ ở view) nên không cần sửa. CẦN verify browser:
> tab Languages hiện widget, đổi Site Language phản ánh đúng locale.

### Vấn đề
Khối ngôn ngữ (`SPBWC_I18n_Notice::render_language_widget()`) nhúng ở **cả** Overview lẫn Settings.
Nên gom về một chỗ trong Settings.

### Yêu cầu
1. Thêm tab **`languages`** vào Settings (`views/menu-settings.php`):
   - Thêm `'languages'` vào `$spbwc_valid_tabs` (`menu-settings.php:54`).
   - Thêm `<a … data-tab="languages">` vào nav `#spbwc-settings-nav` (icon `dashicons-translation`).
   - Thêm panel `#tab-languages` gọi `SPBWC_I18n_Notice::render_language_widget()`.
2. **Bỏ** widget khỏi `views/overview.php` (đồng bộ A1).
3. Giữ nguyên dashboard welcome-notice 1 lần (`maybe_render` trên screen `dashboard`) — đó là onboarding
   push khác mục đích.
4. Controller settings (`class-admin-options.php`) nếu có validate tab thì cho phép `languages`.

### Acceptance
- Settings có tab Languages hiển thị widget; Overview không còn widget; chỉ 1 surface trong admin pages.
- Đổi Site Language vẫn phản ánh đúng locale trong widget.

### Files
`views/menu-settings.php`, `views/overview.php`, `includes/class-i18n-notice.php` (không đổi logic, chỉ nơi gọi),
`includes/class-admin-options.php` (tab whitelist nếu có).

---

## A10 — Unified admin search component

### Vấn đề
4 ô search có markup/CSS riêng, lệch nhau:
- `views/options/options-list-table.php` → `.spbwc-block-search`
- `views/products.php` → `.spbwc-products-search` / `.spbwc-search-input`
- `views/manager-fonts.php` → `.spbwc-font-search`
- `views/templates/library.php` → `.spbwc-tl-toolbar__search`

### Yêu cầu
1. Tạo partial dùng chung `views/partials/search-box.php` nhận args:
   `$id`, `$name`, `$placeholder`, `$value`, `$form` (optional wrap), `$clear_id`.
   Markup chuẩn: wrapper `.spbwc-search`, icon `.spbwc-search__icon` (dashicons-search),
   input `.spbwc-search__input type=search`, nút clear `.spbwc-search__clear` (ẩn khi rỗng).
2. CSS `.spbwc-search*` đặt trong file shared (`static/css/_components.css` mới, hoặc bổ sung tokens):
   token hóa height, radius, border, focus ring, icon color; **RTL** dùng logical properties
   (inline-start cho icon, inline-end cho clear).
3. Refactor 4 view trên dùng partial chung. **Giữ JS hành vi riêng** mỗi trang (filter logic khác nhau) —
   chỉ thống nhất selector mới (`.spbwc-search__input` / `$clear_id`).
4. `wc-product-search` (select2 của Woo) KHÔNG đụng — đó là control khác.

### Acceptance
- 4 ô search trông giống nhau (1 design), clear button hoạt động, focus ring đồng nhất, RTL ổn.
- Không vỡ filter/JS hiện có ở từng trang.

### Files
`views/partials/search-box.php` (mới), `static/css/_components.css` (mới) + enqueue,
4 view: options-list-table, products, manager-fonts, templates/library.

### Status — DONE (2026-06-09)
- Partial `views/partials/search-box.php`: args `id`, `name`, `placeholder`, `value`, `clear_id`,
  `aria_label`, `type`, `input_attrs` (raw, caller-escaped — dùng cho AngularJS ng-* ở fonts),
  `wrapper_id`, `wrapper_class`. Markup `.spbwc-search > .spbwc-search__icon + .spbwc-search__input +
  .spbwc-search__clear` (clear ẩn khi value rỗng). ABSPATH guard + escape mọi arg.
- CSS `static/css/_components.css` (`.spbwc-search*`) token hóa height/radius/border/focus-ring/icon,
  RTL bằng logical properties (`margin-inline-start/end`, `padding-inline`). Modifier `--block` /
  `--grow` (toolbar Template Library). Đăng ký + enqueue handle `spbwc-components` (dep `spbwc-tokens`
  + `dashicons`) trong `includes/class-script-hook.php` qua hook `admin_enqueue_scripts` priority 20,
  chỉ chạy khi `spbwc-admin-ui` đã enqueue (mọi trang admin Storelly).
- 4 view refactor dùng partial. Selector mới: input giữ nguyên id cũ (`spbwc-unified-search`,
  `spbwc-search-input`, `spbwc-tl-search`) + id mới `spbwc-font-search-input`; clear id
  `spbwc-search-clear` (options/products), `spbwc-font-search-clear`, `spbwc-tl-search-clear`.
  JS filter mỗi trang GIỮ NGUYÊN (target theo id). products.js: `syncClearBtn` đổi `style.display`
  → thuộc tính `hidden`. fonts + library thêm inline script wire nút clear (clear value + dispatch
  `input` event cho jQuery/Angular). Không đụng select2 `wc-product-search`.

---

## A11 — Fix flicker header + upsell lúc mở Overview

### Nguyên nhân (đã xác định)
1. `SPBWC_License_Manager::get_overview_stats()` gọi **đồng bộ** trước `include overview.php`
   (`class-admin-options.php:4300`) → nếu remote chưa cache thì block render.
2. Admin notices bị WP relocate bằng JS sau `<hr class="wp-header-end">` (sau paint) → reflow.
3. Dashicons icon-font swap (FOIT/FOUT) khi hero/upgrade button render.
4. Thiếu reserve height cho `page-hero` / `license-hero` / `.spbwc-btn-upgrade`.

### Yêu cầu
1. **Stats non-blocking**: `spbwc_overview()` đọc stats từ transient ngay (`get_transient`); nếu thiếu →
   render skeleton số (`—`/shimmer) và refresh nền qua AJAX (`wp_ajax_spbwc_overview_stats`) sau paint.
   Remote fetch chuyển vào AJAX handler, có timeout ngắn + lưu transient (TTL ~1h như hiện tại).
2. **Reserve layout**: đặt `min-height` (token) cho `.spbwc-page-hero`, `.spbwc-license-hero`,
   `.spbwc-btn-upgrade` để chặn CLS khi nội dung/icon load.
3. **Icon font**: đảm bảo `dashicons` enqueue như dependency của CSS overview (đã là dep ở
   onboarding-notices); cân nhắc preload. Tránh icon nhảy size.
4. CSS overview enqueue ở `admin_enqueue_scripts` (đã vậy) — đảm bảo không có `<style>` inline render muộn
   trong view.

### Acceptance
- Mở Overview: header + upgrade block **không giật**; số liệu hiện skeleton rồi điền mượt (không layout shift).
- Lighthouse/devtools CLS khối header ≈ 0; verify bằng chrome-devtools performance trace.

### Files
`views/overview.php`, `includes/class-admin-options.php` (controller + AJAX stats),
`includes/class-license-manager.php` (tách remote fetch ra AJAX-safe), `static/css/overview.css`,
enqueue `includes/class-script-hook.php`.

---

## A12 — Server Capability warning → FAQ trong Setup Wizard

**Status:** DONE (2026-06-09)

### Implementation notes (as shipped)
- `SPBWC_System_Status` (`includes/class-system-status.php`) — pure read, transient-cached (10 min).
  Checks: image library (Imagick/GD), `memory_limit` (≥128 MB), effective upload limit (min of
  `upload_max_filesize`/`post_max_size`, ≥8 MB), PHP version (≥7.4), WP-Cron (`DISABLE_WP_CRON`),
  cloud. Each returns `{key, level: ok|warn|error, label, detail, faq_anchor}`. Helpers
  `get_warnings()`, `has_error()`, `flush()`.
- **No phone-home:** the `cloud` check reads `SPBWC_Cloud_Connect::is_connected()` (local flag) only —
  it never pings app.storelly.com, and is always `level=ok` (cloud is optional/opt-in), so an
  unconnected store is never contacted.
- Setup Wizard renders warn/error checks as `.spbwc-notice-banner--warn` banners (errors mapped to the
  warn style) each with a "How to fix →" link to `#faq-<key>`.
- FAQ accordion in `views/setup-wizard/landing.php` using native `<details>/<summary>`
  (`#faq-imagick`, `#faq-memory`, `#faq-upload`, `#faq-php`, `#faq-cron`, `#faq-cloud`) — all i18n.
- CSS appended to `static/css/storelly-admin-ui.css` (`.spbwc-faq*`, `.spbwc-sys-warnings`,
  `.spbwc-notice-banner__fix`) — token-driven, no inline style, RTL-safe (flex + logical).

### Vấn đề
Chưa có khối cảnh báo năng lực server chuyên dụng; khi có cảnh báo (vd "Server Capability Warning")
không có hướng dẫn khắc phục. Setup Wizard là nơi hợp lý đặt FAQ.

### Yêu cầu
1. Tạo `includes/class-system-status.php` (`SPBWC_System_Status`) — pure read, cache nhẹ:
   check `Imagick`/`GD`, `memory_limit`, `upload_max_filesize`/`post_max_size`, PHP version,
   WP-Cron (DISABLE_WP_CRON note), cloud reachable (chỉ khi đã connect — không phone-home khi chưa).
   Trả mảng `[{ key, level: ok|warn|error, label, faq_anchor }]`.
2. Render cảnh báo (warn/error) bằng pattern `.spbwc-notice-banner--warn` sẵn có; mỗi cảnh báo có link
   **"How to fix →"** trỏ tới anchor FAQ trong Setup Wizard (`?page=…setup-wizard#faq-<key>`).
3. Bổ sung **FAQ accordion** vào `views/setup-wizard/landing.php`: mỗi capability một mục
   (`id="faq-imagick"`, `faq-memory`, `faq-upload`, `faq-cron`, `faq-cloud`…) với nội dung khắc phục
   ngắn gọn (i18n).
4. Hiển thị cảnh báo ở: Overview (nếu có error nặng), Setup Wizard, hoặc Settings›System — chọn 1 điểm
   chính (Setup Wizard) + 1 nhắc nhẹ (Overview) để tránh noise.

### Acceptance
- Khi thiếu Imagick/memory thấp → cảnh báo hiện + link nhảy đúng mục FAQ trong wizard.
- Không phone-home khi chưa connect (cloud check skip).
- Token-first, RTL ok.

### Files
`includes/class-system-status.php` (mới), `views/setup-wizard/landing.php` (FAQ), view render warning,
CSS (reuse notice-banner + accordion token).

---

## A13 — Bỏ JSON Import/Export → Save to Cloud / Reuse from Cloud

### Quyết định (chốt): **Chỉ Cloud**
Gỡ workflow JSON import/export option-set thủ công của user; thay bằng lưu/khôi phục qua Storelly Cloud.

### Yêu cầu
1. **Gỡ UI JSON**:
   - Accordion "Import / Export" trong `views/options/edit-option-v3.php` (~dòng 335) → thay bằng card
     **"Save to Cloud / Reuse"**.
   - Tab/section Import/Export JSON trong classic `views/options/edit-option.php` → gỡ hoặc thay tương tự.
   - `static/js/global-import-app.js` & `class-product-exporter.php`: **giữ** phần phục vụ seed/sample
     (demo, wizard) — chỉ gỡ entry point JSON option-set cho user. Đánh dấu path user-facing deprecated.
2. **Save to Cloud**: nút đẩy option-set hiện tại lên app.storelly.com.
   - Gate `SPBWC_Cloud_Connect::is_connected()`. Chưa connect → CTA "Connect Storelly Cloud" (trỏ A4 flow),
     không hiện nút Save.
   - AJAX `wp_ajax_spbwc_cloud_option_save`: nonce + `current_user_can('manage_options')`; payload
     option-set (escape), gửi qua `SPBWC_HTTP`/API client hiện có; lưu mapping cloud-id ↔ local option-id.
3. **Reuse from Cloud**: liệt kê option-set đã lưu trên cloud → chọn → tải về áp vào option-set/product khác.
   - AJAX `wp_ajax_spbwc_cloud_option_list` + `…_pull`. Sanitize response, không trust HTML từ server.
4. **Compliance**: khai báo external service (save/reuse → app.storelly.com) trong `readme.txt` mục
   "External services" (data gửi: cấu hình option-set; khi nào: chỉ khi user bấm Save; điều kiện: đã connect).
5. Quyền: tính năng cloud = paid/connected → tôn trọng entitlement `caps[]` (xem `SPEC_FREEMIUM_V1_1.md`).

### Acceptance
- Không còn nút Import/Export JSON cho user; có Save to Cloud / Reuse khi đã connect; có CTA connect khi chưa.
- Save rồi Reuse trên option-set khác → ra đúng cấu hình.
- readme khai báo external service đầy đủ; Plugin Check 0 error; không phone-home khi chưa connect.

### Files
`views/options/edit-option-v3.php`, `views/options/edit-option.php`,
`includes/class-cloud-connect.php` (thêm save/list/pull) hoặc class mới `class-cloud-options.php`,
`includes/class-product-exporter.php` (deprecate user path), AJAX handlers, `readme.txt`.

---

## Thứ tự thực thi (toàn wave)
1. Specs (file này + extend 5 spec cũ).
2. Quick wins: A8 → A1 → A10 → A11.
3. Onboarding/sample: mục 7 → mục 9 → A12.
4. Cloud/pricing: mục 4 → mục 5 → A13.
5. Feature UX: mục 3 → A2 → mục 6.

## Verification chung
- `wp plugin check storelly-product-builder-for-woocommerce` → 0 ERROR (lọc TextDomainMismatch giả do tên
  thư mục dev `storelly-product-builder-woo` ≠ slug).
- Chrome session riêng (`chrome-multi-session` + `wp-admin-login`): screenshot trước/sau, console 0 error.
- A11: chrome-devtools performance trace, CLS ≈ 0.
- A13 / mục 9: chạy thử rồi Undo/cleanup, xác nhận không rác data, không phone-home khi chưa consent.
