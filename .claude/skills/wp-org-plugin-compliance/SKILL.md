---
name: wp-org-plugin-compliance
description: >
  Bộ pipeline tổng thể để phát triển và xuất bản một WordPress plugin TUÂN THỦ ĐẦY ĐỦ guideline
  wordpress.org — từ chuẩn code (security, escaping, sanitization, prefix, i18n), viết readme.txt
  đúng chuẩn, chạy Plugin Check (PCP) để pass auto-scan, tới submit và deploy qua SVN. Bao gồm cả
  quy trình dùng WordPress.org MCP server chính thức (2026) và GitHub Actions auto-deploy.
  LUÔN dùng skill này khi user đề cập: "tuân thủ wordpress.org", "wordpress.org compliance",
  "submit plugin lên wp.org", "plugin bị reject", "pass plugin review", "Plugin Check", "PCP",
  "readme.txt chuẩn", "deploy plugin lên wordpress.org", "SVN wordpress", "plugin guidelines",
  "phát triển plugin chuẩn wordpress", "chuẩn bị submit plugin", "plugin check fail", "escaping
  sanitization wordpress", "nonce verification", "phoning home", "freemium plugin wp.org",
  "cập nhật plugin wordpress.org", hoặc bất kỳ yêu cầu nào liên quan đến việc đưa plugin lên
  hoặc giữ plugin tuân thủ trên kho wordpress.org. Áp dụng cho cả plugin mới và plugin đang
  maintain (như Storelly, Printcart free).
---

# WP.org Plugin Compliance

Pipeline đầy đủ để một WordPress plugin pass review, pass Plugin Check, và ship an toàn lên wordpress.org.

## Nguyên tắc nền tảng (đọc trước)

WordPress.org guideline THAY ĐỔI theo thời gian. Skill này KHÔNG chép toàn bộ guideline vào file
tĩnh (sẽ lỗi thời). Thay vào đó, skill dạy quy trình + chỉ tới **nguồn sống** sau đây, LUÔN ưu tiên
kiểm tra nguồn sống khi quyết định cuối cùng:

| Nguồn sống | URL | Dùng để |
|---|---|---|
| Detailed Plugin Guidelines | developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ | 18 quy tắc bắt buộc |
| Plugin Check (PCP) | wordpress.org/plugins/plugin-check/ | auto-scan trước submit — BẮT BUỘC |
| Common Issues | make.wordpress.org/plugins/handbook/performing-reviews/review-checklist/ | lỗi reviewer sửa ở 95% plugin |
| WordPress.org MCP server | developer.wordpress.org/plugins/wordpress-org/using-the-mcp-server/ | validate readme, submit, check status bằng AI |
| readme.txt spec | wordpress.org/plugins/readme.txt | field bắt buộc/tuỳ chọn |

**Quy tắc số 1:** Trước khi khẳng định "phải làm X để pass review", nếu không chắc → web_fetch nguồn
sống ở trên. Đừng dựa vào trí nhớ về guideline.

---

## Tổng quan quy trình

```
PHASE 0: Setup môi trường        → 2FA, Plugin Check, WP-CLI, SVN
PHASE 1: Code Compliance         → security, escaping, prefix, i18n, no-phone-home
PHASE 2: readme.txt + Headers    → đúng field, version khớp 3 nơi
PHASE 3: Plugin Check (PCP)      → chạy local, fix hết blocker
PHASE 4: Submit (plugin mới)     → web form hoặc MCP server + human review
PHASE 5: Deploy/Update qua SVN   → trunk + tags, hoặc GitHub Actions auto
PHASE 6: Freemium & Upsell       → khoá tính năng đúng luật, không vi phạm
PHASE 7: Maintain                → trả lời reviewer, xử lý bị flag/đóng
```

Đọc file reference tương ứng khi vào từng phase:
- `references/code-compliance.md` — chi tiết security/escaping/prefix/i18n + ví dụ đúng-sai
- `references/readme-and-headers.md` — template readme.txt + plugin header đầy đủ
- `references/plugin-check-and-submit.md` — chạy PCP, dùng MCP server, submit, SVN deploy
- `references/freemium-and-pitfalls.md` — freemium hợp lệ + 12 lý do reject phổ biến

---

## PHASE 0 — Setup môi trường (làm 1 lần)

Trước khi viết/sửa bất cứ gì:

```bash
# 1. Bật 2FA trên tài khoản wordpress.org sẽ submit (BẮT BUỘC từ cuối 2024)
#    https://login.wordpress.org/security

# 2. Cài Plugin Check để tự kiểm tra (bắt buộc về thực tế — submission bị auto-scan bằng PCP)
wp plugin install plugin-check --activate

# 3. WP-CLI để chạy check bằng dòng lệnh
wp plugin check your-plugin-slug

# 4. (Khi deploy) cài svn — wordpress.org chỉ nhận qua SVN, KHÔNG phải Git
```

Checklist Phase 0:
- [ ] 2FA bật trên mọi owner/committer
- [ ] Plugin Check cài và chạy được
- [ ] WP-CLI hoạt động
- [ ] Có local/staging WordPress để test activate/deactivate

---

## PHASE 1 — Code Compliance (lõi của việc pass review)

Đây là nơi 95% plugin bị reviewer sửa. Bốn nhóm bắt buộc — chi tiết + ví dụ ở
`references/code-compliance.md`:

1. **No direct file access** — mọi file PHP mở đầu bằng `if ( ! defined( 'ABSPATH' ) ) exit;`
2. **Sanitize input / Escape output** — `sanitize_*()` khi nhận, `esc_*()` khi in ra
3. **Nonce + capability** — mọi form/AJAX/action phải verify nonce VÀ check `current_user_can()`
4. **Unique prefix** — mọi function/class/option/hook/global có prefix riêng (≥4 ký tự, không generic)
5. **i18n đúng** — text domain khớp slug, không dùng biến trong `__()`
6. **Không phone-home khi chưa opt-in** — không gửi dữ liệu site về server khi user chưa đồng ý
7. **Enqueue đúng** — `wp_enqueue_script/style()`, không hardcode `<script>`/`<style>`
8. **SQL an toàn** — `$wpdb->prepare()` cho mọi query có biến

> CẢNH BÁO đặc thù cho codebase fork (vd Storelly fork từ Printcart): nếu thấy 2 lớp prefix lẫn
> nhau (`spbwc_` ngoài, `pcpb_` trong), comment ngôn ngữ khác, hoặc hardcode locale/timezone
> (`Asia/Ho_Chi_Minh`, `phuong_phap_1`) → đây là RED FLAG. Reviewer có thể nghi "không phải work
> của bạn" hoặc "clone". Phải dọn sạch về MỘT prefix nhất quán trước khi submit.

---

## PHASE 2 — readme.txt + Plugin Headers

Ba con số version phải KHỚP nhau ở 3 nơi (lỗi phổ biến nhất khiến PCP block):
1. `Stable tag:` trong readme.txt
2. `Version:` trong file plugin chính
3. Tên thư mục `tags/x.y.z/` trong SVN (và Git tag nếu auto-deploy)

Chi tiết template + field bắt buộc → `references/readme-and-headers.md`.

---

## PHASE 3 — Plugin Check (PCP) — cổng bắt buộc

```bash
wp plugin check your-plugin-slug
```

Nguyên tắc: **0 ERROR mới được submit.** WARNING nên fix hết nếu có thể. PCP auto-scan lúc submit;
còn ERROR là bị chặn trước cả khi human nhìn thấy. Cách đọc kết quả + fix → `references/plugin-check-and-submit.md`.

---

## PHASE 4 — Submit (chỉ cho plugin MỚI)

Hai cách:
- **Web form**: wordpress.org/plugins/developers/ → upload ZIP → vào hàng đợi human review (vài ngày–vài tuần).
- **WordPress.org MCP server** (2026, khuyên dùng nếu làm với AI): validate readme, submit, check
  status, đọc guideline ngay trong Claude. Cách kết nối → `references/plugin-check-and-submit.md`.

Lưu ý: plugin submit qua MCP vẫn qua ĐÚNG quy trình review như web form. AI không làm nhẹ review.

---

## PHASE 5 — Deploy / Update qua SVN

Plugin ĐÃ approved chỉ update qua SVN (không qua form nữa). Hai cách:
- **Thủ công**: `svn checkout` → copy code vào `trunk/` → `svn copy trunk tags/x.y.z` → `svn commit`.
- **Tự động (khuyên dùng)**: GitHub Actions + `10up/action-wordpress-plugin-deploy`, trigger khi push Git tag.

Workflow YAML đầy đủ + `.distignore` → `references/plugin-check-and-submit.md`.

---

## PHASE 6 — Freemium & Upsell hợp lệ

Bán/nâng cấp ĐƯỢC phép nhưng có luật rõ:
- Upsell chỉ từ settings screen hoặc link trên plugin list page.
- Link "powered by"/ads phải OPT-IN, KHÔNG bật mặc định.
- Không nhúng third-party ads (tracking).
- Tính năng quảng cáo trong readme PHẢI tồn tại thật trong code (quảng cáo tính năng không có =
  rủi ro bị gỡ).

> CẢNH BÁO Storelly: readme nói "giới hạn 5 sản phẩm ở free" nhưng code không enforce. Phải hoặc
> implement thật, hoặc bỏ câu đó. Chi tiết cách làm paywall đúng luật → `references/freemium-and-pitfalls.md`.

---

## PHASE 7 — Maintain

- Trả lời email reviewer trong vòng vài ngày (chậm có thể bị đóng).
- Khi bị flag security: fix nhanh, bump version, commit, báo lại team.
- Forks ĐƯỢC phép nhưng phải "significant improvements" — không được là bản copy 100%.

---

## Khi nào dùng skill nào (phối hợp với hệ skill nội bộ)

| Tình huống | Skill |
|---|---|
| Fork plugin sang niche mới | `wp-plugin-niche-fork` |
| Generate code plugin từ design | `netbase-wp-builder` |
| Tìm/validate niche | `netbase-wp-niche-finder` |
| Audit bảo mật sâu | `security-audit` |
| Review code chung | `code-review` |
| **Đưa plugin lên / giữ tuân thủ wp.org** | **skill này** |

Skill này là lớp "compliance + ship", chạy SAU khi code đã có và TRƯỚC/TRONG khi submit-maintain.
