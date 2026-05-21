<?php
/**
 * Marketplace Bridge — adapts the pc-designer "launcher" module to storelly's runtime.
 * Loaded BEFORE includes/launcher/* so its symbols are available when launcher classes
 * declare hooks. Guarded for coexistence with pc-designer on the same site.
 *
 * @package Storelly
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* -------------------------------------------------------------------------
 * A. Constant aliases — map storelly constants to NBDESIGNER_* names that
 *    the launcher module from pc-designer expects.
 * ------------------------------------------------------------------------- */
if ( ! defined( 'NBDESIGNER_PLUGIN_DIR' ) ) {
    define( 'NBDESIGNER_PLUGIN_DIR', SPBWC_PB_PLUGIN_DIR );
}
if ( ! defined( 'NBDESIGNER_PLUGIN_URL' ) ) {
    define( 'NBDESIGNER_PLUGIN_URL', SPBWC_PB_PLUGIN_URL );
}
if ( ! defined( 'NBDESIGNER_VERSION' ) ) {
    define( 'NBDESIGNER_VERSION', SPBWC_PB_VERSION );
}
if ( ! defined( 'NBDESIGNER_ENABLE_NONCE' ) ) {
    define( 'NBDESIGNER_ENABLE_NONCE', SPBWC_ENABLE_NONCE );
}
if ( ! defined( 'NBDESIGNER_CUSTOMER_URL' ) ) {
    define( 'NBDESIGNER_CUSTOMER_URL', SPBWC_PB_CUSTOMER_URL );
}
if ( ! defined( 'NBDESIGNER_CUSTOMER_DIR' ) ) {
    define( 'NBDESIGNER_CUSTOMER_DIR', SPBWC_PB_CUSTOMER_DIR );
}
if ( ! defined( 'NBDESIGNER_ASSETS_URL' ) ) {
    define( 'NBDESIGNER_ASSETS_URL', SPBWC_PB_ASSETS_URL );
}
if ( ! defined( 'NBDESIGNER_UPLOAD_DIR' ) ) {
    define( 'NBDESIGNER_UPLOAD_DIR', SPBWC_PB_UPLOAD_DIR );
}
if ( ! defined( 'NBDESIGNER_UPLOAD_URL' ) ) {
    define( 'NBDESIGNER_UPLOAD_URL', SPBWC_PB_UPLOAD_URL );
}
/* Additional NBDESIGNER_* constants referenced inside the launcher module. */
if ( ! defined( 'NBDESIGNER_DATA_DIR' ) ) {
    define( 'NBDESIGNER_DATA_DIR', SPBWC_PB_DATA_DIR );
}
if ( ! defined( 'NBDESIGNER_DATA_URL' ) ) {
    define( 'NBDESIGNER_DATA_URL', SPBWC_PB_DATA_URL );
}
if ( ! defined( 'NBDESIGNER_JS_URL' ) ) {
    define( 'NBDESIGNER_JS_URL', SPBWC_PB_JS_URL );
}
if ( ! defined( 'NBDESIGNER_CSS_URL' ) ) {
    define( 'NBDESIGNER_CSS_URL', SPBWC_PB_CSS_URL );
}

/* -------------------------------------------------------------------------
 * B. Helper function getUrlPageNBD()
 *    Minimal stub. PCDesigner resolves page IDs from an options table
 *    via nbd_get_page_id(). Storelly has no equivalent, so we return a
 *    placeholder URL that downstream code can still safely add_query_arg()
 *    onto. The integration step may later swap this for a real lookup.
 * ------------------------------------------------------------------------- */
if ( ! function_exists( 'getUrlPageNBD' ) ) {
    function getUrlPageNBD( $page ) {
        $page = sanitize_key( $page );
        return home_url( '/?spbwc_marketplace_page=' . rawurlencode( $page ) . '&id=' );
    }
}

/* Some launcher code calls nbd_get_page_id() directly. Provide a tolerant stub. */
if ( ! function_exists( 'nbd_get_page_id' ) ) {
    function nbd_get_page_id( $slug ) {
        return 0;
    }
}

/* -------------------------------------------------------------------------
 * C. Hook stubs / triggers
 * ------------------------------------------------------------------------- */

/* 1) nbd_menu — launcher registers admin submenus on this action. */
add_action(
    'admin_menu',
    function () {
        do_action( 'nbd_menu' );
    },
    100
);

/* 2) nbd_admin_pages — filter that whitelists admin pages. */
add_filter(
    'nbd_admin_pages',
    function ( $pages ) {
        return is_array( $pages ) ? $pages : array();
    }
);

/* 3) nbd_create_tables — fire on plugin activation so the launcher's
 *    designer/withdraw/user-design tables get created.
 */
register_activation_hook(
    SPBWC_PB_PLUGIN_DIR . 'storelly-product-builder-for-woocommerce.php',
    function () {
        do_action( 'nbd_create_tables' );
    }
);

/* 4) nbd_after_option_product_design — NOOP filter default.
 *    The real trigger will be wired into spbwc_save_product_builder_design
 *    during the integration step.
 */
add_filter(
    'nbd_after_option_product_design',
    function ( $v ) {
        return $v;
    }
);

/* 5) Filters the launcher hooks INTO but storelly never fires.
 *    Register NOOP defaults so apply_filters() always returns sane values.
 */
add_filter( 'nbo_artwork_action', function ( $v ) { return $v; } );
add_filter( 'nbo_field_class', function ( $v ) { return $v; } );
add_filter( 'nbo_product_options', function ( $v ) { return $v; } );
add_filter( 'nbd_conditional_show_design_btn', function ( $v ) { return $v; } );

/* 6) nbd_multicheckbox_settings — default for multicheckbox values. */
add_filter(
    'nbd_multicheckbox_settings',
    function ( $v ) {
        return $v;
    }
);

/* 7) Settings framework filters — the actual rendering is performed by
 *    SPBWC_Marketplace_Settings_Adapter (Task 2). Here we just guarantee
 *    they always pass through sane defaults.
 */
add_filter(
    'nbdesigner_include_settings',
    function ( $v ) {
        return $v;
    }
);
add_filter(
    'nbdesigner_settings_tabs',
    function ( $v ) {
        return is_array( $v ) ? $v : array();
    }
);
add_filter(
    'nbdesigner_settings_blocks',
    function ( $v ) {
        return is_array( $v ) ? $v : array();
    }
);
add_filter(
    'nbdesigner_settings_options',
    function ( $v ) {
        return is_array( $v ) ? $v : array();
    }
);
add_filter(
    'nbdesigner_default_settings',
    function ( $v ) {
        return is_array( $v ) ? $v : array();
    }
);

/* -------------------------------------------------------------------------
 * D. Marketplace boot guard
 * ------------------------------------------------------------------------- */
if ( ! function_exists( 'spbwc_marketplace_is_enabled' ) ) {
    function spbwc_marketplace_is_enabled() {
        return 'yes' === get_option( 'spbwc_marketplace_enabled', 'no' );
    }
}

/* -------------------------------------------------------------------------
 * E. PCDesigner helper function stubs
 *    The launcher module calls these helpers from PCDesigner core. Storelly
 *    has equivalents under different names; provide thin wrappers.
 * ------------------------------------------------------------------------- */
if ( ! function_exists( 'nbdesigner_get_option' ) ) {
    function nbdesigner_get_option( $key, $default = '' ) {
        return get_option( $key, $default );
    }
}

if ( ! function_exists( 'nbdesigner_get_template' ) ) {
    /**
     * Render a launcher template from the plugin's templates/ directory.
     * Mirrors PCDesigner's behaviour (include with $data extracted into scope).
     *
     * @param string $template Relative path under templates/, e.g. 'launcher/store/tabs.php'.
     * @param array  $data     Variables to extract into template scope.
     */
    function nbdesigner_get_template( $template, $data = array() ) {
        $file = SPBWC_PB_PLUGIN_DIR . 'templates/' . ltrim( $template, '/' );
        if ( ! file_exists( $file ) ) {
            return;
        }
        if ( is_array( $data ) && ! empty( $data ) ) {
            extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        }
        include $file;
    }
}

if ( ! function_exists( 'nbdesigner_render_template' ) ) {
    function nbdesigner_render_template( $template, $data = array() ) {
        nbdesigner_get_template( $template, $data );
    }
}

if ( ! function_exists( 'nbdesigner_maxsize_upload_file' ) ) {
    function nbdesigner_maxsize_upload_file() {
        return wp_max_upload_size();
    }
}

if ( ! function_exists( 'nbdesigner_dimensions_unit' ) ) {
    function nbdesigner_dimensions_unit() {
        return 'px';
    }
}

/* -------------------------------------------------------------------------
 * F. NBD_Image stub
 *    The launcher's preview generator calls NBD_Image::crop_and_resize_* and
 *    NBD_Image::nbdesigner_resize_*. Storelly has SPBWC_Storelly_Image with
 *    resize_imagepng() only. Provide a pass-through stub so the preview job
 *    does not fatal — colour-variant previews simply will not be generated
 *    until the editor module is also ported.
 * ------------------------------------------------------------------------- */
if ( ! class_exists( 'NBD_Image' ) ) {
    class NBD_Image {
        public static function crop_and_resize_png_image( $file, $w, $h ) {
            return $file;
        }
        public static function crop_and_resize_jpg_image( $file, $w, $h ) {
            return $file;
        }
        public static function nbdesigner_resize_imagepng( $file, $w, $h ) {
            if ( class_exists( 'SPBWC_Storelly_Image' ) ) {
                return SPBWC_Storelly_Image::resize_imagepng( $file, $w, $h );
            }
            return $file;
        }
        public static function nbdesigner_resize_imagejpg( $file, $w, $h ) {
            return $file;
        }
    }
}
