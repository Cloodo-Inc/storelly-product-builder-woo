# Reference: Code Compliance

Chi tiết 8 nhóm bắt buộc, mỗi nhóm có ví dụ ĐÚNG vs SAI. Đây là nơi 95% plugin bị reviewer sửa.

---

## 1. No direct file access

Mọi file `.php` phải chặn truy cập trực tiếp ở dòng đầu:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
```

---

## 2. Sanitize input — Escape output

Quy tắc: **làm sạch khi NHẬN, escape khi IN RA.** Hai việc khác nhau, phải làm cả hai.

SAI:
```php
echo $_POST['title'];                          // không sanitize, không escape
update_option( 'my_opt', $_POST['value'] );    // lưu raw input
```

ĐÚNG:
```php
// Nhận input
$title = isset( $_POST['title'] )
    ? sanitize_text_field( wp_unslash( $_POST['title'] ) )
    : '';

// Lưu
update_option( 'myplugin_opt', sanitize_text_field( wp_unslash( $_POST['value'] ) ) );

// In ra
echo esc_html( $title );
echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
printf( esc_html__( 'Hello %s', 'myplugin' ), esc_html( $name ) );
```

Bảng hàm hay dùng:

| Khi nhận | Khi in ra |
|---|---|
| `sanitize_text_field()` | `esc_html()` |
| `sanitize_email()` | `esc_attr()` (trong thuộc tính HTML) |
| `sanitize_textarea_field()` | `esc_url()` |
| `absint()` / `intval()` | `esc_js()` |
| `wp_kses_post()` (cho HTML giàu) | `wp_kses_post()` |
| `wp_unslash()` (gỡ slash WP thêm) | `esc_textarea()` |

---

## 3. Nonce + Capability

Mọi form, AJAX, hành động ghi dữ liệu phải có CẢ HAI: verify nonce + check quyền.

ĐÚNG:
```php
// Khi render form
wp_nonce_field( 'myplugin_save_action', 'myplugin_nonce' );

// Khi xử lý
if ( ! isset( $_POST['myplugin_nonce'] )
    || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['myplugin_nonce'] ) ), 'myplugin_save_action' ) ) {
    wp_die( esc_html__( 'Security check failed', 'myplugin' ) );
}
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Insufficient permissions', 'myplugin' ) );
}
```

AJAX:
```php
add_action( 'wp_ajax_myplugin_do', 'myplugin_ajax_handler' );
function myplugin_ajax_handler() {
    check_ajax_referer( 'myplugin_ajax', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'forbidden', 403 );
    }
    // ... xử lý
}
```

---

## 4. Unique prefix

Mọi thứ global phải có prefix riêng ≥4 ký tự, KHÔNG generic (`wp_`, `_`, `__`, `myplugin`-quá-chung).

SAI:
```php
function get_data() {}              // collision dễ
add_option( 'settings', ... );      // generic
class Helper {}                     // tên chung
```

ĐÚNG:
```php
function spbwc_get_data() {}
add_option( 'spbwc_settings', ... );
class SPBWC_Helper {}
add_action( 'init', 'spbwc_init' );
$GLOBALS['spbwc_state'] = ...;
```

> Với codebase fork: KHÔNG để 2 prefix lẫn nhau. Nếu thấy `spbwc_` ngoài + `pcpb_` trong →
> reviewer nghi clone. Thống nhất về MỘT prefix. Dùng find/replace cẩn thận (tránh đụng meta key
> đã lưu trong DB của khách hiện tại — cần migration nếu đổi key).

---

## 5. Internationalization (i18n)

- Text domain PHẢI khớp slug plugin chính xác.
- KHÔNG dùng biến/hằng trong tham số đầu của `__()`, `_e()`...

SAI:
```php
__( $message, 'myplugin' );           // biến — tool không trích xuất được
_e( "Hello $name", 'my-plugin' );     // nội suy biến
__( 'Save', $textdomain );            // text domain là biến
```

ĐÚNG:
```php
esc_html__( 'Save changes', 'storelly-product-builder-for-woocommerce' );
printf(
    /* translators: %s: tên người dùng */
    esc_html__( 'Hello %s', 'storelly-product-builder-for-woocommerce' ),
    esc_html( $name )
);
```

---

## 6. Không phone-home khi chưa opt-in

KHÔNG gửi dữ liệu site (URL, email admin, API key, đơn hàng...) về server bên ngoài khi user
CHƯA đồng ý rõ ràng. Đây là lý do reject/gỡ rất phổ biến.

ĐÚNG (opt-in pattern):
```php
$settings = get_option( 'myplugin_settings', array() );
$enabled  = isset( $settings['enable_api_sync'] ) && 'yes' === $settings['enable_api_sync'];
if ( ! $enabled ) {
    return; // không làm gì nếu user chưa bật
}
// chỉ gửi khi đã opt-in
```

Mọi external service PHẢI khai báo trong readme.txt mục "External services": gửi gì, khi nào,
tới đâu, link privacy policy + ToS của dịch vụ đó.

---

## 7. Enqueue scripts/styles đúng

SAI:
```php
echo '<script src="' . $url . '/app.js"></script>';   // hardcode
echo '<style>...</style>';                              // inline cứng
```

ĐÚNG:
```php
add_action( 'wp_enqueue_scripts', 'myplugin_assets' );
function myplugin_assets() {
    wp_enqueue_script(
        'myplugin-app',
        plugins_url( 'static/js/app.js', __FILE__ ),
        array( 'jquery' ),
        '1.2.6',          // version để cache-busting
        true              // load ở footer
    );
    wp_enqueue_style( 'myplugin-style', plugins_url( 'static/css/app.css', __FILE__ ), array(), '1.2.6' );
}
```

Truyền dữ liệu PHP → JS bằng `wp_localize_script()` hoặc `wp_add_inline_script()`, không nhúng tay.

---

## 8. SQL an toàn

Mọi query có biến phải dùng `$wpdb->prepare()`.

SAI:
```php
$wpdb->get_results( "SELECT * FROM {$table} WHERE id = $id" );
```

ĐÚNG:
```php
$wpdb->get_results(
    $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
);
```

`%d` cho số nguyên, `%f` cho số thực, `%s` cho chuỗi. Tên bảng không placeholder được — nhưng phải
build từ `$wpdb->prefix`, không từ input.

---

## Checklist nhanh Phase 1

- [ ] Mọi file PHP có `if ( ! defined( 'ABSPATH' ) ) exit;`
- [ ] Mọi `echo`/output dữ liệu động đã `esc_*()`
- [ ] Mọi input đã `sanitize_*()` + `wp_unslash()`
- [ ] Mọi form/AJAX có nonce + `current_user_can()`
- [ ] Prefix nhất quán, không generic, không lẫn 2 prefix
- [ ] Text domain khớp slug, không biến trong `__()`
- [ ] Không phone-home khi chưa opt-in; external service khai báo đủ trong readme
- [ ] Assets dùng `wp_enqueue_*()`
- [ ] SQL có biến dùng `$wpdb->prepare()`
