# SPEC — B2B Account Credit (Wallet · Net Terms · Rebate)

> Status: **DRAFT / building**. Chốt 2026-06-05. Module finance B2B dựng trên engine
> ví/sổ cái/withdraw/commission đã chạy thật trong designer marketplace.
> Liên quan: `docs/SPEC_B2B_CLIENT.md`, `docs/SPEC_FREEMIUM.md`,
> `docs/SPEC_QUOTE_USER_FLOW_UX.md`.

## 1. Vấn đề & cơ hội

Designer marketplace của Storelly đã có một **bộ máy tài chính per-user hoàn chỉnh**:
sổ cái 2 cột (`{prefix}storelly_marketplace_balance`), workflow rút tiền có duyệt
(`{prefix}storelly_marketplace_withdraw` + `SPBWC_Withdraw`), engine hoa hồng
flat/%/combine, và email transaction. Xem `includes/launcher/class.designer.php:356`
(`get_balance`/`get_earnings`) và `includes/launcher/class.withdraw.php`.

B2B (`SPBWC_Company`) đã **khai báo** `META_CREDIT_LIMIT`, `META_PAYMENT_TERMS`,
`META_APPROVAL_THRESHOLD` ở `class-b2b-company.php:54` nhưng credit_limit/payment_terms
hiện **không có engine nào dùng** — scaffolding mồ côi từ fork cmsmart. Module này lấp
khoảng trống đó bằng cách bê pattern marketplace sang phía company.

## 2. Insight nền tảng — 1 sổ cái, 3 góc nhìn

Wallet, Net Terms và Rebate **không phải 3 tính năng** mà là 3 mặt của cùng một số dư:

```
balance(company) = SUM(credit) − SUM(debit)   -- chỉ tính dòng status = 'posted'

  balance > 0   → quỹ trả trước          → "Wallet"
  balance < 0   → công nợ (phải thu)      → "Net Terms"  (chặn bởi credit_limit)

  credit (tiền vào tài khoản company) = topup | payment | rebate | refund | adjustment+
  debit  (tiền tiêu)                   = order_charge | adjustment− | rebate_clawback
```

→ **MỘT bảng, một hàm balance, một màn sao kê.** Rebate chỉ là một `txn_type='rebate'`
ghi credit vào cùng ví — không có bảng/số dư rebate riêng.

**Sức chi khả dụng** (đã hợp nhất ví + hạn mức, vì balance là một con số duy nhất):

```
available_credit = max( 0, balance + credit_limit )
```

(balance dương: tiêu cả ví lẫn hạn mức; balance âm: chỉ còn phần hạn mức chưa dùng.)

## 3. Quyết định đã CHỐT

1. **Phạm vi v1** = Wallet + Net Terms + Rebate. Sales-rep commission để sau.
2. **Bảng B2B riêng** `{prefix}spbwc_b2b_ledger` (copy pattern, KHÔNG đụng bảng
   marketplace đang chạy) → cô lập rủi ro.
3. **Convergence-ready**: designer-payout & company-credit là cùng một "wallet core",
   chỉ khác `owner_type`. Bảng v1 dùng cột `owner_type`('company') + `owner_id`, hàm
   balance nhận owner tổng quát. Tương lai hội tụ = migration + rename, không redesign.
4. **Rebate = cuối kỳ** (job hàng tháng, quét order `completed` đã qua cửa sổ refund)
   → tránh claw-back. Rebate là **một nguồn credit**, gắn rule vào engine giá B2B.
5. **Net terms vượt hạn mức = đẩy vào luồng company-admin DUYỆT** (không chặn cứng),
   tái dùng approval của procurement/quote (`SPBWC_Company::order_needs_approval`,
   `user_can_approve`). Gate theo team/seats/`approval_threshold`.

## 4. Data model — `{prefix}spbwc_b2b_ledger`

| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `id` | bigint UNSIGNED AI | PK |
| `owner_type` | varchar(20) = 'company' | convergence (company\|designer sau) |
| `owner_id` | bigint UNSIGNED | company_id ở v1 |
| `txn_type` | varchar(30) | topup\|order_charge\|payment\|rebate\|refund\|adjustment |
| `ref_type` | varchar(30) NULL | 'order'\|'manual'\|'rebate_run'… (transaction_id trỏ vào đâu) |
| `ref_id` | bigint UNSIGNED = 0 | id order/đối tượng tham chiếu |
| `debit` | decimal(18,4) = 0 | tiền tiêu khỏi tài khoản |
| `credit` | decimal(18,4) = 0 | tiền nạp vào tài khoản |
| `currency` | varchar(10) NULL | mã tiền (mặc định store currency) |
| `status` | varchar(20) = 'posted' | posted\|pending\|void — chỉ `posted` vào balance |
| `note` | text NULL | ghi chú admin / mô tả |
| `created_by` | bigint UNSIGNED = 0 | user thao tác |
| `effective_date` | datetime | mốc ghi nhận (= balance_date marketplace) |
| `due_date` | datetime NULL | đáo hạn = effective + payment_terms (cho aging) |
| `created` | datetime | thời điểm tạo bản ghi |

Index: `KEY owner (owner_type, owner_id)`, `KEY ref (ref_type, ref_id)`, `KEY status (status)`.

Bút toán **append-only**: không UPDATE số tiền. Sửa/hoàn = ghi bản ghi đảo
(`adjustment`/`refund`) hoặc đổi `status` sang `void`. Giữ audit trail.

### 4.1 Chống double-spend (nhiều member tiêu chung)

Bảng WP có thể là MyISAM (không transaction) → KHÔNG dựa vào InnoDB transaction. Dùng
**MySQL named lock** quanh (đọc balance → kiểm tra available → insert):
`GET_LOCK('spbwc_b2b_ledger_{owner_id}', 5)` … `RELEASE_LOCK(...)`. Mọi đường ghi nợ
(`order_charge`) phải đi qua một hàm `post_charge()` có lock.

### 4.2 Map order-status → khi nào ghi nợ "chắc"

Mirror marketplace (`get_balance` lọc theo status). Chỉ `order_charge` `posted` khi order
ở status hợp lệ (mặc định `processing`, `completed`; filter `spbwc_b2b_credit_post_statuses`).
Order `pending`/`on-hold`/`cancelled` → bút toán `pending` hoặc chưa ghi.

## 5. API model — `SPBWC_B2B_Ledger` (class tĩnh, file `includes/b2b/class-b2b-ledger.php`)

Theo đúng pattern `SPBWC_B2B_Price_Rules` (DB_VERSION guard, `table()`, `init()` →
`maybe_install` trên `init`, `drop_table()`).

```
const OWNER_COMPANY = 'company';
TXN_TOPUP|TXN_ORDER_CHARGE|TXN_PAYMENT|TXN_REBATE|TXN_REFUND|TXN_ADJUSTMENT
STATUS_POSTED|STATUS_PENDING|STATUS_VOID

// ghi sổ tổng quát (append-only)
record( $owner_id, $args )            // args: txn_type, debit|credit, ref_type, ref_id, status, note, due_date, created_by, owner_type
// helpers ngữ nghĩa
post_charge( $owner_id, $amount, $ref )   // có named lock + check available
post_topup( $owner_id, $amount, ... )
post_payment( $owner_id, $amount, ... )
post_rebate( $owner_id, $amount, ... )
post_refund( $owner_id, $amount, $ref )
void( $row_id )

// đọc
get_balance( $owner_id, $on_date = '' )          // SUM(credit-debit) posted ≤ date
get_outstanding( $owner_id )                     // max(0, -balance)
get_wallet( $owner_id )                          // max(0, balance)
get_available_credit( $owner_id, $credit_limit ) // max(0, balance + credit_limit)
get_statement( $owner_id, $limit, $offset )      // sao kê
get_aging( $owner_id )                           // nhóm quá hạn 0/30/60/90 theo due_date
```

`owner_type` mặc định `OWNER_COMPANY`; mọi hàm nhận tham số owner tổng quát để sau gộp.

## 6. Milestones

- **M1 — Ledger foundation.** ✅ DONE. Bảng + class `SPBWC_B2B_Ledger` (record/post_*/
  get_balance/get_statement/get_available_credit + named lock) + wiring bootstrap + drop_table.
  Test 9/9.
- **M2 — Wallet (trả trước).** ✅ DONE. Admin "Account credit" tab (top-up/payment/adjustment +
  KPI + statement); My-Account `company-account` endpoint (balance + sao kê); gateway
  `SPBWC_Gateway_Company_Account` ("Pay with Company Account") khi available ≥ cart; charge +
  reverse (cancel/failed). Test 11/11.
- **M3 — Net Terms.** ✅ DONE. Field "Credit limit" ở admin overview (`META_CREDIT_LIMIT`);
  `terms_days()` từ `META_PAYMENT_TERMS`; available = balance + credit_limit; charge net-terms
  → số dư âm; `due_date` = order date + payment_terms. Test 7/7.
- **M4 — Approval integration.** ✅ DONE (Option A — tái dùng procurement queue). Filter
  `spbwc_order_needs_approval` + `gate_over_credit` (net-terms order vượt available → approval);
  action `spbwc_b2b_procurement_approved` → charge order với `allow_overdraft` (idempotent).
  Test 7/7.
- **M5 — Rebate.** ✅ DONE. Per-company field "Volume rebate %" (`META_REBATE_PCT`); class
  `SPBWC_B2B_Rebate` — Action Scheduler recurring monthly (`spbwc_b2b_rebate_run`) →
  `accrue_for_company` cộng `pct%` của net spend (order `completed`, trừ refund) của members vào
  ví qua `post_rebate`; idempotent per period (`META_REBATE_LAST`). Test 5/5.
- **M6 — Lifecycle & compliance.** ✅ DONE (phần local). `woocommerce_order_refunded` → đảo bút
  toán partial/full (`apply_reversal` theo "tổng đã đảo", cap ở charged, idempotent); aging buckets
  hiển thị ở My-Account khi có công nợ; `uninstall.php` drop bảng `spbwc_b2b_ledger`; Plugin Check 0
  ERROR ở file mới. Còn: regen POT (defer) + commit field rebate trong admin.php (đợi agent khác).
  Test 6/6 refund + 4/4 aging render.
- **M7 — Pro/cloud (ngoài v1 free).** Auto-settlement online net-30 (Stripe), dunning tự
  động, aging dashboard nâng cao, export kế toán, payout rebate. Chạm app.storelly.com → PAID.

## 7. Tách Free / Pro (khớp SPEC_FREEMIUM F0–F5)

| Năng lực | Free (local) | Pro (cloud) |
|---|---|---|
| Sổ cái + balance + sao kê | ✅ | |
| Hạn mức + chặn/duyệt checkout + approval nội bộ | ✅ | |
| Top-up & trả nợ **offline** (admin confirm) | ✅ | |
| Rebate tính & cộng ví (cuối kỳ) | ✅ | |
| Auto-settlement online, dunning, aging dashboard, export, payout | | 💰 |

## 8. Rủi ro / mở

- Multi-currency: v1 một currency = store currency; cột `currency` để sẵn.
- Refund một phần → đảo theo tỉ lệ; cần test order partial-refund.
- HPOS: dùng `wc_get_orders`/CRUD, không query `wp_posts` (xem SPEC HPOS).
- Khi hội tụ với designer-payout: thống nhất `txn_type` namespace để 2 phía không đụng.
```
