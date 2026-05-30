# SPEC — Classic Pricing Option Editor Flow (FROZEN REFERENCE)

> **Status:** FROZEN. Tài liệu này mô tả luồng **edit pricing option hiện tại** đúng như
> code đang chạy, để sau này không phải đọc lại toàn bộ source. **KHÔNG sửa luồng/đổi
> code này** khi triển khai flow mới (V2). Flow mới triển khai song song — xem
> `SPEC_FLOW_V2.md`.
>
> **Plugin:** Storelly Product Builder for WooCommerce · **Version tại thời điểm chụp:** 1.2.6
> **Prefix:** `spbwc_` / `SPBWC_` · **Text domain:** `storelly-product-builder-for-woocommerce`
> **Reference tổng plugin:** `docs/SPEC.md`

---

## 1. Tên gọi & phạm vi

Trong code hiện tại có sự lệch tên cần biết trước:

| Thứ người dùng thấy | Tên kỹ thuật | Ghi chú |
|---|---|---|
| Menu **"Pricing Options"** | slug `SPBWC_PB_BUILDER_SLUG` = `storelly-product-builder-for-woocommerce-builder` | menu thật, callback `spbwc_product_builder_options()` |
| Tab đầu tiên **"Builder"** | `data-v2-tab="builder"` | nằm *bên trong* trang edit |
| Tab **"Designer"** | `data-v2-tab="designer"` | chỉ quản lý *canvas views*, KHÔNG phải canvas fabric.js thật |

⚠️ "Builder" vừa là tên tab vừa gần nghĩa với "Product Builder" frontend → đây là một
nguồn gây khó hiểu mà flow V2 sẽ tách ra.

---

## 2. Routing & entry point

- Menu đăng ký tại `includes/class-admin-options.php:292-299` (submenu "Pricing Options").
- Callback `spbwc_product_builder_options()` (`class-admin-options.php` ~ dòng 513) phân nhánh theo
  query `action`:
  - không `action` / list → bảng danh sách (`views/options/options-list-table.php`).
  - `action=create&id=0` → tạo mới.
  - `action=update&id={n}` → sửa option đã có.
  - `action=unpublish` → gỡ publish (nonce `spbwc_unpublish_option_action`).
- Màn hình tạo/sửa render `views/options/edit-option.php` (1669 dòng).

---

## 3. Kiến trúc UI — AngularJS SPA 1 trang, 5 tab

`edit-option.php` là **một AngularJS app** (`ng-app="optionApp"`, `ng-controller="optionCtrl"`,
`edit-option.php:45-46`). Toàn bộ 5 tab render trên CÙNG một trang; chuyển tab bằng JS
(`data-v2-tab` / `data-v2-panel`), không lazy.

| Tab | `data-v2-panel` | Dòng | Nội dung |
|---|---|---|---|
| **Builder** | `builder` | 144-791 | metadata + display mode + quantity/bulk pricing + field palette + field list + live preview |
| **Apply to products** | `apply` | 793-883 | gán option vào product/category |
| **Designer** | `designer` | 884-1008 | canvas views (Front/Back/Inside) + nút thêm nbpb field |
| **Output (PDF)** | `output` | 1013-1050 | cấu hình xuất PDF |
| **Import / Export** | `iox` | 1051+ | import/export option |

Tab strip: `edit-option.php:115-139`. Mỗi tab có badge đếm (`{{ options.fields.length }}`, …).

---

## 4. Tab "Builder" chi tiết

### 4.1 Option metadata (`edit-option.php:147-224`)
- `options.title` (bắt buộc), created/modified/ID hiển thị read-only.
- **Frontend display mode** — radio cards (`edit-option.php:193-221`):
  - `sections` — danh sách dọc, hợp ≤6 field.
  - `matrix` — lưới 2D (paper × sides).
  - `stepper` — wizard từng-bước-một cho buyer (hợp >5 field).
  - ⚠️ `stepper` ở đây là **layout của BUYER**, không phải wizard admin.

### 4.2 Quantity & bulk pricing — cấp option (`edit-option.php:226-349`)
- Toggle `options.quantity_enable` (`y`/`n`).
- `quantity_type`: `r` tier cards / `d` dropdown / `s` stepper (min/max/step).
- `quantity_discount_type`: `p` percent / `f` fixed.
- `quantity_breaks[]`: `{ val, dis, default }` — bậc số lượng + chiết khấu.
- Mặc định 1 bậc qua `set_default_quantity_break($index)`.

### 4.3 Field palette (`edit-option.php:351-450`)
Hai nhóm chip:

**Pricing fields** (gọi `add_field_preset(...)`):
| Chip | Mã | data_type | Mô tả |
|---|---|---|---|
| Multi-choice | `m` | `m` | pick-one + swatch ảnh/màu |
| Number | `n` | `i`/input_type `n` | stepper số (min/max/step) |
| Text input | `t` | `i`/input_type `t` | 1 dòng, có thể tính giá/ký tự |
| Textarea | `a` | `i`/input_type `a` | nhiều dòng |
| File upload | `u` | `i`/input_type `u` | upload artwork |

**Designer fields** (gọi `add_field('nbpb_com'…)`):
| Chip | nbpb_type | Mô tả |
|---|---|---|
| Designer Component | `nbpb_com` | nền/khung/shape holder cho canvas |
| Designer Text | `nbpb_text` | text block sửa được trên canvas |
| Designer Image | `nbpb_image` | placeholder ảnh trên canvas |

→ **Hai nhóm này hiện trộn chung một palette.** Đây là điểm V2 tách: pricing fields ở
menu Pricing Options, designer fields về Product Studio.

### 4.4 Onboarding presets (`edit-option.php:484-510`)
Hiện khi option trống: 4 card quick-start (Paper/Material, Size, Number, Upload).

### 4.5 Builder + Live preview 2 cột (`edit-option.php:471-791`)
- Trái: danh sách field (`include field.php`).
- Phải: live preview sticky, switch Mobile/Desktop, tính `preview_total()` realtime.

---

## 5. Model field & cấu trúc dữ liệu

Field list render ở `views/options/field.php` (`ng-repeat="(fieldIndex, field) in options.fields"`).
Mỗi field card khi mở có **3 tab** (`field.php:76-80`, `templates/field-body.php`):

1. **General** (`field-body.php:5-24`): include 17 sub-template trong
   `views/options/templates/field-body/`: `title`, `description`, `data_type`, `input_type`,
   `input_option`, `text_option`, `placeholder`, `upload_option`, `enabled`, `published`,
   `required`, `price_type`, `depend_qty`, `depend_quantity`, `price`, `price_breaks`,
   `attributes`, `conditional_depend`.
2. **Appearance** (`field-body.php:25-82`): lặp `field.appearance` → render dropdown/segmented/text.
3. **Product builder** (`field-body.php:83-86`): **chỉ hiện khi `field.nbpb_type`** — include
   `field.nbd_template` (template riêng cho nbpb_com/text/image).

### 5.1 Shape của một field
```
options.fields[n] = {
  id,
  nbpb_type,          // '' cho pricing field; 'nbpb_com|nbpb_text|nbpb_image' cho designer field
  nbd_template,       // ng-template id cho tab "Product builder"
  isExpand,
  general: {
    title.value, description.value, data_type.value, input_type.value,
    input_option.value{min,max,step,default}, text_option.value, placeholder.value,
    upload_option.value, enabled.value('y'|'n'), published.value, required.value,
    price_type.value('fixed'|'percent'|'per_char'…), price.value,
    price_breaks[], attributes.options[]{name,color,image_url,price…},
    conditional_depend…
  },
  appearance: { display_type, … }   // key→{type,title,options[],value}
}
```

### 5.2 Pricing per-field
- `field-body/price_type.php` — segmented Fixed / Percent / Per char.
- `field-body/price.php` — input số (prefix `$`).
- `field-body/price_breaks.php` — bậc giá theo SL (per-field, khác quantity_breaks cấp option).
- ⚠️ Ghi nhớ (MEMORY): `quantity_breaks` cấp option **chưa có engine áp dụng giá** ở buyer —
  đừng quảng cáo "save %". Pricing fixed có case `'f'` thiếu break (bug lịch sử).

---

## 6. Tab "Designer" (canvas views) — `edit-option.php:884-1008`

- KHÔNG phải canvas fabric.js. Chỉ quản lý mảng `options.views[]`:
  `{ name, base, base_url, base_width, base_height }`.
- Empty state → `addView()`; mỗi view 1 card (ảnh base qua media library, W×H).
- Có lối tắt thêm nbpb field (giống palette) rồi nhắc qua tab Builder để đặt lên canvas.
- Hidden inputs đồng bộ view ra form: `edit-option.php:516-521`, `957`.
- Canvas thật (fabric.js 539KB) nằm ở **frontend** `views/product-builder/` (buyer-facing),
  enqueue trong `class-script-hook.php` — KHÔNG nạp ở màn admin edit này.

---

## 7. Save flow

- Form POST: `edit-option.php:109-112` (`name="nboForm"`), nonce `spbwc_save_option_action`,
  hidden `options[version]`, `option_id`.
- Field name pattern: `options[fields][{index}][general][...]`,
  `options[fields][{index}][appearance][{key}]`, `options[views][{i}][...]`,
  `options[quantity_breaks][{i}][val|dis|default]`.
- Submit → `spbwc_save_option()` (`class-admin-options.php`) → serialize vào cột `fields`
  của bảng `wp_storelly_product_builder_options` (xem `docs/SPEC.md` §3.2).
- Load: `spbwc_get_option($id)` → `spbwc_build_options()` → `wp_localize_script(... 'STORELLY_OPTIONS')`
  → Angular đọc lúc bootstrap.
- Cảnh báo `max_input_vars` (`edit-option.php:452-469`): vì POST cả cây JSON, option nhiều
  field dễ vượt `php max_input_vars`.

---

## 8. Assets nạp cho màn hình này

Enqueue tại `includes/class-admin-options.php:405-484`, gate bằng `$spbwc_is_builder_hook`
(`:467-472`).

**JS (~400KB):**
- `spbwc-ag` = `libs/builderproductag.min.js` (AngularJS 1.6.9, ~166KB) — `:406`.
- `spbwc-options-script` = `js/admin-options.js` (~74KB) + deps: jquery, wpdialogs,
  jquery-ui-resizable/draggable/droppable/sortable/datepicker/autocomplete, wp-color-picker,
  spbwc-ag, wc-enhanced-select, spbwc-snap-svg, spbwc-tiptip — `:475`.
- `wp_enqueue_media()` gọi **vô điều kiện** ngay đầu `edit-option.php:26`.

**CSS (~325KB):**
- `spbwc-options-style` = `admin-options.css` (~79KB) — `:422`.
- `spbwc-options-v2-style` = `admin-options-v2.css` (~160KB) — `:425`.
- `spbwc-admin-ui` (~46KB) + `spbwc-tokens` (~9KB) — nạp trên MỌI trang Storelly (`:437`).

Tất cả nạp một lần khi mở trang, dù người dùng chỉ xem 1 tab.

---

## 9. Vì sao cần flow mới (đầu vào cho V2)

1. 1 trang gánh 5 tab → DOM + watcher Angular khởi tạo hết, nạp ~400KB JS + ~325KB CSS.
2. Pricing fields và Designer fields **trộn chung** một editor → đa số merchant chỉ cần
   pricing nhưng vẫn thấy designer.
3. Tên gọi chồng chéo ("Builder" tab vs "Product Builder", "Designer" tab vs canvas).
4. `wp_enqueue_media` + WC enhanced select + jQuery UI nạp ngay cả khi không dùng.

---

## 10. Bất biến phải giữ khi làm V2

- Bảng `wp_storelly_product_builder_options` và schema `options.*` **không đổi** — classic và
  V2 cùng đọc/ghi một nguồn (round-trip phải giống hệt).
- Endpoint save `spbwc_save_option`, nonce, field-name pattern giữ nguyên.
- AngularJS controller `optionCtrl` (`admin-options.js`) tái dùng, không viết lại.
- Route classic `?page=…-builder&action=update&id=…` phải tiếp tục hoạt động.
- Mọi PHP `if ( ! defined( 'ABSPATH' ) ) exit;`, sanitize/escape, nonce + capability, text
  domain đúng; `wp plugin check` = 0 ERROR.
