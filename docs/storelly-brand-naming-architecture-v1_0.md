# Storelly — Brand & Naming Architecture v1.0

> **Mục đích:** Nguồn chân lý (source of truth) về định vị thương hiệu, tên sản phẩm,
> tên plugin, bộ menu WP admin và quy ước đặt tên feature cho hệ Storelly.
> Dùng chung cho **dev** (label, slug, IA) và **marketing** (positioning, tagline).
> **Trạng thái:** Approved draft — chốt qua trao đổi với David (NetbaseJSC).
> **Ràng buộc nền:** KHÔNG sửa logic pricing. Chỉ đổi *display label*, giữ nguyên
> code identifiers (`spbwc_` / `SPBWC_PB_*_SLUG` / tên bảng).

---

## 1. Vấn đề gốc — vì sao đang rối

Chữ "Storelly" đang gánh **ba vai khác bản chất** cùng lúc, gây nhầm lẫn cho cả khách lẫn dev:

| "Storelly" xuất hiện ở | Thực chất là gì |
|---|---|
| Storelly.docx (RETREATY) | **Nền tảng / công ty** — POS, site builder, HRM, CRM, store review, mobile app |
| Business Plan v1.0 | **Plugin pricing/configurator** ("Storelly Builder") |
| ERP doc | **Plugin vận hành** ("Storelly ERP") |

Ba va chạm phụ làm rối thêm: "Commerce OS" bị claim 2 lần (platform + plugin Builder); tên
code (`Storelly Product Builder`) lệch tên marketing (`Storelly Builder`); và **B2B** xuất
hiện ở cả Builder lẫn ERP nên khách không biết cài cái nào.

---

## 2. Kiến trúc thương hiệu (branded house)

Mô hình chốt: **Storelly = nền tảng (cái "OS" thật)**, các plugin WooCommerce là *app con* cắm vào nó.

| Tầng | Tên chốt | Tagline | Được gọi "Commerce OS"? |
|---|---|---|---|
| Nền tảng / SaaS | **Storelly** | The Commerce OS for retail (dashboard, license, B2B backend, AI, analytics) | ✅ chỉ ở đây |
| Plugin cấu hình SP | **Storelly Builder** | Advanced Product Options, Pricing & Configurator for WooCommerce | ❌ |
| Plugin vận hành | **Storelly ERP** | B2B Inventory & Multi-Location POS for WooCommerce | ❌ |
| Sản phẩm anh em | **Printcart** | Print + Canvas Designer (superset có canvas) | — |

**Quy tắc một câu để dứt điểm "Commerce OS":** *platform là OS, plugin là app chạy trên OS.*
Builder/ERP chạy standalone được (license qua Storelly API), kết nối platform để mở Cloud/AI/B2B nâng cao.

**Phân vai B2B (giải quyết chồng lấn):**
- *Storelly Builder* → **B2B front-of-store**: tier pricing, quote-to-cash, catalog theo nhóm khách (thứ buyer chạm vào).
- *Storelly ERP* → **B2B back-of-store**: tồn kho đa kho, purchasing, POS.
- *B2B sâu* (net terms, approval chain, punchout, SAP/NetSuite) → sống ở **Storelly Cloud** (platform), cả hai plugin cùng surface.

---

## 3. Product thesis — bản chất plugin Storelly Builder

> **Storelly Builder giúp Buyer "build" một custom product rồi gửi cho Seller — chỉ khác ở *cách build*.**

Ba **build methods** song song, là xương sống của IA:

| Build method | Buyer build bằng cách | Menu tương ứng |
|---|---|---|
| **Pricing Options** | Chọn option có giá (dropdown/swatch/input) + live price | Pricing Options |
| **Visual Builder** | Chọn trực quan theo **ảnh thật** (decals/màu/component → ảnh đổi ngay) | Visual Builder |
| **Quote Requests** | Gửi yêu cầu báo giá (B2B / đơn lớn) | Quote Requests |

Đây là lý do "Product Builder" bị đổi thành **Visual Builder**: cả plugin đều "build product",
nên cần gọi đúng *phương thức* (visual / theo ảnh) thay vì tên chung chung.

---

## 4. Quyết định tên plugin

| Hạng mục | Chốt | Ghi chú |
|---|---|---|
| Brand ngắn (nói/viết) | **Storelly Builder** | — |
| Display name WP.org | `Storelly Builder – Product Options, Pricing & B2B for WooCommerce` | WP.org thích title nhiều keyword |
| Code slug | `storelly-product-builder-for-woocommerce` | **GIỮ NGUYÊN** — slug WP.org gần như không đổi được sau submit |
| Prefix | `spbwc_` / `SPBWC_` | giữ nguyên |
| Text domain | `storelly-product-builder-for-woocommerce` | giữ nguyên |

⚠️ Đang ở **pre-launch (Q1)** — đây là thời điểm cuối để đổi slug nếu muốn khớp brand
(`storelly-builder`). Nếu ngại refactor → giữ slug code, chỉ chỉnh Display Name trong plugin header.

---

## 5. Bộ menu WP admin — Storelly Builder

**Quy ước:** mỗi submenu **đúng 2 từ**. Chỉ đổi *display label*, giữ nguyên slug constants.
Item bị khoá vẫn **hiện** dưới dạng teaser 🔒 + tooltip "Upgrade to unlock" (cú PLG).

### 5.1 Thứ tự khuyến nghị (đã nhóm)

```
📦 Storelly Builder
├─ Overview Dashboard      (landing — counts · license · quota)
│  ── Build methods ──
├─ Pricing Options         build method 1 — option + giá  (+New/Edit → Option Wizard)
├─ Visual Builder          build method 2 — configurator theo ảnh thật (bike-builder)
├─ Quote Requests          build method 3 — quote-to-cash
│  ── Quản lý ──
├─ Linked Products         product ↔ build method linkage
├─ Options Templates       kho template của Pricing Options
├─ Custom Orders           mọi order có Options
├─ Design Files            file khách tạo từ Visual Builder
├─ B2B Clients             quản lý khách B2B
│  ── Cấu hình & onboarding ──
├─ Custom Fonts            font cho text personalization
├─ General Settings        cart/checkout · frontend · advanced
├─ Setup Wizard            cài sample products + onboarding lần đầu
└─ License Plan            activation + upgrade CTA
```

### 5.2 Bảng đổi tên (current → new) + nguồn

| Hiện tại | Mới (2 từ) | Nội dung | Slug giữ |
|---|---|---|---|
| Overview | **Overview Dashboard** | dashboard | `SPBWC_PB_OVERVIEW_SLUG` |
| Pricing Options | **Pricing Options** | build method 1 | `SPBWC_PB_BUILDER_SLUG` |
| *(mới)* | **Visual Builder** | build method 2 | `SPBWC_PB_VISUAL_BUILDER_SLUG` *(thêm mới)* |
| Quotes | **Quote Requests** | build method 3 | quote slug hiện có |
| Products | **Linked Products** | linkage | `SPBWC_PB_PRODUCTS_SLUG` |
| Template Library | **Options Templates** | template options | template slug hiện có |
| Orders | **Custom Orders** | order có options | `SPBWC_PB_ORDERS_SLUG` |
| Designs | **Design Files** | output Visual Builder | designs slug hiện có |
| Marketplace Settings | **B2B Clients** | khách B2B | marketplace slug hiện có |
| Fonts | **Custom Fonts** | font personalization | fonts slug hiện có |
| Settings | **General Settings** | settings | `SPBWC_PB_OPTIONS_SLUG` |
| Global Import | **Setup Wizard** | sample + onboarding | global-import slug hiện có |
| License | **License Plan** | license + upgrade | `SPBWC_PB_LICENSE_SLUG` |

> **Visual Builder (mới):** trỏ tới assets `views/product-builder/` sẵn có; thêm một slug
> constant riêng, KHÔNG đụng logic pricing.

### 5.3 Hiển thị theo tier (PLG)

| Submenu | Free | Pro | Cloud |
|---|---|---|---|
| Overview Dashboard | ✅ | ✅ | ✅ |
| Pricing Options | ✅ (max 3 SP, dropdown/swatch) | ✅ unlimited + 30+ field types | ✅ |
| Visual Builder | 🔒 teaser | ✅ 2D configurator | ✅ + 3D (Y2) |
| Quote Requests | 🔒 teaser | ✅ basic (PDF + email) | ✅ quote-to-cash |
| Linked Products | ✅ | ✅ | ✅ |
| Options Templates | ✅ (3 presets) | ✅ 12+ presets | ✅ + marketplace |
| Custom Orders | ✅ (detail >5 khoá) | ✅ | ✅ |
| Design Files | — (theo Visual Builder) | ✅ | ✅ |
| B2B Clients | 🔒 | 🔒 (3 tiers basic) | ✅ unlimited + net terms + approvals |
| Custom Fonts | ✅ | ✅ | ✅ |
| General Settings · Setup Wizard · License Plan | ✅ | ✅ | ✅ |

*(Sales Analytics chưa có trong menu hiện tại — bổ sung khi mở Cloud, xem §8.)*

---

## 6. Quy ước đặt tên feature

| Feature | Tên chốt | Tránh dùng | Lý do |
|---|---|---|---|
| Theming/appearance toàn cục | **Storefront Studio** | ❌ "Designer Studio" | Trùng & gợi nhầm Canvas Designer (đã gỡ); Storelly *không* có freeform canvas |
| Visual configurator theo ảnh | **Visual Builder** | ❌ "Product Builder" (chung chung) | Gọi đúng phương thức build |
| Luồng tạo/sửa một option | **Option Wizard** | — | Mở từ Pricing Options (M1–M5), KHÔNG phải menu |
| Onboarding + sample install | **Setup Wizard** | — | Là menu (đổi từ Global Import) |
| Object code (option/table/prefix) | giữ nguyên | đừng đổi | "Pricing Option", `spbwc_`, tên bảng |

⚠️ **Hai "wizard" khác nhau — đừng nhầm:** *Setup Wizard* (onboarding, sample product) vs
*Option Wizard* (tạo từng option). Micro-copy phải phân biệt rõ.

---

## 7. Core user flows (theo sản phẩm)

**Storelly Builder — CORE (doanh thu):**
Buyer cấu hình (qua Pricing Options *hoặc* Visual Builder) → live price → Add to Cart → Order.
Nhánh visual: mở fullscreen → chọn theo ảnh thật → DONE → mang selection + giá vào cart.

**Storelly Builder — activation:** Merchant tạo option qua **Option Wizard** (từ Pricing Options).
Quyết định adoption — KPI: time-to-first-product ≤ 3 phút, wizard completion ≥ 80%.

**Storelly Builder — onboarding:** **Setup Wizard** cài sample products/templates + kích hoạt license.

**Quote-to-cash (B2B/Cloud):** enquiry → quote draft → admin edit → PDF branded → accept → order → net-terms invoice.

**Storelly ERP — CORE (ops):** Connect Woo ↔ Storelly → choose sync → first sync → ongoing sync. Giá trị = stock accuracy.

---

## 8. Open questions

| # | Câu hỏi | Trạng thái |
|---|---|---|
| 1 | Trademark "Storelly" (WIPO/USPTO) có xung đột? | Theo Business Plan §B — cần check Q1 |
| 2 | Nếu cài cả Builder + ERP: 2 top-level "Storelly Builder"/"Storelly ERP" (giờ) hay 1 top-level "Storelly" chung (về sau)? | Khuyến nghị: tách giờ, hợp nhất sau |
| 3 | WP.org slug: giữ `storelly-product-builder...` hay đổi `storelly-builder` trước submit? | Chốt trước Q1 submission |
| 4 | Sales Analytics đặt ở đâu trong menu khi mở Cloud? | Bổ sung khi launch Cloud |
| 5 | Còn cần "Custom Fonts" khi đã gỡ Canvas? | Giữ cho text personalization; có thể gộp vào Settings sau |

---

## Appendix — nguồn tham chiếu

- `Storelly-Business-Plan-v1_0.docx` — định vị, tier, persona, competitive
- `Storelly_Builder_WooCommerce_Plugin.docx` + `___Roadmap.docx` — vision & menu gốc
- `Storelly_ERP_WooCommerce_Plugin.docx` — plugin vận hành
- `Storelly.docx` — vision nền tảng (RETREATY)
- `storelly_product_builder_v1_0SPEC.md` — kiến trúc kỹ thuật, slug constants
- `storelly-flows-spec.md` — core flow buyer
- `printing-options-technical-spec.md` — field types 20/21/22 (Visual Builder)
- Milestone doc V2 (Wizard + Designer Studio) — bối cảnh redesign

*— End of v1.0 —*
