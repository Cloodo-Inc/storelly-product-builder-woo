<?php
/**
 * Export PDF using Cloud2Print API
 * 
 * This class handles PDF export using the cloud2print.net cloud API.
 * All PDF generation is performed remotely, no local libraries required.
 *
 * @package SPBWC_Product_Builder
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPBWC_Storelly_Export_PDF')) {
    class SPBWC_Storelly_Export_PDF {
        
        /**
         * Cloud2Print API base URL
         */
        const API_BASE_URL = 'https://api.cloud2print.net';

        public function __construct() {
        }

        /**
         * Export PDF using Cloud2Print API
         *
         * @param string $folder_design Design folder name
         * @param bool $include_background Whether to include background in PDF
         * @return array List of generated PDF files
         */
        public static function spbwc_export_pdf($folder_design, $include_background = false) {
            $path           = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design;
            $folder         = $path . '/customer-pdfs';
            $result         = array();
            $pages          = array();

            if (!file_exists($folder)) {
                wp_mkdir_p($folder);
            }
            
            $config = '';
            $config_path = $path . '/config.json';
            if (file_exists($config_path)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON configuration file from uploads directory.
                $config_json = file_get_contents($config_path);
                if ($config_json !== false) {
                    $config = json_decode($config_json);
                }
            }
            
            $design_output = '';
            $design_output_path = $path . '/design_output.json';
            if (file_exists($design_output_path)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON design output from uploads directory.
                $design_json = file_get_contents($design_output_path);
                if ($design_json !== false) {
                    $design_output = json_decode($design_json);
                }
            }
            $dpi            = isset($design_output->dpi) ? (int) $design_output->dpi : 300;
            $unit           = isset($design_output->dimension_unit) ? $design_output->dimension_unit : 'px';
            $datas          = array();
            
            if (isset($config->views) && count($config->views)) {
                foreach ($config->views as $side) {
                    $datas[] = (array)$side;
                }
            };
            
            $unit_ratio     = self::spbwc_get_unit_ratio($dpi, $unit);
            $used_font_path = $path . '/used_font.json';
            $used_fonts     = array();
            
            if (file_exists($used_font_path)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local JSON file from uploads directory.
                $font_json = file_get_contents($used_font_path);
                if ($font_json !== false) {
                    $used_fonts = json_decode($font_json);
                    if (!is_array($used_fonts) && !is_object($used_fonts)) {
                        $used_fonts = array();
                    }
                }
            }
            $font_css       = self::spbwc_build_font_css($used_fonts);
            $requests       = array();
            
            foreach ($datas as $key => $data) {
                $page_settings = array(
                    'width'         => $data['base_width'] * $unit_ratio . 'in',
                    'height'        => $data['base_height'] * $unit_ratio . 'in',
                    'design_width'  => $data['base_width'] * $unit_ratio . 'in',
                    'design_height' => $data['base_height'] * $unit_ratio . 'in',
                    'design_top'    => 0,
                    'design_left'   => 0,
                    'include_bg'    => false,
                );

                $pages[$key] = array(
                    'width'         => $data['base_width'] * $unit_ratio,
                    'height'        => $data['base_height'] * $unit_ratio,
                    'design_top'    => 0,
                    'design_left'   => 0,
                );

                $include_background = (isset($data['base_url']) && $data['base_url']) ? $include_background : false;

                $allow_exts     = array('jpg', 'jpeg', 'png', 'svg');

                if ($include_background) {
                    $product_bg     = is_numeric($data['base_url']) ? wp_get_attachment_url($data['base_url']) : $data['base_url'];
                    if (SPBWC_Storelly_IO::spbwc_check_file_type(basename($product_bg), $allow_exts)) {
                        $page_settings['include_bg']    = true;
                        $page_settings['bg_src']        = $product_bg;
                    }
                }

                $pages[$key]['page_settings'] = $page_settings;

                $svg_path = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design . '/frame_' . $key . '_svg.svg';
                if (file_exists($svg_path)) {
                    $html_url           = self::spbwc_build_html_page($folder_design, $key, $svg_path, $page_settings, $font_css);
                    $url_segment        = urlencode($html_url);
                    $settings_segment   = base64_encode(wp_json_encode(array(
                        'width'         => $data['base_width'] * $unit_ratio . 'in',
                        'height'        => $data['base_height'] * $unit_ratio . 'in'
                    )));

                    $requests[] = array(
                        'index'         => $key,
                        'url'           => self::API_BASE_URL . '/pdf/' . $url_segment . '/' . $settings_segment,
                    );
                }
            }
            
            $pdfs = self::spbwc_request_create_pdf($requests, $folder, $folder_design);
            foreach ($pdfs as $key => $pdf) {
                $pages[$key]['file'] = $pdf;
            }
            
            $result = SPBWC_Storelly_IO::spbwc_get_list_files($folder);
            return $result;
        }

        /**
         * Get file contents via HTTP request
         *
         * @param string $url URL to fetch
         * @return string|false File contents or false on failure
         */
        public static function spbwc_file_get_contents($url) {
            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 30,
                    'sslverify'   => false, // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Using wp_remote_get with sslverify disabled for compatibility with self-signed certificates in development.
                    'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                )
            );
            
            if (is_wp_error($response)) {
                return false;
            }
            
            if (is_array($response) && isset($response['body'])) {
                return trim($response['body']);
            }
            
            return false;
        }

        /**
         * Convert unit to ratio for PDF dimensions
         *
         * @param int $dpi DPI value
         * @param string $unit Unit type (mm, in, ft, px, cm)
         * @return float Unit ratio
         */
        public static function spbwc_get_unit_ratio($dpi, $unit) {
            switch ($unit) {
                case 'mm':
                    $unit_ratio = 1 / 25.4;
                    break;
                case 'in':
                    $unit_ratio = 1;
                    break;
                case 'ft':
                    $unit_ratio = 1 / 12;
                    break;
                case 'px':
                    $unit_ratio = 1 / $dpi;
                    break;
                default:
                    $unit_ratio = 1 / 2.54;
                    break;
            }
            return $unit_ratio;
        }

        /**
         * Build font CSS for Google Fonts
         *
         * @param array $fonts Array of font objects
         * @return void
         */
        public static function spbwc_build_font_css($fonts) {
            $google_fonts = array();
        
            foreach ($fonts as $font) {
                if ($font->type == 'google') {
                    $font_name = str_replace(' ', '+', $font->name);
                    $google_fonts[] = $font_name;
                }
            }  
            if (!empty($google_fonts)) {
                $google_font_url = '//fonts.googleapis.com/css?family=' . implode('|', $google_fonts) . ':400,400i,700,700i';
                wp_enqueue_style('custom-google-fonts', $google_font_url, array(), '1.0.0');
            }
        }
        
        /**
         * Build HTML page for PDF generation
         *
         * @param string $folder_design Design folder name
         * @param int $key Page index
         * @param string $svg_path Path to SVG file
         * @param array $page_settings Page settings
         * @param mixed $font_css Font CSS
         * @return string URL to the generated HTML page
         */
        public static function spbwc_build_html_page($folder_design, $key, $svg_path, $page_settings, $font_css) {
            $pdf_temp_path = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design . '/pdf-templates';
            if (!file_exists($pdf_temp_path)) {
                wp_mkdir_p($pdf_temp_path);
            }

            $html_path  =  $pdf_temp_path . '/' . $key . '.html';
            $html_url   = SPBWC_PB_CUSTOMER_URL . '/' . $folder_design . '/pdf-templates/' . $key . '.html';
            
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local SVG file from uploads directory for PDF generation.
            $svg_string = file_get_contents($svg_path);
            $svg_string = preg_replace("/<(?:\?xml|!DOCTYPE).*?>/", "", $svg_string);

            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obstart_ob_start -- Output buffer opened and closed in same scope for template rendering.
            ob_start();
            include SPBWC_PB_PLUGIN_DIR . 'views/pdf-template.php';
            $template = ob_get_clean(); // Buffer closed here - no early return possible.

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing temporary HTML file to uploads directory for PDF service.
            file_put_contents($html_path, $template);
            return $html_url;
        }

        /**
         * Send requests to Cloud2Print API and download generated PDFs
         *
         * @param array $requests Array of API request URLs
         * @param string $folder Output folder path
         * @param string $folder_design Design folder name
         * @return array Array of generated PDF file paths
         */
        public static function spbwc_request_create_pdf($requests, $folder, $folder_design) {
            $result     = array();
            
            foreach ($requests as $k => $request) {
                $output_file    = $folder . '/' . $folder_design . '_' . $request['index'] . '.pdf';
                $download       = self::spbwc_download_remote_file($request['url'], $output_file);
                if ($download) {
                    $result[$request['index']] = $output_file;
                }
            }

            return $result;
        }

        /**
         * Download remote file and save to local path
         *
         * @param string $url  Remote URL.
         * @param string $path Local file path.
         * @return bool True on success, false on failure.
         */
        public static function spbwc_download_remote_file($url, $path) {
            $response = wp_remote_get(
                $url,
                array(
                    'timeout'    => 20,
                    'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0',
                )
            );

            if ( is_wp_error( $response ) ) {
                return false;
            }

            $body = wp_remote_retrieve_body( $response );

            if ( '' === $body ) {
                return false;
            }

            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            global $wp_filesystem;

            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }

            if ( ! $wp_filesystem ) {
                return false;
            }

            return (bool) $wp_filesystem->put_contents( $path, $body, FS_CHMOD_FILE );
        }
    }
}
public static function request_create_pdf($requests, $folder, $folder_design) {
$result = array();
$multiCurl = array();
foreach ($requests as $i => $request) {
$multiCurl[$i] = wp_remote_get($request['url'], array(
'timeout' => 30,
'User-Agent' => 'Mozilla/4.0 (compatible;)'
));
}

foreach ($multiCurl as $k => $res) {

$output_file = $folder . '/' . $folder_design . '_' . $requests[$k]['index'] . '.pdf';
$download = self::download_remote_file($res, $output_file);
if ($download) {
$result[$requests[$k]['index']] = $output_file;
}
}

return $result;
}
public static function download_remote_file($url, $path) {
$data = wp_remote_get($url, array(
'timeout' => 20,
'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'
));

if ($data) {
$file = fopen($path, "w+");
fputs($file, $data);
fclose($file);
return true;
}
return false;
}
}
}