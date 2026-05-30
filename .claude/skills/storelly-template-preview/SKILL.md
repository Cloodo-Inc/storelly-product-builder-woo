# storelly-template-preview

> Sửa / mở rộng / debug **Template Library Live Preview** (mục Preview trong admin Template Library của Storelly Product Builder). Preview render bằng chính storefront Cloodo qua iframe — KHÔNG có mockup song song. Dùng skill này bất cứ khi nào đụng tới: preview dialog, sample base price, viewport switcher, preview-against-product, bridge postMessage, hoặc storefront field row card design (dùng chung). Trigger phrases: "template preview", "Template Library Preview", "WYSIWYG preview", "preview iframe", "Cloodo field row", "preview-against-product", "preview against this product", "spbwc_tpl_preview", "template-preview-bridge", "smoke template preview".

---

## When to use

- Thêm điều khiển mới vào toolbar dialog (vd: chip preset giá, switcher dark/light, ô search field).
- Đổi cách iframe đo chiều cao / nhận thông điệp / verify origin.
- Sửa storefront field row CSS (file dùng chung cho preview + product page thật).
- Bị báo: preview "trống/không lên giá/blank", "Updating mãi", "tab Fields rỗng", "iframe scroll trong scroll".
- Đụng tới `class-template-preview-render.php`, `template-preview-bridge.js`, `template-library.js`, `views/templates/library.php`, `static/css/template-library.css`, `static/css/storefront-options.css`.
- Trước khi tag release ở plugin Storelly: chạy smoke test ở mục dưới.

## Spec full ở đâu

Mọi quyết định kiến trúc + endpoint contract + design decision: **`docs/SPEC_TEMPLATE_PREVIEW.md`**. Đọc spec đó trước nếu chưa quen feature.

## Invariants — KHÔNG được phá

1. **Một nguồn sự thật cho UI**: preview KHÔNG được dựng markup riêng. Phải render qua `templates/single-product/option-builder.php` + `storefront-options.css` + `option-builder.js`/`storefront-enhance.js`/`conditional-logic.js`.
2. **Endpoint phải gated bởi cap + nonce**: `current_user_can('spbwc_manage_product_builder')` + `wp_verify_nonce($n, 'spbwc_tpl_preview_iframe')`. Tuyệt đối không bypass cho "tiện".
3. **Localize `product_id=0`** trong `spbwc_option_builder_variable` ngay cả khi merchant preview-against-product. Chỉ dùng GIÁ + TÊN của product thật. Sai cái này → buyer core query product → side-effect.
4. **Bridge chỉ chạy trong iframe preview**: guard `body.spbwc-tpl-preview-doc` + `window.parent !== window`. Sai cái này → script chạy trên product page thật.
5. **Origin check postMessage**: parent phải so sánh `event.origin === L.previewOrigin` trước khi tin payload.
6. **Wrap form.cart + qty input** trong preview doc — nếu bỏ, total = 0 vì core đọc qty từ DOM.
7. **filemtime cache-bust JS+CSS admin** — đừng đổi sang version cố định cho dev, hỏng workflow live-edit.

## Cấu trúc 30 giây

```
Admin dialog ──(iframe src)──► template_redirect endpoint
              ◄──postMessage── template-preview-bridge.js
                                (height + total + product)
```

Trong endpoint: `resolve_preview_request($_GET)` (testable) → `render_document(options, base, pid, pname)` (xuất HTML + exit).

## Pre-commit checklist

```bash
# 1. PHP lint
docker exec wp_app php -l /var/www/html/wp-content/plugins/storelly-product-builder-woo/includes/templates/class-template-preview-render.php

# 2. JS syntax (Node)
node --check static/js/template-library.js
node --check static/js/template-preview-bridge.js

# 3. Smoke test (4 case + 1 skip)
docker exec wp_app wp eval-file \
  /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/smoke-template-preview-render.php \
  --allow-root
# Phải in: "All 4 passed (1 skipped)"

# 4. Regenerate .pot nếu thêm string mới
docker exec wp_app wp i18n make-pot \
  /var/www/html/wp-content/plugins/storelly-product-builder-woo \
  /var/www/html/wp-content/plugins/storelly-product-builder-woo/languages/spbwc-product-builder.pot \
  --slug=storelly-product-builder-for-woocommerce \
  --domain=storelly-product-builder-for-woocommerce \
  --allow-root
```

Live-test trong browser: dùng skill `wp-admin-login` để vào admin → Storelly Builder → Options Templates → click Preview của template bất kỳ.

## Khi cần thêm tham số query mới (vd `&with_image=1`)

1. PHP: `resolve_preview_request()` đọc + sanitize input (luôn dùng `sanitize_text_field(wp_unslash())` cho string, `absint`/`(float)` cho số).
2. PHP: forward giá trị tới `render_document()` và `enqueue_storefront_assets()` qua tham số method.
3. JS: thêm vào `loadPreviewFrame()` (append `&with_image=` vào url).
4. JS: handler UI (button/dropdown/...) gọi `loadPreviewFrame()`.
5. Smoke test: thêm 1 case cho param mới.
6. Spec: cập nhật `docs/SPEC_TEMPLATE_PREVIEW.md` section 4 (Endpoint contract).

## Khi cần forward dữ liệu MỚI từ iframe ra parent

Đừng tạo postMessage type mới linh tinh. Pattern:

1. Bridge: thêm hàm `postFoo()` postMessage `{ source: 'spbwc-tpl-preview', type: 'foo', value: ... }`.
2. Bridge: gọi `postFoo()` ở chỗ phù hợp (initial / mutation / one-shot).
3. Parent JS `onPreviewMessage()`: thêm nhánh `else if (ev.data.type === 'foo')`. **Vẫn check `ev.origin === expected`**.
4. Parent: cập nhật state + UI.

## Lỗi thường gặp + cách sửa

| Triệu chứng | Nguyên nhân | Sửa |
|---|---|---|
| Hero "YOUR TOTAL" trống | qty=0 (DOM thiếu `input.qty`) hoặc base=0 | Check form.cart wrapper trong `render_document()`; test với base > 0 |
| Subtitle không hiện `est. $X` | `event.origin` mismatch hoặc bridge không load | Console iframe: tìm "spbwc_tpl_preview_context"; check `L.previewOrigin` |
| Iframe scroll trong scroll | Bridge không post height (template rất tall) | Kiểm tra `ResizeObserver` support / poll interval còn chạy không |
| Browser cache JS cũ | Quên filemtime version | Đảm bảo enqueue dùng `$js_ver = filemtime(...)` |
| Tab Fields rỗng khi mở | AJAX `spbwc_template_preview` fail | Console: kiểm 403/500; verify nonce action `spbwc_template_library` |
| Preview "trống" sau khi click Apply popular combo | Iframe re-render Angular → bridge mất MutationObserver attach | Đã có 4s poll dự phòng — nếu vẫn hỏng, re-attach observer khi `[data-spbwc-cloodo-total]` thay đổi parent |
| PCP báo TextDomainMismatch trong file của tôi | Đổi text domain không khớp `storelly-product-builder-for-woocommerce` | Luôn dùng đúng domain — đừng đổi sang folder slug |

## Files bạn sẽ đụng nhiều nhất

```
includes/templates/class-template-preview-render.php   ← controller PHP
static/js/template-preview-bridge.js                    ← bridge iframe→parent
static/js/template-library.js                           ← admin glue
views/templates/library.php                             ← dialog markup
static/css/template-library.css                         ← admin styles (toolbar/Fields/About)
static/css/storefront-options.css                       ← CHUNG storefront — đụng cẩn thận
```

## Smoke output chuẩn

```
PASS  1) anonymous → 403 [got status=403]
PASS  2) admin + bad nonce → 403 [got status=403]
PASS  3) good nonce + fake slug → 404 [got status=404]
PASS  4a) good nonce + real slug → 200 + options.fields populated [status=200, fields=4]
SKIP  4b) HTTP body contains .nbo-wrapper.nbo-style-cloodo [skipped inside wp-cli; …]

All 4 passed (1 skipped)
```

Bất kỳ FAIL nào trong 1-4a là regression — **không commit / không tag**.

## Memory liên quan (ngữ cảnh project)

- `[[template_preview_fidelity_gap]]` — preview giờ là WYSIWYG (ĐÃ FIX), trước đây dựng UI Classic riêng.
- `[[core_hardcodes_base_price]]` — buyer core bỏ qua giá localized; phải seed `scope.price`.
- `[[quantity_breaks_no_engine]]` — đừng quảng cáo "Save %"; đã gỡ khỏi mọi nơi.

## Commit history

```
2409c2c  feat(template-library): WYSIWYG live preview via shared storefront renderer
d3054fb  feat(template-library): polish preview UX
a5f65d2  style(preview): redesign field row, Fields tab, About tab
ec3a69c  chore(release): 1.3.0 — preview-on-product, smoke tests, version bump
```
