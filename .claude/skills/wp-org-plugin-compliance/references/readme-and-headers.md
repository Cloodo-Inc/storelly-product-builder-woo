# Reference: readme.txt + Plugin Headers

---

## Template readme.txt chuẩn

```
=== Plugin Name ===
Contributors: yourwporgusername
Donate link: https://example.com/donate
Tags: tag1, tag2, tag3, tag4, tag5
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.2.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mô tả ngắn dưới 150 ký tự, một dòng. Đây là dòng hiện ở kết quả tìm kiếm.

== Description ==

Mô tả đầy đủ. Markdown-lite được hỗ trợ. Nêu rõ plugin làm gì, cho ai.

== External services ==

(BẮT BUỘC nếu plugin gọi service ngoài.)
Plugin này kết nối tới [Tên dịch vụ] để [mục đích].
- Gửi dữ liệu gì, khi nào: ...
- Service owner: ...
- Privacy Policy: https://...
- Terms of Service: https://...

== Installation ==

1. Upload thư mục plugin vào /wp-content/plugins/
2. Kích hoạt qua menu Plugins
3. ...

== Frequently Asked Questions ==

= Câu hỏi? =
Trả lời.

== Screenshots ==

1. Mô tả screenshot-1.png
2. Mô tả screenshot-2.png

== Changelog ==

= 1.2.6 =
* Fixed: ...
* Security: ...

= 1.2.5 =
* ...

== Upgrade Notice ==

= 1.2.6 =
Bản vá bảo mật, nên cập nhật.
```

---

## Quy tắc các field

| Field | Bắt buộc | Lưu ý |
|---|---|---|
| Contributors | Có | username wordpress.org, cách nhau dấu phẩy |
| Tags | Có | TỐI ĐA 5 tag. Quá 5 bị cắt. Không tag thương hiệu/đối thủ |
| Requires at least | Có | version WP tối thiểu |
| Tested up to | Có | version WP cao nhất đã test — cập nhật mỗi release lớn |
| Stable tag | Có | PHẢI khớp tag SVN + Version header |
| Requires PHP | Nên | đặt thực tế (7.4+ khuyên dùng) |
| License | Có | GPL-compatible bắt buộc |
| Short description | Có | < 150 ký tự, ngay dưới header |

---

## Plugin header (file PHP chính)

```php
<?php
/**
 * Plugin Name:       Storelly Product Builder for WooCommerce
 * Plugin URI:        https://storelly.com/product-builder
 * Description:       Mô tả ngắn gọn chức năng.
 * Version:           1.2.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Storelly Team
 * Author URI:        https://storelly.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       storelly-product-builder-for-woocommerce
 * Domain Path:       /languages
 *
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0.0
 * WC tested up to:   9.x
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

---

## Ba con số version phải KHỚP (lỗi block phổ biến nhất)

```
readme.txt   →  Stable tag: 1.2.6
plugin.php   →  Version: 1.2.6
SVN          →  tags/1.2.6/
Git tag      →  1.2.6   (nếu dùng auto-deploy)
```

Nếu lệch → Plugin Check block submission, hoặc wordpress.org không nhận version mới.

---

## Assets (banner/icon/screenshot)

KHÔNG nằm trong ZIP plugin. Nằm ở thư mục `assets/` riêng trong SVN:

```
assets/
  banner-772x250.png        (banner thường)
  banner-1544x500.png       (banner retina)
  icon-128x128.png
  icon-256x256.png
  screenshot-1.png          (khớp thứ tự mô tả trong readme == Screenshots ==)
  screenshot-2.png
```

Với GitHub Actions auto-deploy: đặt assets trong `.wordpress-org/` ở repo, action sẽ tự đồng bộ
sang `assets/` của SVN. Có thể deploy assets KHÔNG cần release qua workflow `asset-update` riêng
(xem `plugin-check-and-submit.md` mục G).

### Bẫy: caption ↔ Stable tag (TÁCH BIỆT file ảnh vs caption)
- **File ảnh** (`screenshot-N.png`, banner, icon) lên SVN `assets/` ngay khi push (asset-update),
  độc lập version.
- **Caption + SỐ LƯỢNG screenshot hiển thị** lại đọc từ mục `== Screenshots ==` trong readme.txt
  của **bản STABLE tag đang live** — KHÔNG phải trunk, KHÔNG phải file ảnh.
- Hệ quả: đẩy 10 file ảnh nhưng readme stable cũ chỉ liệt kê 3 caption → wp.org chỉ hiện 3 ảnh đầu,
  dưới caption CŨ. Muốn 10 ảnh + caption mới lên → readme mới phải là bản stable ⇒ **cắt release
  tag**. Mọi thay đổi CHỮ trong readme (short desc, Description, caption) chỉ lên sóng theo tag mới.
- Số caption `N.` PHẢI = số file `screenshot-N`. Caption thừa không có file → không hiện gì.

### External services: khai ĐÚNG thực tế, đừng khai thừa
- Library nhúng **bundled local** (vd Vue.js ở `static/libs/vue.global.prod.js`) → KHÔNG khai như
  "external service / CDN". Khai một CDN không gọi runtime = sai, reviewer dễ nghi. Liệt kê ở mục
  `== Third-party resources ==` (bundled) thay vì `== External services ==`.
- Chỉ khai ở `== External services ==` cái plugin THỰC SỰ gọi ra ngoài (verify bằng `grep` URL trong
  code). Mô tả "dùng để làm gì" phải đúng chỗ dùng (vd Google Fonts dùng cho PDF export, không phải
  "admin styling" nếu URL nằm ở class export PDF).
