# Reference: Plugin Check, Submit & SVN Deploy

---

## A. Plugin Check (PCP) — cổng bắt buộc

```bash
# Cài
wp plugin install plugin-check --activate

# Chạy
wp plugin check your-plugin-slug

# Chỉ chạy nhóm check nhất định
wp plugin check your-plugin-slug --categories=security,plugin_repo
```

Hoặc UI: Tools > Plugin Check trong wp-admin.

Cách đọc kết quả:
- **ERROR** → BẮT BUỘC fix. Còn 1 ERROR là bị chặn submit.
- **WARNING** → nên fix hết. Nhiều warning làm reviewer mất thiện cảm.
- Mỗi dòng có file + số dòng + mã lỗi → đi thẳng tới chỗ đó sửa.

Nhóm lỗi hay gặp: `late_escaping` (output chưa escape), `direct_db_query`, `nonce_verification`,
`i18n_*` (text domain sai/biến trong `__()`), `WordPress.Security.*`.

Plugin Check còn có **Plugin Namer** (cần WP 7.0+): kiểm tra tên plugin có trùng/đụng trademark
không trước khi submit. Hữu ích để tránh reject vì tên.

---

## B. WordPress.org MCP server (2026)

Server MCP chính thức của wordpress.org cho phép AI (Claude) làm trực tiếp:

Tools:
- **Validate Readme** — kiểm tra readme.txt trước submit
- **Get Plugin Status** — xem status + feedback reviewer nếu đang review
- **Submit Plugin** — submit plugin mới, hoặc cập nhật submission đang review

Resources (AI đọc để tư vấn đúng): Plugin Guidelines, Developer FAQ, Plugin Readmes spec,
Plugin Headers, Plugin Check Guide, Reserved Slugs.

Prompts: "Prepare Plugin for Submission" — checklist từng bước.

Lưu ý quan trọng:
- Plugin submit qua MCP vẫn qua ĐÚNG quy trình human review như web form. AI KHÔNG làm nhẹ review.
- Bạn (dev) chịu trách nhiệm review mọi thứ AI sinh ra trước khi submit.
- Cách kết nối cập nhật tại: developer.wordpress.org/plugins/wordpress-org/using-the-mcp-server/
  (web_fetch trang này để lấy URL endpoint MCP mới nhất khi cần).

---

## C. Submit plugin MỚI

Quy trình:
```
1. Bật 2FA tài khoản submit
2. Chạy Plugin Check → 0 error
3. Validate readme (PCP hoặc MCP)
4. Submit ZIP qua wordpress.org/plugins/developers/  HOẶC qua MCP Submit Plugin
5. Vào hàng đợi human review (vài ngày → vài tuần)
6. Reviewer email yêu cầu sửa (nếu có) → sửa → reply
7. Approved → nhận quyền SVN
8. svn commit lần đầu → plugin xuất hiện công khai
```

Plugin phải HOÀN CHỈNH lúc submit (guideline #16). Không submit bản rỗng/placeholder.

---

## D. SVN Deploy — thủ công

```bash
# Lần đầu: checkout
svn checkout https://plugins.svn.wordpress.org/your-plugin-slug/ svn-dir
cd svn-dir

# Copy code mới vào trunk (loại file dev)
rsync -av --delete \
  --exclude='.git' --exclude='.github' --exclude='node_modules' \
  --exclude='tests' --exclude='.distignore' \
  /path/to/git-repo/ trunk/

# Đảm bảo readme Stable tag + Version header = 1.2.6 trước khi tag
svn copy trunk tags/1.2.6

# Khai báo file thêm/xoá
svn add --force .
svn status                                          # ! = file đã xoá
svn delete $(svn status | grep '^!' | awk '{print $2}') 2>/dev/null

# Commit (dùng application password, không phải pass đăng nhập)
svn commit -m "Release 1.2.6: security fixes" --username your-wporg-user
```

Assets commit riêng:
```bash
cp banner-772x250.png icon-256x256.png screenshot-1.png assets/
svn add assets/* --force
svn commit -m "Update assets" --username your-wporg-user
```

---

## E. SVN Deploy — tự động bằng GitHub Actions (khuyên dùng)

`.github/workflows/deploy.yml`:

```yaml
name: Deploy to WordPress.org
on:
  push:
    tags:
      - "[0-9]+.[0-9]+.[0-9]+"
jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      # Bật nếu có build:
      # - uses: actions/setup-node@v4
      #   with: { node-version: 20 }
      # - run: npm ci && npm run build
      - name: WordPress.org Plugin Deploy
        uses: 10up/action-wordpress-plugin-deploy@stable
        with:
          generate-zip: true
        env:
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          SLUG: your-plugin-slug
```

`.distignore` (gốc repo — loại file dev khỏi bản phát hành):
```
.git
.github
.gitignore
.distignore
node_modules
tests
composer.json
composer.lock
package.json
package-lock.json
README.md
CLAUDE.md
*.map
.DS_Store
```

Thiết lập 1 lần:
1. wordpress.org → login.wordpress.org/security → tạo Application Password (KHÔNG dùng pass thường)
2. GitHub repo → Settings → Secrets → Actions:
   - `SVN_USERNAME` = username wordpress.org
   - `SVN_PASSWORD` = application password
3. Đặt 2 file trên vào repo

Phát hành mỗi version:
```bash
# Sửa Stable tag + Version = 1.2.6, thêm changelog, rồi:
git commit -am "Release 1.2.6"
git tag 1.2.6
git push origin main --tags
# Actions tự đẩy lên SVN trunk + tags/1.2.6
```

Assets cho auto-deploy: đặt trong `.wordpress-org/` ở repo, action tự sync.

---

## F. Khác biệt cốt lõi Git vs SVN (nhớ kỹ)

| | Git (dev) | SVN (wordpress.org) |
|---|---|---|
| Nơi viết code | GitHub/GitLab | không viết ở đây |
| Phát hành dựa vào | branch/tag tuỳ ý | `Stable tag` trong readme trỏ tới `tags/` |
| Cấu trúc | tuỳ | bắt buộc trunk/ + tags/ + assets/ |
| Cách lên wp.org | KHÔNG trực tiếp | bắt buộc qua SVN (tay hoặc Actions) |

Bản chất auto-deploy = Actions làm hộ bước copy Git → SVN → commit SVN.

---

## G. Cập nhật assets (banner/icon/screenshot) KHÔNG cần release

Assets sống ở `assets/` của SVN, **độc lập với code**. Đừng bắt buộc bump version chỉ để đổi
banner/screenshot. Thêm workflow RIÊNG dùng `10up/action-wordpress-plugin-asset-update`, chạy khi
push `main` có đổi `.wordpress-org/**`:

`.github/workflows/assets.yml`:
```yaml
name: Update WordPress.org Assets
on:
  push:
    branches: [ main ]
    paths: [ ".wordpress-org/**" ]
  workflow_dispatch:
jobs:
  assets:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: 10up/action-wordpress-plugin-asset-update@stable
        env:
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          SLUG: your-plugin-slug
          ASSETS_DIR: .wordpress-org   # mặc định của action; ghi rõ cho minh bạch
```
- Dùng CHUNG secrets `SVN_*` với `deploy.yml` → không setup thêm.
- `deploy.yml` (tag) cũng tự sync assets kèm; nhưng asset-update cho phép đổi ảnh BẤT CỨ LÚC NÀO
  không cần cắt version. Thêm `.wordpress-org` vào `.distignore` để ảnh không lọt vào zip plugin.

---

## H. Verify SVN nhanh — KHÔNG cần checkout

Sau khi push, kiểm tra trực tiếp wp.org SVN bằng `curl` (HTTP, không cần `svn`):
```bash
SVN=https://plugins.svn.wordpress.org/your-plugin-slug
curl -s "$SVN/tags/"              | grep -oE '[0-9.]+/'   # các tag đã có
curl -s "$SVN/trunk/readme.txt"   | sed -n '1p;7p'        # trunk: title + Requires PHP
curl -s "$SVN/tags/1.6.6/readme.txt" | sed -n '1p'        # readme trong 1 tag cụ thể
curl -s "$SVN/assets/"            | grep -oE 'href="[^"]+"' # banner/icon/screenshot đã lên?
```
Trang directory wp.org có cache (vài phút → vài giờ) nhưng SVN là nguồn gốc → curl SVN cho biết
deploy đã xong & ĐÚNG nội dung chưa, không cần đợi cache.

---

## I. Build zip cài tay = đúng gói SVN (folder phải = slug)

Để bản zip giống hệt cái ship lên wp.org VÀ tránh false-positive Plugin Check:
1. `git archive HEAD` (chỉ file tracked → tự loại dev junk untracked) vào staging dir tên = **slug**
   wp.org (KHÔNG phải tên thư mục dev — folder≠slug làm Plugin Check báo ~ngàn `TextDomainMismatch`
   GIẢ vì PCP suy text-domain từ tên folder).
2. Xoá tiếp các path `.distignore` (docs, tools, tests, `.github`, `.claude`, `.wordpress-org`…).
3. Nén bằng `zip` trong container Linux (forward-slash chuẩn). **KHÔNG dùng PowerShell
   `Compress-Archive`** — nó tạo entry separator `\` → WordPress Linux `unzip_file()` giải nén
   thành file tên `folder\file` → hỏng cài đặt.
4. Chạy `wp plugin check <slug>` trên staging folder=slug → kết quả THẬT (0 false TextDomainMismatch).

---

## J. Gotchas release (đúc kết Storelly, 2026)

- **Stale SVN tag**: `tags/x.y.z` đã tồn tại trên SVN → 10up action **KHÔNG ghi đè** tag đó khi
  re-deploy (chỉ cập nhật `trunk`). Nếu lỡ deploy tag với readme sai rồi sửa → phải **bump version
  mới** (tag mới luôn deploy sạch). ⇒ Đừng push tag khi local tag còn trỏ commit cũ. Verify bằng
  mục H trước khi mừng.
- **Push non-fast-forward khi repo bị agent/PR song song chèn**: ĐỪNG `--force`. `git fetch` rồi
  `git merge origin/main` (nếu remote-only toàn docs/.distignore → merge sạch); hoặc nếu commit của
  mình là sibling đã amend nhiều lần → `git reset --soft <origin/main>` rồi commit lại phần khác
  biệt thành 1 commit MỚI trên đỉnh origin/main → ff push được, không rewrite cái đã đẩy. Push tag
  KHÔNG cần ff (lên được kể cả khi branch còn rejected).
- **Remote state authoritative**: dưới proxy (rtk…), `git log origin/main` / `rev-list --count` có
  thể đọc tracking ref STALE. Dùng `git ls-remote origin refs/heads/main` + `git merge-base
  --is-ancestor` để biết SỰ THẬT remote + check ff.
