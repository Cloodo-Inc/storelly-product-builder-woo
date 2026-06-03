# SPEC — Auto-First Onboarding & Activation

> **Triết lý:** merchant cài xong là thấy giá trị Storelly NGAY, không phải động tay kỹ thuật.
> Mọi thứ chạy ngầm; merchant chỉ thấy thông báo + tiến trình. Đúng **2 cú bấm consent** duy
> nhất (bật Cloud, Publish-all) để giữ plugin hợp lệ trên wordpress.org và không phá storefront.
>
> Mục tiêu số: ↑ success rate · ↑ activation (chạm aha sớm) · ↑ retention (review + upgrade).
> Plugin freemium trên wordpress.org, free giới hạn 5 sản phẩm.
>
> **Trạng thái: DRAFT — chờ review, chưa code.**
>
> **Quyết định đã chốt với user:**
> - Welcome render **trên Overview**, tự kích hoạt (không submenu riêng).
> - Ưu tiên **Import sample trước** (time-to-value ngắn nhất).
> - **#4 Cloud (Storelly account + PDF): 1-click consent** — KHÔNG auto khi chưa bấm.
> - **#8/#5 Migrate Woo → publish live: chuẩn bị ngầm + 1 nút "Publish all"** + Undo.

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
M6  Request Quote auto-publish + site-wide CTA badge                                (yc #11)
M7  Freemium contextual upsell tại limit                                           (yc #10)
M8  Compliance: plugin check 0 error + POT regen + readme + version bump
```

M1–M2 an toàn, đã xong. M4–M5 đụng store thật + mạng → làm cẩn thận, test kỹ.
yc #9 (My Account endpoint) gài kèm M1 (đăng ký endpoint lúc activate).
