# CLAUDE.md — Storelly Product Builder for WooCommerce

Đây là repo plugin WordPress **xuất bản công khai trên wordpress.org**. Mọi thay đổi phải giữ
plugin TUÂN THỦ guideline wordpress.org và pass Plugin Check.

## Quy tắc bắt buộc

1. Trước khi đụng code/readme/submit, tham khảo skill `wp-org-plugin-compliance` trong `.claude/skills/`.
2. Mọi file PHP mở đầu bằng `if ( ! defined( 'ABSPATH' ) ) exit;`.
3. Sanitize mọi input (`sanitize_*` + `wp_unslash`), escape mọi output (`esc_*`).
4. Mọi form/AJAX/action: verify nonce + `current_user_can()`.
5. Dùng MỘT prefix nhất quán. KHÔNG để lẫn `spbwc_` và `pcpb_`. Báo cáo nếu phát hiện prefix lẫn.
6. KHÔNG phone-home khi user chưa opt-in (`enable_api_sync`). Mọi external service phải khai báo
   trong readme.txt mục "External services".
7. Text domain LUÔN là `storelly-product-builder-for-woocommerce`. Không dùng biến trong `__()`.
8. SQL có biến phải `$wpdb->prepare()`. Assets dùng `wp_enqueue_*()`.
9. Ba con số version (readme `Stable tag`, header `Version`, Git tag) phải KHỚP mỗi release.
10. KHÔNG hardcode locale/timezone (`Asia/Ho_Chi_Minh`, `phuong_phap_1`). Lấy từ cấu hình site.

## Trước mỗi lần submit/release

- [ ] Chạy `wp plugin check storelly-product-builder-for-woocommerce` → 0 ERROR.
- [ ] readme: tags ≤ 5, version khớp, external services khai báo đủ.
- [ ] Tính năng quảng cáo trong readme PHẢI tồn tại thật trong code (vd giới hạn "5 sản phẩm").
- [ ] Không có comment ngôn ngữ lạ / dữ liệu test còn sót.

## Trước khi sửa flow lớn (cart, checkout, designer, export PDF)

Cung cấp impact report ngắn trước khi sửa: ảnh hưởng gì, có phá flow WooCommerce/đồng bộ order không.

## Quy trình release

```
Sửa code + bump version (readme Stable tag + header Version) + changelog
→ wp plugin check (0 error)
→ git commit + git tag x.y.z + push --tags
→ GitHub Actions tự deploy lên SVN (trunk + tags/x.y.z)
```

Không sửa workflow deploy hoặc release script mà không giải thích tác động.
