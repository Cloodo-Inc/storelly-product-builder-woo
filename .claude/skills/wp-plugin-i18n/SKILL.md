---
name: wp-plugin-i18n
description: >
  Pipeline đầy đủ để một WordPress plugin THỰC SỰ multilingual — từ i18n bootstrap
  (Text Domain + Domain Path + load_plugin_textdomain), regen POT đúng convention,
  sinh RTL CSS qua rtlcss, batch dịch nhiều locale (msginit + Python translation
  table + msgfmt), tới chạy Plugin Check i18n_usage và lọc lỗi thật khỏi noise.
  Bao gồm cả workflow extend ngôn ngữ mới và best practices về translator comments
  + ordered placeholders cho plural-rule-complex locales.
  LUÔN dùng skill này khi user đề cập: "đa ngôn ngữ", "i18n", "multilingual",
  "internationalization", "dịch plugin", "translate plugin", "thêm ngôn ngữ",
  "RTL", "right-to-left", "load_plugin_textdomain", "Text Domain", "Domain Path",
  "msgfmt", "msginit", "rtlcss", "file .po", "file .mo", "POT regen", "wp i18n
  make-pot", "translate.wordpress.org", "GlotPress", "language pack", "translator
  comments", "plural forms", "i18n_usage", "PCP i18n", "Plugin Check i18n",
  "TextDomainMismatch", "translation bundle". Áp dụng cho mọi plugin trong monorepo
  WP của Netbase (Storelly, Printcart, cmsmart, giganticprint, wppartner) và plugin
  mới sắp tạo.
---

# WordPress Plugin i18n Pipeline

Pipeline biến một WordPress plugin từ "english-only" thành "thật sự multilingual"
— có .mo bundle cho 10-15 locale, RTL CSS, infrastructure để cộng đồng dịch thêm,
và pass `wp plugin check --checks=i18n_usage`.

> Skill này KHÔNG chép từng quy tắc i18n vào file tĩnh (PCP/WP i18n thay đổi theo
> phiên bản). Skill dạy **quy trình** + **artifact mẫu hoạt động được**, có thể
> chạy lại mỗi khi POT regen.

---

## Nguyên tắc nền tảng

1. **Text Domain phải khớp WP.org slug.** Nếu plugin slug là
   `storelly-product-builder-for-woocommerce` thì Text Domain LUÔN là chuỗi đó,
   không được chế biến. Đây là điều kiện để WP 4.6+ tự load language packs từ
   translate.wordpress.org.

2. **Tên file PO/MO phải theo convention** `{textdomain}-{locale}.{po,mo}`. POT
   nên đặt `{textdomain}.pot` (không bắt buộc runtime nhưng đúng convention).
   Khi load_plugin_textdomain() chạy, nó tìm đúng tên này trong thư mục được khai
   báo bởi `Domain Path` header.

3. **Phân biệt 3 vùng dịch:**
   - PHP strings wrap `__()/esc_html__()` → dịch qua text domain (mọi locale pack).
   - JS strings: legacy `wp_localize_script` bag (PHP-fed, vẫn dịch qua __()) HOẶC
     modern `wp.i18n.__()` + `wp_set_script_translations()`. Chọn pattern nhất quán.
   - CSS RTL: tự động qua `rtlcss` sinh file `*-rtl.css`; WP core auto-load khi
     `is_rtl()`.

4. **3 con số phải khớp khi release:** plugin header `Version`, readme.txt
   `Stable tag`, git tag. (Quy tắc release WP.org — không đặc thù i18n nhưng
   ảnh hưởng nếu rebuild POT giữa các version.)

---

## Tổng quan pipeline

```
PHASE 0: Audit hiện trạng     → grep load_plugin_textdomain, Domain Path,
                                wp_set_script_translations, *-rtl.css, *.po/*.mo
PHASE 1: i18n bootstrap       → Text Domain, Domain Path, load_plugin_textdomain
PHASE 2: POT generation       → wp i18n make-pot đúng tên convention
PHASE 3: RTL CSS              → rtlcss trên mọi CSS chính
PHASE 4: Locale bundle        → msginit + translation table + msgfmt cho N locale
PHASE 5: PCP i18n_usage       → fix UnorderedPlaceholders + MissingTranslators
PHASE 6: Workflow tiếp        → submit translate.wordpress.org, monitor coverage
```

---

## PHASE 0: Audit hiện trạng

Trước khi sửa, biết plugin đang đứng đâu. 4 lệnh grep:

```bash
# Có load_plugin_textdomain chưa?
grep -rn 'load_plugin_textdomain\|load_textdomain\|wp_set_script_translations' includes/

# Plugin header có Domain Path?
head -25 <plugin-main>.php | grep -E 'Text Domain|Domain Path'

# Đã có bundled translations chưa?
ls languages/*.{po,mo,pot} 2>/dev/null

# Có RTL CSS chưa?
ls static/css/*-rtl.css 2>/dev/null
```

Output cho biết phải làm phase nào.

---

## PHASE 1: i18n bootstrap (5 phút)

### Plugin header (`<plugin-main>.php` line ~20)

```
Text Domain:            <textdomain-khớp-slug>
Domain Path:            /languages
```

### load_plugin_textdomain hook (sau khối `define()` constants)

```php
add_action( 'init', '<prefix>_load_textdomain' );
function <prefix>_load_textdomain() {
    load_plugin_textdomain(
        '<textdomain>',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}
```

> Hook trên `init` chứ KHÔNG `plugins_loaded` — vì WP 6.7+ đã chuyển sang
> "translation deferring" và `plugins_loaded` quá sớm trong một số scenario.

### Verify

```bash
docker exec <container> wp eval '
  $path = WP_PLUGIN_DIR . "/<plugin-slug>/languages/<textdomain>-vi.mo";
  unload_textdomain("<textdomain>");
  load_textdomain("<textdomain>", $path);
  echo __("Some Known String", "<textdomain>");
' --allow-root
```

---

## PHASE 2: POT regen + đúng tên

```bash
docker exec <container> bash -c '
  cd /var/www/html/wp-content/plugins/<plugin-slug> &&
  wp i18n make-pot . languages/<textdomain>.pot --allow-root
'
```

POT name = `{textdomain}.pot`. Nếu plugin đang có POT tên cũ (legacy `spbwc-product-builder.pot`),
RENAME qua `git mv` trước khi regen — convention quan trọng cho translator workflow.

---

## PHASE 3: RTL CSS (30 phút)

### Cài rtlcss trong dev container

```bash
docker exec <container> sh -c '
  apt-get update -qq && apt-get install -y -qq nodejs npm &&
  npm install -g rtlcss
'
```

### Batch generate

```bash
docker exec <container> sh -c '
  cd /var/www/html/wp-content/plugins/<plugin-slug> &&
  for f in static/css/*.css; do
    case "$f" in *-rtl.css) ;; *)
      out="${f%.css}-rtl.css"
      rtlcss "$f" "$out"
    ;; esac
  done
'
```

WP tự load `*-rtl.css` khi `is_rtl()` → **không cần sửa enqueue**. Test bằng
cách đổi site language → Arabic / Hebrew và xem layout có flip không.

### Gotchas RTL

- rtlcss tự động hoá ~90%. 10% còn lại cần QA tay:
  - Icon có hướng (chevron, arrow → cần flip)
  - `transform: translateX()` (rtlcss có flip nhưng đôi khi sai dấu)
  - `background-position` với pixel cố định
- Bundle RTL-aware fonts (Noto Sans Arabic/Hebrew) nếu plugin có text personalization.

---

## PHASE 4: Locale bundle (đây là phase "ngon nhất" với AI)

Workflow 3 bước cho mỗi locale: `msginit` → translation table → `msgfmt`.

### Bước 4.1: msginit skeleton

```bash
docker exec <container> sh -c '
  cd /var/www/html/wp-content/plugins/<plugin-slug>/languages &&
  for loc in vi fr_FR de_DE es_ES pt_BR it_IT ja zh_CN ru_RU ar; do
    msginit -i <textdomain>.pot -l $loc \
      -o <textdomain>-$loc.po --no-translator --no-wrap
  done
'
```

### Bước 4.2: Python translation table → apply

Tạo file `tools/apply-translations.py` theo template **PROVEN WORK** từ Storelly
(xem `references/apply-translations-template.py`). Cấu trúc:

- `CORE = {msgid: {locale: trans}}` — strings dịch cho TẤT CẢ locale (menu labels,
  common verbs).
- `EXTRA_BY_LOCALE = {locale: {msgid: trans}}` — strings dịch riêng cho 1 locale
  (vd: tiếng Việt full coverage).
- Parser tự handle multi-line msgid + skip plural entries.
- Re-runnable: chạy lại sau khi POT regen, fill thêm strings mới.

Run:

```bash
docker exec <container> sh -c '
  cd /var/www/html/wp-content/plugins/<plugin-slug> &&
  python3 tools/apply-translations.py
'
```

Output cho biết mỗi locale fill bao nhiêu msgstrs.

### Bước 4.3: Compile .mo

```bash
docker exec <container> sh -c '
  cd /var/www/html/wp-content/plugins/<plugin-slug>/languages &&
  for loc in vi fr_FR de_DE es_ES pt_BR it_IT ja zh_CN ru_RU ar; do
    msgfmt --statistics -o <textdomain>-$loc.mo <textdomain>-$loc.po
  done
'
```

### Locale priority list (Top 15 WP install base, 2026)

Tier 1 (mandatory): `vi fr_FR de_DE es_ES pt_BR it_IT ja zh_CN ru_RU ar`
Tier 2 (high-impact): `nl_NL pl_PL tr_TR sv_SE id_ID`
Tier 3 (nice-to-have): `ko_KR he_IL zh_TW cs_CZ hu_HU ro_RO th uk fi`

Mỗi locale Tier-1 nên có **ít nhất 30 core strings** (menu labels, common admin verbs,
critical error messages). Native locale của dev (vi cho team Việt) nên có **150-300+ strings**.

---

## PHASE 5: PCP i18n_usage check

### Chạy

```bash
docker exec <container> wp plugin check <plugin-slug> \
  --checks=i18n_usage --allow-root \
  --format=csv --fields=type,code,line,message
```

### Lọc 3 loại lỗi

```bash
# Đếm tổng theo code
grep -oE 'WordPress\.WP\.I18n\.[A-Za-z]+' /tmp/pcp.csv | sort | uniq -c
```

### Xử lý theo loại

**`TextDomainMismatch`** (thường vài nghìn dòng): **noise**. PCP đọc text domain
expected = folder name local. Nếu folder local ngắn hơn slug WP.org (vd folder
`storelly-product-builder-woo` vs slug `storelly-product-builder-for-woocommerce`)
→ mismatch ảo. Khi user cài qua wp.org folder = slug → tự hết. **KHÔNG fix.**

**`UnorderedPlaceholdersText`** (bug thật): strings có nhiều placeholder phải dùng
ordered form vì languages khác có word order khác.

```php
// SAI
__( 'Showing %d of %d.', 'td' )
// ĐÚNG
/* translators: 1: shown count, 2: total count. */
__( 'Showing %1$d of %2$d.', 'td' )
```

**`MissingTranslatorsComment`** (cần fix): mọi string có `%s`/`%d` placeholder
phải có comment `/* translators: ... */` ngay trên dòng `__()` để translator
hiểu context.

```php
/* translators: %s: product name shown in the picker dialog title. */
$title = sprintf( __( 'Images for: %s', 'td' ), $product_name );
```

### Mục tiêu: 0 lỗi non-TextDomainMismatch

Submit wp.org review cần PCP pass. Fix hết Unordered + MissingTranslators.

---

## PHASE 6: Workflow sau khi bundle

### Submit lên translate.wordpress.org

Sau khi plugin live trên wp.org, tự động được GlotPress project. Encourage cộng
đồng dịch — bundled .mo là fallback cho locale chưa được community phủ.

### Khi POT regen

POT thay đổi (vì code thêm strings) → `msgmerge --update` tất cả .po, rồi
re-run apply-translations.py + msgfmt:

```bash
docker exec <container> sh -c '
  cd .../languages &&
  for loc in <list>; do
    msgmerge --update --backup=none --no-wrap \
      <textdomain>-$loc.po <textdomain>.pot
  done &&
  cd .. && python3 tools/apply-translations.py &&
  cd languages &&
  for loc in <list>; do
    msgfmt -o <textdomain>-$loc.mo <textdomain>-$loc.po
  done
'
```

### Extend ngôn ngữ mới

1. Thêm locale vào `LOCALES` list trong `apply-translations.py`.
2. Thêm dict vào `EXTRA_BY_LOCALE[<new-locale>]` với ~30 core strings.
3. Run msginit + apply + msgfmt cho locale đó.
4. Commit `<textdomain>-<new-locale>.{po,mo}` + script update.

### Common pitfalls

- **Đừng dùng biến trong text domain**: `__($string, $domain_var)` → PCP báo lỗi,
  GlotPress không extract được.
- **Plural strings phải dùng `_n()`** chứ KHÔNG `__()` rồi sprintf "%d items":
  ```php
  // SAI
  printf( __( '%d items', 'td' ), $count );
  // ĐÚNG
  printf( _n( '%d item', '%d items', $count, 'td' ), $count );
  ```
  Locale như Polish/Russian/Arabic có nhiều form plural (3-6 form) — `_n()` cho phép.
- **Đừng nối chuỗi trong `__()`**: `__("Hello " . $name)` → KHÔNG dịch được.
  Dùng `sprintf( __( 'Hello %s', 'td' ), $name )`.
- **JS strings**: nếu dùng wp_localize_script bag, PHP-side phải wrap __() — bag
  được tự động dịch. Nếu chuyển sang wp.i18n.__() trong JS, BẮT BUỘC call
  `wp_set_script_translations()` khi enqueue (không thì JS không nhận translation).

---

## Verify cuối cùng

Trước khi commit + push một i18n release:

```bash
# 1. PHP lint
docker exec <container> sh -c '
  find . -name "*.php" -not -path "*/vendor/*" -exec php -l {} \;
' | grep -v "No syntax errors"

# 2. PCP i18n
docker exec <container> wp plugin check <slug> --checks=i18n_usage \
  --allow-root | grep -vE 'TextDomainMismatch' | grep ERROR

# 3. Live load test (mỗi locale Tier-1)
for loc in vi fr_FR de_DE; do
  docker exec <container> wp eval "
    \$p = WP_PLUGIN_DIR.'/<slug>/languages/<textdomain>-$loc.mo';
    unload_textdomain('<textdomain>');
    load_textdomain('<textdomain>', \$p);
    echo '$loc: '.__('Overview Dashboard','<textdomain>').\"\\n\";
  " --allow-root
done

# 4. RTL test
# Settings → General → Site Language → Arabic → check layout flip
```

---

## Tham khảo

Artifacts PROVEN trong repo Storelly (copy/adapt sang plugin khác):
- `tools/apply-translations.py` — Python script hoạt động cho 15 locales (CORE
  dict + EXTRA_BY_LOCALE dict + VI_EXTRA dict). Parser handle multi-line msgid +
  skip plural entries. Re-runnable sau POT regen.
- `languages/storelly-product-builder-for-woocommerce.pot` — POT đúng tên convention.
- `languages/storelly-product-builder-for-woocommerce-{15-locales}.{po,mo}` —
  bundle 15 locale Tier 1+2 đã compile.
- `static/css/*-rtl.css` — 18 file RTL CSS sinh bằng rtlcss, làm ví dụ cho
  cumulative scope.
- Bootstrap reference: `storelly-product-builder-for-woocommerce.php` lines
  20-21 (Text Domain + Domain Path) và lines ~58-66 (load_plugin_textdomain hook).

Skills bổ trợ:
- `wp-org-plugin-compliance` — chuẩn submit/review tổng thể (i18n là 1 phần).
- Khi cần forking plugin mới sang niche khác, kết hợp với `wp-plugin-niche-fork`
  (đã có sẵn translations table — chỉ cần đổi text domain trong CORE values nếu
  brand mới khác).

## Pitfall đã học (Storelly Q2 2026)

1. **rtk CLI wrapper lọc output của `git diff`** — nếu thấy diff trống bất ngờ, ghi
   ra file rồi Read thay vì pipe trực tiếp.
2. **PCP TextDomainMismatch noise** chiếm 99% lỗi raw — folder local thường ngắn
   hơn slug WP.org. ALWAYS filter trước khi đánh giá "có bao nhiêu lỗi i18n".
3. **15K translation cho 10 locale × 1500 strings là không khả thi 1 session** —
   phải scope: Tier-1 locales ~30 core strings + native locale full coverage
   (~200-500 strings). Phần còn lại đẩy về translate.wordpress.org.
4. **Đừng dùng `wp_set_script_translations()` nếu JS chưa migrate sang `wp.i18n.__()`** —
   no-op. Plugin có thể đi với pattern wp_localize_script + __() pattern,
   functional translations vẫn flow qua text domain.
5. **POT rename phải đi kèm grep audit** — search references trong docs/, scripts,
   CI configs trước khi mv.

## Trigger memory

Dùng skill này khi user đề cập: đa ngôn ngữ, i18n, dịch plugin, RTL, msgfmt,
.po/.mo, translate.wordpress.org, PCP i18n, hoặc bất kỳ task nào về làm cho
plugin hỗ trợ nhiều ngôn ngữ. Cũng dùng để onboard plugin mới (Tier-1 setup
mất ~2-3h theo workflow này).
