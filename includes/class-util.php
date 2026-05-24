<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('SPBWC_Storelly_PB_Util')) {
    class SPBWC_Storelly_PB_Util
    {
        public function __construct()
        {
            //TODO
        }
        public static function spbwc_get_page_id($page)
        {
            $page = get_option('spbwc_' . $page . '_page_id');
            return $page ? absint($page) : -1;
        }
        /**
         * Get number of decimals for option prices.
         * Uses spbwc_number_of_decimals if set, otherwise WooCommerce default.
         *
         * @return int
         */
        public static function spbwc_get_option_decimals()
        {
            $val = get_option('spbwc_number_of_decimals', '');
            if ('' !== $val && is_numeric($val)) {
                return min(6, max(0, absint($val)));
            }
            return function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        }
        public static function spbwc_get_url_page($page)
        {
            switch ($page) {
                case 'product_builder':
                    $post = self::spbwc_get_page_id('product_builder');
                    break;
                default:
                    $post = self::spbwc_get_page_id($page);
                    break;
            }
            return get_post($post) ? get_page_link($post) : '#';
        }
        public static function spbwc_get_max_input_var()
        {
            return abs(intval(ini_get('max_input_vars')));
        }
        public static function spbwc_get_max_upload_default()
        {
            if (function_exists('wp_max_upload_size')) {
                return round(wp_max_upload_size() / 1024 / 1024);
            } else {
                return abs(intval(ini_get('post_max_size')));
            }
        }
        public static function spbwc_get_image_thumbnail($id, $size = 'thumbnail')
        {
            if (absint($id) != 0) {
                $image = wp_get_attachment_image_src($id, $size);
                if (!$image) {
                    $image_url = wp_get_attachment_url($id);
                } else {
                    $image_url = $image[0];
                }
            } else {
                $image_url = SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
            }
            return $image_url;
        }

        /**
         * Deterministic accent color for an option title.
         *
         * Uses a small Storelly-aligned palette and indexes by char code so
         * the same title always renders the same color across list rows and
         * the edit-page hero.
         *
         * @param string $seed Any string (title, slug…).
         * @return string `#rrggbb`.
         */
        public static function spbwc_option_color($seed)
        {
            $palette = array('#1d4ed8', '#8b5cf6', '#ec4899', '#f97316', '#16a34a', '#0ea5e9', '#f59e0b', '#6366f1');
            $seed    = is_string($seed) ? $seed : '';
            $code    = $seed !== '' ? ord(mb_substr($seed, 0, 1)) : 0;
            return $palette[$code % count($palette)];
        }

        /**
         * Generate a deterministic inline SVG thumbnail for a pricing option.
         *
         * Pulls the first multi-choice field with attributes and renders up
         * to 4 swatches in a 2x2 grid. If no swatch data is available, falls
         * back to a brand-color tile with the title initial.
         *
         * Output is a self-contained SVG (no remote URLs). Safe to echo with
         * wp_kses on an SVG-friendly whitelist or print raw inside admin pages.
         *
         * @param array $option_row Row from `{$wpdb->prefix}storelly_product_builder_options`.
         *                          Expects keys: `title`, `fields` (serialized).
         * @param int   $size       Pixel size for the rendered SVG box. Default 64.
         * @return string SVG markup.
         */
        public static function spbwc_render_option_thumbnail($option_row, $size = 64)
        {
            $size  = max(32, absint($size));
            $title = isset($option_row['title']) && is_string($option_row['title']) ? $option_row['title'] : '';

            $swatches = array();
            // Accept fields as either a serialized string (from DB) or a pre-parsed
            // array (when the caller already unserialized to avoid a second round-trip).
            if ( isset( $option_row['fields'] ) ) {
                // @codingStandardsIgnoreLine PHPCS does not understand maybe_unserialize on row blobs.
                $raw = is_string( $option_row['fields'] )
                    ? maybe_unserialize( $option_row['fields'] )
                    : $option_row['fields'];
                if (is_array($raw) && isset($raw['fields']) && is_array($raw['fields'])) {
                    foreach ($raw['fields'] as $field) {
                        if (!is_array($field) || empty($field['general']['data_type']['value'])) {
                            continue;
                        }
                        if ($field['general']['data_type']['value'] !== 'm') {
                            continue;
                        }
                        $attrs = isset($field['general']['attributes']['value']) ? $field['general']['attributes']['value'] : null;
                        if (!is_array($attrs)) {
                            continue;
                        }
                        foreach ($attrs as $attr) {
                            if (count($swatches) >= 4) {
                                break 2;
                            }
                            $color = isset($attr['color']) ? $attr['color'] : '';
                            $image = isset($attr['image_url']) ? $attr['image_url'] : '';
                            // Skip empty white placeholders unless we have nothing
                            if ($color === '#ffffff' && empty($image)) {
                                $color = '';
                            }
                            if ($color === '' && empty($image) && empty($attr['name'])) {
                                continue;
                            }
                            $swatches[] = array(
                                'color' => $color,
                                'image' => $image,
                                'name'  => isset($attr['name']) ? $attr['name'] : '',
                            );
                        }
                    }
                }
            }

            $accent  = self::spbwc_option_color($title);
            $initial = $title !== '' ? mb_strtoupper(mb_substr($title, 0, 1)) : '?';

            $svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . esc_attr((string) $size) . '" height="' . esc_attr((string) $size) . '" viewBox="0 0 64 64" role="img" aria-label="' . esc_attr($title) . '">';
            $svg .= '<rect width="64" height="64" rx="10" fill="' . esc_attr($accent) . '" opacity="0.12"/>';

            if (count($swatches) === 0) {
                // Fallback — brand tile + initial.
                $svg .= '<rect x="6" y="6" width="52" height="52" rx="8" fill="' . esc_attr($accent) . '"/>';
                $svg .= '<text x="32" y="40" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif" font-size="26" font-weight="700" text-anchor="middle" fill="#ffffff">' . esc_html($initial) . '</text>';
            } else {
                // 2x2 grid; cells default to brand tint if attribute lacks a color.
                $cells = 4;
                $positions = array(
                    array(6, 6),
                    array(33, 6),
                    array(6, 33),
                    array(33, 33),
                );
                for ($i = 0; $i < $cells; $i++) {
                    list($x, $y) = $positions[$i];
                    if (isset($swatches[$i])) {
                        $s     = $swatches[$i];
                        $color = $s['color'] !== '' ? $s['color'] : $accent;
                        if ($s['image'] !== '') {
                            $svg .= '<defs><pattern id="spbwc-thumb-img-' . esc_attr((string) $i) . '" patternUnits="userSpaceOnUse" x="' . esc_attr((string) $x) . '" y="' . esc_attr((string) $y) . '" width="25" height="25">';
                            $svg .= '<image href="' . esc_url($s['image']) . '" x="0" y="0" width="25" height="25" preserveAspectRatio="xMidYMid slice"/>';
                            $svg .= '</pattern></defs>';
                            $svg .= '<rect x="' . esc_attr((string) $x) . '" y="' . esc_attr((string) $y) . '" width="25" height="25" rx="3" fill="url(#spbwc-thumb-img-' . esc_attr((string) $i) . ')"/>';
                        } else {
                            $svg .= '<rect x="' . esc_attr((string) $x) . '" y="' . esc_attr((string) $y) . '" width="25" height="25" rx="3" fill="' . esc_attr($color) . '"/>';
                        }
                    } else {
                        $svg .= '<rect x="' . esc_attr((string) $x) . '" y="' . esc_attr((string) $y) . '" width="25" height="25" rx="3" fill="' . esc_attr($accent) . '" opacity="0.18"/>';
                    }
                }
            }

            $svg .= '</svg>';
            return $svg;
        }
        public static function spbwc_custom_notices($command, $mes = '')
        {
            switch ($command) {
                case 'success':
                    if (!$mes)
                        $mes = esc_html__('Your settings have been saved.', 'storelly-product-builder-for-woocommerce');
                    $notice = '<div class="updated notice notice-success is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'storelly-product-builder-for-woocommerce') . '</span>
                                </button>
                            </div>';
                    break;
                case 'error':
                    if (!$mes)
                        $mes = esc_html__('Irks! An error has occurred.', 'storelly-product-builder-for-woocommerce');
                    $notice = '<div class="notice notice-error is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'storelly-product-builder-for-woocommerce') . '</span>
                                </button>
                            </div>';
                    break;
                case 'notices':
                    if (!$mes)
                        $mes = esc_html__('Irks! An error has occurred.', 'storelly-product-builder-for-woocommerce');
                    $notice = '<div class="notice notice-warning">
                                <p>' . $mes . '</p>
                            </div>';
                    break;
                case 'warning':
                    if (!$mes)
                        $mes = esc_html__('Warning.', 'storelly-product-builder-for-woocommerce');
                    $notice = '<div class="notice notice-warning is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'storelly-product-builder-for-woocommerce') . '</span>
                                </button>
                            </div>';
                    break;
                default:
                    $notice = '';
            }
            return $notice;
        }
        public static function spbwc_locate_template($template_name, $template_path = '', $default_path = '')
        {
            // Set variable to search in pc-product-builder folder of theme.
            if (!$template_path):
                $template_path = 'pc-product-builder/';
            endif;
            // Set default plugin templates path.
            if (!$default_path):
                $default_path = SPBWC_PB_PLUGIN_DIR . 'templates/'; // Path to the template folder
            endif;
            // Search template file in theme folder.
            $template = locate_template(array(
                $template_path . $template_name,
                $template_name
            ));
            // Get plugins template file.
            if (!$template):
                $template = $default_path . $template_name;
            endif;
            return apply_filters('spbwc_locate_template', $template, $template_name, $template_path, $default_path);
        }
        public static function spbwc_get_template( $template_name, $args = array(), $tempate_path = '', $default_path = '' ) {
            if ( is_array( $args ) && isset( $args ) ) {
                extract( $args );
            }
        
            $template_file = self::spbwc_locate_template( $template_name, $tempate_path, $default_path );
        
            if ( ! file_exists( $template_file ) ) {
                $message = sprintf(
                    /* translators: %1$s: Template file path. */
                    esc_html__( '%1$s does not exist.', 'storelly-product-builder-for-woocommerce' ),
                    esc_html( $template_file )
                );
        
                _doing_it_wrong(__FUNCTION__, esc_html($message), '1.3.1');
                return;
            }
        
            include $template_file;
        }
        public static function spbwc_is_product_builder_page()
        {
            return is_page(self::spbwc_get_page_id('product_builder'));
        }

        public static function spbwc_is_product_builder($id)
        {
            $id = self::spbwc_get_wpml_original_id($id);
            $option_id = STORELLY_FRONTEND_OPTIONS::get_product_option($id);
            if ($option_id)
                return true;
            return false;
        }
        public static function spbwc_get_wpml_original_id($id, $type = 'post', $current_lang = false)
        {
            if (class_exists('SitePress')) {
                global $sitepress; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $sitepress from WPML.
                $langcode = $sitepress->get_default_language();
                if ($current_lang) {
                    $langcode = $sitepress->get_current_language();
                }
                if (function_exists('icl_object_id')) {
                    $id = icl_object_id($id, $type, true, $langcode);
                }
            }
            return $id;
        }
        public static function spbwc_get_redirect_url()
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading readonly query arg for redirect helper.
            $rd = isset($_GET['rd']) ? wc_clean( sanitize_text_field( wp_unslash( $_GET['rd'] ) ) ) : '';
            switch ($rd) {
                case 'print_option':
                    $get = array(
                        'action' => 'edit',
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Readonly query args for redirect helper.
                        'id' => isset($_GET['oid']) ? absint( wp_unslash( $_GET['oid'] ) ) : '',
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Readonly query args for redirect helper.
                        'paged' => isset($_GET['paged']) ? absint( wp_unslash( $_GET['paged'] ) ) : '',
                    );
                    $redirect_url = add_query_arg($get, admin_url('admin.php?page=' . SPBWC_PB_BUILDER_SLUG));
                    break;
                default:
                    // Sanitize redirect URL to prevent injection attacks
                    $redirect_url = esc_url_raw( $rd );
                    break;
            }
            return apply_filters('storelly_redirect_url', $redirect_url);
        }
        public static function spbwc_get_product_pre_builder($option_id, $pcpb_cart_item_key)
        {
            $data = array();
            if ($pcpb_cart_item_key != '') {
                $cart_item = WC()->cart->get_cart_item($pcpb_cart_item_key);
                if (isset($cart_item['pcpb_meta'])) {
                    $builder_folder = $cart_item['pcpb_meta']['pcpb'];
                    $path = SPBWC_PB_CUSTOMER_DIR . '/' . $builder_folder;
                    $data['config'] = self::spbwc_get_data_from_json($path . '/config.json');
                    $data['design'] = self::spbwc_get_data_from_json($path . '/design.json');
                }
            } else {
                global $wpdb; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Global variable $wpdb.
                $table_name = $wpdb->prefix . 'storelly_product_builder_options';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name uses $wpdb->prefix and is trusted.
                $options = $wpdb->get_results($wpdb->prepare("SELECT builder FROM {$table_name} WHERE `id` = %d", $option_id), 'ARRAY_A');
                if (isset($options[0])) {
                    $builder_folder = $options[0]['builder'];
                    if ($builder_folder) {
                        $path = SPBWC_PB_CUSTOMER_DIR . '/' . $builder_folder;
                        $data['config'] = self::spbwc_get_data_from_json($path . '/config.json');
                        $data['design'] = self::spbwc_get_data_from_json($path . '/design.json');
                    }
                }
            }
            return $data;
        }
        public static function spbwc_get_image_thumbnail_pritcart($id, $size = 'thumbnail')
        {
            if (absint($id) != 0) {
                $image = wp_get_attachment_image_src($id, $size);
                if (!$image) {
                    $image_url = wp_get_attachment_url($id);
                } else {
                    $image_url = $image[0];
                }
            } else {
                $image_url = SPBWC_PB_ASSETS_URL . 'images/placeholder.png';
            }
            return $image_url;
        }
        public static function spbwc_get_data_from_json($path = '')
        {
            if ( ! file_exists( $path ) ) {
                return null;
            }
            $content = self::spbwc_get_local_file_contents( $path );
            return $content ? json_decode( $content ) : null;
        }
        public static function spbwc_is_base64_string($s)
        {
            if (($b = base64_decode($s, TRUE)) === FALSE) {
                return FALSE;
            }
            $e = mb_detect_encoding($b);
            if (in_array($e, array('UTF-8', 'ASCII'))) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
        public static function spbwc_read_json_setting($fullname)
        {
            if (file_exists($fullname)) {
                $content = self::spbwc_get_local_file_contents($fullname);
                $list = $content ? json_decode($content) : array();
            } else {
                $list = '[]';
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Initializing empty JSON setting file.
                file_put_contents($fullname, $list);
                $list = array();
            }
            return $list;
        }
        public static function spbwc_get_list_google_font()
        {
            $path = SPBWC_PB_DATA_CONFIG_DIR . 'listgooglefonts.json';
            $data = (array) self::spbwc_read_json_setting($path);
            return wp_json_encode($data);
        }
        public static function spbwc_font_subsets()
        {
            return array(
                'all' => array(
                    'name' => 'All language',
                    'preview_text' => 'Abc Xyz',
                    'default_font' => 'Roboto'
                ),
                'arabic' => array(
                    'name' => 'Arabic',
                    'preview_text' => 'ءيوهن',
                    'default_font' => 'Cairo'
                ),
                'bengali' => array(
                    'name' => 'Bengali',
                    'preview_text' => 'অআইঈউ',
                    'default_font' => 'Hind Siliguri'
                ),
                'cyrillic' => array(
                    'name' => 'Cyrillic',
                    'preview_text' => 'БВГҐД',
                    'default_font' => 'Roboto'
                ),
                'cyrillic-ext' => array(
                    'name' => 'Cyrillic Extended',
                    'preview_text' => 'БВГҐД',
                    'default_font' => 'Roboto'
                ),
                'chinese-simplified' => array(
                    'name' => 'Chinese (Simplified)',
                    'preview_text' => '一二三四五',
                    'default_font' => 'ZCOOL XiaoWei'
                ),
                'devanagari' => array(
                    'name' => 'Devanagari',
                    'preview_text' => 'आईऊऋॠ',
                    'default_font' => 'Noto Sans'
                ),
                'greek' => array(
                    'name' => 'Greek',
                    'preview_text' => 'αβγδε',
                    'default_font' => 'Roboto'
                ),
                'greek-ext' => array(
                    'name' => 'Greek Extended',
                    'preview_text' => 'αβγδε',
                    'default_font' => 'Roboto'
                ),
                'gujarati' => array(
                    'name' => 'Gujarati',
                    'preview_text' => 'આઇઈઉઊ',
                    'default_font' => 'Shrikhand'
                ),
                'gurmukhi' => array(
                    'name' => 'Gurmukhi',
                    'preview_text' => 'ਆਈਊਏਐ',
                    'default_font' => 'Baloo Paaji'
                ),
                'hebrew' => array(
                    'name' => 'Hebrew',
                    'preview_text' => 'אבגדה',
                    'default_font' => 'Arimo'
                ),
                'japanese' => array(
                    'name' => 'Japanese',
                    'preview_text' => '一二三四五',
                    'default_font' => 'Sawarabi Mincho'
                ),
                'kannada' => array(
                    'name' => 'Kannada',
                    'preview_text' => 'ಅಆಇಈಉ',
                    'default_font' => 'Baloo Tamma'
                ),
                'khmer' => array(
                    'name' => 'Khmer',
                    'preview_text' => 'កខគឃង',
                    'default_font' => 'Hanuman'
                ),
                'korean' => array(
                    'name' => 'Korean',
                    'preview_text' => '가개갸거게',
                    'default_font' => 'Nanum Gothic'
                ),
                'latin' => array(
                    'name' => 'Latin',
                    'preview_text' => 'Abc Xyz',
                    'default_font' => 'Roboto'
                ),
                'latin-ext' => array(
                    'name' => 'Latin Extended',
                    'preview_text' => 'Abc Xyz',
                    'default_font' => 'Roboto'
                ),
                'malayalam' => array(
                    'name' => 'Malayalam',
                    'preview_text' => 'അആഇഈഉ',
                    'default_font' => 'Baloo Chettan'
                ),
                'myanmar' => array(
                    'name' => 'Myanmar',
                    'preview_text' => 'ကခဂဃင',
                    'default_font' => 'Padauk'
                ),
                'oriya' => array(
                    'name' => 'Oriya',
                    'preview_text' => 'ଅଆଇଈଉ',
                    'default_font' => 'Baloo Bhaina'
                ),
                'sinhala' => array(
                    'name' => 'Sinhala',
                    'preview_text' => 'අආඇඈඉ',
                    'default_font' => 'Abhaya Libre'
                ),
                'tamil' => array(
                    'name' => 'Tamil',
                    'preview_text' => 'க்ங்ச்ஞ்ட்',
                    'default_font' => 'Catamaran'
                ),
                'telugu' => array(
                    'name' => 'Telugu',
                    'preview_text' => 'అఆఇఈఉ',
                    'default_font' => 'Gurajada'
                ),
                'thai' => array(
                    'name' => 'Thai',
                    'preview_text' => 'กขคฆง',
                    'default_font' => 'Kanit'
                ),
                'vietnamese' => array(
                    'name' => 'Vietnamese',
                    'preview_text' => 'Abc Xyz',
                    'default_font' => 'Roboto'
                )
            );
        }
        public static function spbwc_zip_files($file_names, $archive_file_name, $option_name = array())
        {
        if (file_exists($archive_file_name)) {
            wp_delete_file($archive_file_name);
        }
            $pathZip = SPBWC_PB_DATA_DIR . '/download';
        if (!file_exists($pathZip)) {
            wp_mkdir_p($pathZip);
        }
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($archive_file_name, ZIPARCHIVE::CREATE) !== TRUE) {
                    /* translators: %s: archive file path. */
                    _doing_it_wrong(__FUNCTION__, sprintf(esc_html__('Cannot open %s.', 'storelly-product-builder-for-woocommerce'), esc_html($archive_file_name)), '1.3.1');
                    return false;
                }
                foreach ($file_names as $key => $file) {

                    $file_ext = pathinfo($file, PATHINFO_EXTENSION);

                    $path_arr = explode('/', $file);
                    $name = $path_arr[count($path_arr) - 2] . '_' . basename($file);
                    if (isset($option_name[$key]) && $option_name[$key]) {
                        $name = $option_name[$key] . '.' . $file_ext;
                    }

                    $zip->addFile($file, $name);
                }
                $zip->close();
            } else {
                // Load PclZip library if ZipArchive is not available.
                // PclZip is a WordPress core library for creating ZIP archives.
                if ( ! class_exists( 'PclZip' ) ) {
                    // Check if WordPress admin includes are available before loading.
                    if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/class-pclzip.php' ) ) {
                        // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile -- Loading WordPress core PclZip library as fallback when ZipArchive is unavailable.
                        include_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );
                    }
                }
                if ( class_exists( 'PclZip' ) ) {
                    $archive = new PclZip($archive_file_name);
                    foreach ($file_names as $file) {
                        $path_arr = explode('/', $file);
                        $dir = dirname($file) . '/';
                        $archive->add($file, PCLZIP_OPT_REMOVE_PATH, $dir, PCLZIP_OPT_ADD_PATH, $path_arr[count($path_arr) - 2]);
                    }
                }
            }
            if (file_exists($archive_file_name)) {
                return true;
            }
            return false;
        }
        public static function spbwc_get_local_file_contents( $path ) {
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }
            if ( ! $wp_filesystem ) {
                return false; // Unable to initialize filesystem
            }
            return $wp_filesystem->get_contents( $path );
        }
    }
}
