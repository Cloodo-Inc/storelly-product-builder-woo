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
  slug         (required)   template slug trong storage/print-templates/
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

**Status:** DRAFT (2026-06-09) · part of `SPEC_ADMIN_UX_POLISH_W2.md`

### Vấn đề
Preview render chưa nhất quán giữa các điểm gọi: Template Library (`views/templates/library.php`),
edit-option (`views/options/preview.php`), và buyer storefront. Khung/aspect/skeleton/fallback khác nhau.

### Yêu cầu
1. **Một đường render duy nhất** qua `SPBWC_Template_Preview_Render` (iframe storefront WYSIWYG — đã có từ
   các commit §8). Mọi điểm gọi đi qua API/helper chung, không tự dựng markup preview riêng.
2. **Chuẩn khung**: aspect ratio + max-width token hóa; cùng border/radius/background; loading **skeleton**
   thống nhất; fallback khi thiếu base image (placeholder + message, không vỡ layout).
3. **Debounce + auto-grow + live total** (đã làm ở library) áp dụng nhất quán nơi có preview tương tác.
4. RTL + token-first; không inline style mới.

### Acceptance
- Preview ở Library / edit-option / (nếu có) product giống nhau về khung + skeleton + fallback.
- Sửa style storefront → mọi preview đổi theo (vẫn WYSIWYG, không drift).

### Files
`includes/templates/class-template-preview-render.php`, `views/templates/library.php`,
`views/options/preview.php`, CSS preview (token).
