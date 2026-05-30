# SPEC — New Flow V2: Pricing Options Wizard + Product Studio

> **Status:** DESIGN / PROPOSAL. Tài liệu thiết kế luồng mới, triển khai **song song** với
> classic (xem `SPEC_FLOW_CLASSIC.md`) — classic giữ nguyên code, V2 thêm route/file riêng,
> **không sửa logic pricing**.
>
> **Plugin:** Storelly Product Builder for WooCommerce · **Target version:** ≥ v1.4.0 (wizard
> opt-in), v2.0.0 khi default-flip · **Prefix:** `spbwc_` / `SPBWC_`
> **Liên quan:** roadmap thực thi tại `docs/DEV_MILESTONES_V2_WIZARD.md`; tham chiếu kỹ thuật
> `docs/SPEC.md`; classic `docs/SPEC_FLOW_CLASSIC.md`.

---

## 1. Mục tiêu & nguyên tắc

1. **Tách bạch 2 việc khác bản chất thành 2 menu độc lập:**
   - **Pricing Options** — đa số product chỉ cần cái này (chọn paper/size/qty + giá). Nhẹ,
     dạng wizard, KHÔNG nạp gì liên quan canvas.
   - **Product Studio** — chỉ cho product cần build trực quan (designer fields + product
     builder preview + xuất PDF). Nặng (fabric.js) nhưng chỉ nạp khi vào menu này.
2. **Wizard + lazy-load:** thanh-bước-dọc (step rail), mỗi bước render lazy (`ng-if`), asset
   nạp theo từng màn hình.
3. **Không phá classic:** cùng bảng `wp_storelly_product_builder_options`, cùng schema
   `options.*`. Round-trip classic ↔ V2 phải cho data y hệt.
4. **Giữ AngularJS** (`optionCtrl`) — relayout, không rewrite.
5. **wordpress.org compliant:** ABSPATH guard, sanitize/escape, nonce + capability, text
   domain, `wp plugin check` = 0 ERROR; bump version 3 nơi mỗi release.

---

## 2. Thuật ngữ — "Product Studio" (KHÔNG dùng "Designer")

Storelly chỉ có **product builder**, không phải canvas designer độc lập → flow mới dùng từ
**Product Studio** cho toàn bộ phần trực quan.

| Cũ (classic) | Mới (V2) |
|---|---|
| Tab "Designer" trong edit-option | Menu top-level **Product Studio** |
| "Designer Component / Text / Image" | giữ tên field (kế thừa) nhưng *thuộc* Product Studio |
| nhãn "Builder" (tab field) | đổi thành **"Fields"** để hết chồng tên |

> **Lưu ý hòa hợp roadmap:** `DEV_MILESTONES_V2_WIZARD.md` (M6-M7) gọi là **"Designer
> Studio"** và định nghĩa nó là *theme/layout toàn cục của storefront*. Spec này **mở rộng &
> đổi tên** khái niệm đó thành **"Product Studio"** với phạm vi rộng hơn: gồm cả designer
> fields (nbpb_*) + views + product-builder preview + PDF output. Khi cập nhật roadmap, thống
> nhất dùng "Product Studio". Theme/layout toàn cục là một *tab con* trong Product Studio.

---

## 3. Khái niệm cốt lõi — Designer field "kế thừa nhưng độc lập"

Các field **Designer Component (`nbpb_com`)**, **Designer Text (`nbpb_text`)**, **Designer
Image (`nbpb_image`)** + cờ **"Product Builder enable"**:

- **Kế thừa:** lưu trong **cùng** mảng `options.fields[]`, cùng cấu trúc field
  (`general`, `appearance`) như pricing field. Phân biệt bằng thuộc tính `field.nbpb_type`
  (rỗng = pricing field) và `field.nbd_template` (template tab "Product builder").
  Bằng chứng: `views/options/templates/field-body.php:83-86` (tab `tab-product-builder`
  chỉ hiện `ng-if="field.nbpb_type"`), `views/options/field.php:79,116-118`.
- **Độc lập về bản chất:** chúng không phải "thuộc tính giá" — chúng định nghĩa thành phần
  trên canvas/preview. Khi một option có ≥1 nbpb field (hoặc bật "Product Builder enable"),
  option đó **được mở thêm Product Studio preview** để admin dựng trực quan.

**Quy tắc V2:**
- Menu **Pricing Options** chỉ thao tác pricing field (m/n/t/a/u). Nếu option có nbpb field,
  hiện badge "Has Studio" + nút "Open in Product Studio ↗".
- Menu **Product Studio** là nơi thêm/sắp đặt nbpb field + views + preview + PDF. Khi admin
  thêm nbpb field ở đây, nó ghi vào cùng `options.fields[]` của option đang chọn.
- Một option có thể: (a) chỉ pricing (đa số) — không bao giờ chạm Studio; hoặc (b) pricing +
  studio (product cần build trực quan).

---

## 4. Information Architecture mới (2 menu)

```
Storelly Builder (top menu)
├── Overview
├── Pricing Options     [WIZARD]  ← slug cũ SPBWC_PB_BUILDER_SLUG, thêm action=wizard
│       (chỉ pricing field; nhẹ; không nạp fabric/media tới khi cần)
├── Product Studio  🎨  [MỚI]     ← slug mới SPBWC_PB_STUDIO_SLUG
│   ├── (chọn option) → Views → Designer fields → Preview
│   └── menu phụ "Output (PDF)" ĐI KÈM Product Studio (không ở Pricing Options)
├── Products
├── Orders
├── Quotes
├── Designs
└── License …
```

- **Output (PDF)** chuyển từ tab trong edit-option → màn con của Product Studio (vì PDF gắn
  với product builder, không liên quan pricing).
- **Import/Export** giữ ở Pricing Options (thao tác trên option data).

---

## 5. Pricing Options — luồng Wizard (rail + lazy)

Layout: **rail bước dọc trái (sticky)** + **nội dung bước (lazy `ng-if`)** + **live preview phải**.

| # | Bước | Tái dùng từ classic | Asset nạp khi mở bước |
|---|---|---|---|
| ① | **Basics** | title + display mode (`edit-option.php:160-222`) | nhẹ |
| ② | **Fields** | palette pricing + field list + per-field price (`:351-514`, `field.php`) | `wp-color-picker` (lazy) |
| ③ | **Quantity & pricing** | quantity breaks cấp option (`:226-349`) | nhẹ |
| ④ | **Apply to products** | panel apply (`:793-883`) | `wc-enhanced-select` + product search (lazy) |
| ⑤ | **Review & Publish** | summary + publish | nhẹ |

**Hành vi:**
- **Tạo mới (id=0):** wizard tuần tự, "Save & next", gate publish tới khi qua Basics + ≥1 field.
- **Sửa (id>0):** bấm thẳng bước bất kỳ trên rail (vẫn lazy).
- **Lazy thật:** panel chưa mở dùng `ng-if` (không compile DOM/watcher) thay vì `ng-show`.
- Palette ở bước Fields **chỉ còn pricing field** (m/n/t/a/u). Không còn chip designer.
- Nếu option có nbpb field (tạo từ Studio): hiện thẻ thông tin "This option has a Product
  Studio" + link mở Studio (read-only ở Pricing Options).

**Không có canvas / fabric.js / Output PDF ở menu này.**

---

## 6. Product Studio — luồng mới

Truy cập: menu **Product Studio** → chọn option (hoặc mở từ Pricing Options qua link
`?page=<studio-slug>&option_id={id}`).

Bố cục tab con (mobile-first, có thể lazy từng tab):
1. **Views** — quản lý `options.views[]` (tái dùng UI view-card classic `edit-option.php:884-1008`).
2. **Designer fields** — palette nbpb_com/text/image (tái dùng `add_field('nbpb_*')`), đặt &
   cấu hình; ghi vào `options.fields[]` của option đang chọn.
3. **Preview** — preview product-builder thật (iframe, theo M3 trong roadmap), viewport switch.
4. **Output (PDF)** — cấu hình xuất PDF (panel `output` di chuyển từ classic `:1013+`).
5. *(tùy chọn)* **Theme/Layout** — gộp khái niệm "Designer Studio" cũ (M6/M7): brand color,
   radius, display_mode default, override per-option.

**Asset:** chỉ menu này mới enqueue `spbwc-fabric` (~539KB), snap.svg, spectrum, media library.
Pricing Options không bao giờ chạm tới.

---

## 7. Data contract (bất biến)

- Một nguồn dữ liệu duy nhất: bảng `wp_storelly_product_builder_options`, schema `options.*`.
- Pricing Options ghi `options.fields[]` (pricing) + `options.quantity_*` + `options.product_ids`.
- Product Studio ghi `options.fields[]` (nbpb) + `options.views[]` + cấu hình output PDF.
- Cả hai dùng cùng endpoint save `spbwc_save_option` + nonce `spbwc_save_option_action` +
  field-name pattern `options[fields][{i}]...` như classic.
- **Round-trip test bắt buộc:** tạo/sửa qua V2 → mở lại bằng classic → data y hệt, và ngược lại.

---

## 8. Asset strategy (giảm tải)

Sửa `includes/class-admin-options.php:405-494` + `includes/class-script-hook.php`:

1. **Tách enqueue theo screen:** gate riêng cho `action=wizard` (Pricing Options) vs slug
   Product Studio. fabric/snap/spectrum **chỉ** ở Product Studio.
2. **Bỏ `wp_enqueue_media()` vô điều kiện** (`edit-option.php:26`) ở flow V2 — chỉ gọi khi mở
   bước/tab cần media.
3. **Lazy deps:** `wc-enhanced-select` chỉ bước Apply; `wp-color-picker` chỉ bước Fields.
4. **Lazy panel** bằng `ng-if`.

**Ước tính Pricing Options (tải đầu):** ~400KB JS → ~240KB; fabric.js 539KB → **0KB**.

---

## 9. Hằng số / route / file mới (KHÔNG sửa classic)

| Hạng mục | Vị trí | Việc |
|---|---|---|
| Slug Studio | `storelly-product-builder-for-woocommerce.php:46-54` | thêm `SPBWC_PB_STUDIO_SLUG` |
| Đăng ký menu Studio | `class-admin-options.php:292-331` | `add_submenu_page(...)` + callback `spbwc_product_studio_page()` |
| Route wizard | `class-admin-options.php` (callback list/edit) | nhận `action=wizard` |
| Enqueue theo screen | `class-admin-options.php:405-494`, `class-script-hook.php` | gate riêng wizard vs studio |
| Shell wizard | `views/options/edit-option-wizard.php` (mới) | rail + 5 bước |
| Bước wizard | `views/options/wizard/step-*.php` (mới) | tái dùng `field.php` + sub-template |
| Shell Studio | `views/product-studio.php` + `views/product-studio/*.php` (mới) | Views/Fields/Preview/Output |
| JS wizard | `static/js/admin-option-wizard.js` (mới) | step nav + lazy enqueue, reuse `optionCtrl` |
| JS studio | `static/js/product-studio.js` (mới) | views + nbpb + iframe preview |
| CSS | `static/css/admin-option-wizard.css`, `product-studio.css` (mới) | extend `_tokens.css` |

**Classic giữ nguyên:** `views/options/edit-option.php`, `static/js/admin-options.js`,
`admin-options.css`, `admin-options-v2.css` — chỉ thêm banner "Try new editor" (không đổi logic).

---

## 10. Coexistence & migration

- **v1.4.0:** wizard opt-in qua link "✨ Try new editor (beta)" ở list page; classic là default.
- **v1.5.0:** wizard đủ 5 bước; Product Studio beta.
- **v1.6.0:** default-flip sang wizard, classic vẫn vào được qua footer "↩ Switch to classic".
- **v2.0.0:** classic retire (redirect `action=update` → `action=wizard`).
- Cờ rollback (admin option) để quay lại classic nếu wizard có sự cố.

---

## 11. Map sang roadmap milestones (`DEV_MILESTONES_V2_WIZARD.md`)

| Spec V2 | Milestone tương ứng | Khác biệt cần điều chỉnh roadmap |
|---|---|---|
| Pricing Options wizard (5 bước) | M1 (skeleton+fields), M2 (template), M3 (preview), M4 (apply), M5 (flip) | rail-dọc thay vì progress-bar ngang; bỏ chip designer khỏi palette Fields |
| Product Studio (Views/Fields/Preview/Output/Theme) | M6, M7 (đang gọi "Designer Studio") | đổi tên → "Product Studio"; mở rộng phạm vi: thêm Views + nbpb fields + Output PDF, không chỉ theme/layout |
| Token/CSS hardening | M0 | giữ nguyên |
| Buyer-side | M8 | giữ nguyên |

---

## 12. Acceptance (chung cho mọi PR V2)

- [ ] Round-trip classic ↔ V2 cho data y hệt (tạo option 3 pricing field + 1 nbpb field).
- [ ] Pricing Options KHÔNG nạp fabric.js / spectrum; Network tab xác nhận.
- [ ] `?page=…-builder&action=update` (classic) vẫn chạy bình thường.
- [ ] Mobile 375px: không scroll ngang; touch target ≥44×44.
- [ ] `wp plugin check storelly-product-builder-for-woocommerce` → 0 ERROR.
- [ ] Version bump khớp 3 nơi (readme Stable tag, header Version, git tag) + changelog.

---

## 13. Open questions

1. **Product Studio truy cập theo option:** menu Studio mở danh sách option-có-studio, hay
   bắt buộc vào từ Pricing Options? (đề xuất: cả hai — menu có list + link sâu).
2. **"Product Builder enable" — đã xác minh:** KHÔNG có cờ boolean riêng trong
   `admin-options.js`. Trạng thái "có Studio / designable" hiện được **suy ra** từ sự hiện
   diện của `options.views[]` (+ nbpb field). Frontend quyết định designable bằng
   `config_data->views` (`includes/class-product-builder-frontend.php:191-269`). → V2 nên định
   nghĩa rõ tín hiệu: badge "Has Studio" = `options.views.length > 0 || có field.nbpb_type`.
   Cân nhắc thêm cờ tường minh `options.studio_enable` để tránh nhập nhằng.
3. **Theme/Layout (M6 cũ)** có gộp vào Product Studio ngay, hay tách màn riêng sau?
4. **Đặt nhãn menu:** "Product Studio" hay "Studio" / icon nào?
