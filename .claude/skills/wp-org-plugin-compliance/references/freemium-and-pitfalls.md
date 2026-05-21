# Reference: Freemium & Pitfalls

---

## A. Freemium / Upsell hợp lệ trên wordpress.org

ĐƯỢC phép:
- Upsell từ **settings screen** của plugin, hoặc **link trên plugin list page**.
- Link tới site dev (UTM được phép ở nơi cho phép link).
- Khoá tính năng premium, hiện CTA nâng cấp.

KHÔNG được:
- Forward-facing links (credit, "powered by", ads) **bật mặc định** — phải opt-in.
- Third-party ads (vì tracking).
- Affiliate link ẩn / link rút gọn — dùng link affiliate thật, công khai.
- Làm tê liệt site hoặc spam admin notice để ép mua.
- Quảng cáo tính năng KHÔNG tồn tại trong code.

---

## B. Cách làm paywall "giới hạn N sản phẩm" ĐÚNG

Ví dụ thực tế (Storelly readme nói "free tối đa 5 sản phẩm" nhưng code không enforce). Cách
implement hợp lệ:

```php
/**
 * Giới hạn số sản phẩm customizable ở bản free.
 */
define( 'SPBWC_FREE_PRODUCT_LIMIT', 5 );

function spbwc_can_create_more_options() {
    // Nếu đã có license premium hợp lệ → không giới hạn
    if ( spbwc_has_valid_premium() ) {
        return true;
    }
    $count = spbwc_count_builder_options(); // đã có sẵn spbwc_record_count() trong fields-list-table.php
    return $count < SPBWC_FREE_PRODUCT_LIMIT;
}

// Khi tạo option mới
if ( ! spbwc_can_create_more_options() ) {
    // Hiện CTA nâng cấp ngay tại màn settings (hợp lệ)
    add_settings_error(
        'spbwc_messages',
        'spbwc_limit',
        sprintf(
            /* translators: %d: số giới hạn */
            esc_html__( 'Free version allows up to %d customizable products. Upgrade for unlimited.', 'storelly-product-builder-for-woocommerce' ),
            SPBWC_FREE_PRODUCT_LIMIT
        ),
        'warning'
    );
    return;
}
```

Nguyên tắc:
- CTA nâng cấp chỉ ở settings/list page, không nhồi toàn admin.
- Phải khớp với điều readme quảng cáo (nếu readme nói 5 thì code phải là 5).
- Nếu KHÔNG muốn enforce → bỏ câu đó khỏi readme để tránh "quảng cáo sai".

---

## C. 12 lý do reject / bị gỡ phổ biến nhất

| # | Lỗi | Cách tránh |
|---|---|---|
| 1 | Output chưa escape | `esc_html/attr/url()` mọi nơi in dữ liệu |
| 2 | Input chưa sanitize | `sanitize_*()` + `wp_unslash()` |
| 3 | Thiếu nonce / capability | nonce + `current_user_can()` mọi action |
| 4 | Prefix generic / thiếu | prefix riêng ≥4 ký tự cho mọi global |
| 5 | Phone-home không opt-in | chỉ gửi data sau khi user bật rõ ràng |
| 6 | External service không khai báo | mục "External services" trong readme |
| 7 | Version lệch giữa 3 nơi | đồng bộ Stable tag / Version / tag SVN |
| 8 | Hardcode script/style | dùng `wp_enqueue_*()` |
| 9 | SQL không prepare | `$wpdb->prepare()` |
| 10 | i18n sai (biến trong `__()`, text domain lệch) | string literal + text domain = slug |
| 11 | Clone 100% plugin khác | fork phải có "significant improvements" |
| 12 | Trademark trong tên/slug | tránh tên thương hiệu; check Plugin Namer |

---

## D. Red flags đặc thù codebase FORK (rất quan trọng cho Storelly/Printcart)

Reviewer dễ nghi "không phải work của bạn" hoặc "clone" khi thấy:

| Red flag | Hành động |
|---|---|
| 2 prefix lẫn nhau (`spbwc_` ngoài + `pcpb_` trong) | thống nhất 1 prefix; cẩn thận meta key đã lưu DB |
| Comment lẫn ngôn ngữ lạ (vd tiếng Việt trong plugin tiếng Anh) | dịch/dọn sạch comment |
| Hardcode locale/timezone (`Asia/Ho_Chi_Minh`, `phuong_phap_1`) | thay bằng giá trị từ site/cấu hình |
| Library bundle không khai license | liệt kê license trong readme FAQ |
| Payload API trỏ tới backend ERP/kế toán không liên quan | gỡ field thừa, chỉ gửi đúng dữ liệu cần |

Nếu fork từ codebase nội bộ của chính công ty: vẫn phải dọn, vì reviewer không biết điều đó — họ
chỉ thấy code. "Forks are permitted, however they must show significant improvements or changes."

---

## E. Khi bị reviewer email

- Trả lời trong vài ngày. Im lặng lâu có thể bị đóng submission.
- Sửa đúng điểm họ nêu, bump version, mô tả rõ đã sửa gì.
- Lịch sự, ngắn gọn, dẫn chứng dòng code đã sửa.
- Nếu bị hỏi về similarity với plugin khác → giải thích differentiation (xem skill
  `wp-plugin-niche-fork` PHASE 6 cho template trả lời).
