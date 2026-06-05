# Email System — As-Built Audit & Consolidation Spec

> Status: **As-built audit (Part A) + consolidation proposal (Part C). E1 + E2 ĐÃ BUILD (2026-06-04).**
> Feature area: Toàn bộ email do plugin phát ra (Quote, B2B, Marketplace/Launcher, Custom Order)
> Owner modules: `includes/quote/`, `includes/b2b/`, `includes/launcher/`, `includes/class-request-quote.php`
> Last updated: 2026-06-04

> **Tiến độ build:** E1 ✅ · E2 ✅ · E3 (designer-message → WC_Email) ✅ · E5 (rename
> `nbdl_*` → `spbwc_email_*` + migration) ✅ · E6a (Custom Order: received + proof) ✅ ·
> E6b (email log table) ✅ · E6c (Send test) ✅ · Menu **Storelly › Emails** dashboard
> (gom email + toggle/deep-link WC + log) + design token ✅. Còn: lớp Premium/watermark
> (đang cân nhắc). Chi tiết §14.

Spec này lập bản đồ **toàn bộ** email mà Storelly phát ra hôm nay (Part A — mọi finding có
`file:line`), chỉ ra **lỗ hổng & nợ kỹ thuật** (Part B), rồi đề xuất một **kiến trúc email hợp
nhất** với milestone rõ ràng (Part C). Phần A là sự thật hiện trạng; phần C được đánh dấu rõ là
CHƯA build.

Nguyên tắc nền: plugin xuất bản trên wordpress.org. Mọi email phải (1) i18n bằng text domain
`storelly-product-builder-for-woocommerce`, (2) escape/sanitize input-output, (3) KHÔNG phone-home
khi chưa opt-in. Xem [[project_freemium_local_vs_cloud]] — gửi email là local/free; chỉ đính kèm
PDF qua cloud (Cloud2Print) mới chạm dịch vụ trả phí.

---

## 1. Mục tiêu của hệ thống email

Mọi sự kiện nghiệp vụ phát sinh trong plugin (khách gửi quote, admin báo giá, quote sắp hết hạn,
khách accept/decline, đội B2B cần duyệt đơn, designer được kích hoạt/rút tiền…) cần một **thông báo
email** tới đúng người, đúng nội dung, có thương hiệu WooCommerce, đa ngôn ngữ, và (tuỳ chọn) đính
kèm PDF. Mục tiêu của spec là chuẩn hoá việc đó về **một đường ống duy nhất** thay vì hai phong cách
song song như hiện nay.

---

## 2. Bức tranh tổng thể — hai phong cách đang tồn tại

Hôm nay plugin gửi email theo **hai cách khác nhau**, đây là vấn đề trung tâm spec này giải quyết:

| Phong cách | Cơ chế | Dùng ở đâu | Ưu / Nhược |
|---|---|---|---|
| **A. WC_Email chuẩn** | Class kế thừa `WC_Email`, đăng ký qua `woocommerce_email_classes` + `woocommerce_email_actions`, render bằng `email-header.php`/`email-footer.php`, đính kèm qua `get_attachments()` | Quote (7 email), Launcher/Marketplace (5 email) | Có branding, có HTML+plain, có trang Settings của WooCommerce, hỗ trợ attachment. **Chuẩn mong muốn.** |
| **B. `wp_mail()` thô** | Gọi `wp_mail()` trực tiếp, body plain-text tự ghép | B2B (4 chỗ), Designer REST (1 chỗ), Quote legacy (2 hàm dead) | Không branding, không HTML, không trang settings, recipient/subject hardcode. **Nợ kỹ thuật.** |

→ Định hướng (Part C): **mọi email phải đi qua phong cách A.** Phong cách B chỉ chấp nhận như fallback
tạm thời và phải được migrate.

---

## Part A — Hiện trạng (as-built, có file:line)

### 3. WC_Email classes (phong cách A)

#### 3.1 Nhóm Quote — 7 class
File: `includes/quote/class-quote-email-types.php`. Đăng ký tại `includes/quote/class-quote-emails.php:21`
(filter `woocommerce_email_classes`) + `:22` (filter `woocommerce_email_actions`).

| Class | Email ID | Action trigger | Người nhận | Mục đích | PDF? |
|---|---|---|---|---|---|
| `SPBWC_Email_Quote_New` | `spbwc_quote_new` | `spbwc_quote_new_notification` | Admin | Khách vừa gửi yêu cầu báo giá | – |
| `SPBWC_Email_Quote_Ack` | `spbwc_quote_ack` | `spbwc_quote_ack_notification` | Khách | Xác nhận đã nhận yêu cầu | – |
| `SPBWC_Email_Quote_Sent` | `spbwc_quote_sent` | `spbwc_quote_sent_notification` | Khách | Admin đã báo giá xong | ✅ |
| `SPBWC_Email_Quote_Reminder` | `spbwc_quote_reminder` | `spbwc_quote_reminder_notification` | Khách | Nhắc quote sắp hết hạn (7/3/1 ngày) | ✅ |
| `SPBWC_Email_Quote_Accepted` | `spbwc_quote_accepted` | `spbwc_quote_accepted_notification` | Admin | Khách đã chấp nhận | – |
| `SPBWC_Email_Quote_Declined` | `spbwc_quote_declined` | `spbwc_quote_declined_notification` | Admin | Khách đã từ chối | – |
| `SPBWC_Email_Quote_Converted` | `spbwc_quote_converted` | `spbwc_quote_converted_notification` | Khách | Đã tạo order, sẵn sàng thanh toán | ✅ |

- Render: dùng header/footer của WooCommerce core (`wc_get_template('emails/email-header.php')`),
  KHÔNG có template riêng trong plugin → body sinh trong `build_body()` của từng class.
- PDF: lấy qua `SPBWC_Quote_PDF::email_attachments()`, gate bằng `class_exists('SPBWC_Quote_PDF')`
  (`class-quote-email-types.php:115`). Đây là điểm chạm cloud duy nhất của email — xem §8.
- i18n: subject/heading/body đều `__()`/`esc_html__()` đúng text domain.

#### 3.2 Nhóm Launcher/Marketplace — 5 class
Đăng ký tại `includes/launcher/class.launcher.php:48-49`, danh sách `:403-418`.
Có template riêng trong `templates/launcher/emails/` (HTML + `plain/` variant).

| Class | Email ID | Action trigger | Người nhận | Mục đích |
|---|---|---|---|---|
| `SPBWC_Email_Withdraw_Request` | `nbdl_email__withdraw_request` | `spbwc_marketplace_after_withdraw_request` | Admin | Designer yêu cầu rút tiền |
| `SPBWC_Email_Withdraw_Approved` | `nbdl_email_withdraw_approved` | `spbwc_marketplace_withdraw_request_approved` | Designer | Rút tiền được duyệt |
| `SPBWC_Email_Withdraw_Cancelled` | `nbdl_email_withdraw_cancelled` | `spbwc_marketplace_withdraw_request_cancelled` | Designer | Rút tiền bị huỷ |
| `SPBWC_Email_Designer_Enabled` | `nbdl_email_designer_enable` | `spbwc_marketplace_designer_enabled` | Designer | Tài khoản được kích hoạt |
| `SPBWC_Email_Designer_Disabled` | `nbdl_email_designer_disable` | `spbwc_marketplace_designer_disabled` | Designer | Tài khoản bị vô hiệu hoá |

- ⚠️ Prefix lẫn: email ID dùng `nbdl_` (di sản fork) trong khi phần còn lại của plugin là `spbwc_`.
  CLAUDE.md cấm prefix lẫn — xem §10 (Part B) để xử lý. **Lưu ý:** đổi email ID là breaking change
  với settings đã lưu (`woocommerce_emails_*`), cần migrate có chủ đích, không đổi cẩu thả.
- Các class này có `init_form_fields()` đầy đủ: `enabled`, `recipient`, `subject`, `heading`,
  `email_type`, và (designer_enabled) `email_from_name`/`email_from_email`/`enable_bcc`
  (`includes/launcher/emails/designer_enabled.php:87-161`, `withdraw_request.php:91-136`).

Templates: `templates/launcher/emails/{withdraw_request,withdraw_approved,withdraw_cancelled,designer_enabled,designer_disabled}.php`
+ bản `plain/` tương ứng (5 HTML + 5 plain = 10 file), dùng `do_action('woocommerce_email_header'/'_footer')`.

### 4. `wp_mail()` thô (phong cách B)

> **CẬP NHẬT (E1+E2 đã build):** 4 email B2B (#1-4) đã migrate sang WC_Email — xem §3.3. Hai
> hàm dead-code (#6,#7) đã xoá. Chỉ còn designer-message (#5) đi `wp_mail()` trực tiếp (đã
> sanitize input ở E1), chờ E3 migrate nốt.

| # | Vị trí (trước E2) | Người nhận | Trạng thái sau E1/E2 |
|---|---|---|---|
| 1 | `class-b2b-team.php` (`email_invite()`) | Email được mời | ✅ → `do_action('spbwc_b2b_invite_notification')` → `SPBWC_Email_B2B_Invite` |
| 2 | `class-b2b-procurement.php` (`notify_approvers()`) | Approver(s) | ✅ → `spbwc_b2b_approval_needed_notification` → `SPBWC_Email_B2B_Approval_Needed` |
| 3 | `class-b2b-procurement.php` (`notify_requester()`) | Người đặt | ✅ → `spbwc_b2b_approval_outcome_notification` → `SPBWC_Email_B2B_Approval_Outcome` |
| 4 | `class-b2b-account.php` (`notify_owner()`) | Chủ company mới | ✅ → `spbwc_b2b_company_ready_notification` → `SPBWC_Email_B2B_Company_Ready` |
| 5 | `includes/launcher/api/designer.php` (`send_email_to_designer()`) | Designer | ⚠️ vẫn `wp_mail()` (đã sanitize E1); migrate ở E3 |
| 6 | `class-request-quote.php` (`send_quote_notification_email()`) | Admin | ✅ ĐÃ XOÁ (dead code) |
| 7 | `class-request-quote.php` (`send_quote_customer_email()`) | Khách | ✅ ĐÃ XOÁ (dead code) |

#### 3.3 Nhóm B2B — 4 class (E2, mới)
File: `includes/b2b/class-b2b-email-types.php` (base `SPBWC_Email_Base` + 4 class). Đăng ký tại
`includes/b2b/class-b2b-emails.php` (`SPBWC_B2B_Emails::init()`), wire trong plugin chính sau
`SPBWC_B2B_Procurement::init()`.

| Class | Email ID | Action trigger | Người nhận |
|---|---|---|---|
| `SPBWC_Email_B2B_Invite` | `spbwc_b2b_invite` | `spbwc_b2b_invite_notification` | Email được mời |
| `SPBWC_Email_B2B_Approval_Needed` | `spbwc_b2b_approval_needed` | `spbwc_b2b_approval_needed_notification` | Approver(s) (comma-join) |
| `SPBWC_Email_B2B_Approval_Outcome` | `spbwc_b2b_approval_outcome` | `spbwc_b2b_approval_outcome_notification` | Người đặt (body branch approved/rejected) |
| `SPBWC_Email_B2B_Company_Ready` | `spbwc_b2b_company_ready` | `spbwc_b2b_company_ready_notification` | Chủ company |

- `SPBWC_Email_Base` (generic, prefix `spbwc_`): `wrap()` header/footer WC, `get_content_html/plain()`,
  abstract `build_body()`, `dispatch($recipient)`, helper `cta()`/`myaccount_endpoint_url()`.
- Subject/heading override + enable/disable kế thừa từ `WC_Email::init_form_fields()` → có entry ở
  WooCommerce › Settings › Emails. i18n đủ; gửi local, không phone-home.

### 5. Điểm trigger (action → email)

Quote (đầy đủ chuỗi vòng đời):
- `spbwc_quote_new_notification` ← `class-request-quote.php:533`, `quote/class-quote-bucket.php:249`
- `spbwc_quote_ack_notification` ← `class-request-quote.php:535`, `class-quote-bucket.php:251` (chỉ khi có email khách)
- `spbwc_quote_sent_notification` ← `quote/class-quote-admin.php:211` (admin bấm "Send")
- `spbwc_quote_reminder_notification` ← `quote/class-quote-scheduler.php:98` (Action Scheduler quét hằng ngày, mốc 7/3/1 ngày)
- `spbwc_quote_accepted_notification` ← `class-request-quote.php:1187`
- `spbwc_quote_converted_notification` ← `class-request-quote.php:1189` (chỉ khi đã tạo order)
- `spbwc_quote_declined_notification` ← `class-request-quote.php:1196`

Marketplace: `spbwc_marketplace_designer_enabled`/`_disabled` ← `launcher/class.designer.php:250,253`.
Các action withdraw được khai báo ở `class.launcher.php:411-417` (điểm fire nằm trong logic balance/withdrawal của launcher).

B2B: gọi `wp_mail()` đồng bộ ngay trong method (không qua WC action) — xem §4.

> Lưu ý: status quote đổi qua `spbwc_quote_status_changed` (`quote/class-quote-model.php:238`,
> tham số `$post_id,$from,$to`) — nhưng email lại nghe từng action chuyển trạng thái riêng, không
> nghe `_status_changed` chung.

### 6. Settings / options liên quan email

- `spbwc_quote_settings['admin_email']` — recipient cho quote-new (sanitize, default `get_option('admin_email')`),
  UI tại `includes/class-admin-options.php:1228-1249` (save) + `:1303-1392` (form).
- Các WC_Email Quote: chỉ có `enabled` mặc định 'yes'; **CHƯA expose subject/heading override trên UI**
  (kế thừa default WC, nhưng không có field admin riêng → merchant khó tuỳ biến).
- Các WC_Email Launcher: có đầy đủ field settings (recipient/subject/heading/from/bcc/type).
- KHÔNG có override toàn cục `wp_mail_from`/`wp_mail_from_name` — mọi email kế thừa from-address của WooCommerce.

### 7. Filters/hooks email

| Hook | Loại | Dùng |
|---|---|---|
| `woocommerce_email_classes` | filter | đăng ký class (quote `:21`, launcher `:48`) |
| `woocommerce_email_actions` | filter | đăng ký action trigger (quote `:22`, launcher `:49`) |
| `woocommerce_email_header` / `_footer` | action | branding trong template launcher + wrap quote |
| `spbwc_quote_status_changed` | action | sự kiện đổi status (không trực tiếp gửi mail) |

KHÔNG dùng: `woocommerce_email_recipient_*`, `wp_mail_from`, `wp_mail_from_name`, filter `wp_mail`,
`woocommerce_email_headers`.

### 8. Cloud / external

- Điểm chạm duy nhất: **đính kèm PDF báo giá** qua `SPBWC_Quote_PDF::email_attachments()` (Cloud2Print).
  Email vẫn gửi local qua `wp_mail`/`WC_Email`; chỉ FILE PDF mới sinh từ cloud. Phù hợp mô hình
  freemium [[project_freemium_local_vs_cloud]] (gửi mail = free; PDF = paid/cloud).
- KHÔNG có gửi email qua app.storelly.com, không queue mail ra ngoài, không webhook bounce.

### 9. i18n

Toàn bộ subject/heading/body của phong cách A và các `wp_mail()` B2B đều dùng `__()`/`esc_html__()`/
`esc_html_e()` đúng text domain `storelly-product-builder-for-woocommerce`. **Ngoại lệ duy nhất:** 2
hàm dead-code quote legacy (§4 #6,#7) hardcode chuỗi tiếng Anh không qua `__()`.

---

## Part B — Lỗ hổng & nợ kỹ thuật

### 10. Phát hiện ưu tiên

#### 10.1 [P1] Dead code legacy quote email — XOÁ ✅ (E1a)
`send_quote_notification_email()` và `send_quote_customer_email()` (`class-request-quote.php:551,567`)
**không được gọi ở bất kỳ đâu** (grep toàn repo chỉ thấy định nghĩa + 1 dòng spec cũ tham chiếu sai
line 278/294 đã lỗi thời). Chúng hardcode subject `[Storelly Quote] #..` không i18n, body plain
không escape. Flow thật đã chạy bằng 7 class WC_Email. → **Xoá cả 2 hàm**, cập nhật
`docs/SPEC_QUOTE_USER_FLOW_UX.md:40`.

#### 10.2 [P1] B2B & Designer dùng `wp_mail()` thô — migrate sang WC_Email — B2B ✅ (E2)
4 email B2B (§4 #1-4) đã migrate sang WC_Email (§3.3). Designer message (§5) còn `wp_mail()` (đã
sanitize ở E1) — migrate nốt ở E3.

#### 10.3 [P2] Designer REST `send_email_to_designer` — sanitize input ✅ (E1b)
`includes/launcher/api/designer.php`. **Có** `permission_callback => permission_check` (`:98`) nên
KHÔNG phải lỗ hổng auth. Đã sửa: subject `sanitize_text_field(wp_unslash())`, message `wp_kses_post(
wp_unslash())`, guard `is_email($to)` + chặn subject/message rỗng trước khi gửi.

#### 10.4 [P2] Prefix lẫn `nbdl_` trong email ID launcher
5 email launcher dùng ID `nbdl_*` (di sản fork) trái CLAUDE.md quy tắc 1-prefix. **KHÔNG đổi cẩu
thả** — email ID là khoá lưu settings `woocommerce_emails_<id>`; đổi sẽ mất config merchant. Phương
án an toàn: giữ ID cũ + thêm migration map, HOẶC chấp nhận deprecation note. Quyết định để ngỏ (§13 D2).

#### 10.5 [P3] ~~Quote WC_Email thiếu UI subject/heading~~ — KHÔNG phải gap
Đánh giá lại: 7 email quote kế thừa `WC_Email::init_form_fields()` của cha → đã có sẵn field
enabled/subject/heading/email_type ở WooCommerce › Settings › Emails. Audit ban đầu nhầm. Bỏ E4.

#### 10.6 [P3] Không có from-name/from-address riêng & không log gửi
Mọi email kế thừa from của WooCommerce (chấp nhận được). Nhưng không có log "đã gửi/thất bại",
không retry, không test-send. Với quote/B2B (giá trị cao) nên có audit tối thiểu.

#### 10.7 [P3] Custom Order không có email riêng
Custom Order (xem [[project_custom_order_cow_folders]]) dựa hoàn toàn vào email WC chuẩn theo status
order; PDF queue riêng qua Action Scheduler nhưng không có email "thiết kế của bạn đã sẵn sàng tải".
Cân nhắc có cần email riêng hay không (§13 D3).

---

## Part C — Đề xuất kiến trúc hợp nhất (CHƯA build)

### 11. Nguyên tắc

1. **Một đường ống:** mọi email nghiệp vụ = một class `WC_Email`, đăng ký qua
   `woocommerce_email_classes` + `woocommerce_email_actions`. Không còn `wp_mail()` rải rác.
2. **Một base class chung:** tạo `abstract SPBWC_Email_Base extends WC_Email` gom: load template
   từ thư mục plugin, helper `placeholder` (`{site_title}`, `{quote_number}`, `{company_name}`…),
   attachment hook, default từ WooCommerce, trim/escape. Quote + B2B + Launcher cùng kế thừa.
3. **Template hoá:** mọi body chuyển sang file template (`templates/emails/...`) thay vì ghép
   chuỗi trong PHP, để merchant/theme override qua `wc_get_template()`. Quote hiện build inline →
   tách template.
4. **Settings nhất quán:** mọi class có `init_form_fields()` đủ enabled/recipient/subject/heading/type.
5. **Freemium-safe:** gửi luôn local; attachment cloud (PDF) chỉ thêm khi feature cloud bật
   ([[project_freemium_local_vs_cloud]]); không phone-home.
6. **Compliance:** i18n đủ, sanitize input REST, escape output, prefix `spbwc_` cho class/action mới.

### 12. Email cần thêm/migrate (B2B → WC_Email)

| Class đề xuất | Action mới | Người nhận | Thay cho |
|---|---|---|---|
| `SPBWC_Email_B2B_Invite` | `spbwc_b2b_invite_notification` | Email được mời | `class-b2b-team.php:336` |
| `SPBWC_Email_B2B_Approval_Needed` | `spbwc_b2b_approval_needed_notification` | Approver(s) | `class-b2b-procurement.php:428` |
| `SPBWC_Email_B2B_Approval_Outcome` | `spbwc_b2b_approval_outcome_notification` | Người đặt | `class-b2b-procurement.php:459` |
| `SPBWC_Email_B2B_Company_Ready` | `spbwc_b2b_company_ready_notification` | Chủ company | `class-b2b-account.php:58` |
| `SPBWC_Email_Designer_Message` | (giữ REST, đẩy qua WC_Email) | Designer | `designer.php:324` |

Mỗi class: HTML + plain template trong `templates/emails/b2b/`, đăng ký trong một file gom
`includes/b2b/class-b2b-emails.php` (mirror `class-quote-emails.php`).

### 13. Quyết định để ngỏ (chốt trước khi build)

- **D1 — Base class:** tạo `SPBWC_Email_Base` mới rồi refactor cả Quote+Launcher vào, hay chỉ áp
  cho email B2B mới và để Quote/Launcher nguyên trạng? (refactor rộng = rủi ro hồi quy email quote
  đang chạy tốt).
- **D2 — Prefix `nbdl_`:** giữ nguyên ID cũ (kèm note) hay đổi `spbwc_` + viết migration copy
  settings `woocommerce_emails_nbdl_* → woocommerce_emails_spbwc_*`?
- **D3 — Custom Order email:** có cần email "design sẵn sàng" riêng, hay dùng order-note/email WC chuẩn?
- **D4 — Email log:** có thêm bảng/option log gửi + nút "Send test" không (P3, có thể tách release riêng)?
- **D5 — Async:** B2B hiện gửi đồng bộ trong request; có chuyển sang `do_action` + Action Scheduler
  như quote để tránh chậm request không?

### 14. Milestone đề xuất

Quyết định đã chốt khi build (2026-06-04): **D1** = chỉ áp `SPBWC_Email_Base` cho email B2B/designer
mới, KHÔNG refactor Quote/Launcher. **D5** = B2B dùng `do_action` đồng bộ qua `woocommerce_email_actions`
(giống Quote), chưa async Action Scheduler. D2/D3/D4 vẫn để ngỏ.

| MS | Nội dung | Trạng thái |
|---|---|---|
| **E1** | Dọn dead code (§10.1) + sanitize REST designer (§10.3). | ✅ DONE |
| **E2** | `SPBWC_Email_Base` + `class-b2b-emails.php`, migrate 4 email B2B sang WC_Email (§3.3). | ✅ DONE |
| **E3** | Migrate designer-message REST sang WC_Email (`SPBWC_Email_Designer_Message`, free-form subject; REST fire `do_action`). | ✅ DONE |
| **E4** | ~~init_form_fields subject/heading cho quote~~ — BỎ (đã có sẵn từ cha, §10.5). | ✖ N/A |
| **E5** | Rename `nbdl_email_*` → `spbwc_email_*` + `SPBWC_Email_Install::migrate_nbdl_settings()` copy `woocommerce_<old>_settings`. | ✅ DONE (D2=rename+migrate) |
| **E6** | Custom Order emails (`spbwc_order_received` local + `spbwc_order_proof` cloud, đính PDF), email log (bảng `spbwc_email_log`), Send test. | ✅ DONE (D3=cả 2 email; D4=bảng DB) |
| **Menu** | **Storelly › Emails** dashboard (`SPBWC_Email_Admin`): gom email theo nhóm + badge enable/disable + deep-link WC editor + Send test + log viewer; design token `static/css/email-admin.css`. | ✅ DONE |
| **E7** | `wp plugin check` 0 ERROR (chỉ WARNING custom-table + TextDomainMismatch môi trường); POT regen có chuỗi mới. | ✅ DONE |

**Kiến trúc email mới (E3/E5/E6):** mọi email Storelly giờ chung prefix `spbwc_` (giúp log filter
sạch). Base `SPBWC_Email_Base` tách ra `includes/email/class-email-base.php` (B2B + Order + Designer-
message kế thừa). Log bắt qua `woocommerce_email_sent` (lọc `spbwc_`). Installer version-gated trên
`admin_init`. Launcher email + designer-message chỉ surface khi `spbwc_marketplace_enabled=yes`.

### 15. Impact tới flow WooCommerce

- Migrate B2B sang WC_Email **không** đụng cart/checkout/order — chỉ đổi cách phát email, action mới
  thêm vào `woocommerce_email_actions`. Cần test: trang WooCommerce > Settings > Emails liệt kê đủ
  class mới, bật/tắt hoạt động, gửi thử mỗi trigger.
- Đổi email ID `nbdl_` (nếu chọn D2-đổi) là breaking với settings đã lưu → bắt buộc migration, ghi
  changelog rõ.
- Không thay đổi điểm chạm Cloud2Print → freemium giữ nguyên.

---

## 16. Tham chiếu file

- Quote emails: `includes/quote/class-quote-email-types.php`, `class-quote-emails.php`, `class-quote-scheduler.php`, `class-quote-admin.php`
- Launcher emails: `includes/launcher/emails/*.php`, `includes/launcher/class.launcher.php`, `templates/launcher/emails/**`
- B2B emails (wp_mail): `includes/b2b/class-b2b-team.php`, `class-b2b-procurement.php`, `class-b2b-account.php`
- Designer REST: `includes/launcher/api/designer.php`
- Dead code: `includes/class-request-quote.php:551,567`
- Settings: `includes/class-admin-options.php:1228-1392`
