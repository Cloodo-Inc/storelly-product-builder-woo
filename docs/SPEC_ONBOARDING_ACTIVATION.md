# SPEC — Auto-First Onboarding & Activation

> **Triết lý:** merchant cài xong là thấy giá trị Storelly NGAY, không phải động tay kỹ thuật.
> Mọi thứ chạy ngầm; merchant chỉ thấy thông báo + tiến trình. Đúng **2 cú bấm consent** duy
> nhất (bật Cloud, Publish-all) để giữ plugin hợp lệ trên wordpress.org và không phá storefront.
>
> Mục tiêu số: ↑ success rate · ↑ activation (chạm aha sớm) · ↑ retention (review + upgrade).
> Plugin freemium trên wordpress.org, free giới hạn 5 sản phẩm.
>
> **Trạng thái: M1–M7 ĐÃ SHIP. Audit 2026-06-03 phát hiện gap → đợt remediation M9 (xem §8).**
>
> **Quyết định đã chốt với user:**
> - Welcome render **trên Overview**, tự kích hoạt (không submenu riêng).
> - Ưu tiên **Import sample trước** (time-to-value ngắn nhất).
> - **#4 Cloud (Storelly account + PDF): 1-click consent** — KHÔNG auto khi chưa bấm.
> - **#8/#5 Migrate Woo → publish live: chuẩn bị ngầm + 1 nút "Publish all"** + Undo.
> - **(2026-06-03) Làm cả 4 hạng mục M9** (bundle demo local · review request · sửa dismiss + quote badge default · hardening woo-prepare). Viết spec trước, code từng phần có test.

---

## 1. Hiện trạng — funnel & điểm đứt gãy

### Flow hôm nay (fresh WooCommerce install)

```
Install → Activate
  → spbwc_plugin_activation()  [storelly-product-builder-for-woocommerce.php:71]
      - gate WooCommerce (wp_die nếu chưa active)   ✓
      - tạo page / table / folders / set version      ✓
      - KHÔNG redirect, KHÔNG seed gì                  ✗
  → user rơi về plugins.php (lạc) → tự mò menu → Overview toàn số 0
  → tự build option-set thủ công (concept lạ) → tự link product → tự ra storefront
     ← AHA MOMENT nằm tận bước ~14
```

### Đứt gãy chính

| # | Gap | File:line |
|---|-----|-----------|
| 1 | Không redirect / không có welcome sau activate | `storelly-product-builder-for-woocommerce.php:71-80` |
| 2 | Setup Wizard (demo/seed) bị chôn submenu | `includes/class-admin-options.php:357-363` |
| 3 | Empty state câm (Overview & list toàn 0, không dẫn bước) | `views/overview.php`, `views/options/options-list-table.php` |
| 4 | Không có flow "create → link → xem store" | edit option view |
| 5 | Không review request, không activation metric | (chưa có) |

**Nhận định:** time-to-value quá dài + concept khó. Vũ khí mạnh nhất (engine *Import Sample* +
*Import Woo Variations* 3-step Scan→Rules→Confirm, có Undo) **đã có sẵn nhưng bị giấu**. Tầm nhìn
mới: kéo các engine đó lên chạy NGẦM ngay sau activate, merchant chỉ xem tiến trình.

---

## 2. Trải nghiệm mục tiêu (sau khi chốt)

```
ACTIVATE
  ├─ (ngầm) tạo table/folders/page + set spbwc_activated_at + store UUID ổn định
  └─ set transient redirect → mở Overview ở chế độ Welcome (tự kích hoạt)

OVERVIEW (Welcome mode) — merchant KHÔNG phải làm gì, chỉ xem:
  ① [tự chạy ngầm] Import sample products  ──► thông báo "Đã thêm N sản phẩm demo" + [Xem] + [Undo]
  ② [tự chạy ngầm] Scan Woo variations     ──► "Tìm thấy M sản phẩm có biến thể"
        └─ import ngầm thành BẢN CHỜ (chưa publish) + auto-link sẵn
        └─ banner: "Đã chuẩn bị M sản phẩm — [Publish tất cả] (1 nút)"  ← cú bấm #1 (an toàn live)
  ③ [card consent] "Bật Cloud: PDF in ấn + lưu hồ sơ store"  ← cú bấm #2 (opt-in wp.org)
        └─ bấm → đăng ký Storelly (email admin + store URL + store UUID) → kích PDF → email welcome
  ④ Activation checklist + metrics + CTA (xem §3.6)

AHA MOMENT đến trong vài giây đầu: storefront có sản phẩm demo customize được + thấy rõ
pricing option áp lên chính sản phẩm Woo hiện có.
```

Nguyên tắc: **mọi thao tác ghi-đè-lên-store-thật đều reversible và/hoặc chờ 1 cú bấm**; mọi thao
tác chỉ-thêm-data-demo thì chạy ngầm + Undo.

---

## 3. Đặc tả từng phần (ánh xạ 11 yêu cầu của user)

### 3.1 — Welcome trên Overview, tự kích hoạt  *(yc #1)*
- Không tạo submenu riêng. `spbwc_plugin_activation()` set
  `set_transient('spbwc_activation_redirect', 1, 60)`. Hook `admin_init`: nếu có transient
  và không phải bulk/network activate và user `manage_options` → `wp_safe_redirect` tới Overview
  với cờ `?spbwc-welcome=1`.
- Overview đọc cờ + option `spbwc_onboarding_state` để render Welcome mode (banner tiến trình,
  checklist) thay cho dashboard rỗng. Khi onboarding xong → Overview về chế độ thường.

### 3.2 — Auto-import sample products  *(yc #2)*  ⚠️ ĐÃ ĐIỀU CHỈNH SAU KHI ĐỌC CODE
- **Phát hiện chặn (M3):** engine "Import Sample Products" (`SPBWC_Global_Import_Controller`)
  KHÔNG có dataset bundled. `fetch_demo_rows()` chỉ `wp_remote_get()` từ
  `https://app.storelly.com/product-data/data/data.json`. File local
  `storage/printcart/demo_datas.json` KHÔNG tồn tại.
- ⇒ "Auto-import sample chạy ngầm khi activate" = **tự động gọi HTTP ra app.storelly.com
  không consent** → cùng vấn đề compliance như #4 (phone-home), lại chậm/flaky. KHÔNG làm
  background fetch âm thầm.
- **Phương án (cần user chốt):**
  - **(A) Đề xuất:** bỏ background auto-fetch. Dùng nút 1-click "Import demo products" trên
    Welcome (M2 — đã làm) làm đường sample: user TỰ bấm = consent ngầm, dẫn tới UI `?tab=sample`
    sẵn có. Compliant, 0 thêm việc.
  - **(B)** Bundle một bộ sample nhỏ (2–3 demo product + ảnh) trong plugin + seeder tự viết
    chạy ngầm KHÔNG mạng. Compliant + nhanh, nhưng phải tạo/curate asset demo (tăng dung lượng).
  - **(C)** Auto-fetch remote — KHÔNG chấp nhận (compliance).
- Nếu chọn A/B: toast "Đã thêm N demo" + **Undo**; meta `_spbwc_is_sample` để Undo gom đúng.

### 3.3 — Auto-scan + import Woo variations (bản chờ)  *(yc #3, #5, #8)*
- **Scan** chạy ngầm (Action Scheduler), tái dùng engine 3-step. Chỉ đọc → an toàn.
- **Import** ngầm thành **bản chờ / chưa publish**: tạo option-set từ variation + **auto-link**
  sẵn vào đúng product ID (yc #5), nhưng **chưa hiển thị trên storefront**.
- Storelly options **layer lên trên**, KHÔNG xoá/đụng variation gốc của Woo → luôn revert được.
- Dashboard banner: "Đã chuẩn bị M sản phẩm — **[Publish tất cả]**". Bấm 1 nút → publish hàng loạt
  (yc #8). Có **[Undo / Unpublish all]**.
- ⚠️ Đếm "đã link / đã migrate" phải tương thích **HPOS** (memory: HPOS đang bật — dùng API Woo
  đúng, không query thẳng wp_posts cho order; product vẫn ở post type `product`).

### 3.4 — Cloud opt-in: đăng ký Storelly + PDF + store ID  *(yc #4)*
- **1-click consent** (đã chốt). Card trên Welcome: "Bật Cloud — PDF in ấn chất lượng cao + lưu
  hồ sơ store". Nút **KHÔNG tick sẵn** (wp.org bắt buộc opt-in chủ động).
- Bấm → background: đăng ký với `SPBWC_API_URL` gửi { admin email, store URL, **store UUID** },
  nhận về store ID/profile → bật `enable_api_sync` + `enable_cloud2print_api` → gửi email welcome
  (qua Storelly, không phải tự gửi từ site).
- **Store UUID ổn định** (yc #4 "cài lại vẫn nhận store cũ"): sinh 1 lần, lưu `spbwc_store_uuid`
  (autoload no). Cân nhắc lưu kèm trong table/option để khôi phục khi reinstall (xem §6 Q3).
- **readme "External services"**: khai báo rõ endpoint, dữ liệu gửi (email admin, store URL,
  store UUID), thời điểm (chỉ sau khi bấm consent), mục đích, link privacy.
- Trước khi consent: KHÔNG gọi mạng, KHÔNG gửi gì. (Guard cứng — đây là điều kiện sống còn wp.org.)

### 3.5 — My Account endpoint tự publish  *(yc #9)*
- Endpoint/tab "My Designs / My Quotes" tự đăng ký (flush rewrite 1 lần khi activate). Chỉ thêm
  tab, không phá gì. Đã có nền tảng từ saved-designs/quote CPT.

### 3.6 — Dashboard activation metrics + CTA  *(yc #6)*
- Thay stat-cards "toàn 0" bằng **activation metrics có ngữ cảnh + CTA tương ứng**:
  - Sản phẩm demo: N → CTA "Xem trên store".
  - Sản phẩm Woo đã chuẩn bị: M (chờ publish) → CTA "Publish tất cả".
  - Cloud: chưa bật → CTA "Bật Cloud (1 click)".
  - Đã link / đã publish: x/y → CTA "Xem sản phẩm".
- **Checklist** (auto-detect, dismiss được): ☐ Có sản phẩm builder · ☐ Publish lên store ·
  ☐ Bật Cloud · ☐ (tuỳ chọn) Xem trên storefront. Đủ → "All set 🎉".

### 3.7 — Request Quote tự publish + site-wide CTA badge  *(yc #11)*
- Quote feature (CPT `spbwc_quote` đã có) bật sẵn. Thêm **widget badge "Request a Quote"
  toàn site** (floating button/badge), bật/tắt + vị trí ở Quote Settings. Published mặc định,
  merchant chỉnh được.

### 3.8 — Freemium gating tới limit mới upsell  *(yc #10)*
- Merchant dùng thoải mái tới khi chạm limit (5 SP). Khi gắn product **thứ 5** hoặc bấm feature
  Pro-only → upsell **theo ngữ cảnh value** (không nag, dismiss được, 1 chỗ). Trước đó: im lặng.

---

## 4. Ràng buộc compliance wordpress.org (bắt buộc — điều kiện sống còn)

- **Không phone-home trước consent** (§3.4). Đây là lý do #1 plugin bị gỡ kho.
- Mọi file PHP mới: `if ( ! defined('ABSPATH') ) exit;`. Input `wp_unslash`+`sanitize_*`,
  output `esc_*`. Mọi action/redirect/dismiss/publish/undo: **nonce + `current_user_can`**.
- Prefix `spbwc_`/`SPBWC_`. Text domain `storelly-product-builder-for-woocommerce`, không biến
  trong `__()`. SQL có biến → `$wpdb->prepare()`. Asset → `wp_enqueue_*`.
- Tác vụ nặng (import/scan/publish hàng loạt) → **Action Scheduler**, không chạy trong activation
  hook (tránh timeout/wp_die).
- readme "External services" cập nhật đủ; tính năng quảng cáo phải tồn tại thật (limit 5 SP…).
- String mới → regen `.pot` (skill `wp-plugin-i18n`). Trước submit: `wp plugin check` 0 ERROR,
  3 version khớp. Chạy skill `wp-org-plugin-compliance` trước khi đụng readme/submit.

---

## 5. Tài sản tái dùng (giảm rủi ro & công sức)

- `SPBWC_I18n_Notice` (`includes/class-i18n-notice.php`) — pattern chuẩn option-key + nonce
  dismiss + sticky + design tokens `spbwc-block`. Clone cho: redirect-flag, welcome banner,
  review notice, upsell notice.
- Engine **Import Sample** (`?tab=sample`) + **Import Woo Variations** 3-step có Undo — KHÔNG
  viết lại, chỉ gọi ngầm + bọc UI tiến trình.
- Quote CPT `spbwc_quote`, saved-designs CPT, Action Scheduler (đã dùng cho order PDF) — sẵn sàng.

---

## 6. Câu hỏi mở còn lại (chốt trước khi code phần Cloud/migrate)

1. **Checklist "đã xem storefront"**: khó đo. Đề xuất coi done khi bấm "View on store", hoặc bỏ
   bước này. → cần chốt.
2. **Migrate store đang live thật sự lớn** (hàng trăm SP): "Publish all" 1 nút có cần chia batch
   + progress + cho chọn subset không? Đề xuất: publish theo batch ngầm + progress, vẫn 1 nút.
3. **Khôi phục store cũ khi reinstall**: store UUID lưu ở option sẽ mất khi uninstall xoá option.
   Có muốn lưu UUID ở chỗ bền hơn (table riêng giữ qua reinstall / hoặc Storelly trả lại theo
   store URL) không? → quyết định ảnh hưởng uninstall cleanup.
4. **Email welcome**: gửi qua Storelly (sau consent) — đúng chứ? (Không tự gửi từ site để tránh
   spam/deliverability.)
5. **edit-option "concept khó"**: có gộp cải thiện preset-pattern-khi-tạo-mới vào đợt này không,
   hay tách sau? (Auto-import đã che phần lớn nhu cầu này.)

---

## 7. Thứ tự triển khai đề xuất

```
M1  ✅ DONE  Activation plumbing: store UUID + spbwc_activated_at + transient redirect + Welcome cờ
            → includes/class-onboarding.php (SPBWC_Onboarding). Lint OK + functional test OK.
M2  ✅ DONE  Overview Welcome UI: banner 3-CTA (demo lead) + checklist progress + dismiss   (yc #1,#6)
            → views/overview.php + static/css/overview.css. Tự ẩn khi onboarding xong.
M3  ✅ DONE (PA-A) Sample = nút 1-click "Import demo products" ở Welcome (M2); bỏ background auto-fetch
            để giữ compliant (engine sample là remote-fetch, không bundled). Xem §3.2.        (yc #2)
M4  ✅ DONE  Auto-scan(ngầm) Woo → bản chờ published=0 + auto-link + "Publish all" + Undo  (yc #3,#5,#8)
            → includes/setup-wizard/class-woo-prepare.php (SPBWC_Woo_Prepare) + banner overview.php.
            Trigger khi mở Welcome (maybe_schedule). Slug woo_prep_ riêng. Default: empty+swatch+ảnh.
            Verify full-cycle PASS (prepare ẩn → publish hiện → undo ẩn, 0 sót). Bắt+vá 2 bug cache
            (publish_all & undo phải bust spbwc_option_<id> + delete_transient WP-API, không direct SQL).
M5  ✅ CLIENT DONE  Cloud 1-click consent: đăng ký + store UUID tất định + bật PDF+order-sync   (yc #4)
            → includes/class-cloud-connect.php (SPBWC_Cloud_Connect: connect/disconnect/link_manual,
            consent log), store_uuid tất định hash(url+email) giữ qua uninstall (class-onboarding.php),
            payload register +store_uuid (class-productbuilder-api.php), card consent overview.php,
            readme External services sửa "chỉ sau khi bấm Enable Cloud". Spec: docs/SPEC_M5_CLOUD_CONSENT.md.
            Lint + test non-network PASS. CÒN PHỤ THUỘC BACKEND: /api/v1/register idempotent-by-store_uuid
            (team Storelly) để reinstall re-link + email welcome. uninstall cleanup secrets → để M8.
M6  ✅ DONE  Request Quote site-wide CTA badge (floating, settings: toggle/vị trí/label/URL;     (yc #11)
            mở modal trên product, link URL nơi khác) → class-request-quote.php + quote-storefront.css
            + Quote Settings (class-admin-options.php). Commit 69d5504. Test render PASS.
M7  ✅ DONE  Freemium contextual upsell: SPBWC_Upsell_Notice chỉ hiện trên màn Storelly khi Free   (yc #10)
            plan + chạm limit (max_products/max_pricing_options), dismiss snooze 30 ngày. Commit 69d5504.
M8  ✅ DONE (2026-06-04) Compliance cho M9: code mới 0 ERROR thật (PCP) · POT regen gồm string M9.1 ·
        readme changelog 1.5.7 + sửa External services (demo Welcome là local) · 3 version khớp 1.5.7.
        ⚠️ Ngoài phạm vi: 603 lỗi PCP fork cũ ở file KHÁC (escaping/heredoc/fwrite/ABSPATH) + ~3370
        TextDomainMismatch (false positive do tên thư mục dev) — cần đợt dọn compliance riêng + chạy
        PCP dưới slug chuẩn `storelly-product-builder-for-woocommerce`.
M9  ⏳ REMEDIATION (2026-06-03) — vá gap audit phát hiện sau khi M1–M7 ship. Xem §8.
        M9.1/M9.2/M9.3/M9.4 ✅ DONE + test PASS (2026-06-04).
```

M1–M2 an toàn, đã xong. M4–M5 đụng store thật + mạng → làm cẩn thận, test kỹ.
yc #9 (My Account endpoint) gài kèm M1 (đăng ký endpoint lúc activate).

---

## 8. Audit sau khi ship (2026-06-03) — gap & remediation M9

> Rà soát toàn bộ code thực thi M1–M7 đối chiếu spec. Khung onboarding tốt (security nonce+cap,
> HPOS-compat, cache-bust, reversibility OK), nhưng **đường vàng "Import demo" mong manh** và
> **retention (review request) bỏ trống** — đúng 2 thứ mục tiêu yêu cầu. 4 hạng mục dưới đã chốt làm.

### M9.1 — Bundle demo local (fallback cho CTA chủ đạo)  ✅ DONE (2026-06-04)
> **Chốt:** chỉ bundle **bag** làm sample mặc định (user, 2026-06-04). Ảnh nén WebP ≤700px q80 →
> 104 ảnh / **2.24 MB** (từ 12.28 MB gốc). Bundle: `storage/printcart/demo/bag.json` (97KB) +
> `storage/printcart/demo/img/<oldId>.webp`. Generator dev: `tools/_gen-bag-bundle.php`.
>
> **Seeder local** `includes/class-demo-seeder.php` (SPBWC_Demo_Seeder): đọc bundle, sideload từng
> webp **từ file (KHÔNG mạng — offline-thật)**, map oldId→newAttachmentId (ảnh thiếu→0; `free_design_tools.image`
> là object config nên giữ nguyên), rewrite fields, tạo WC product publish + option-set published + link +
> tag `_spbwc_is_sample` (cả product lẫn attachment) để Undo gom đúng. Idempotent. AJAX `spbwc_demo_seed`/
> `spbwc_demo_undo` (nonce+`manage_woocommerce`). Welcome card đổi: chưa seed→"Add demo product" (1-click),
> đã seed→"View on store" + "Remove demo"; bundle vắng→fallback remote sample cũ.
> Test `tools/_test-demo-seed.php` PASS (seed→product customizable + designer gating + 103 ảnh resolve →
> idempotent → undo sạch product/option/attachments). Đã sửa bug nhỏ trong `fetch_demo_rows` không cần —
> seeder là đường riêng, không đụng remote path.

### M9.1b — Auto-install bag demo lúc activate (DRAFT)  ✅ DONE (2026-06-06)
> **Vấn đề (user, 2026-06-06):** cài plugin xong KHÔNG thấy tự import Visual Builder của bag sample —
> seeder chỉ chạy khi bấm tay card Welcome, trong khi B2B/Quote sample tự cài lúc activate. Data Visual
> Builder (`nbpb_com` + `views` + ảnh vải) ĐÃ có sẵn trong bundle; thiếu mỗi trigger.
>
> **Chốt hướng (user):** auto-install nhưng để **draft** (không lên storefront công khai cho tới khi
> merchant tự publish) — an toàn về mặt "tự tạo nội dung public".
>
> **Cơ chế** (mirror `SPBWC_B2B_Sample`):
> - `SPBWC_Demo_Seeder::arm()` gọi trong activation hook → set `spbwc_demo_seed_pending`.
> - `maybe_seed()` trên `admin_init`: chạy ĐÚNG MỘT LẦN ở admin load đầu tiên khi WooCommerce + actor
>   `manage_woocommerce` sẵn sàng; `seed('draft')`. Guard `spbwc_demo_autoseeded` (one-shot) để merchant
>   Undo không bị re-seed; nếu demo đã tồn tại (bấm card trước) thì chỉ retire pending, không tạo trùng.
> - `seed( $status = 'publish' )`: thêm tham số status — card Welcome thủ công vẫn **publish** như cũ;
>   auto-seed dùng **draft**.
> - `publish()` + AJAX `spbwc_demo_publish` (nonce+cap): flip product draft→publish, trả `view_url`.
> - `seeded_status()`: trả post_status để UI phân nhánh.
> - **Notice** sau auto-seed (`autoseeded_notice`, dismissible): "demo imported as draft" + nút
>   "Preview & publish demo" → Overview.
> - **Welcome card** (`views/overview.php`) status-aware: seeded+draft → "Your demo is ready to go live"
>   + "Publish & view" (`#spbwc-demo-publish`) + "Open in builder" (edit link) + "Remove demo"; seeded+publish
>   → card "live" cũ ("View on store"); chưa seed → "Add demo product"; bundle vắng → fallback remote.
>
> Test: `tools/_test-demo-draft.php` PASS (10/10 — `seeded_status` draft/publish · `publish()` flip +
> view_url · `arm()`/`maybe_seed()` one-shot guard, không tạo trùng). Full seed path không đổi (chỉ thêm
> tham số status mặc định `publish`). Compliance: vẫn 0 mạng, nonce+cap đủ, draft = không public.

### M9.1c — Fix race auto-seed: nghẽn install + bag bị nhân bản  ✅ DONE (2026-06-07)
> **Vấn đề (user, live, 2026-06-07):** cài plugin trên live → WordPress nghẽn/treo một lúc, sau đó
> Storelly hiện **6 product bag** thay vì 1.
>
> **Nguyên nhân:** `maybe_seed()` chạy đồng bộ trên `admin_init`, sideload **~102 ảnh bundle** (sinh
> nhiều thumbnail) → request đầu treo 30–90s ("nghẽn"). Cờ done (`spbwc_demo_autoseeded` + `OPTION_STATE`)
> chỉ ghi **SAU** khi `seed()` xong. Trong lúc trang treo, merchant reload / mở thêm tab admin → mỗi
> full page-load lại fire `admin_init`, đều thấy `pending` còn set và `is_seeded()` vẫn `false` (state chưa
> kịp ghi) → **mỗi request khởi động một `seed()` riêng** → nhân bản bag + nhiều sideload song song làm
> meltdown server. `SPBWC_B2B_Sample::maybe_seed()` dính đúng race này (có thể tạo trùng company).
>
> **Fix (mức gọn-an-toàn, user chốt):**
> - **Atomic DB lock** (`acquire_lock()`/`release_lock()`) bằng `INSERT IGNORE` trên unique index
>   `wp_options.option_name` — primitive WP duy nhất atomic xuyên request. Chỉ MỘT request thắng lock và
>   chạy seed; các request còn lại bail ngay. Lock cũ do run crash để lại tự lành sau `LOCK_TTL` (600s)
>   nhờ compare-and-swap. Áp dụng cho cả `SPBWC_Demo_Seeder` (`spbwc_demo_seed_lock`) và
>   `SPBWC_B2B_Sample` (`spbwc_b2b_seed_lock`).
> - **`is_seeded()` fallback:** nếu mất `OPTION_STATE` (run crash sau khi tạo product), quét product có
>   meta `_spbwc_is_sample=1` → vẫn nhận ra đã seed, không tạo bag thứ 2.
> - `delete_option(pending)` chỉ chạy sau khi request đã thực sự thắng lock + thử seed (không retry import
>   nặng mỗi load nếu bundle hỏng; card Welcome là fallback thủ công).
>
> Test (wp eval trên DB thật): lock first=true / held=false / stale-steal=true / release sạch; seed →
> `is_seeded` true → xóa state → fallback vẫn true → đúng 1 sample product → undo về 0. Compliance: vẫn 0
> mạng; direct DB query có `$wpdb->prepare` + phpcs:ignore; không string mới.

### M9.1d — Fix bundle thiếu ảnh component_icon + view base  ✅ DONE (2026-06-07)
> **Vấn đề (user, 2026-06-07):** trên bag demo đã import, thumbnail các component (SIDE PANELS / MIDDLE
> BLOCK / INSIDE STORAGE…) bị **trống/đen**, và **base image của view "Inside"** hiện sai (một khối đen lạ).
>
> **Nguyên nhân:** cả generator (`tools/_gen-bag-bundle.php::spbwc_gen_ids`) lẫn importer
> (`SPBWC_Demo_Seeder::remap_images`) chỉ thu thập / remap key **`image`**. Nhưng component lưu thumbnail
> dưới key **`component_icon`** và mỗi view lưu base canvas dưới key **`base`** (171/222/253/289/316 +
> 327/328). Những id này KHÔNG được bundle (thiếu webp) và KHÔNG remap khi import → trên site mới trỏ vào
> attachment không tồn tại / sai → ảnh trống/lệch. (`pb_config` chỉ chứa `image` nên sub-swatch vẫn ổn.)
>
> **Fix:**
> - Tập key ảnh dùng chung `SPBWC_Demo_Seeder::IMAGE_KEYS = ['image','component_icon','base']`; `remap_images`
>   remap cả ba. Generator `spbwc_gen_ids` thu thập cùng tập key (giữ contract đồng bộ qua comment chéo).
> - Regen bundle: `image_ids` 103 → 110, thêm 7 webp thật (handles/sid-panels/middle-block/inside-base/
>   strap icon + Front/Top base). `bag.json` diff chỉ mở rộng `image_ids`, structure giữ nguyên.
>
> Test browser (localhost, option mới seed): Visual Builder → 3 view base (`demo-bag-327/328/289`) + cả 5
> component icon render; storefront customizer → swatch tile + sub-swatch đều có ảnh; console 0 lỗi ảnh.

### M9.1e — Setup Wizard: nút "Remove demo data" (user-friendly cleanup)  ✅ DONE (2026-06-07)
> **Vấn đề (user, 2026-06-07):** việc dọn bag demo trùng/hỏng đang phải chạy script `wp-cli` —
> không phải merchant nào cũng làm được. Cần đưa thành UI trong Setup Wizard.
>
> **Chốt hướng (user):** chỉ đặt trong **Setup Wizard**, chỉ **một nút "Remove demo data"** (không
> re-install — demo đã auto-cài lúc activate), **không** auto-detect.
>
> **Cơ chế:**
> - Engine `SPBWC_Demo_Seeder::cleanup_all()` quét MỌI demo bag (meta `_spbwc_is_sample` + product
>   tham chiếu bởi option row `demo_sample_%`) → xoá product + option row + attachment, trả counts;
>   giữ `OPTION_AUTODONE` để KHÔNG auto-reseed sau khi merchant cố ý xoá. `find_all_demo_products()` +
>   `count_demo_products()` dùng chung. `undo()` (nút Remove ở Overview) nay delegate sang cleanup_all
>   nên cũng dọn được bản trùng (trước chỉ xoá 1 bản trong OPTION_STATE).
> - Handler `admin_post_spbwc_demo_cleanup` (cap `manage_woocommerce` + `check_admin_referer`) → cleanup
>   → redirect `?demo_cleaned=N` về landing.
> - UI: card "Demo data" trong `views/setup-wizard/landing.php`, **chỉ hiện khi count>0**, hiển thị số
>   demo đang cài + nút submit (form POST `admin-post.php`, nonce, confirm() inline). Notice xanh sau khi
>   xoá. Dùng component admin-ui sẵn (.spbwc-quick-card/.spbwc-cta-btn) — token-consistent, không inline style.
>
> Test browser (localhost): card render đúng số; bấm → confirm đúng chữ → cleanup_all xoá 1 product + 1
> option + 213 attachment (DB về 0) → redirect + notice "Removed 1 demo product and its data." → card tự
> ẩn. Compliance: cap+nonce đủ, escape đủ (esc_url/attr/js/html), `$_GET` đọc hiển thị có ph: ignore +
> cast int, text domain literal + `_n()`, 0 mạng.
>
> Bonus: `tools/_clean-demo-bags.php` (wp-cli dry-run/apply/reseed) vẫn còn cho ops trên server không vào admin.

### (cũ) M9.1 — phân tích ban đầu
- **Gap:** CTA "Fastest / See it live with demo products" (`views/overview.php:141`) dẫn tới sample
  import, nhưng `fetch_demo_rows()` chỉ `wp_remote_get( DEMO_DATA_URL )`
  (`includes/class-global-import-controller.php:9, ~1049`). KHÔNG có bundled dataset; offline /
  backend down / 404 → timeout 20s → mảng rỗng câm → "No products found". Aha moment chết ngay
  trên đường vàng. (Điểm cộng đã có: import xong `status='publish'`, customize được ngay; chỉ gọi
  mạng khi user bấm = consent ngầm, không phone-home.)
- **Quyết định (Phương án B trong §3.2):** bundle bộ demo nhỏ trong plugin + seeder local KHÔNG mạng.
- **Việc cần làm:**
  - Tạo `storage/printcart/demo_datas.json` (2–3 demo product, schema khớp `fetch_demo_rows()` đang
    kỳ vọng) + ảnh nhỏ kèm (ưu tiên dùng ảnh có sẵn / SVG nhẹ để không phình dung lượng).
  - `fetch_demo_rows()`: thử remote trước (giữ nguyên), **fail thì đọc file bundled** thay vì trả `[]`.
    Hoặc đảo: ưu tiên bundled để nhanh + offline-safe, remote chỉ để "refresh" — chốt khi code.
  - Gắn meta `_spbwc_is_sample = 1` cho mọi product import từ đường demo (kèm `_spbwc_external_id`
    hiện có) để Undo gom đúng, không lẫn import khác (gap §3.2 còn treo).
- **Acceptance:** store fresh OFFLINE bấm "Import demo products" → có ≥2 product publish, customize
  được trên storefront; Undo chỉ xoá đúng demo.

### M9.2 — Review request notice (retention lever #1)  ✅ DONE (2026-06-04)
> Ship: `includes/class-review-notice.php` (SPBWC_Review_Notice), require trong main plugin. Test PASS.
- **Gap:** `spbwc_activated_at` lưu (`includes/class-onboarding.php:100`) + getter `get_activated_at()`
  (`:207`) nhưng KHÔNG nơi nào dùng. Không có notice xin đánh giá wp.org, không retention nudge.
  Spec liệt kê gap #5 nhưng không milestone nào giải quyết.
- **Quyết định:** clone pattern `SPBWC_I18n_Notice` (option-key + nonce dismiss + sticky + tokens
  `spbwc-block`). Hiện notice xin review khi: `now - activated_at ≥ 14 ngày` **VÀ** đã chạm "thành tựu"
  (≥1 option-set publish HOẶC ≥1 order chứa sản phẩm Storelly) — tránh xin review lúc chưa thấy giá trị.
- **UX:** 3 nút — "Sure, I'll review" (mở `wordpress.org/support/plugin/storelly-product-builder-for-woocommerce/reviews/`,
  set done vĩnh viễn) · "Maybe later" (snooze ~30 ngày) · "Already did / No thanks" (done vĩnh viễn).
  Chỉ hiện trên màn admin Storelly (như upsell), không nag toàn wp-admin.
- **Acceptance:** notice không hiện trước 14 ngày / chưa có thành tựu; dismiss đúng từng nhánh; không
  hiện lại sau khi done.

### M9.3 — Sửa dismiss Welcome + quote badge default  ✅ DONE (2026-06-04)
> Ship: SPBWC_Onboarding::maybe_restore_welcome/get_restore_url/is_dismissed + banner "Show the setup
> guide again" trong overview.php; render_quote_badge coi badge mặc định ON khi key vắng (esc_url đã
> sẵn ở render). Test PASS.
- **Dismiss vĩnh viễn:** `SPBWC_Onboarding` (`includes/class-onboarding.php:66-81`) set `dismissed=true`
  sticky → lỡ bấm "Skip setup" là mất hướng dẫn dù onboarding chưa xong, không gọi lại được.
  → Thêm link nhỏ "Show setup guide" trên Overview khi `dismissed && ! is_onboarding_complete()`
  (xoá cờ dismissed, nonce-protected). Khi onboarding complete thì im như cũ.
- **Quote badge default:** `includes/class-request-quote.php:163` gate `enable_quote_badge === 'yes'`
  nhưng không thấy code set default ON (spec §3.7 yêu cầu published mặc định). → set default `'yes'`
  khi khởi tạo settings (migration an toàn: chỉ set nếu key chưa tồn tại, không đè merchant đã tắt).
- **Badge URL escape:** `quote_badge_url` (`:191`) nhận URL tùy ý → bọc `esc_url()` khi render (đang
  thiếu) để chặn URL bẩn; KHÔNG cần whitelist (merchant tự nhập, chỉ là output escape).
- **Acceptance:** Skip rồi vẫn gọi lại được guide; badge bật mặc định trên store mới; URL output escape.

### M9.4 — Hardening woo-prepare (store lớn + race)  ✅ DONE (2026-04-06)
> Ship: INLINE_BUDGET 8s + run_inline_budget() time-box, ajax_status drive batch khi không có AS
> (resume qua poll), LOCK_TRANSIENT re-entrancy lock quanh run_batch (tách run_batch_locked),
> add_option atomic chặn double-start. Test PASS (stale guard + lock back-off).
- **Timeout fallback sync:** `includes/setup-wizard/class-woo-prepare.php` batch 15 qua Action Scheduler
  OK, nhưng fallback sync loop (`guard < 1000` × 15 = 15.000 product đồng bộ, ~:145-149) → timeout
  trên shared hosting khi AS không khả dụng. → chunk theo thời gian (vd dừng batch khi vượt ngân sách
  ~10s) hoặc giảm trần guard + để lần render sau tiếp tục; log cảnh báo khi rơi vào sync fallback.
- **Race tạo trùng:** không có UNIQUE `(template_slug, product_id)`; 2 AJAX đồng thời có thể chèn trùng
  (option-state guard giảm nhưng không tuyệt đối). → guard nhập bằng lock transient ngắn quanh
  `run_batch()` / hoặc kiểm tra `_spbwc_option_id` ngay trước insert trong cùng transaction.
- **Acceptance:** store ~500+ variable product prepare không timeout (chia nhiều lượt có progress);
  chạy scan/AJAX trùng không tạo 2 option-set cho cùng product.

### Ngoài phạm vi đợt này (ghi nhận, chưa làm)
- store_uuid không bền khi đổi domain/admin email (`md5(host+path+email)`, `:196`) — Q3 §6 còn mở,
  phụ thuộc backend Storelly re-link theo store URL. Để sau.
