# Plan: wp.org compliance audit report — marketplace port

## Context

Storelly Product Builder for WooCommerce đã merge module designer marketplace
(lift-and-shift từ pc-designer) vào nhánh main qua PR #6. Plugin hiện đã
approved trên wordpress.org (`storelly-product-builder-for-woocommerce`).
Trước khi đẩy update tiếp theo lên wp.org, cần xác minh xem code mới có
vi phạm guideline wp.org / Plugin Check (PCP) hay không.

User đã chọn: **chỉ viết báo cáo, không fix code lần này**. Hai audit
song song (code-level + plugin-level) đã chạy xong và cho ra list cụ thể
file:line. Việc còn lại là consolidate findings vào một báo cáo persistent
trong repo để (a) làm bằng chứng audit, (b) làm checklist cho các PR cleanup
tiếp theo, (c) ghi rõ quyết định kiến trúc "bỏ React admin, viết lại bằng PHP".

## Deliverable

Tạo MỘT file markdown duy nhất trong repo storelly:

`/home/user/storelly-product-builder-woo/docs/wp-org-compliance-audit.md`

Commit lên nhánh phụ `claude/wp-org-compliance-audit` (KHÔNG đụng nhánh
chính), push, mở draft PR.

## Cấu trúc báo cáo

Báo cáo viết bằng tiếng Việt + bảng (để consistent với phong cách user),
mỗi finding kèm file:line, severity, đề xuất fix. Cấu trúc:

```
# wp.org Compliance Audit — Designer Marketplace Port

## TL;DR
- Severity matrix
- Top 5 blockers
- Verdict: chưa thể push wp.org

## Phương pháp
- 2 explore agent (code-level + plugin-level)
- Phạm vi: files lifted + new bridge + main plugin bootstrap
- Pre-existing storelly code: in-scope chỉ khi block update tiếp theo

## Findings — Marketplace port (do PR #6 gây ra)

### BLOCKER
1. AJAX `nbdl_update_designer_status` thiếu nonce + cap check
   - includes/launcher/class.launcher.php:565
   - Fix: thêm wp_verify_nonce() + current_user_can('manage_options')
2. views/launcher/dist/app.js (764 KB minified) không có build source

### HIGH
3. ~45 chỗ unescaped output trong templates/launcher/store/*
   (bảng chi tiết file:line + biến + escape function đề xuất)
   - popup.php, designs.php, settings.php, design-table.php,
     dashboard.php, withdraw.php, notification.php, upload-form.php,
     featured-designers-shortcode.php

### MEDIUM
4. NBDESIGNER_PLUGIN_URL dùng trong src attribute không qua esc_url
5. wp_kses_post vs esc_html trong notification.php

## Findings — Pre-existing (block update kế tiếp)
1. class-productbuilder-api.php: "Asia/Ho_Chi_Minh" + "phuong_phap_1"
   (vi phạm CLAUDE.md rule 10)
2. unpkg.com (Vue CDN) + app.storelly.com/product-data chưa declare
   trong readme.txt External services
3. Missing git tag 1.2.6
4. spbwc-product-builder.pot cần regenerate cho strings marketplace

## Đã pass (clear)
- Prefix mixing: intentional, accept
- Text domain: 188/188 calls đúng
- Sanitize input: tất cả $_POST/$_GET đều wrap
- SQL prepare: clean (CRUD qua model classes)
- ABSPATH guard: 21/21 files
- External services trong launcher: none
- File ops: path-constrained
- dbDelta: dùng đúng

## Decision đã chốt
- React admin SPA (views/launcher/dist/app.js) sẽ bị BỎ.
  Admin marketplace sẽ viết lại bằng PHP + WP_List_Table + jQuery.
  → Effort ước lượng: 3-4 ngày công
  → Lý do: tránh "minified third-party code" red flag, đồng nhất
    phong cách UI với storelly hiện tại, không phải maintain bundle build

## Roadmap fix (đề xuất chia 3 PR)

PR A — Security blockers + escape pass (1-1.5 ngày)
- Fix nonce/cap cho nbdl_update_designer_status
- Bulk escape pass 45+ chỗ trong templates/launcher/store/*
- Verify với PCP

PR B — React admin → PHP rewrite (3-4 ngày)
- Xóa views/launcher/dist/
- Tạo views/marketplace/ + admin renderer dùng WP_List_Table
- Re-implement: designer list, designer edit, design list, withdraw list,
  report dashboard (Chart.js từ CDN hoặc bundle nhẹ)
- AJAX endpoints giữ nguyên action names nbdl_* nhưng đổi sang gọi qua
  storelly/v1 REST nếu thuận tiện

PR C — Pre-existing cleanup + release (0.5 ngày)
- Fix Asia/Ho_Chi_Minh + phuong_phap_1 → wp_timezone_string()
- Declare unpkg.com + demo-data trong readme External services
- Regenerate .pot
- Bump version 1.2.7
- Tag + push → trigger auto-deploy workflow

## Verification (sau mỗi PR)

Mục tiêu: `wp plugin check storelly-product-builder-for-woocommerce` → 0 ERROR.

Cách chạy PCP trong môi trường này:
1. wp-cli không có sẵn trong remote container.
   Hai option:
   (a) Setup WP-CLI + WP test install trong container (cần script bootstrap)
   (b) User chạy ở local sau khi pull nhánh

Khuyến nghị: viết SessionStart hook (.claude/) cài WP test + wp-cli +
plugin-check addon. Sau đó mỗi PR có thể chạy auto. Skill
`session-start-hook` đã có sẵn cho việc này.

Manual fallback verification (không cần wp-cli):
- php -l toàn bộ file PHP (đã clean với commit hiện tại)
- grep cho remaining unescaped patterns
- diff với baseline báo cáo

## Files tạo

- `docs/wp-org-compliance-audit.md` (file duy nhất, ~250-300 lines)

## Files KHÔNG đụng

- Toàn bộ source code (per quyết định "chỉ báo cáo")
- readme.txt
- main plugin file
- launcher/ và marketplace/

## Verification của báo cáo

1. File tồn tại tại đúng path, markdown render đẹp trên GitHub
2. Mỗi finding có file:line cụ thể, không hand-wave
3. Severity matrix khớp với 2 audit gốc
4. Roadmap 3-PR khả thi (effort estimate có lý)
5. Mention quyết định "bỏ React" để team có context khi review

## Out of scope (cho plan này)

- Code fix (đã chốt không fix)
- Chạy PCP thực tế (depends on environment setup)
- React → PHP rewrite implementation
- readme.txt update
- Version bump / git tag
