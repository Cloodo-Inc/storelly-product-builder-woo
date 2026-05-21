# wp-org-plugin-compliance

Bộ skill tổng thể để phát triển & xuất bản WordPress plugin tuân thủ wordpress.org.

## Vì sao tạo mới thay vì dùng repo có sẵn

Các repo skill WordPress công khai (elvismdev, jorgerosal, Automattic) tập trung vào **review/audit
code** hoặc **dựng theme/site**, KHÔNG tập trung vào **compliance + ship lên wordpress.org**. Bộ này
lấp đúng khoảng trống đó và khớp convention hệ skill nội bộ của bạn (frontmatter + PHASE + trigger
phrases tiếng Việt).

Điểm thiết kế quan trọng: skill KHÔNG chép guideline tĩnh (sẽ lỗi thời). Thay vào đó trỏ tới
**nguồn sống** (Plugin Check + WordPress.org MCP server 2026) và dạy quy trình.

## Cấu trúc

```
wp-org-plugin-compliance/
  SKILL.md                              ← skill chính (7 phase)
  references/
    code-compliance.md                  ← security/escaping/prefix/i18n + ví dụ đúng-sai
    readme-and-headers.md               ← template readme.txt + plugin header
    plugin-check-and-submit.md          ← PCP, MCP server, submit, SVN, GitHub Actions
    freemium-and-pitfalls.md            ← freemium hợp lệ + 12 lý do reject + red flags fork
```

## Cài đặt

Giải nén và đặt thư mục vào nơi chứa user skill của bạn (cùng cấp với `wp-plugin-niche-fork`,
`netbase-wp-builder`...). Thường là `/mnt/skills/user/` hoặc thư mục skill project tương ứng.

## Khi nào tự kích hoạt

Skill trigger khi nhắc tới: tuân thủ wordpress.org, submit plugin, plugin bị reject, Plugin Check,
readme.txt chuẩn, deploy SVN, freemium plugin, cập nhật plugin wp.org... (xem `description` đầy đủ
trong SKILL.md).

## Phối hợp với skill khác

- Fork sang niche mới → `wp-plugin-niche-fork`
- Generate code từ design → `netbase-wp-builder`
- Audit bảo mật sâu → `security-audit`
- **Ship & giữ tuân thủ wp.org → skill này**
