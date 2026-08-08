# UX/UI Bug Audit — bug THẬT đã xác minh trong code

> **Ngày:** 2026-08-07 · **HEAD:** `fa675fd` · **Owner:** David
> Rà soát bằng đọc code + `git log/blame` (không mở browser, không sửa file khi audit). Chỉ liệt kê
> **bug thật còn tồn tại ở HEAD** — đã loại feature-chưa-làm, "chưa đẹp", và bug đã fix.
> `dist/build/**` là mirror build → đã loại khỏi mọi kết luận; file:line dưới đây là **source HEAD**.

Phân loại: **P0** = mất dữ liệu / sai nghiêm trọng, fix rẻ · **P1** = sai hiển thị diện rộng ·
**P2** = bug tình huống (cart-edit/edge) · **P3** = dead control (cần quyết định sản phẩm).

## Trạng thái fix (2026-08-08)

| Bug | Trạng thái | Ghi chú |
|---|---|---|
| B1 color2 save-drop | ✅ FIXED | `admin-options.js` getJsonFields carry `color2` (option + sub-attr) |
| B2 import-404 image | ✅ FIXED | `class-printcart-import-adapter.php` nhánh scalar reset 0 |
| B3 VB auto-save stale | ✅ FIXED | `visual-builder.js` bọc `$apply` như handler Ctrl+S — **cần browser-verify** |
| B4 RTL storefront | ✅ FIXED | `wp_style_add_data(rtl,replace)` cho 3 handle + sinh `quote-storefront-rtl.css` (rtlcss) — **cần browser-verify RTL** |
| B5 cart-block mapping | ✅ FIXED | `cart-block-save.js` match theo permalink, fallback index — **cần browser-verify** |
| B6 upload filename | ✅ FIXED | `input.php` `end(explode())` thay index [1] |
| B7 advanced-dropdown | ✅ FIXED | `advanced-dropdown.php` `selected($selected,$current)` |
| **FOUC** admin editor flash | ✅ FIXED | AngularJS admin (`spbwc-ag`) + app nạp ở FOOTER, thiếu rule ng-cloak ở head → `wp_add_inline_style('spbwc-admin-ui', …ng-cloak…)` render-blocking. **cần browser-verify** |
| D1 price_breaks/depend_quantity | ✅ FIXED (ẩn) | David chọn hướng (b): ẩn 2 control chết trong `field-body.php` (verified 0-ref engine); `price[1..3]` không có UI riêng; data cũ vẫn round-trip. Chỉ classic editor phơi (V3 không) |
| D2 cart-fee/ind_qty/fixed_amount | ⏳ latent | scaffolding fork, nhiều khả năng không nằm trong `price_type.options` (merchant không chạm tới) → để dọn code sau, không phải bug user-facing |

Verify cú pháp: `php -l` sạch 4 file PHP; `node --check` sạch 3 file JS. Chưa bump version (chờ David chốt release).

---

## P0 — Mất dữ liệu / sai nghiêm trọng

### B1 · Gradient swatch mất màu thứ 2 sau khi Save  ·  effort S  ·  confidence **Cao** (2 agent xác nhận độc lập)
- **User thấy:** merchant bấm "+" thêm `color2` cho swatch gradient, chọn màu, Save → reload thì swatch về **đơn sắc**, mất màu 2.
- **Nguyên nhân:** `getJsonFields()` whitelist option KHÔNG carry `color2` — `static/js/admin-options.js:2126-2133`. PHP save decode `options[jsonFields]` rồi **ghi đè toàn bộ `fields[]`** (`includes/class-admin-options.php:2435-2437`) nên named input `[color2]` cũng bị bỏ. Rớt ở mọi luồng (classic + V3 + Visual Builder). UI có thật: `views/options/templates/field-body/attributes.php:132-135`; setter `admin-options.js:1950-1966`; storefront render `templates/single-product/options-builder/swatch.php:43-44`.
- **Fix:** thêm `color2: op.color2` (chỉ khi defined) vào object option trong `getJsonFields` — giống cách đã carry `placeholder`/`price_breaks`. Cùng lớp lỗi với đợt fix whitelist trước (`c7a49762`); đây là key sót lại.
- **Browser-verify:** không bắt buộc (cơ chế drop chắc từ code); nên xác nhận thị giác 1 lần.

### B2 · Import ảnh 404 giữ ID ảnh cũ → hiện ảnh của sản phẩm KHÁC  ·  effort S  ·  confidence **Cao**
- **User thấy:** import template/option; nếu ảnh nguồn trả 404, option/sub-attribute ở install đích hiển thị **ảnh của một sản phẩm khác** (ID cũ tình cờ trỏ media khác).
- **Nguyên nhân:** nhánh scalar `_url` chỉ `if ($attachment_id) { $value[$media_key] = $attachment_id; }` — **thiếu `else`** — `includes/class-printcart-import-adapter.php:455-457`. `add_attachment_from_url()` trả `0` khi 404 (dòng 181/186/191/196). Nhánh array (dòng 473) đã reset `0` đúng; nhánh scalar bị sót → giữ nguyên ID số của install nguồn. Options lưu cả `image` (ID) + `image_url` (URL).
- **Fix:** thêm `else { $value[$media_key] = 0; }` sau dòng 457 (mirror nhánh array). Cân nhắc `unset()` để rơi về placeholder thay vì ID 0.
- **Browser-verify:** không bắt buộc.

---

## P1 — Sai hiển thị diện rộng

### B3 · Visual Builder "Auto-saved ✓" nhưng gửi payload CŨ → mất chỉnh sửa  ·  effort M  ·  confidence Cao (cơ chế) · **cần browser-verify**
- **User thấy:** trong Visual Builder, sửa field, sau 30s thấy toast "Auto-saved ✓", nhưng thực tế các sửa đổi **kể từ lần Save tay gần nhất không được lưu**; `vbDirty=false` còn gỡ cảnh báo rời trang → rời trang là mất.
- **Nguyên nhân:** `triggerAutoSave` gọi `getJsonFields()` ngoài Angular digest — `static/js/visual-builder.js:256`. Hàm set `$scope.jsonFields` rồi submit qua native `setTimeout(0)`; hidden input `ng-value="jsonFields"` (`views/visual-builder/edit.php:235`) chỉ flush trong digest. Auto-save chạy từ `setTimeout(30000)` → **không có digest** giữa set property và submit → POST mang jsonFields cũ (hoặc `""` → bị `empty_fields_blocked` ở `class-admin-options.php:2450-2455`). Manual Save an toàn vì chạy trong `$apply` (Ctrl+S đã bọc `$apply` đúng — `edit.php:420-425`).
- **Fix:** bọc `getJsonFields()` trong `$rootScope.$apply(...)` (hoặc `$scope.$applyAsync()` trước submit), đồng bộ với cách Ctrl+S đã làm.

### B4 · Storefront vỡ layout toàn bộ trên site RTL  ·  effort M  ·  confidence Cao (gốc rễ) · **visual cần browser-verify**
- **User thấy:** trên site RTL (Arabic/Hebrew): option builder trên trang product, modal designer, modal Request-a-Quote đều lệch — padding/margin/float/absolute sai phía, sticky bar + swatch grid + summary sai bên.
- **Nguyên nhân:** 3 handle CSS storefront dùng thuộc tính vật lý và **không** có bản RTL được nạp: `includes/class-frontend-options.php:431-432` (storefront-options.css), `includes/class-product-builder-frontend.php:610-620` (app-product-builder.css), `includes/class-request-quote.php:351` (quote-storefront.css). Không có `wp_style_add_data($h,'rtl','replace')` ở đâu trong `includes/` (grep = 0); các file `-rtl.css` build sẵn không được enqueue; quote modal còn không có file `-rtl`. Module account-shell/b2b lại làm đúng (`is_rtl()` swap) → chứng tỏ storefront bị bỏ sót. **Đi ngược chuẩn "RTL nhất quán" trong CLAUDE.md.**
- **Fix:** thêm `wp_style_add_data($handle,'rtl','replace')` ngay sau mỗi `wp_register_style` của 3 handle; generate `quote-storefront-rtl.css` còn thiếu.

---

## P2 — Bug tình huống (cart-edit / edge case)

### B5 · Cart block: nút "Save design" gắn nhầm dòng  ·  effort M  ·  confidence Med · **cần browser-verify**
- **User thấy:** trong WooCommerce **Cart block** (React) nhiều item xen kẽ design/non-design, nút "Save design" có thể hiện ở dòng sai hoặc thiếu ở dòng design khi React re-render (đổi qty/xoá item).
- **Nguyên nhân:** ghép `rows[i]` ↔ `cart.items[i]` **theo chỉ số vị trí**, không match theo identity — `static/js/cart-block-save.js:53-72` (comment tự nhận "best-effort"). Store API order khác DOM order khi sort/paginate/row phụ.
- **Fix:** match theo `item.key` (Store API trả `key`) đối chiếu thuộc tính row thay vì index.

### B6 · Field Upload: tên file sai/trống + PHP notice khi Edit item trong giỏ  ·  effort S  ·  confidence Med · cần cart-edit để tái hiện
- **User thấy:** sửa item giỏ có field Upload → tên file hiển thị sai hoặc trống, kèm PHP notice.
- **Nguyên nhân:** `$filename = explode('/', $form_values[$field['id']])[1];` cứng lấy index `[1]` — `templates/single-product/options-builder/input.php:40`. Nếu value không đúng dạng `folder/file` (0 hoặc >1 dấu `/`) → undefined index / sai tên. `option_processing:861` làm đúng bằng phần tử cuối.
- **Fix:** `$parts = explode('/', $val); $filename = end($parts);`

### B7 · advanced-dropdown luôn "selected" option cuối khi JS tắt  ·  effort S  ·  confidence Med-High · impact THẤP
- **User thấy:** trước khi Angular bootstrap / nếu JS tắt, native `<select>` (display_type = Advanced dropdown) hiển thị option **cuối** được chọn thay vì giá trị đúng.
- **Nguyên nhân:** `selected($selected, $selected)` — truyền cùng biến 2 lần → luôn true cho mọi option — `templates/single-product/options-builder/advanced-dropdown.php:19`. So `dropdown.php:23` làm đúng `selected($selected, $current)`.
- **Fix:** thêm biến `$current` cho option đang lặp, dùng `selected($selected, $current)`.
- **Ghi chú:** impact thấp vì `<select>` này `display:none` và Angular `ng-model` đè giá trị hiển thị/submit — nhưng vẫn là bug code, fix 1 dòng.

---

## P3 — Dead control (cần David quyết: WIRE engine hay GỠ UI)

> Cùng bản chất: **control admin cho merchant cấu hình nhưng storefront không thực thi** → merchant nhập vào tưởng có tác dụng, thực tế bị bỏ qua. Scaffolding mồ côi kế thừa từ fork cmsmart. Mỗi cái có 2 hướng: (a) nối lại engine (feature, effort L) hoặc (b) ẩn/gỡ control để không hứa suông (bug-fix, effort M).

### D1 · price_breaks / option `price[1..3]` / depend_quantity không có engine storefront
- Engine chỉ đọc `$option['price'][0]` (`class-frontend-options.php:885,889`); grep `price[1..3]` toàn src = 0 match. `price_breaks`/`depend_quantity` chỉ tồn tại ở admin UI + save + build_config, **không** ở engine storefront. (Lưu ý: `quantity_breaks` THÌ đã có engine đầy đủ — đừng nhầm.)
- **Hệ quả:** merchant nhập giá theo bậc / tier price[1..3] / điều kiện theo số lượng → lưu OK nhưng storefront **không tính, không hiển thị discount**.

### D2 · cart-fee / ind_qty / fixed_amount ("for all items") không tính tiền
- `$line_price['fixed'/'percent'/'xfactor']` chỉ khởi tạo mặc định, **không nơi nào gán lại** → block `if` luôn false → `cart_item_fee` rỗng — `class-frontend-options.php:843-847,930,943-972`. Switch `price_type` (903-928) chỉ có f/p/p+/c/cp. Template vẫn tham chiếu `field.ind_qty`/`cart_item_fee.enable` (`option-builder.php:298-299,321-324`).
- **Hệ quả:** field kiểu cart-fee (vd import từ cmsmart) cấu hình xong không tính tiền, dòng "Cart item fee" trong Summary không bao giờ hiện.

---

## Đã kiểm tra nhưng KHÔNG phải bug / ĐÃ FIX ở HEAD (đã loại — khỏi kiểm lại)

| Điểm nghi | Kết luận | Bằng chứng |
|---|---|---|
| Pricing calc 'f' thiếu `break` | ĐÃ FIX | `class-frontend-options.php:907` (commit `efaefc9a`) |
| Core hardcode base price "" → total chỉ surcharge | ĐÃ FIX | `storefront-enhance.js:25-36` seed `scope.price` (commit `62f7f8c3`) |
| HPOS status quote legacy bị cắt cụt | ĐÃ FIX | status ≤20 ký tự + map slug cụt (commit `07ae82c7`) |
| Save nuốt sub_attributes/conditional_depend/price_breaks/depend_qty | ĐÃ FIX (các key documented) | `admin-options.js:2095-2170` (commit `c7a49762`) — chỉ còn `color2` = B1 |
| Swatch placeholder 135 borrow | ĐÃ FIX | `swatch.php:64-105` (commit `a1b219dc`, refine `21e34ee3`) |
| Customize button gating (pricing-only) | ĐÃ FIX | `spbwc_product_has_designer()` (commit `99873ff5`) |
| `select` field render thành text-input | KHÔNG đúng | `option-builder.php:139-193` route đúng; dropdown/advanced-dropdown render `<select>` thật |
| Canvas userLayers mất khi cart-edit | ĐÃ xử lý | `includeExport`/`rehydrateUserLayer` (`app-product-builder.js:120-132,2416`) |
| wptexturize phá `{{ }}` Angular | KHÔNG còn | text-node có nháy = 0; dùng `ng-bind` |
| quantity_breaks không hiện discount | KHÔNG (có engine) | server `class-frontend-options.php:1019-1044` + client mirror |
| Modal V3 CTA bị clip viewport ngắn | ĐÃ FIX | `app-product-builder.css:8188-8202` flex-column |
| Breadcrumb chồng ở option editor | KHÔNG chồng | classic gate `empty($product_id)`; V3 không render product-breadcrumb |
| Overview stats blocking → CLS/flicker | KHÔNG CLS | số render server-side (`overview.php:755,774,793`) — chỉ là mối lo perf query |
| Delete/Duplicate ghi sai bảng (stale fork) | ĐÃ FIX | target `{prefix}storelly_product_builder_options` |
| Currency entities không decode | ĐÃ xử lý | `html_entity_decode(...)` (`option-builder.php:13`) |

---

## Thứ tự fix đề xuất

1. **Lô quick-win code-proven (S, không cần browser):** B1 `color2` + B2 import-404. Gộp 1 patch, ~vài dòng.
2. **B3 Visual Builder auto-save** (M) — mất data âm thầm, ưu tiên cao; browser-verify trước/sau.
3. **B4 RTL storefront** (M) — diện rộng, đúng chuẩn CLAUDE.md.
4. **Lô cart-edit (P2):** B6 upload filename (S) + B5 cart-block (M) + B7 dropdown-selected (S).
5. **D1/D2 dead controls** — David quyết wire-hay-gỡ trước khi động.
