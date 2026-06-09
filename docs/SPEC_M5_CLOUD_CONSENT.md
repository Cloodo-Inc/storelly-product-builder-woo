# SPEC — M5: Cloud 1-click Consent (Storelly account + PDF + store identity)

> Part of the auto-first onboarding (docs/SPEC_ONBOARDING_ACTIVATION.md §3.4, yc #4).
>
> **Nguyên tắc sống còn (wordpress.org):** TUYỆT ĐỐI không gọi mạng / không gửi dữ liệu
> merchant ra `app.storelly.com` cho tới khi merchant **chủ động bấm 1 nút consent**. Ô consent
> KHÔNG được tick sẵn. Vi phạm = plugin bị gỡ kho (CLAUDE.md rule #6).
>
> **Trạng thái: SHIPPED (client).** 1-click consent + manual key + store-UUID re-link đã code
> (`SPBWC_Cloud_Connect`, `SPBWC_Onboarding`, payload `store_uuid`). Cập nhật 1.6.4:
> - Activation KHÔNG còn provision WC key (bỏ `register_activation_hook` → tránh fatal; key sinh
>   trong `connect()`).
> - **Site tự gửi email tài khoản** cho admin sau register (password sinh client-side nên server
>   có thể không biết — xem §2.2).
> - `uninstall.php` xoá secrets + flags, GIỮ `spbwc_store_uuid` (M5.5 done).
> - CTA "Connect Storelly" hiện trên Custom Order ▸ Design khi cloud PDF chưa bật.
> - Settings save default `enable_cloud2print_api='no'`.
>
> CÒN backend-phụ thuộc: register idempotent-by-uuid (§3.2 Q1) và **payment plan** trên License
> menu (chưa có — chỉ có activate_key; cần Storelly payment API).

---

## 1. Tài sản đã có (tái dùng, KHÔNG viết lại)

| Thành phần | Vị trí | Vai trò |
|-----------|--------|---------|
| `SPBWC_Storelly_Product_Builder_API::spbwc_create_user_storelly()` | `includes/class-productbuilder-api.php:38` | POST `/api/v1/register` → nhận `username` + `unauth_token`, lưu `spbwc_connect_api_keys`. Idempotent. |
| `…::spbwc_generate_key()` | `:102` | Tạo WC REST consumer key/secret (để Storelly pull order/product), lưu `spbwc_connect_api_keys`. |
| `SPBWC_Storelly_HTTP` | `includes/class-http.php` | `spbwc_post_data_without_auth()`, `spbwc_post_data()` (header `X-STORLY: unauth_token`). |
| Opt-in flags | `spbwc_pb_settings['enable_api_sync']`, `['enable_cloud2print_api']` ('yes'/'no') | Gate order-sync + cloud PDF. |
| Store UUID | `SPBWC_Onboarding::get_store_uuid()` (M1) | Định danh store ổn định, **local-only** tới giờ. |
| Connected check | `SPBWC_Onboarding::is_cloud_connected()` (M1) | true khi `enable_api_sync === 'yes'`. |

> Lưu ý: phone-home tự động ĐÃ bị gỡ trước đó (comment ở `:12`). M5 chỉ thêm **một** điểm gọi
> hợp lệ: sau cú bấm consent.

---

## 2. Trải nghiệm (UX)

### 2.1 Card consent trên Welcome (Overview)
- Render trong vùng Welcome (M2) khi `! SPBWC_Onboarding::is_cloud_connected()`.
- Nội dung: tiêu đề "Bật Cloud — PDF in ấn chất lượng cao + lưu hồ sơ store"; 2–3 gạch đầu dòng
  lợi ích; **1 nút** "Bật Cloud" (primary). KHÔNG checkbox tick sẵn.
- Link nhỏ: "What gets shared?" → mở chi tiết (email admin, store URL, store ID) + link Privacy/ToS.
- Sau khi connected: card chuyển thành trạng thái "Cloud connected ✓" + nút "Disconnect".

### 2.2 Luồng khi bấm (tất cả chạy ngầm, chỉ hiện thông báo)
```
Bấm "Bật Cloud"
  → AJAX spbwc_cloud_connect (nonce + manage_options)
  → [ngầm] spbwc_generate_key()         // WC REST keys
  → [ngầm] spbwc_create_user_storelly() // POST /api/v1/register (kèm store_uuid)
  → nếu success: set enable_api_sync='yes' + enable_cloud2print_api='yes'
                 lưu consent log (user id + time + version)
  → site gửi email tài khoản tới admin_email (username + password sinh client-side +
    link app.storelly.com/login); filter spbwc_send_account_email để tắt/đổi
  → reload: card → "Cloud connected ✓"
  → nếu fail: hiện lỗi nhẹ + nút thử lại; KHÔNG bật flag, KHÔNG chặn onboarding
```

---

## 3. Dữ liệu gửi đi & Store identity (reinstall re-link)

### 3.1 Payload register (mở rộng payload hiện có)
Giữ nguyên payload `spbwc_create_user_storelly()` + **thêm**:
- `store_uuid` = `SPBWC_Onboarding::get_store_uuid()`
- (đã có) `email` = admin email, `woocommerce_app_url` = `home_url()`, WC keys, locale, timezone.

### 3.2 Reinstall re-link (yc #4) — **CHỐT: Hướng A (UUID tất định) + mapping tay fallback**

Hiện `/api/v1/register` tạo user mới mỗi lần (username có `time()`) → reinstall = store trùng.
Khắc phục với bề mặt backend tối thiểu:

**Định danh client — store_uuid TẤT ĐỊNH, giữ qua uninstall:**
- Quy tắc `get_store_uuid()`: **có giá trị đã lưu thì dùng; thiếu thì derive** =
  `wp_hash( normalize(home_url) . '|' . strtolower(admin_email) )` (định dạng dạng uuid). KHÔNG
  random nữa (đổi so với M1 — xem §5 M5.0).
  - `normalize(url)` = bỏ scheme + `rtrim('/')` + lowercase host (vd `https://Shop.com/` →
    `shop.com`). (Cảnh báo: đổi domain sẽ đổi giá trị derive — nhưng giá trị ĐÃ LƯU vẫn được giữ,
    nên migration với DB còn nguyên vẫn khớp.)
- **uninstall.php GIỮ LẠI `spbwc_store_uuid`** (đã chốt Q1) → reinstall trên cùng DB nhận lại ngay.
- Nếu DB bị wipe sạch → `get_store_uuid()` derive lại = **đúng UUID cũ** (cùng url+email) → khớp,
  KHÔNG cần lookup.

**Backend cần (DUY NHẤT, nhỏ + rõ): register idempotent theo `store_uuid`.**
- Client gửi `store_uuid` trong payload register.
- Server: nếu đã có store cho `store_uuid` này → trả store_id/unauth_token CŨ (không tạo mới).
  Nếu chưa → tạo mới gắn với uuid đó.
- → code bổ sung phía backend Storelly nếu thiếu (user OK build).

**Ma trận tình huống:**
| Tình huống | Kết quả |
|-----------|---------|
| Reinstall, DB còn (UUID giữ) | Auto khớp ✓ |
| Wipe DB, cùng url+email | Derive ra UUID cũ → auto khớp ✓ (không lookup) |
| Đổi domain, DB còn | UUID đã lưu vẫn cũ → khớp ✓ |
| Wipe DB **+** đổi domain (hiếm) | UUID đổi → rơi xuống **mapping tay** ↓ |

**Fallback mapping tay (ca hiếm):** card hiện "Đã có Store ID cho site này?" → ô nhập Store ID +
nút "Link". (Tuỳ chọn nâng cao: endpoint lookup theo url/email trả candidate để auto-suggest —
làm sau nếu cần.)

### 3.3 Cái gì KHÔNG gửi
- Không gửi dữ liệu khách hàng, không gửi gì trước consent. Trước consent: 0 request mạng.

---

## 4. Compliance wordpress.org (bắt buộc)

- **No phone-home pre-consent** — guard cứng: đường dẫn duy nhất tới register là AJAX consent.
- **AJAX**: `check_ajax_referer` (nonce) + `current_user_can('manage_options')`.
- **Consent không tick sẵn**; nút là hành động chủ động.
- **readme.txt mục "External services"** phải khai báo (bắt buộc để pass review):
  - Service: Storelly Dashboard API — `https://app.storelly.com/api/v1/register` (+ các endpoint
    sync order/PDF đã dùng).
  - Dữ liệu gửi: admin email, store URL, store UUID, WC REST keys, locale, timezone — **chỉ sau
    khi merchant bấm Bật Cloud**.
  - Mục đích: tạo hồ sơ store, kích hoạt cloud PDF, đồng bộ order (khi bật).
  - Link: Privacy Policy + Terms của Storelly.
- **Consent log (GDPR):** lưu `spbwc_cloud_consent` = { user_id, time, plugin_version, ip? (cân
  nhắc KHÔNG lưu IP) } để chứng minh consent.
- **Disconnect/opt-out:** nút Disconnect → set `enable_api_sync='no'` + `enable_cloud2print_api='no'`;
  (tuỳ chọn) gọi server revoke. Người dùng phải dừng được việc chia sẻ dữ liệu bất cứ lúc nào.
- Mọi string mới → POT (skill `wp-plugin-i18n`). Chạy skill `wp-org-plugin-compliance` trước khi
  đụng readme/submit.

---

## 5. Triển khai (khi được duyệt)

```
M5.0  Đổi SPBWC_Onboarding::get_store_uuid()/on_activate: seed UUID TẤT ĐỊNH = wp_hash(normalize
      (home_url)+'|'+lower(admin_email)) thay cho wp_generate_uuid4 ngẫu nhiên. Giá trị đã lưu vẫn
      ưu tiên (install cũ không đổi). (Đụng file M1 đã commit — forward change.)
M5.1  Class SPBWC_Cloud_Connect (includes/class-cloud-connect.php):
      - AJAX spbwc_cloud_connect / spbwc_cloud_disconnect / spbwc_cloud_link_manual (nonce + cap).
      - connect(): generate_key() → create_user_storelly()(+store_uuid) → set CẢ enable_api_sync
        + enable_cloud2print_api = 'yes' → consent log.
      - disconnect(): clear cả hai flag (+optional server revoke).
      - link_manual($store_id): gắn store_id nhập tay (ca §3.2 fallback).
      - is_connected() (đồng bộ SPBWC_Onboarding::is_cloud_connected).
M5.2  Mở rộng payload register thêm store_uuid; server idempotent-by-uuid (cần backend §3.2).
M5.3  Card consent + connected/disconnect + ô "link Store ID tay" trên Welcome (views/overview.php) + JS.
M5.4  readme.txt: cập nhật "External services" + bump version (header + Stable tag khớp).
M5.5  uninstall.php: GIỮ LẠI spbwc_store_uuid (Q1); XOÁ secrets (spbwc_connect_api_keys) + flags opt-in.
M5.6  POT regen + plugin check 0 error.
```

### Acceptance / test
- [ ] Trước consent: 0 outbound request (kiểm bằng log/mạng).
- [ ] Bấm consent → register thành công → `enable_api_sync`/`enable_cloud2print_api` = 'yes';
      `spbwc_connect_api_keys` có `username`+`unauth_token`.
- [ ] Card đổi sang "connected"; `is_cloud_connected()` = true; checklist M2 tick "Cloud".
- [ ] Disconnect → flags = 'no'; card về trạng thái mời bật lại.
- [ ] Register fail (mock lỗi mạng) → không bật flag, hiện lỗi, onboarding vẫn dùng được.
- [ ] Reinstall (mô phỏng) → cùng store URL nhận lại store_id cũ (phụ thuộc backend §3.2).

---

## 6. Quyết định & câu hỏi còn lại

**Đã chốt:**
- **Q1 uninstall:** GIỮ LẠI `spbwc_store_uuid` (xoá secrets + flags). → §5 M5.5.
- **Q2 store identity:** Hướng A (UUID tất định hash(url+email), giữ qua uninstall, server
  idempotent-by-uuid) + mapping tay cho ca wipe+đổi domain. → §3.2.
- **Q5 consent scope:** bật CẢ `enable_api_sync` (order-sync) + `enable_cloud2print_api` (PDF).

**Còn cần xác nhận (không chặn code phần client, chỉ chặn re-link & email):**
1. **Backend idempotent-by-uuid** ở `/api/v1/register`: nhận `store_uuid`, cùng uuid → trả store
   cũ (không tạo trùng). → cần team backend Storelly build/confirm (user OK bổ sung endpoint).
2. ~~Email welcome bởi Storelly~~ → **đã chuyển sang site tự gửi** (§2.2). Backend không cần lo.
3. **`activated_at` legacy = 0**: set `activated_at = time()` lần đầu nếu đang 0 (gài kèm M5.0).

---

## 7. Backlog — CHƯA LÀM (chờ API backend Storelly)

> Quyết định 1.6.4: chỉ ghi spec, **chưa code**. Cả hai phụ thuộc hợp đồng API phía Storelly.

### M5.7 — Store chooser khi re-install (mở rộng §3.2 fallback)
- **Hiện trạng:** client gửi `store_uuid` tất định + ô nhập `store_id` tay (`ajax_link_manual`).
  Đủ cho ca auto-match (DB còn / cùng url+email) và ca paste tay.
- **Thiếu:** UI cho admin **chọn** store khi có nhiều store khớp email/url (thay vì nhớ & paste id).
- **Cần backend:** endpoint lookup, vd `GET /api/v1/stores/lookup?store_uuid=&email=&app_url=` →
  trả `[{store_id, name, app_url, last_seen}]` (auth bằng unauth_token hoặc signed nonce).
- **Client (khi có endpoint):** sau khi register/derive uuid, nếu server trả `multiple_candidates`,
  card consent hiện danh sách radio store → admin pick → gửi `store_id` đã chọn qua
  `spbwc_cloud_link_manual` (đã có). Không cần phone-home thêm trước consent.

### M5.8 — Thanh toán plan in-wp-admin qua payment API Storelly
- **Hiện trạng:** `views/license.php` nút "Upgrade to X" chỉ mở `app.storelly.com/subscription`
  ở tab mới; `SPBWC_License_Manager::activate_key()` chỉ kích hoạt key đã có.
- **Mục tiêu:** chọn plan + thanh toán **ngay trên License menu**, sau đó tự `activate_key`/`sync`.
- **Cần backend (hợp đồng API + tuân thủ PCI — KHÔNG nhận thẻ trực tiếp trên site):**
  - `POST /api/v1/billing/checkout-session` `{package_id, store_uuid, return_url}` → trả
    `{checkout_url}` (hosted checkout Storelly/Stripe) **hoặc** `{client_secret}` (nếu dùng
    Stripe Elements nhúng — cân nhắc PCI SAQ-A).
  - Webhook/endpoint xác nhận: `GET /api/v1/license/status` sau khi thanh toán xong (đã có) →
    cập nhật `spbwc_license_data`.
  - Khuyến nghị: **hosted checkout redirect** (đơn giản + PCI-safe) thay vì nhúng form thẻ.
- **Client (khi có endpoint):** nút "Upgrade" → AJAX tạo checkout-session (nonce + cap) →
  redirect tới `checkout_url`; `return_url` về License page với `?spbwc_paid=1` → gọi
  `sync_from_api()` + hiện trạng thái mới. Dùng `payment-integration` agent khi build.
- **Lưu ý compliance:** nếu nhúng form thẻ trên wp-admin phải khai báo external service + script
  PCI; ưu tiên redirect hosted để tránh.

---

## M5.9 — Storelly Account: component đăng ký/kết nối thống nhất (Wave 2, item 4)

**Status:** IMPLEMENTED (2026-06-09) · part of `SPEC_ADMIN_UX_POLISH_W2.md`

> **Đã code:** Component "Storelly Account" hợp nhất đặt đầu tab Settings ▸ Integration
> (`views/menu-settings.php`), 3 trạng thái: (chưa kết nối) nút **Enable Cloud** 1-click + dòng "Already
> have an account? Link with Store ID" mở ô Store ID + Save; (đã kết nối) hiện username
> (`spbwc_connect_api_keys['username']`) + store URL + scope cloud-PDF/order-sync + nút **Disconnect**.
> Tái dùng `SPBWC_Cloud_Connect` AJAX (`spbwc_cloud_connect` / `_disconnect` / `_link_manual`) qua nonce
> `spbwc_cloud_connect` (nút `type="button"` + JS trong IIFE settings, không nested form). Welcome cloud-card
> (`overview.php`) rút gọn: giữ 1-click Enable, BỎ ô link tay (về Settings) + link "Manage in Settings". Nêu
> privacy + link, KHÔNG phone-home trước khi bấm. API Keys card cũ giữ làm fallback nâng cao (hint trỏ lên
> component). CẦN verify browser: connect/disconnect/link chạy, hiện username sau connect, tab Integration
> không vỡ.

### Vấn đề
Thông tin "tài khoản Storelly" rải 3 chỗ rời rạc, UX mơ hồ:
- Welcome cloud-card (`views/overview.php:362+`) — 1-click "Enable Cloud" + link "Already have a Store ID".
- Settings›Integration "API Keys" card (`views/menu-settings.php:716+`) — nhập SID/Secret thủ công.
- License page — trạng thái plan.
Merchant không rõ "tạo tài khoản mới" vs "link tài khoản có sẵn", email đăng nhập từ đâu.

### Yêu cầu
1. **Một component "Storelly Account"** (đặt làm nguồn chính trong Settings — tab riêng hoặc đầu tab
   Integration), 3 trạng thái rõ:
   - **Chưa kết nối**: nút "Enable Cloud" (1-click tạo tài khoản + connect qua `SPBWC_Cloud_Connect::connect()`),
     dòng phụ "Already have an account? Link with Store ID" mở ô nhập (`link_manual`).
   - **Đã kết nối**: hiển thị email/username (`spbwc_connect_api_keys['username']`), store URL, scope đang
     bật (cloud PDF / order sync), nút **Disconnect** (`ajax_disconnect`).
   - **Link thủ công**: ô Store ID + Save (`ajax_link_manual`).
2. **Welcome cloud-card** rút gọn còn entry 1-click trỏ tới component này (không lặp toàn bộ UI).
3. Nêu rõ privacy (dữ liệu gửi khi connect: admin email + store URL + store UUID) + link Privacy.
   **Không phone-home** trước khi bấm.
4. Tái dùng `SPBWC_Cloud_Connect` (đã có connect/disconnect/link_manual). Không refactor lớn (khớp A6
   của `SPEC_FREEMIUM_V1_1`).

### Acceptance
- Một nơi duy nhất quản lý account; Welcome chỉ là entry. Connect/Disconnect/Link đều chạy; hiện email
  login sau connect. Token-first, RTL ok, không phone-home khi chưa consent.

### Files
`includes/class-cloud-connect.php`, `views/menu-settings.php` (Integration/Account tab), `views/overview.php`.

---

## M5.10 — Save to Cloud / Reuse option-set (Wave 2, item 13)

**Status:** DRAFT (2026-06-09) · đặc tả đầy đủ tại `SPEC_ADMIN_UX_POLISH_W2.md` §A13

Tóm tắt: gỡ JSON Import/Export option-set của user, thay bằng **Save to Cloud** + **Reuse from Cloud**
gate `SPBWC_Cloud_Connect::is_connected()`. Thêm AJAX `spbwc_cloud_option_save/list/pull` (nonce + cap),
mapping cloud-id ↔ local option-id, khai báo external service trong readme. Tôn trọng entitlement `caps[]`
(`SPEC_FREEMIUM_V1_1`). Xem §A13 cho chi tiết file + acceptance.
