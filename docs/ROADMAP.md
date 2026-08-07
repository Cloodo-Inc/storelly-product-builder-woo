# Storelly — Release Roadmap (biweekly cadence)

> **Status:** DRAFT · **Ngày:** 2026-08-07 · **Owner:** David
> **Hiện tại:** v1.7.0 · **Cadence:** 1 minor release / 2 tuần
> **Trọng tâm 3 trục:** UX/UI polish · Activation (user cài xong → tạo được product đầu tiên) · Retention

Tài liệu này là **kế hoạch phát hành định kỳ**, không phải spec kỹ thuật. Mỗi feature vẫn trỏ về
`docs/SPEC_*.md` nguồn để lấy chi tiết implement. Phần mockup dưới đây là **wireframe định hướng UX/UI**,
không phải pixel-perfect — dùng để chốt bố cục + userflow trước khi code.

## Giả định đang dùng (David chỉnh nếu sai)

1. **Thứ tự:** *interleave cân bằng* — retention rẻ → polish admin → editor → buyer → V2 wizard cuối (S6).
2. **Backend `app.storelly.com`:** *đang làm, ~1–2 tháng nữa* → track Monetization (§5) xếp nửa sau roadmap,
   nửa đầu chỉ làm phần **không phụ thuộc backend**.
3. **V2 Designer Studio Wizard** cần **chốt scope trước S6** (hiện "pending approval" trong
   `DEV_MILESTONES_V2_WIZARD.md`).

Nếu 3 giả định này đổi, §3 (thứ tự sprint) và §5 (chèn track backend) sẽ được xếp lại tương ứng.

---

## 0. Nguyên tắc cadence

| Quy tắc | Chi tiết |
|---|---|
| **Nhịp** | Đóng băng feature vào **thứ Sáu tuần lẻ**, release **thứ Sáu tuần chẵn**. 2 tuần = 1 minor. |
| **Version** | Mỗi sprint bump **minor** (`1.8.0 → 1.9.0 …`). Hotfix giữa sprint = **patch** (`1.8.1`). Ba con số (readme `Stable tag` + header `Version` + git tag) **phải khớp**. |
| **Theme** | Mỗi release có **1 theme rõ ràng** (dòng đầu changelog). Không dồn nhiều theme rời rạc vào 1 bản. |
| **Definition of Done** | Xem §6. Bắt buộc: `wp plugin check` 0-ERROR + design-token pass + Chrome smoke-test + changelog + spec sync. |
| **Buffer** | Chừa ~20% mỗi sprint cho bug/hotfix. Không nhồi 100% capacity vào feature. |
| **An toàn cadence** | Ưu tiên **visible-win + backend-independent** ở sprint đầu → đảm bảo *luôn có gì đó để release đúng hẹn*. Khối lớn (V2 wizard, Vault) tách track / xếp cuối. |

---

## 1. Bản đồ trạng thái — engine đã xong, thiếu "lớp vỏ"

```
                        ĐÃ SHIP (v1.7.0)                          │  CÒN THIẾU (nguồn roadmap)
────────────────────────────────────────────────────────────────┼──────────────────────────────────
 ACTIVATION   Welcome Wizard full-screen 3-step ····· ✓          │  V2 Editor Wizard/Designer Studio (M0–M9) ✗
              Demo 1-click + "Remove demo data" ····· ✓          │  Woo-seed: hide native variation form   ✗
              Woo auto-prepare (Setup Wizard seed) ·· ✓          │  Per-product preview/diff trước bulk     ✗
              Cloud consent + deterministic UUID ···· ✓          │
────────────────────────────────────────────────────────────────┼──────────────────────────────────
 UX/UI        Design-token core wave-1 + z/typography ✓          │  Overview hero/stat-card + fix CLS flicker ✗
              Dialog/Toast system (spbwcDialog) ····· ✓          │  Settings tab router (License/System/About)✗
              Menu 3-band BUILD/SELL/CONFIGURE ······ ✓          │  Edit Option v3 two-pane redesign          ✗
              Linked-product metabox redesign ······· ✓          │  Options list redesign                     ✗
              My-Account portal M1–M6 shell ········· ✓          │  Inline raw pages (Custom Order/Quote/Sys) ✗
                                                                  │  Control-token wave-2 (options/VB css)     ✗
────────────────────────────────────────────────────────────────┼──────────────────────────────────
 RETENTION    Email lifecycle E1–E7 (ack+reminder+   ✓          │  Watermarked local PDF fallback            ✗
              accepted) + log + Send-test                        │  Upsell-by-intent (bỏ trigger max_products)✗
              Quote B2B M1–M7 + Quote Form Enhance ·· ✓          │  Expiry/grace + renew comms                ✗
              caps[]/can() entitlement engine ······· ✓          │  Buyer canvas M2 (restore userLayers)      ✗
              F2 gate cloud_pdf + order_sync ········ ✓          │  `select` field render dropdown            ✗
────────────────────────────────────────────────────────────────┼──────────────────────────────────
 MONETIZATION Account & Plan U1–U4 + connect-back ··· ✓          │  Design Vault / Config Snapshots           ✗ (backend)
              (plugin-side)                                      │  Order-sync analytics / B2B service layer  ✗ (backend)
              Plans page + caps matrix ·············· ✓          │  Free marketplace + versioned updates      ✗ (backend)
```

**Kết luận:** hầu hết *logic* đã có. Roadmap = **hoàn thiện trải nghiệm** (polish + vài khối activation/retention
lớn), không phải xây engine mới.

---

## 2. North-star funnel — userflow tổng thể (activation → retention → upsell)

Mọi sprint phải trả lời: *"nó cải thiện bước nào của funnel này?"*

```
 ┌──────────┐   ┌───────────────┐   ┌─────────────────┐   ┌──────────┐   ┌──────────────┐   ┌───────────┐
 │ INSTALL  │──▶│  ACTIVATE     │──▶│  FIRST OPTION   │──▶│ PUBLISH  │──▶│ BUYER USES   │──▶│ RETENTION │
 │ plugin   │   │ Welcome       │   │ (aha moment):   │   │ gán vào  │   │ configure +  │   │ merchant  │
 │ + Woo    │   │ Wizard: demo, │   │ tạo option-set  │   │ product/ │   │ live price + │   │ quay lại, │
 │          │   │ Woo-prepare   │   │ đầu tiên        │   │ category │   │ canvas + PDF │   │ mở rộng   │
 └──────────┘   └───────────────┘   └─────────────────┘   └──────────┘   └──────────────┘   └─────┬─────┘
      S(gate)         ✓ đã tốt          ⚠ ĐIỂM YẾU           ✓ ok            ⚠ canvas M2          │
   Plugin Check                       V2 Wizard (S6)                        + select field        │
                                                                                                  ▼
                                                                                        ┌───────────────────┐
                                                                                        │ UPSELL → CLOUD    │
                                                                                        │ PDF/Vault/analytics│
                                                                                        │ upsell-by-intent   │
                                                                                        └───────────────────┘
                                                                                          S1 + Monetization §5
```

- **Điểm yếu #1 — FIRST OPTION:** editor option hiện "concept khó", time-to-first-option cao → **V2 Wizard (S6)**.
- **Điểm yếu #2 — BUYER USES:** canvas mất text/ảnh buyer tự thêm khi cart-edit; `select` render sai → **S1, S5**.
- **Đòn bẩy retention rẻ nhất:** PDF không còn dead-end + upsell đúng chỗ → **S1** (không cần backend).

---

## 3. Roadmap 6 sprint (tổng quan)

| Sprint | Ver | Theme | Nội dung chính | Trục | Backend? |
|---|---|---|---|---|:---:|
| **S1** | 1.8.0 | Retention/upsell rẻ nhất | Watermarked local PDF fallback · re-point upsell (bỏ `max_products`) · fix `select` field | Retention + Buyer | Không |
| **S2** | 1.9.0 | Bộ mặt sau khi cài | Overview redesign + fix CLS (A11) · **Settings tab router** (foundation) · quick: menu icon+badge, breadcrumb | UX + Activation | Không |
| **S3** | 1.10.0 | Nơi merchant sống | **Edit Option v3** two-pane · control-token **wave-2** | UX (retention) | Không |
| **S4** | 1.11.0 | Dọn nốt trang xấu | **Options list** redesign · inline raw pages → component (Custom Order/Quote/System/About) | UX | Không |
| **S5** | 1.12.0 | Trải nghiệm khách cuối | Canvas **M2** restore `userLayers` + clamp · Canvas M3 mobile bottom-sheet | Buyer UX | Không |
| **S6** | 1.13.0 | Activation leap ⚠ | V2 Designer Studio **M0+M1** (wizard skeleton + step-2 pricing) · Woo-seed hide-variation toggle | Activation | Không |

**Track Monetization (§5)** — chèn đan xen từ ~S4 trở đi *khi backend sẵn sàng*: `#4 expiry/grace → #5 order
analytics → #6 snapshots → #7 Design Vault → #8 B2B layer → #10 marketplace`.

---

## 4. Chi tiết từng sprint — userflow + mockup

Legend mockup: `▓`=primary CTA · `▒`=secondary · `[ ]`=input · `( )`=radio · `▸`=nav item · `···`=vùng cuộn.

---

### S1 · v1.8.0 — "Retention & upsell rẻ nhất (0 backend)"

**Nguồn:** `SPEC_FREEMIUM.md` F3/F4 · `SPEC_QUOTE_USER_FLOW_UX.md §11`.
**Vì sao trước tiên:** cả 3 item *không phụ thuộc backend*, đòn bẩy retention/revenue cao/chi phí thấp,
đảm bảo release đầu tiên của cadence chắc chắn giao được.

#### 4.1 Watermarked local PDF fallback

Hiện tại: free user bấm **Download PDF** → gate chặn → **dead-end** (không ra gì). Sửa thành: xuất PDF local
qua FPDI (đã bundle) **có watermark**, kèm CTA nâng cấp bỏ watermark.

**Userflow:**
```
Merchant/Buyer bấm [Download PDF]
        │
        ▼
  cloud_license_active()?  ──yes──▶  PDF sạch (cloud render)  ── hết
        │ no
        ▼
  FPDI render local + overlay watermark "Made with Storelly · storelly.com"
        │
        ▼
  Trả file + admin notice: "Bản xem trước có watermark. Bản in sạch cần Cloud plan → [Unlock]"
```

**Mockup — notice sau khi tải (admin) + toast (storefront):**
```
┌─ Storelly ────────────────────────────────────────────────────────────┐
│  ⬇  Đã tạo PDF xem trước (có watermark).                               │
│                                                                        │
│  Bản in sạch — không watermark, độ phân giải in — cần Storelly Cloud.  │
│                                        ▓ Unlock print-ready PDF ▓  ✕   │
└────────────────────────────────────────────────────────────────────────┘
        └─ deep-link → Account & Plan (intent = cloud_pdf) ─┘
```

**Acceptance:** free user luôn nhận được *một* file (không dead-end); watermark hiện rõ ở mọi trang; nút
Unlock trỏ Account&Plan với `?intent=cloud_pdf`; không mint REST key; khai báo external service không đổi.

#### 4.2 Re-point Upsell Notice (bỏ trigger đếm sản phẩm)

`class-upsell-notice.php:59-64` vẫn fire upsell theo `max_products` (đếm sản phẩm) — **sai thông điệp** vì
Storelly quảng bá "no product limit". Chuyển sang fire theo **intent** (`spbwc_cloud_locked`) — chỉ khi user
thực sự chạm feature Cloud.

**Trước → Sau:**
```
TRƯỚC (sai):  user tạo > N products  ──▶  "Bạn đã đạt giới hạn, nâng cấp!"   ✗ mâu thuẫn positioning
SAU  (đúng):  user bấm feature Cloud ──▶  "Feature này cần Cloud plan → Unlock"  ✓ đúng intent
```

**Acceptance:** grep sạch mọi nhánh `max_products` trong upsell; upsell chỉ xuất hiện tại điểm chạm cloud_pdf/
order_sync; copy nhất quán "local free, no limit".

#### 4.3 Fix `select` field render dropdown

Field type `select` đang render thành **text-input** trên storefront → buyer thấy ô gõ chữ thay vì dropdown.

**Mockup — Get-a-Quote form (storefront), trước/sau:**
```
   TRƯỚC (bug)                         SAU (fix)
 Material                            Material
 [ type here...            ]         [ Cotton            ▾ ]
                                       ├ Cotton
                                       ├ Polyester
                                       └ Silk
```
**Chạm:** `includes/class-request-quote.php` (render) + `includes/class-admin-options.php` (options editor).

---

### S2 · v1.9.0 — "Bộ mặt sau khi cài" (Overview + Settings foundation)

**Nguồn:** `SPEC_ADMIN_UI_REDESIGN.md` U1/U2 §5.1 · `SPEC_ADMIN_UX_POLISH_W2.md` A11.
**Vì sao:** Overview là **màn đầu tiên** merchant thấy sau activation → first impression quyết định retention.
Settings tab router là **FOUNDATION** (chặn Emails-tab, fold System/About/License/Setup) → làm sớm.

#### 4.4 Overview redesign + fix flicker/CLS (A11)

**Userflow:** activation xong → redirect Overview → *phải* load mượt (không giật header/upsell) + dẫn hành động
tiếp theo rõ ràng (tạo option / xem demo / connect cloud).

**Mockup — Overview mới:**
```
┌─ Storelly › Overview ─────────────────────────────────────────────────────────┐
│                                                                                │
│  Welcome back 👋                                    [ ● Cloud: not connected ] │
│  Turn WooCommerce products into configurable, priced-live products.            │
│  ▓ Create your first option set ▓   ▒ Watch 2-min demo ▒                       │
│                                                                                │
│  ┌── stat-card ──┐ ┌── stat-card ──┐ ┌── stat-card ──┐ ┌── stat-card ──┐       │
│  │ Option sets   │ │ Products live │ │ Quotes (open) │ │ Custom orders │       │
│  │      12       │ │      34       │ │       3       │ │       7       │       │
│  └───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘       │
│    ↑ min-height reserved → KHÔNG nhảy layout khi số liệu AJAX về (fix CLS)      │
│                                                                                │
│  Recent activity ·············································· [ View all ]     │
│   • Quote #1043 accepted — 2h ago                                              │
│   • "Bag" option set updated — yesterday                                       │
│                                                                                │
│  ┌ Quick cards ────────────┬──────────────────────┬────────────────────────┐  │
│  │ ▸ Setup Wizard          │ ▸ Emails             │ ▸ Account & Plan       │  │
│  │   seed từ Woo variations │   transactional mail │   unlock cloud features │  │
│  └─────────────────────────┴──────────────────────┴────────────────────────┘  │
└────────────────────────────────────────────────────────────────────────────────┘
```
**Kỹ thuật A11:** stat cards load qua transient + AJAX skeleton, `min-height` cố định để reserve chỗ →
Cumulative Layout Shift ≈ 0. Quét 43 hardcoded hex trong `overview.css` → token.

#### 4.5 Settings tab router (FOUNDATION)

Gộp **License · System Info · About · Setup Wizard · Emails** vào 1 trang Settings tabbed, kèm **redirect map**
back-compat cho slug cũ (không vỡ bookmark/link).

**Mockup — Settings shell:**
```
┌─ Storelly › Settings ─────────────────────────────────────────────────────────┐
│  [ General ] [ Integration ] [ Emails ] [ License ] [ System ] [ About ] [Setup]│
│  ══════════                                                                     │
│  ▼ General                                                                      │
│   Request-a-Quote badge     ( ● on  ○ off )                                     │
│   Default display type      [ swatch            ▾ ]                             │
│   ...                                                                           │
│                                                            ▓ Save changes ▓     │
└────────────────────────────────────────────────────────────────────────────────┘
   old ?page=spbwc-license  ──301 nội bộ──▶  ?page=spbwc-settings&tab=license
```
**Quick wins kèm sprint:** menu SVG mask icon (thay `logo.png`) + count badge orders/quotes/B2B; gỡ breadcrumb
chồng ở option editor khi mở từ product-context.

---

### S3 · v1.10.0 — "Editor đẹp — nơi merchant sống"

**Nguồn:** `SPEC_ADMIN_UI_REDESIGN.md` U1 §5.3 + §9 wave-2.
**Vì sao:** editor option là nơi merchant dành **nhiều thời gian nhất** → trải nghiệm ở đây = retention lớn nhất.

#### 4.6 Edit Option v3 — layout two-pane

Thay layout dọc dài bằng **2 cột**: nav section bên trái + canvas chỉnh sửa bên phải + **sticky save-bar**.

**Mockup:**
```
┌─ Edit option set: "Bag customizer" ───────────────────────────── ▓ Save ▓  ▒Preview▒ ┐
│┌ sections ─────┐┌ canvas ──────────────────────────────────────────────────────────┐│
││ ▸ Fields   ●  ││  Field: Size                                              [⋮ ▾]   ││
││ ▸ Pricing     ││  ┌──────────────────────────────────────────────────────────────┐││
││ ▸ Conditions  ││  │ Type   [ Dropdown ▾ ]   Label [ Size            ]            │││
││ ▸ Quantity    ││  │ Options:  S  · M  · L  · XL              [ + Add option ]     │││
││ ▸ Display     ││  │ Price impact  ( ● none  ○ flat  ○ per-unit )                 │││
││ ▸ Apply to    ││  └──────────────────────────────────────────────────────────────┘││
││···············││  Field: Material                                          [⋮ ▾]   ││
││               ││  ┌──────────────────────────────────────────────────────────────┐││
││ + Add section ││  │ ...                                                          │││
│└───────────────┘└──────────────────────────────────────────────────────────────────┘│
│  sticky save-bar (--st-z-sticky): thay đổi chưa lưu ● ·············· ▓ Save ▓ ▒Undo▒  │
└──────────────────────────────────────────────────────────────────────────────────────┘
```
**Kèm:** control-token wave-2 → token hóa `admin-options.css` + `admin-options-v2.css` + `visual-builder.css`
(hết "island", đồng bộ toàn hệ).

---

### S4 · v1.11.0 — "Dọn nốt trang xấu"

**Nguồn:** `SPEC_ADMIN_UI_REDESIGN.md` U1 §5.2 + U3 §6.

#### 4.7 Options list redesign

**Mockup:**
```
┌─ Storelly › Option Sets ───────────────────────────────────────────────────────┐
│  Option sets                                          ▓ + New option set ▓      │
│  [ 🔎 Search…        ]  Filter: (All)(Applied)(Draft)(B2B)   Sort [ Recent ▾ ]  │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │ ☐  Name                 Applied to        Fields   Updated      Actions   │  │
│  │ ☐  Bag customizer       3 products        8        2h ago       ✎  ⧉  🗑  │  │
│  │ ☐  Business card         Category: Print   5        yesterday    ✎  ⧉  🗑  │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│  ▒ Bulk: Apply to… ▒  ▒ Duplicate ▒  ▒ Delete ▒                                 │
│  (empty state: "No option sets yet — create one or import from Woo variations") │
└─────────────────────────────────────────────────────────────────────────────────┘
```

#### 4.8 Inline raw pages → component library

Đưa **Custom Orders · Quote Settings (→ tab dưới Quotes) · System Info · About** lên hero + component pattern
chung (đây là các trang xấu nhất còn sót). Mỗi trang: hero header + card sections + token controls.

---

### S5 · v1.12.0 — "Trải nghiệm khách cuối" (Buyer canvas)

**Nguồn:** `SPEC_CANVAS_TEXT_IMAGE_TABS.md` M2 + M3.
**Vì sao:** khách của merchant có trải nghiệm tốt → merchant thấy giá trị → giữ plugin (retention gián tiếp).

#### 4.9 Canvas M2 — restore `userLayers` + clamp print-area

**Bug hiện tại:** buyer tự thêm text/ảnh (userLayers) → thêm vào cart → **quay lại edit** thì layer **biến mất**.
M2: persist + khôi phục userLayers khi cart-edit/reorder + clamp layer trong print-area per-view.

**Userflow (fix):**
```
Buyer thêm text "Team A" + logo  ──▶  Add to cart  ──▶  Edit from cart
        │                                                     │
        ▼                                                     ▼
   userLayers lưu vào cart item meta          Builder đọc lại meta → RENDER LẠI đúng "Team A" + logo
                                              (trước đây: canvas trống → mất design)
```

**Mockup — canvas mobile bottom-sheet (M3):**
```
        ┌───────────────────────────┐
        │        [product view]     │
        │      ┌───────────┐        │   ← print-area clamp: layer không kéo ra ngoài
        │      │  Team A    │        │
        │      │   🅛logo    │        │
        │      └───────────┘        │
        ├───────────────────────────┤
        │  [ Text ] [ Images ] [ ⋯ ]│   ← bottom-sheet tabs (mobile)
        │  ┌─ Text ──────────────┐  │
        │  │ [ Add text     +2$ ]│  │
        │  │ Font [ Inter ▾ ]    │  │
        │  └─────────────────────┘  │
        │            ▓ Add to cart ▓ │
        └───────────────────────────┘
```
**Kèm M3:** empty states, a11y (focus-trap), toast tràn-mép, POT regen, Plugin Check 0-error cho 2 tab mới.

---

### S6 · v1.13.0 — "Activation leap" ⚠ CẦN CHỐT SCOPE TRƯỚC

**Nguồn:** `DEV_MILESTONES_V2_WIZARD.md` M0 + M1 (+ M2/M3 các sprint sau).
**Ràng buộc cứng của doc:** KHÔNG đụng logic pricing · KHÔNG tích hợp canvas designer · schema `options.*` giữ
nguyên. Đây là blocker **quyết định** (không phải kỹ thuật) — David bật đèn xanh scope trước khi code.

**Vì sao đáng giá nhất:** giải trực tiếp điểm yếu #1 của funnel — biến "tạo option đầu tiên" từ *concept khó*
thành *wizard 4 bước*, rút ngắn time-to-first-option (đòn bẩy activation mạnh nhất).

**Userflow mục tiêu (đủ 4 bước — S6 làm M0 token + M1 skeleton/step-2, M2/M3 sprint sau):**
```
 Step 1 TEMPLATE        Step 2 FIELDS+PRICING     Step 3 PREVIEW          Step 4 APPLY & DONE
 ┌──────────────┐       ┌──────────────────┐      ┌──────────────┐        ┌──────────────────┐
 │ chọn mẫu có  │──────▶│ thêm field +     │─────▶│ live buyer   │───────▶│ gán product/     │
 │ sẵn (gallery)│       │ giá (mobile-1st) │      │ preview iframe│        │ category + Publish│
 └──────────────┘       └──────────────────┘      └──────────────┘        └──────────────────┘
      M2                    M1 (S6)                    M3                       M4
```

**Mockup — Wizard shell (M1, step 2):**
```
┌─ New option set — Step 2 of 4 ─────────────────────────── ●─●─○─○ ┐
│  Add fields & pricing                                             │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  Field 1   Type [ Dropdown ▾ ]  Label [ Size          ]     │ │
│  │            Options  S · M · L        Price  ( ● none )      │ │
│  ├─────────────────────────────────────────────────────────────┤ │
│  │  Field 2   Type [ Text ▾ ]      Label [ Name print   ]     │ │
│  │            Price  ( ○ none  ● flat [ 2.00 ] )               │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  [ + Add field ]                                                  │
│                                        ▒ Back ▒     ▓ Next → ▓    │
└───────────────────────────────────────────────────────────────────┘
```
**Kèm:** Woo-seed §14 — toggle **ẩn form variation Woo gốc** sau khi seed (hết double-UI cho merchant vừa migrate).

---

## 5. Track Monetization (backend-gated) — chèn khi `app.storelly.com` sẵn sàng

Đây là **reason-to-pay** của gói Cloud. **Không** xếp cứng vào 6 sprint trên vì phụ thuộc backend; chèn đan xen
từ ~S4 trở đi *khi từng service live*. Thứ tự là **chuỗi phụ thuộc bắt buộc**:

```
 caps engine (✓ đã có) ─┬─▶ #4 Expiry/grace + renew comms      [backend: license status]     High retention
                        │
                        ├─▶ #5 Order-sync payload options[]    [backend: Dashboard + O-3]     → nền cho #8
                        │      + Option Analytics ("Top earning option" card trên Overview)
                        │
                        ├─▶ #6 Config Snapshots (version-history option-set → giảm churn)     [backend snapshots]
                        ├─▶ #7 Design Vault (backup design cross-device, sống sót migration)   [backend vault]  ★dính nhất
                        ├─▶ #8 B2B service layer (invoice_pdf + email_trigger + analytics_b2b) [backend cron/email] ← sau #5
                        ├─▶ #9 Asset Library in-canvas (6 sample free + "Browse 500+")         [backend CDN]
                        └─▶ #10 Free marketplace + versioned template updates                  [backend templates]
```

- **Standard tier ($49) reason-to-pay:** #5, #6, #7, #9.
- **Premium tier ($99) reason-to-pay:** #8.
- **Quick-win backend-independent còn lại** (đã đưa vào S1): #1 re-point upsell, #2 watermark PDF. Item #3
  (consent inline + context-return deep-link) chỉ cần backend trả `ctx` → làm cùng #4 khi backend sẵn sàng.

**Nguồn:** `SPEC_FREEMIUM_V1_1.md` F6–F11 · `SPEC_ACCOUNT_PLAN_UX.md §5.3–5.4`.

---

## 6. Definition of Done — gate mỗi sprint (bắt buộc)

Chạy trước khi tag release (theo `CLAUDE.md` + skill `storelly-finish-task`):

- [ ] **Plugin Check** dưới slug chuẩn `storelly-product-builder-for-woocommerce` → **0 ERROR** (né ~4.5k TextDomainMismatch giả bằng cách chạy đúng folder=slug).
- [ ] **Design-token pass** cho mọi UI đụng tới: không hardcode hex/inline-style, dùng `static/css/_tokens.css`, giữ RTL.
- [ ] **Chrome smoke-test** (session riêng, skill `chrome-multi-session` + `wp-admin-login`): mở đúng trang bị ảnh hưởng, screenshot, 0 console error.
- [ ] **Compliance diff**: ABSPATH · sanitize+escape · nonce+cap · 1 prefix (`spbwc_`) · text-domain literal · `$wpdb->prepare` · enqueue · no phone-home khi chưa opt-in.
- [ ] **Version khớp 3 nơi**: readme `Stable tag` = header `Version` = git tag.
- [ ] **Changelog** 1 theme rõ + readme tags ≤ 5 + external services khai báo đủ.
- [ ] **Spec sync**: nếu đụng feature có trong `docs/SPEC_*.md` → update spec cùng thay đổi (đánh dấu milestone).
- [ ] **Xác minh wp.org**: sau deploy SVN, trunk + `tags/x.y.z` khớp; version live = version vừa tag.

---

## 7. Quyết định mở (David chốt)

1. **Thứ tự (§ giả định 1):** giữ *interleave* (V2 wizard ở S6) hay đẩy V2 wizard sớm hơn (activation-first) /
   hoãn hẳn (polish-first triệt để)?
2. **Backend readiness (§ giả định 2):** `app.storelly.com` live khi nào → quyết định chèn track §5 vào sprint nào.
3. **Scope V2 wizard (§ giả định 3):** bật đèn xanh `DEV_MILESTONES_V2_WIZARD.md` M0–M4? Giữ ràng buộc "không
   đụng pricing/canvas/schema"?
4. **Xác minh version live wp.org:** memory ghi live=1.2.5 nhưng local=1.7.0 — cần confirm trunk SVN đang ở đâu
   để biết có phải "đẩy bù" các bản đã tag trước khi bắt đầu cadence mới.

---

*Cập nhật roadmap này mỗi khi đóng 1 sprint: đánh dấu ✓, ghi ver thực đã release, dời item trượt sang sprint kế.*
