# SPEC — Canvas "Add Text" & "Add Images" tabs (Hybrid free-form)

> Status: DRAFT (chờ duyệt hướng) · Ngày: 2026-06-03 · Owner: David
> Liên quan: V3 customizer (`views/product-builder/wrapper.php`), `static/js/app-product-builder.js`,
> `includes/class-product-builder-frontend.php`, admin field editor (`static/js/admin-options.js`,
> `includes/class-admin-options.php`).

## 1. Vấn đề & hiện trạng (đã kiểm chứng)

Plugin có 2 khái niệm "text/image" tách biệt:

| | Field thường (`nbd_type`) | Designer field (`nbpb_type`) |
|---|---|---|
| Loại | `text` (input), `upload` (file) | `nbpb_text`, `nbpb_image` |
| Render | form option cạnh sản phẩm | lên canvas |
| Trong canvas V3 | bị ẩn (`nbo-hidden`) | hiện trong accordion "Customize parts" |

Canvas V3 hiện tại:
- Tab nav trái chỉ có 4 tab: **Customize / Details / Shipping / Help**.
- `nbpb_text`/`nbpb_image` CÓ hoạt động nhưng **chỉ là placeholder admin đặt sẵn** — mỗi field = 1 dòng
  accordion; buyer điền nội dung / upload vào đúng vị trí admin định (`app-product-builder.js:144-248`,
  `addText()` :656, `addImage()` :1433 đều thao tác trên `currentComponent`).
- **KHÔNG có** chức năng buyer tự thêm text/ảnh tự do (Canva/Printful-style). Comment ASCII đầu
  `wrapper.php` (dòng 14-19) đã vẽ sẵn icon `T` / `📷` như "future expansion" nhưng chưa dựng.

→ Khoảng trống: chưa có **tab Text** và **tab Images** cho buyer tự thêm layer.

## 2. Quyết định đã chốt (với David)

1. **Mô hình: Hybrid** — giữ nguyên placeholder admin (`nbpb_*`) + thêm khả năng buyer **tự thêm
   text/ảnh tự do** khi admin bật.
2. **Kiểm soát admin: bật/tắt + ràng buộc** — toggle per-product (hoặc global fallback) cho từng tool,
   kèm giới hạn (số layer tối đa, fonts/màu cho phép, định dạng & dung lượng ảnh, vùng in/print area).
3. **Pricing: phí cố định mỗi layer** — mỗi text layer +X, mỗi ảnh +Y (admin cấu hình). Khớp engine
   giá hiện có (cộng vào `computeBuildTotal`, không cần price-break engine).

## 3. Kiến trúc dữ liệu

### 3.1 Admin config (mới) — cấp option-set / product builder settings

Thêm khối `free_design_tools` vào settings của builder (không đụng cấu trúc field hiện có):

```jsonc
free_design_tools: {
  text: {
    enabled: "n",                 // "y" | "n"
    max_layers: 3,                // 0 = không giới hạn
    price_per_layer: 0,           // phí cố định / layer (currency của site)
    allow_all_font: "y",          // tái dùng convention nbpb_text_configs
    custom_fonts: [], google_fonts: [],
    allow_change_color: "y",
    allow_all_color: "y", colors: [],
    default_text: ""
  },
  image: {
    enabled: "n",
    max_layers: 2,
    price_per_layer: 0,
    allow_type: "png,jpg,jpeg,svg",   // tái dùng upload_option convention
    min_size: 0, max_size: <site default>
  },
  print_area: {                  // vùng buyer được đặt layer; per-view
    mode: "full",                // "full" = cả design-zone | "custom" = bbox riêng
    views: [ { left, top, width, height } ]   // chỉ dùng khi mode = "custom"
  }
}
```

Lưu/đọc: mở rộng `build_config_*` trong `class-admin-options.php` + nơi serialize settings của option-set.
Print-area `custom` có thể để **M2** (M1 default = full design-zone).

### 3.2 Buyer layer model (frontend, runtime)

Layer buyer tự thêm KHÔNG phải `component` admin → quản lý ở mảng riêng để feed giá + persist + cart:

```jsonc
resource.userLayers: [
  { uid, kind: "text",  views: { 0: {...fabricProps}, 1: {...} }, content, fontId, color, price },
  { uid, kind: "image", views: { 0: {...fabricProps} }, src, price }
]
```

- `uid`: id duy nhất (vd `ul_` + counter; KHÔNG dùng `Date.now()`/`Math.random()` trong workflow scripts,
  nhưng frontend runtime thì dùng được).
- Mỗi fabric object mang `userLayerUid` + `isUserLayer:1` để map ngược khi select/save/load.
- `saveDesign()` đã serialize toàn canvas qua `toJSON()` → layer tự lưu vào design.json. Ta CHỈ cần thêm
  `resource.userLayers` (metadata + giá) vào payload save để backend tính giá & khôi phục.

## 4. Admin UX

Vị trí: trong màn hình cấu hình product builder (cùng chỗ cấu hình option-set / designer views).

Thêm panel **"Customer design tools"** với 2 card:

```
┌ Customer design tools ───────────────────────────────┐
│ ▢ Allow customers to add their own TEXT              │
│     Max text layers: [ 3 ]   Price per layer: [ $2 ] │
│     Fonts: (•) All  ( ) Selected…   Colour: (•) All… │
│ ▢ Allow customers to upload their own IMAGES         │
│     Max image layers:[ 2 ]   Price per layer: [ $5 ] │
│     Allowed: [png,jpg,jpeg,svg]  Max size:[ 10 ] MB  │
│ Print area: (•) Full design zone  ( ) Custom (M2)    │
└──────────────────────────────────────────────────────┘
```

- Toggle off → tab tương ứng KHÔNG hiện ở frontend.
- Validate: max_layers ≥ 0; price ≥ 0; allow_type lọc theo whitelist an toàn (đồng bộ
  `class-product-builder-frontend.php` upload guard).

## 5. Frontend UX/UI — 2 tab mới trong canvas

### 5.1 Tab nav (trái, 64px)

Thêm 2 tab sau "Customize", chỉ render khi admin bật:

```
🖌️ Customize   ← parts + placeholder admin (giữ nguyên)
T  Text        ← MỚI (nếu free_design_tools.text.enabled)
📷 Images      ← MỚI (nếu free_design_tools.image.enabled)
📄 Details
🚚 Shipping
❓ Help
```

(Khớp đúng icon T/📷 đã vẽ sẵn trong comment ASCII của `wrapper.php`.)

### 5.2 Tab "Text" — anatomy

```
┌ Add text ───────────────────────────┐
│ [  + Add a text layer  ]  (primary)  │  ← tạo layer mới, auto-select trên canvas
│ ── Your text layers (2/3) ──────────│  ← đếm theo max_layers
│ ┌─────────────────────────────────┐ │
│ │ ☰ "Happy Birthday"      +$2  🗑 │ │  ← row/layer: click = select; drag ☰ = z-order
│ │   Content: [Happy Birthday    ] │ │  (mở inline khi layer được chọn)
│ │   Font:    [ Pacifico      ▾ ] │ │
│ │   Colour:  [● ● ● ● + picker ] │ │
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │ ☰ "Your name here"      +$2  🗑 │ │
│ └─────────────────────────────────┘ │
│ ⚠ Max 3 text layers reached         │  ← khi đạt giới hạn, nút Add disable + hint
└──────────────────────────────────────┘
```

### 5.3 Tab "Images" — anatomy

```
┌ Add images ─────────────────────────┐
│ ┌─ drop zone ───────────────────┐   │
│ │  ⬆  Click or drop image        │   │  ← upload → tạo layer mới
│ │  png,jpg,svg · max 10MB        │   │
│ └────────────────────────────────┘   │
│ ── Your images (1/2) ───────────────│
│ ┌─────────────────────────────────┐ │
│ │ [thumb] logo.png        +$5  🗑 │ │  ← click = select; drag = z-order
│ │   [ Replace ]  [ Center ]       │ │
│ └─────────────────────────────────┘ │
│ Recent uploads: [▢][▢][▢]           │  ← gallery localStorage (tái dùng resource.uploaded)
└──────────────────────────────────────┘
```

### 5.4 User flows

**F1 — Thêm text layer**
1. Buyer mở tab Text → "Add a text layer".
2. Tạo fabric Textbox ở giữa print-area của view hiện tại (`isUserLayer:1`, default content/font/color
   theo admin config), push vào `resource.userLayers`, auto-select trên canvas + mở inline editor.
3. Buyer sửa content/font/màu → cập nhật realtime (giống `updateText()` nhưng theo `userLayerUid`).
4. Summary cộng `price_per_layer`; progress KHÔNG bắt buộc (layer tự do là optional).

**F2 — Upload image layer**
1. Tab Images → drop/click → validate (type/size theo admin) → POST `spbwc_customer_upload`
   (tái dùng endpoint hiện có) → nhận URL.
2. `fabric.Image.fromURL` → auto-fit print-area → push `resource.userLayers` (kind image) → select.
3. Summary cộng `price_per_layer`.

**F3 — Chọn / sửa / xoá layer**
- Click layer trên canvas → tab tương ứng active + scroll/expand đúng row (map qua `userLayerUid`).
- 🗑 xoá: remove fabric object mọi view + xoá khỏi `userLayers` + refreshSummary.
- Drag handle ☰ → đổi z-order (bringForward/sendBackwards) đồng bộ giữa các view.

**F4 — Print-area clamp**
- Layer bị giới hạn di chuyển trong print-area (M1: full design-zone; M2: custom bbox).
- Cảnh báo nhẹ khi layer tràn mép (toast/outline đỏ), không chặn cứng.

**F5 — Giới hạn số layer**
- Đạt `max_layers` → disable nút Add + hint. `0` = không giới hạn.

### 5.5 Trạng thái & edge cases
- Admin tắt cả 2 tool → không render tab nào (hành vi y như hiện tại).
- Empty state mỗi tab (chưa có layer): minh hoạ + CTA.
- Mobile/bottom-sheet: tab Text/Images theo cùng pattern responsive hiện có.
- i18n: mọi chuỗi qua `__()/esc_html_e()` text-domain `storelly-product-builder-for-woocommerce`.

## 6. Pricing

- Engine: mở rộng `computeBuildTotal()` (`app-product-builder.js`) cộng
  `Σ userLayers[i].price` vào surcharge, trước volume-discount.
- `refreshSummary()`: thêm dòng "Custom text/images (+$Z)" trong breakdown khi có user layers.
- `$watch` signature (dòng 2322-2333): bổ sung chữ ký của `userLayers` (số lượng + giá) để summary +
  persist cập nhật khi thêm/xoá layer.
- **Cart/Order (impact)**: giá line-item server-side phải cộng phí layer. `saveDesign` gửi kèm
  `userLayers` (số lượng + đơn giá) → backend (`class-product-builder-frontend.php` +
  nơi tính giá add-to-cart) cộng `count * price_per_layer` vào giá cộng thêm và lưu vào order meta để
  hiển thị/đối soát. **KHÔNG tin giá từ client** — server đọc lại admin config (`price_per_layer`) và
  nhân theo số layer hợp lệ (đã clamp max_layers).

## 7. Impact report (flow lớn — theo CLAUDE.md)

- **Cart/Checkout**: design.json đã tự chứa layer (toJSON) nên render preview/PDF không đổi cấu trúc;
  chỉ THÊM nhánh giá. Rủi ro: nếu không validate server-side, buyer có thể giả giá → bắt buộc tính lại
  server.
- **Save/Load & Reorder**: cần khôi phục `userLayers` khi load lại (cart edit / reorder). design.json
  khôi phục được hình ảnh layer, nhưng metadata giá/uid cần lưu riêng (payload save) để dựng lại panel +
  tính giá. Bổ sung vào pre_builder load.
- **Đồng bộ order (Cloud2Print/PDF)**: layer là fabric object chuẩn → xuất PDF/SVG đi qua pipeline cũ,
  không phá. Cần xác nhận DPI/print-area khi clamp (M2).
- **HPOS**: chỉ là order meta, không đụng storage order.
- **Compliance wp.org**: upload tái dùng guard hiện có; thêm tool KHÔNG thêm external service.

## 8. File touch-points (dự kiến)

| Vùng | File | Việc |
|---|---|---|
| Admin config | `includes/class-admin-options.php`, `static/js/admin-options.js`, `static/css/admin-options.css` | thêm `free_design_tools` + UI panel |
| Frontend markup | `views/product-builder/wrapper.php` | 2 tab nav + 2 tabpanel |
| Frontend logic | `static/js/app-product-builder.js` | `userLayers` model, addUserText/addUserImage, select/delete/z-order, mở rộng `computeBuildTotal`/`refreshSummary`/`$watch`/`saveDesign`/persist/load |
| Frontend style | `static/css/app-product-builder.css` | style 2 tab + layer rows |
| Backend | `includes/class-product-builder-frontend.php` (+ nơi tính giá add-to-cart, order meta) | nhận `userLayers`, tính lại giá server, lưu order meta |
| i18n | `languages/*.pot` | regen sau khi xong (skill `wp-plugin-i18n`) |

## 9. Milestones

- **M1** — Admin toggle + ràng buộc cơ bản (no custom print-area) · tab Text/Images · add/edit/delete/
  z-order · pricing fixed per-layer · summary + add-to-cart giá đúng (server-validated) · persist
  localStorage.
- **M2** — Custom print-area per-view + clamp + cảnh báo tràn mép · khôi phục userLayers khi cart-edit/
  reorder.
- **M3** — Đánh bóng: mobile bottom-sheet, empty states, a11y, toasts; POT regen; Plugin Check 0 error.

## 9b. M1 verification (2026-06-04)

Browser test trên BAG (`bag-customizable`, option-set 8): tab Text/Images hiện đúng khi admin bật;
thêm/sửa text layer OK; giá live $97.20 → $99.20 (+$2); design lưu kèm `user-layers.json {text:1}`;
**giá server-side `fee=2` xác nhận đúng** (docker logs). Setting test giữ bật trên option 8 + 17.

**Bug đã phát hiện & vá khi test:** trên host có `get_filesystem_method()` != `direct` (vd `ftpsockets`),
`WP_Filesystem()` fail → `SPBWC_Storelly_IO::spbwc_get_local_file_contents()` trả `false` ở frontend →
phí layer (và các read frontend khác như config.json) im lặng hỏng. Đã thêm fallback đọc/ghi trực tiếp
trong `includes/class-io.php`. Ngoài ra `spbwc_get_free_design_tools()` normalize `'on'/1/true → 'y'`
(checkbox form POST lưu `"on"`), admin toggle dùng hidden `ng-value` round-trip `'y'/'n'`.

**UX polish:** badge số layer trên tab, auto-focus ô content khi add text, swatch màu preset cho text.

## 10. Quyết định (đã chốt)

1. Admin config: **per option-set + global fallback** — mỗi product builder cấu hình riêng; nếu trống
   dùng default toàn site ở Settings.
2. Phí layer: **mỗi layer** (nhiều text → nhiều lần phí).
3. View scope: **chỉ view đang xem** — layer chỉ thuộc view buyer đang mở khi thêm.
4. Giới hạn: **chỉ riêng từng loại** (max text, max image), KHÔNG có trần tổng.
```
