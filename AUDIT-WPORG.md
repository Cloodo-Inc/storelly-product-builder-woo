# Audit Prompt — Chuẩn bị submit lên wordpress.org

> Cách dùng: trong Claude Code, mở repo này và gõ:
> "Đọc file AUDIT-WPORG.md và thực hiện đầy đủ từng phase. Sau mỗi phase, dừng lại
> báo cáo kết quả trước khi sang phase tiếp theo."
>
> Yêu cầu Claude Code đọc skill `wp-org-plugin-compliance` trong .claude/skills/ trước khi bắt đầu.

---

## PHASE A — Dọn danh tính (identity cleanup) — LÀM TRƯỚC TIÊN

Repo này có lệch danh tính. Kiểm tra và sửa:

1. [ ] Xác nhận tên plugin nhất quán ở MỌI nơi: Plugin Name header, file chính, text domain,
       readme, Contributors. Hiện tại là "Printcart Printing Options" / `printcart-printing-options`.
2. [ ] Đổi tên repo Git nếu vẫn là `storelly_product_builder_woo` → cho khớp slug plugin
       (ví dụ `printcart-printing-options`). Đây là plugin MỚI, không phải Storelly.
3. [ ] Kiểm tra KHÔNG còn sót prefix/text-domain/comment của Storelly (`spbwc_`, `storelly`)
       hoặc của codebase gốc (`pcpb_`, `nbpb_`) lẫn lộn. Phải dùng MỘT prefix nhất quán.
4. [ ] Kiểm tra slug `printcart-printing-options` CHƯA bị ai đăng ký trên wordpress.org
       (web_fetch wordpress.org/plugins/printcart-printing-options/ — nếu 404 thì còn trống).
5. [ ] Kiểm tra tên không đụng trademark; cân nhắc dùng Plugin Namer của Plugin Check.

## PHASE B — Sửa readme/README sai sự thật

1. [ ] README hiện ghi "Install from WordPress.org → Search for Printcart Printing Options"
       NHƯNG plugin chưa có trên wordpress.org. XÓA hoặc sửa phần này — quảng cáo sai = rủi ro reject.
2. [ ] Tạo readme.txt CHUẨN wordpress.org (khác README.md của GitHub). Theo template trong
       skill references/readme-and-headers.md. Bắt buộc: Stable tag, Tags ≤ 5, Tested up to,
       Requires PHP, License, short description < 150 ký tự.
3. [ ] Nếu plugin gọi service ngoài (Cloud2Print, API nào đó) → thêm mục "External services"
       khai báo đầy đủ: gửi gì, khi nào, tới đâu, link privacy + ToS.
4. [ ] Ba con số version khớp: readme Stable tag = header Version = Git tag dự định.

## PHASE C — Code compliance (theo references/code-compliance.md)

Rà toàn bộ thư mục includes/, templates/, views/, báo cáo kèm số dòng:
1. [ ] Mọi file PHP có `if ( ! defined( 'ABSPATH' ) ) exit;`
2. [ ] Mọi output động đã escape (`esc_html/attr/url`)
3. [ ] Mọi input đã sanitize + `wp_unslash`
4. [ ] Mọi form/AJAX có nonce + `current_user_can()`
5. [ ] SQL có biến dùng `$wpdb->prepare()`
6. [ ] i18n đúng: text domain = slug, không biến trong `__()`
7. [ ] Assets dùng `wp_enqueue_*()`, không hardcode
8. [ ] KHÔNG phone-home khi chưa opt-in
9. [ ] KHÔNG hardcode locale/timezone hay dữ liệu test sót lại
10. [ ] Library bundle (jQuery, Angular.js, eval-math...) khai license trong readme

## PHASE D — Plugin Check (cổng bắt buộc)

1. [ ] `wp plugin install plugin-check --activate`
2. [ ] `wp plugin check printcart-printing-options`
3. [ ] Fix HẾT ERROR (0 error mới được submit). Fix warning nếu có thể.
4. [ ] Báo cáo danh sách lỗi còn lại + đã fix gì.

## PHASE E — Chuẩn bị submit

1. [ ] Merge branch fork về `main` sau khi audit pass.
2. [ ] Đảm bảo plugin HOÀN CHỈNH (guideline #16) — không placeholder, không tính năng quảng cáo
       mà không có thật.
3. [ ] Chuẩn bị assets: banner, icon, screenshot (theo references/readme-and-headers.md).
4. [ ] Bật 2FA tài khoản wordpress.org sẽ submit.
5. [ ] Submit qua wordpress.org/plugins/developers/ HOẶC WordPress.org MCP server.

## PHASE F — Sau khi approved: thiết lập auto-deploy SVN

1. [ ] Thêm .github/workflows/deploy.yml (10up/action-wordpress-plugin-deploy).
2. [ ] Thêm .distignore.
3. [ ] Tạo Application Password trên wordpress.org, thêm SVN_USERNAME + SVN_PASSWORD vào GitHub Secrets.
4. [ ] Từ đó mỗi release: bump version 3 nơi → git tag → push → Actions tự đẩy SVN.

---

## Lưu ý quan trọng

- Đây là plugin MỚI, KHÔNG phải bản update của Storelly (slug khác hoàn toàn).
- Plugin submit qua MCP vẫn qua human review như thường. AI không làm nhẹ review.
- "Forks are permitted, however they must show significant improvements" — nếu reviewer hỏi về
  similarity với plugin khác, xem skill wp-plugin-niche-fork PHASE 6.
