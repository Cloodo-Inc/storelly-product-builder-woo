# SPEC — Template Library Live Preview (v1.3.0)

> Mục **Template Library → Preview** trong admin render **giao diện storefront thật** (Cloodo) cho 1 template bundled, qua iframe dùng chung renderer frontend. Sửa style storefront là preview đổi theo — KHÔNG có mockup song song.

Ship: commit `2409c2c` → `ec3a69c`. Push lên `origin/main` 2026-05-30.

---

## 1. Mục tiêu thiết kế

| Yêu cầu | Cách đạt |
|---|---|
| Preview phải **giống 1:1** UI khách thật sự thấy | Render bằng chính `templates/single-product/option-builder.php` + chính `static/css/storefront-options.css` + chính `static/js/option-builder.js` / `storefront-enhance.js` / `conditional-logic.js`. Một nguồn sự thật. |
| Sửa storefront → preview đổi tự động | Iframe nạp đúng các handle script/style đó qua đường enqueue thường. Không re-implement markup. |
| Live total chạy theo lựa chọn buyer thật | AngularJS bootstrap bình thường; `storefront-enhance.js` seed `scope.price` từ `cfg.price` localized → total = base + surcharge. |
| Merchant gõ giá hoặc chọn product → preview cập nhật | Toolbar dialog: ô "Sample base price" (debounce 400ms + localStorage) và picker Select2 "Preview against product" → reload iframe với `&base=N` hoặc `&product_id=N`. |
| Endpoint không lộ ra ngoài | `template_redirect` gated bởi cap `spbwc_manage_product_builder` + nonce `spbwc_tpl_preview_iframe`. |

## 2. Kiến trúc

```
Admin dialog (Template Library page)
 └─ #spbwc-tl-preview-frame  ── iframe ───┐
                                          ▼
                              GET / ?spbwc_tpl_preview=1
                                  &_spbwcnonce=<nonce>
                                  &slug=<template-slug>
                                  &base=<float>           (optional)
                                  &product_id=<int>       (optional)

template_redirect ─► SPBWC_Template_Preview_Render::maybe_render_preview()
  │
  ├─ resolve_preview_request($_GET)   ← unit-testable
  │    1. current_user_can(cap)        else  status=403
  │    2. wp_verify_nonce              else  status=403
  │    3. slug + base + product_id parse
  │    4. catalog->get_template_data   else  status=404
  │    5. build_runtime_options()      → flatten descriptor→runtime
  │    return ['status'=>200, 'options'=>..., 'base_price'=>..., 'product_id'=>..., 'product_name'=>...]
  │
  ├─ render_document(options, base, product_id, product_name)
  │    a. enqueue_storefront_assets()   — registers exact storefront handles
  │    b. localize spbwc_option_builder_variable  (giống storefront, product_id=0)
  │    c. enqueue spbwc-tpl-preview-bridge + localize spbwc_tpl_preview_context (product name)
  │    d. ob_start(); spbwc_get_template('single-product/option-builder.php', $args)
  │    e. echo <!doctype>...<wp_head/>...<form.cart>+qty input...$markup...<wp_footer/>
  │
  └─ exit;
```

Trong iframe, **`template-preview-bridge.js`** (chạy CHỈ trong `body.spbwc-tpl-preview-doc`) postMessage tới parent 3 loại event:

| `type` | `value`              | Khi nào                                | Parent dùng để... |
|--------|----------------------|----------------------------------------|-------------------|
| height | int (px)             | DOMContentLoaded + ResizeObserver       | Auto-grow iframe (cap 82vh) |
| total  | string ("$50.00")    | MutationObserver `[data-spbwc-cloodo-total]` | Cập nhật subtitle "est. $X" |
| product| {product_id,name}    | One-shot khi load                       | Subtitle "with <name>" |

Origin check: chỉ chấp nhận `event.origin === L.previewOrigin` (= home_url scheme://host[:port]).

## 3. Files (đầy đủ scope feature)

| File | Vai trò |
|---|---|
| `includes/templates/class-template-preview-render.php` | Controller — endpoint + flatten + render. Phương thức `resolve_preview_request()` là điểm test. |
| `static/js/template-preview-bridge.js` | Bridge iframe→parent (height + total + product context). |
| `views/templates/library.php` | Dialog markup: viewport switcher, sample price + currency symbol, product picker, iframe, error/Updating/Loading overlays. |
| `static/js/template-library.js` | Admin glue: debounce base price, localStorage persist, postMessage listener, product picker (Select2), iframe loader. |
| `includes/templates/class-template-library-admin.php` | Enqueue + localize (`previewUrl`, `previewOrigin`, `currencySymbol`, i18n). JS/CSS dùng filemtime cache-bust. |
| `static/css/template-library.css` | Toolbar + iframe wrap + Fields tab (card list) + About tab (metadata grid). |
| `static/css/storefront-options.css` | Field-row card design (CHUNG cho storefront và preview). |
| `tools/smoke-template-preview-render.php` | Smoke test 4-case + 1 skip. |

## 4. Endpoint contract

```
URL    : home_url('/?spbwc_tpl_preview=1&_spbwcnonce=…&slug=…&base=…&product_id=…')
Method : GET
Auth   : cookie session WP + cap spbwc_manage_product_builder
Nonce  : action 'spbwc_tpl_preview_iframe'

Params:
  slug         (required*)  template slug trong storage/print-templates/ (*trừ khi có `draft`)
  draft        (optional)   JSON option ĐANG SỬA (chưa lưu), POST từ edit-option preview.
                            Có `draft` → bỏ qua catalog/slug, render thẳng draft qua
                            build_runtime_options() (cùng đường flatten như bundled template).
                            Gửi qua POST (form target=iframe) vì payload lớn; nonce vẫn ở GET.
  base         (optional)   sample base price, float ≥ 0
  product_id   (optional)   nếu set + sản phẩm tồn tại → dùng wc_get_product()->get_price() làm base

Responses (HTML body):
  200  doctype + wp_head + cart form + option-builder.php markup + wp_footer
  403  thiếu cap / nonce sai      (wp_die)
  404  slug không có trong catalog (wp_die)
  500  catalog class không load    (wp_die)

Headers:
  Content-Type: text/html; charset=…
  X-Robots-Tag: noindex, nofollow
  Cache-Control / Pragma / Expires: nocache_headers()
```

## 5. Quyết định thiết kế đáng nhớ

- **Localize `product_id=0`** trong `spbwc_option_builder_variable` ngay cả khi `?product_id=42` — vì buyer core đọc product_id và có thể query → side-effect không mong muốn. Chỉ dùng GIÁ thật của product (đã gập vào `base_price`) + TÊN (forward qua bridge).
- **Wrap `<form class="cart">` + qty input value=1** trong preview doc — vì core đọc qty từ `input.qty` của DOM; thiếu → total = price × 0 = "".
- **filemtime cho cả CSS và JS** trong admin enqueue — production build pin SPBWC_PB_VERSION, dev không cần bump tay.
- **Skill khả truy ngược: `body.spbwc-tpl-preview-doc`** — guard cho bridge biết "tôi đang trong preview" (không attach trên product page thật).
- **`#spbwc-tl-preview-live { display:none }` default + `.spbwc-tl-tabpanel--active#spbwc-tl-preview-live { display:flex }`** — vì `#id` specificity thắng `.class`, nếu để default flex thì tab Fields/About active mà live panel vẫn hiện.

## 6. Smoke tests

```bash
docker exec wp_app wp eval-file \
  /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/smoke-template-preview-render.php \
  --allow-root
```

Pass criteria:
- 1) anonymous → 403
- 2) admin + bad nonce → 403
- 3) good nonce + fake slug → 404
- 4a) good nonce + real slug ("business-cards") → 200 + `options.fields` populated
- 4b) HTTP body chứa `.nbo-wrapper.nbo-style-cloodo` → SKIP trong wp-cli (self-deadlock); script in lệnh curl host để chạy thủ công

Exit code 0 = pass.

## 7. Hạn chế đã biết / cải tiến tương lai

- **Preview chưa preview ảnh thật của product** khi chọn "Preview against product". Mới reflect: giá + tên. Cần truyền `product_image_url` qua bridge.
- **Iframe height cap 82vh** — template rất dài (Wide Format 26 field) vẫn scroll trong iframe. Có thể dynamic cap theo dialog height.
- **Smoke test 4b chỉ chạy được từ host** vì wp-cli + PHP-FPM cùng pool. Nếu chuyển sang PHPUnit thật + WordPress Test Framework có thể test luôn 4b.
- **Bridge MutationObserver** poll thêm 4s đề phòng Angular re-render `[data-spbwc-cloodo-total]` node — nếu Angular bắt đầu replace deep node, có thể cần re-attach observer.
- **i18n_usage compliance**: 1 string mới (`'with'` cho subtitle) có thể bị PCP gắn flag "ambiguous" — có translator comment rồi.

## 8. Commit history liên quan

```
2409c2c  feat(template-library): WYSIWYG live preview via shared storefront renderer
d3054fb  feat(template-library): polish preview UX — debounce, auto-grow, live total
a5f65d2  style(preview): redesign field row, Fields tab, About tab
ec3a69c  chore(release): 1.3.0 — preview-on-product, smoke tests, version bump
```

## 9. Memory pointers (project context)

- `[[template_preview_fidelity_gap]]` — bối cảnh "trước" và lý do làm WYSIWYG.
- `[[core_hardcodes_base_price]]` — vì sao phải seed `scope.price` qua storefront-enhance.
- `[[quantity_breaks_no_engine]]` — vì sao "Save %" bị gỡ khỏi preview cũ.

---

## 10. Milestone W2-PREVIEW — Chuẩn hóa Option Template Preview (Wave 2, item 6)

**Status:** M1 SHIPPED (2026-06-09) — edit-option hội tụ về shared renderer (hướng **Hybrid**).
Part of `SPEC_ADMIN_UX_POLISH_W2.md`.

### Vấn đề (đã chẩn đoán)
Mục **Live preview** trong edit-option (`views/options/edit-option.php`, `<aside class="v2-preview">`)
là **mockup AngularJS tự dựng song song** (`v2-prv-*` + `preview_total()` trong `admin-options.js`) —
KHÔNG dùng storefront renderer. Drift thật:
- engine `preview_total()` chỉ cộng `attr.price[0]` của field multi-choice → text/number/upload = $0,
  bỏ qua price_breaks / option price[1..3] / depend_quantity / giá-theo-ký-tự;
- bỏ qua `display_mode` (sections/matrix/stepper) → luôn list dọc;
- không chạy conditional logic; designer component không render.

### Hướng đã chốt: **Hybrid** (user, 2026-06-09)
Giữ mockup inline cho phản hồi tức thì khi build, NHƯNG bản **authoritative** đi qua shared renderer.

### M1 — đã ship (commit kèm milestone này)
1. **Một đường render authoritative** qua `SPBWC_Template_Preview_Render`: endpoint nhận thêm tham số
   `draft` (POST) = JSON option đang sửa (chưa lưu) → bỏ qua catalog, flatten qua `build_runtime_options()`
   (cùng descriptor-shape như bundled template) → render bằng CHÍNH `single-product/option-builder.php` +
   `storefront-options.css` + `option-builder.js`/`conditional-logic.js`/`storefront-enhance.js`. Single
   source of truth, zero drift (display_mode/conditional/pricing đều 1:1 với frontend).
2. **Nút "Preview as storefront"** trong header preview mở **modal iframe** (`#spbwc-sf-preview`), POST
   `angular.toJson($scope.options)` + base price vào iframe; tái dùng `template-preview-bridge.js`
   (height + live total qua postMessage, origin-checked).
3. **Reframe mockup inline** thành "Quick sketch" + note dẫn người dùng sang storefront preview cho bản
   chính xác. Mockup giữ token sẵn có.
4. **Token-first + RTL-safe**: modal dùng `--shadow-xl`, `--nbd-radius-lg`, `--nbd-st-*`, `--text-*`;
   layout flex đối xứng (không cần rule RTL riêng).
5. **Smoke**: thêm case 5 (`draft` → 200 + descriptor flattened + catalog bypassed) vào
   `tools/smoke-template-preview-render.php` (5/5 pass).

### M2 — đã ship (cùng đợt, 2026-06-10)
1. **Auto-refresh live khi sửa field**: modal mở → `$scope.$watch(angular.toJson(options))` debounce 600ms
   re-render iframe (không chỉ base price). Refresh giữ frame cũ + dim (`is-updating`) thay vì veil trắng,
   đúng pattern "Updating…" của Library. Watch deregister khi đóng modal.
2. **Skeleton + trạng thái thống nhất**: lần mở đầu hiện **skeleton shimmer** (title/options grid/CTA);
   refresh dùng pill "Updating…" góc trên; error state riêng. Khung dùng CHUNG `render_document()`
   (stage max-width 760px) nên Library ↔ modal nhất quán; framing token hóa (`--shadow-*`, `--nbd-radius-*`),
   pill dùng logical properties (RTL-safe).
3. **Gỡ legacy `views/options/preview.php`**: là widget "Create Pre builder" ẩn trùng (CTA "Pre-builder"
   thật đã ở savebar). Xoá file + `include_once`. CSS `.frontend-prview` thành dead (để dọn sau).

### Còn lại (M3+)
- POT regen: string "Updating…" mới + gỡ "Create Pre builder" (chạy ở release gate).
- Dọn CSS `.frontend-prview` chết trong admin-options(.css/-rtl/-v2/-v2-rtl).
- (tùy chọn) viewport switcher desktop/tablet/mobile trong modal như Library.

### Files
M1: `includes/templates/class-template-preview-render.php` (draft mode),
`includes/class-admin-options.php` (`spbwc_preview_iframe_data()` + localize),
`views/options/edit-option.php` (button + modal), `static/js/admin-options.js`,
`static/css/admin-options-v2.css`, `tools/smoke-template-preview-render.php` (case 5).
M2: `static/js/admin-options.js` (watch/refresh/is-updating), `views/options/edit-option.php`
(skeleton + updating pill, gỡ preview.php include), `static/css/admin-options-v2.css`
(skeleton/shimmer/updating), xoá `views/options/preview.php`.
