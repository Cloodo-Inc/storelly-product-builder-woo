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
        public static function spbwc_custom_notices($command, $mes = '')
        {
            switch ($command) {
                case 'success':
                    if (!$mes)
                        $mes = esc_html__('Your settings have been saved.', 'pc-product-builder');
                    $notice = '<div class="updated notice notice-success is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'pc-product-builder') . '</span>
                                </button>
                            </div>';
                    break;
                case 'error':
                    if (!$mes)
                        $mes = esc_html__('Irks! An error has occurred.', 'pc-product-builder');
                    $notice = '<div class="notice notice-error is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'pc-product-builder') . '</span>
                                </button>
                            </div>';
                    break;
                case 'notices':
                    if (!$mes)
                        $mes = esc_html__('Irks! An error has occurred.', 'pc-product-builder');
                    $notice = '<div class="notice notice-warning">
                                <p>' . $mes . '</p>
                            </div>';
                    break;
                case 'warning':
                    if (!$mes)
                        $mes = esc_html__('Warning.', 'pc-product-builder');
                    $notice = '<div class="notice notice-warning is-dismissible">
                                <p>' . $mes . '</p>
                                <button type="button" class="notice-dismiss">
                                    <span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'pc-product-builder') . '</span>
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
            if (!$template_path) :
                $template_path = 'pc-product-builder/';
            endif;
            // Set default plugin templates path.
            if (!$default_path) :
                $default_path = SPBWC_PB_PLUGIN_DIR . 'templates/'; // Path to the template folder
            endif;
            // Search template file in theme folder.
            $template = locate_template(array(
                $template_path . $template_name,
                $template_name
            ));
            // Get plugins template file.
            if (!$template) :
                $template = $default_path . $template_name;
            endif;
            return apply_filters('spbwc_locate_template', $template, $template_name, $template_path, $default_path);
        }
        public static function spbwc_get_template($template_name, $args = array(), $tempate_path = '', $default_path = '')
        {
            if (is_array($args) && isset($args)) :
                extract($args);
            endif;
            $template_file = self::spbwc_locate_template($template_name, $tempate_path, $default_path);
            if (!file_exists($template_file)) :
                _doing_it_wrong(__FUNCTION__, sprintf('<code>%s</code> does not exist.', $template_file), '1.3.1');
                return;
            endif;
            include $template_file;
        }
        public static function spbwc_is_product_builder_page()
        {
            return is_page(self::spbwc_get_page_id('product_builder'));
        }

        public static function spbwc_is_product_builder($id)
        {
            $id     = self::spbwc_get_wpml_original_id($id);
            $check  = get_post_meta($id, '_storelly_pb_enable', true);
            if ($check) return true;
            return false;
        }
        public static function spbwc_get_wpml_original_id($id, $type = 'post', $current_lang = false)
        {
            if (class_exists('SitePress')) {
                global $sitepress;
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
            $rd                 = wc_clean($_GET['rd']);
            switch ($rd) {
                case 'print_option':
                    $get                = array(
                        'action'    => 'edit',
                        'id'        => isset($_GET['oid']) ? absint($_GET['oid']) : '',
                        'paged'     => isset($_GET['paged']) ? absint($_GET['paged']) : '',
                    );
                    $redirect_url       = add_query_arg($get, admin_url('admin.php?page=spbwc-product-builder-options'));
                    break;
                default:
                    $redirect_url       = $rd;
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
                    $path           = SPBWC_PB_CUSTOMER_DIR . '/' . $builder_folder;
                    $data['config'] = self::spbwc_get_data_from_json($path . '/config.json');
                    $data['design'] = self::spbwc_get_data_from_json($path . '/design.json');
                }
            } else {
                global $wpdb;
                $table_name = $wpdb->prefix . 'spbwc_product_builder_options';
                $options = $wpdb->get_results($wpdb->prepare("SELECT builder FROM $table_name WHERE `id` = %d", $option_id), 'ARRAY_A');   
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
            $content = file_exists($path) ? file_get_contents($path) : '';
            return json_decode($content);
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
                $list = json_decode(file_get_contents($fullname));
            } else {
                $list = '[]';
                file_put_contents($fullname, $list);
                $list = array();
            }
            return $list;
        }
        public static function spbwc_get_list_google_font()
        {
            $path = SPBWC_PB_PLUGIN_DIR . 'data/listgooglefonts.json';
            $data = (array) self::spbwc_read_json_setting($path);
            return wp_json_encode($data);
        }
        public static function spbwc_font_subsets()
        {
            return array(
                'all'   =>  array(
                    'name'  =>  'All language',
                    'preview_text'  =>  'Abc Xyz',
                    'default_font'  =>  'Roboto'
                ),
                'arabic'   =>  array(
                    'name'  =>  'Arabic',
                    'preview_text'  =>  'ءيوهن',
                    'default_font'  =>  'Cairo'
                ),
                'bengali'   =>  array(
                    'name'  =>  'Bengali',
                    'preview_text'  =>  'অআইঈউ',
                    'default_font'  =>  'Hind Siliguri'
                ),
                'cyrillic'   =>  array(
                    'name'  =>  'Cyrillic',
                    'preview_text'  =>  'БВГҐД',
                    'default_font'  =>  'Roboto'
                ),
                'cyrillic-ext'   =>  array(
                    'name'  =>  'Cyrillic Extended',
                    'preview_text'  =>  'БВГҐД',
                    'default_font'  =>  'Roboto'
                ),
                'chinese-simplified'   =>  array(
                    'name'  =>  'Chinese (Simplified)',
                    'preview_text'  =>  '一二三四五',
                    'default_font'  =>  'ZCOOL XiaoWei'
                ),
                'devanagari'   =>  array(
                    'name'  =>  'Devanagari',
                    'preview_text'  =>  'आईऊऋॠ',
                    'default_font'  =>  'Noto Sans'
                ),
                'greek'   =>  array(
                    'name'  =>  'Greek',
                    'preview_text'  =>  'αβγδε',
                    'default_font'  =>  'Roboto'
                ),
                'greek-ext'   =>  array(
                    'name'  =>  'Greek Extended',
                    'preview_text'  =>  'αβγδε',
                    'default_font'  =>  'Roboto'
                ),
                'gujarati'   =>  array(
                    'name'  =>  'Gujarati',
                    'preview_text'  =>  'આઇઈઉઊ',
                    'default_font'  =>  'Shrikhand'
                ),
                'gurmukhi'   =>  array(
                    'name'  =>  'Gurmukhi',
                    'preview_text'  =>  'ਆਈਊਏਐ',
                    'default_font'  =>  'Baloo Paaji'
                ),
                'hebrew'   =>  array(
                    'name'  =>  'Hebrew',
                    'preview_text'  =>  'אבגדה',
                    'default_font'  =>  'Arimo'
                ),
                'japanese'   =>  array(
                    'name'  =>  'Japanese',
                    'preview_text'  =>  '一二三四五',
                    'default_font'  =>  'Sawarabi Mincho'
                ),
                'kannada'   =>  array(
                    'name'  =>  'Kannada',
                    'preview_text'  =>  'ಅಆಇಈಉ',
                    'default_font'  =>  'Baloo Tamma'
                ),
                'khmer'   =>  array(
                    'name'  =>  'Khmer',
                    'preview_text'  =>  'កខគឃង',
                    'default_font'  =>  'Hanuman'
                ),
                'korean'   =>  array(
                    'name'  =>  'Korean',
                    'preview_text'  =>  '가개갸거게',
                    'default_font'  =>  'Nanum Gothic'
                ),
                'latin'   =>  array(
                    'name'  =>  'Latin',
                    'preview_text'  =>  'Abc Xyz',
                    'default_font'  =>  'Roboto'
                ),
                'latin-ext'   =>  array(
                    'name'  =>  'Latin Extended',
                    'preview_text'  =>  'Abc Xyz',
                    'default_font'  =>  'Roboto'
                ),
                'malayalam'   =>  array(
                    'name'  =>  'Malayalam',
                    'preview_text'  =>  'അആഇഈഉ',
                    'default_font'  =>  'Baloo Chettan'
                ),
                'myanmar'   =>  array(
                    'name'  =>  'Myanmar',
                    'preview_text'  =>  'ကခဂဃင',
                    'default_font'  =>  'Padauk'
                ),
                'oriya'   =>  array(
                    'name'  =>  'Oriya',
                    'preview_text'  =>  'ଅଆଇଈଉ',
                    'default_font'  =>  'Baloo Bhaina'
                ),
                'sinhala'   =>  array(
                    'name'  =>  'Sinhala',
                    'preview_text'  =>  'අආඇඈඉ',
                    'default_font'  =>  'Abhaya Libre'
                ),
                'tamil'   =>  array(
                    'name'  =>  'Tamil',
                    'preview_text'  =>  'க்ங்ச்ஞ்ட்',
                    'default_font'  =>  'Catamaran'
                ),
                'telugu'   =>  array(
                    'name'  =>  'Telugu',
                    'preview_text'  =>  'అఆఇఈఉ',
                    'default_font'  =>  'Gurajada'
                ),
                'thai'   =>  array(
                    'name'  =>  'Thai',
                    'preview_text'  =>  'กขคฆง',
                    'default_font'  =>  'Kanit'
                ),
                'vietnamese'   =>  array(
                    'name'  =>  'Vietnamese',
                    'preview_text'  =>  'Abc Xyz',
                    'default_font'  =>  'Roboto'
                )
            );
        }
        public static function spbwc_zip_files($file_names, $archive_file_name, $option_name = array())
        {
            if (file_exists($archive_file_name)) {
                unlink($archive_file_name);
            }
            $pathZip = SPBWC_PB_DATA_DIR . '/download';
            if (!file_exists($pathZip)) {
                mkdir($pathZip);
            }
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($archive_file_name, ZIPARCHIVE::CREATE) !== TRUE) {
                    exit("cannot open <$archive_file_name>\n");
                }
                foreach ($file_names as $key => $file) {

                    $file_ext   = pathinfo($file, PATHINFO_EXTENSION);

                    $path_arr = explode('/', $file);
                    $name = $path_arr[count($path_arr) - 2] . '_' . basename($file);
                    if (isset($option_name[$key]) && $option_name[$key]) {
                        $name = $option_name[$key] . '.' . $file_ext;
                    }

                    $zip->addFile($file, $name);
                }
                $zip->close();
            } else {
                require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
                $archive = new PclZip($archive_file_name);
                foreach ($file_names as $file) {
                    $path_arr = explode('/', $file);
                    $dir = dirname($file) . '/';
                    $archive->add($file, PCLZIP_OPT_REMOVE_PATH, $dir, PCLZIP_OPT_ADD_PATH, $path_arr[count($path_arr) - 2]);
                }
            }
            if (file_exists($archive_file_name)) {
                return true;
            }
            return false;
        }
    }
}
