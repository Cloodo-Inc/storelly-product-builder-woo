<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Printcart_Export_PDF')) {
    class Printcart_Export_PDF {
        public static $dpi =  300;
        public static $unit = 'px';
        public function __construct() {
        }
        public static function download_google_font($font_name = '') {
            $path_dst = array(
                'r' =>  PRINTCART_PB_FONT_DIR . '/' . $font_name . '.ttf'
            );
            $google_font_path = PRINTCART_PB_DATA_CONFIG_DIR . '/google-fonts-ttf.json';
            $fonts = json_decode(file_get_contents($google_font_path));
            $items = $fonts->items;
            foreach ($items as $item) {
                if ($item->family == $font_name) {
                    $font = $item->files;
                    break;
                }
            }
            if (isset($font)) {
                $path_src = isset($font->regular) ? $font->regular : reset($font);
                if (!file_exists($path_dst['r'])) {
                    copy($path_src, $path_dst['r']);
                }
                if (isset($font->italic)) {
                    $path_dst['i'] = PRINTCART_PB_FONT_DIR . '/' . $font_name . 'i.ttf';
                    if (!file_exists($path_dst['i'])) {
                        copy($font->italic, $path_dst['i']);
                    }
                }
                if (isset($font->{"700"})) {
                    $path_dst['b'] = PRINTCART_PB_FONT_DIR . '/' . $font_name . 'b.ttf';
                    if (!file_exists($path_dst['b'])) {
                        copy($font->{"700"}, $path_dst['b']);
                    }
                }
                if (isset($font->{"700italic"})) {
                    $path_dst['bi'] = PRINTCART_PB_FONT_DIR . '/' . $font_name . 'bi.ttf';
                    if (!file_exists($path_dst['bi'])) {
                        copy($font->{"700italic"}, $path_dst['bi']);
                    }
                }
            }

            return $path_dst;
        }
        public static function cloudExportPdf($folder_design, $include_background = false) {
            $path           = PRINTCART_PB_CUSTOMER_DIR . '/' . $folder_design;
            $folder         = $path . '/customer-pdfs';
            $result         = array();
            $pages          = array();

            if (!file_exists($folder)) {
                wp_mkdir_p($folder);
            }
            $config         = file_exists($path . '/config.json') ? json_decode(file_get_contents($path . '/config.json')) : '';
            $datas = array();
            if (isset($config->views) && count($config->views)) {
                foreach ($config->views as $side) {
                    $datas[] = (array)$side;
                }
            };
            $unit_ratio     = self::get_unit_ratio(self::$dpi, self::$unit);
            $used_font_path = $path . '/used_font.json';
            $used_fonts     =  file_exists($used_font_path) ? json_decode(file_get_contents($used_font_path)) : array();
            $font_css       = self::build_font_css($used_fonts);
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
                    if (Printcart_IO::checkFileType(basename($product_bg), $allow_exts)) {
                        $page_settings['include_bg']    = true;
                        $page_settings['bg_src']        = $product_bg;
                    }
                }

                $pages[$key]['page_settings'] = $page_settings;

                $svg_path = PRINTCART_PB_CUSTOMER_DIR . '/' . $folder_design . '/frame_' . $key . '_svg.svg';
                if (file_exists($svg_path)) {
                    $html_url           = self::build_html_page($folder_design, $key, $svg_path, $page_settings, $font_css);
                    $url_segment        = urlencode($html_url);
                    $url_segment        = urlencode('https://botaksign-dev.s3.us-east-1.amazonaws.com/test-product-builder/NBDesigner.html');
                    $settings_segment   = base64_encode(json_encode(array(
                        'width'         => $data['base_width'] * $unit_ratio . 'in',
                        'height'        => $data['base_height'] * $unit_ratio . 'in'
                    )));

                    $requests[] = array(
                        'index'         => $key,
                        'url'           => 'https://api.cloud2print.net/pdf/' . $url_segment . '/' . $settings_segment,
                    );
                }
            }
            $pdfs = self::request_create_pdf($requests, $folder, $folder_design);
            foreach ($pdfs as $key => $pdf) {
                $pages[$key]['file'] = $pdf;
            }
            $result = Printcart_IO::get_list_files($folder);
            return $result;
        }
        public static function exportPDF($folder_design, $include_background = false) {
            if (!class_exists('TCPDF')) {
                require_once(PRINTCART_PB_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php');
            }
            require_once(PRINTCART_PB_PLUGIN_DIR . 'lib/fpdi/autoload.php');

            $path           = PRINTCART_PB_CUSTOMER_DIR . '/' . $folder_design;
            $folder         = $path . '/customer-pdfs';
            $output_file    = $folder . '/' . $folder_design . '.pdf';
            $result         = array();
            if (!file_exists($folder)) {
                wp_mkdir_p($folder);
            }
            $enable_Cloud_export_pdf = true;
            if ($enable_Cloud_export_pdf) {
                // self::cloudExportPdf($folder_design, $include_background);
                $output_file    = PRINTCART_PB_CUSTOMER_DIR . '/' . $folder_design . '/customer-pdfs' . '/' . $folder_design . '.pdf';
                $result = Printcart_IO::get_list_files_by_type(PRINTCART_PB_CUSTOMER_DIR . '/' . $folder_design . '/customer-pdfs', 1, 'pdf');
            } else {
                $config     = file_exists($path . '/config.json') ? json_decode(file_get_contents($path . '/config.json')) : '';
                $datas = array();
                if (isset($config->views) && count($config->views)) {
                    foreach ($config->views as $side) {
                        $datas[] = (array)$side;
                    }
                };
                $used_font_path = $path . '/used_font.json';
                $used_font      = file_exists($used_font_path) ? json_decode(file_get_contents($used_font_path)) : array();
                $path_font      = array();
                foreach ($used_font as $font) {
                    $font_name = $font->name;
                    if ($font->type == 'google') {
                        $path_font = self::download_google_font($font_name);;
                    }
                    $true_type = nbd_get_truetype_fonts();
                    if (in_array($font_name, $true_type)) {
                        foreach ($path_font as $pfont) {
                            $fontname = TCPDF_FONTS::addTTFfont($pfont, 'TrueType', '', 32);
                        }
                    } else {
                        foreach ($path_font as $pfont) {
                            $fontname = TCPDF_FONTS::addTTFfont($pfont, '', '', 32);
                        }
                    }
                }
                $pdfs       = array();
                $unitRatio  = 10;
                switch (self::$unit) {
                    case 'mm':
                        $unitRatio = 1;
                        break;
                    case 'in':
                        $unitRatio = 25.4;
                        break;
                    case 'ft':
                        $unitRatio = 304.8;
                        break;
                    case 'px':
                        $unitRatio = 25.4 / self::$dpi;
                        break;
                    default:
                        $unitRatio = 10;
                        break;
                }
                foreach ($datas as $key => $data) {
                    $proWidth   = $data['base_width'];
                    $proHeight  = $data['base_height'];

                    $pdfs[$key]['background']           = $data['base_url'];
                    $pdfs[$key]['product-width']        = round($proWidth * $unitRatio, 2);
                    $pdfs[$key]['product-height']       = round($proHeight * $unitRatio, 2);
                    $pdfs[$key]['include_background']   = $include_background;
                    if ($include_background && $data['base_url']) {
                        $pdfs[$key]['include_background']   = 0;
                    }
                }
                $bgWidth        = $pdfs[0]['product-width'];
                $bgHeight       = $pdfs[0]['product-height'];
                $pWidth         = $bgWidth;
                $pHeight        = $bgHeight;
                $pdf_format     = array($pWidth, $pHeight);
                if ($pWidth > $pHeight) {
                    $orientation = "L";
                } else {
                    $orientation = "P";
                }

                $pdf = new \setasign\Fpdi\TcpdfFpdi($orientation, 'mm', $pdf_format, true, 'UTF-8', false);

                $pdf->SetMargins(0, 0, 0, true);
                $pdf->SetCreator(get_site_url());
                $pdf->SetTitle(get_bloginfo('name'));
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetAutoPageBreak(TRUE, 0);

                foreach ($pdfs as $key => $_pdf) {
                    $background         = $_pdf['background'];
                    $path_bg = (absint($background) > 0) ? get_attached_file($background) : Printcart_IO::convert_url_to_path($background);
                    $bgWidth    = (float)$_pdf['product-width'];
                    $bgHeight   = (float)$_pdf['product-height'];
                    $pdf_format     = array($bgWidth, $bgHeight);
                    if ($bgWidth > $bgHeight) {
                        $orientation = "L";
                    } else {
                        $orientation = "P";
                    }

                    $pdf = new \setasign\Fpdi\TcpdfFpdi($orientation, 'mm', $pdf_format, true, 'UTF-8', false);

                    $pdf->SetMargins(0, 0, 0, true);
                    $pdf->SetCreator(get_site_url());
                    $pdf->SetTitle(get_bloginfo('name'));
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                    $pdf->SetAutoPageBreak(TRUE, 0);

                    $pdf->AddPage();
                    if ($include_background && $path_bg) {
                        $img_ext    = array('jpg', 'jpeg', 'png');
                        $svg_ext    = array('svg');
                        $eps_ext    = array('eps', 'ai');

                        $check_img  = Printcart_IO::checkFileType(basename($path_bg), $img_ext);
                        $check_svg  = Printcart_IO::checkFileType(basename($path_bg), $svg_ext);
                        $check_eps  = Printcart_IO::checkFileType(basename($path_bg), $eps_ext);

                        $ext        = pathinfo($path_bg);
                        if ($check_img) {
                            $pdf->Image($path_bg, 0, 0, $bgWidth, $bgHeight, '', '', '', false);
                        }
                        if ($check_svg) {
                            $pdf->ImageSVG($path_bg, 0, 0, $bgWidth, $bgHeight, '', '', '', 0, true);
                        }
                        if ($check_eps) {
                            $pdf->ImageEps($path_bg, 0, 0, $bgWidth, $bgHeight, '', true, '', '', 0, true);
                        }
                    }
                    $svg = $path . '/svgpath/frame_' . $key . '_svg.svg';
                    self::convert_svg_url($path . '/', 'frame_' . $key . '_svg.svg');
                    $pdf->ImageSVG($svg, 0, 0, $bgWidth, $bgHeight, '', '', '', 0, true);
                    $output_file = $folder . '/' . $folder_design . '_' . $key . '.pdf';
                    $pdf->Output($output_file, 'F');
                    $result[] = $output_file;
                }


                return $result;
            }
        }
        public static function printcart_file_get_contents($url) {
            $response = wp_remote_get($url);
            if (is_array($response) && !is_wp_error($response)) {
                $result   = trim($response['body']);
                return $result;
            }
            if (ini_get('allow_url_fopen')) {
                $checkPHP = version_compare(PHP_VERSION, '5.6.0', '>=');
                if (is_ssl() && $checkPHP) {
                    $result = file_get_contents($url, false, stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false))));
                } else {
                    $result = file_get_contents($url);
                }
            } else {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_SSLVERSION, 3);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $result = curl_exec($ch);
                curl_close($ch);
                if (false === $result) {
                    $ch     = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $result = curl_exec($ch);
                    curl_close($ch);
                }
            }
            return $result;
        }
        public static function convert_svg_url($path, $file) {
            $svg_path = $path . '/svgpath';
            if (!file_exists($svg_path)) wp_mkdir_p($svg_path);
            $new_svg_path = $svg_path . '/' . $file;
            $xdoc = new DomDocument;
            $xdoc->Load($path . $file);
            /* image path */
            $images = $xdoc->getElementsByTagName('image');
            for ($i = 0; $i < $images->length; $i++) {
                $tagName        = $xdoc->getElementsByTagName('image')->item($i);
                $attribNode     = $tagName->getAttributeNode('xlink:href');
                $img_src        = $attribNode->value;
                if (strpos($img_src, "data:image") !== FALSE)
                    continue;
                if (strpos($img_src, "data:img") !== FALSE)
                    continue;
                $type           = strtolower(pathinfo($img_src, PATHINFO_EXTENSION));
                $type           = ($type == 'svg') ? 'svg+xml' : $type;

                $path_image     = Printcart_IO::convert_url_to_path($img_src);

                $data   = self::printcart_file_get_contents($path_image);
                $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                $tagName->setAttribute('xlink:href', $base64);
            }

            //process clipPath 
            $defsEl = $xdoc->getElementsByTagName('defs')->item(0);
            $cpEls  = $xdoc->getElementsByTagName('clipPath');

            $has_flag = false;
            foreach ($cpEls as $cpEl) {
                $grandParentGroup   = $cpEl->parentNode->parentNode;
                if ($grandParentGroup->hasAttribute('flag-clip')) {
                    $has_flag = true;
                    break;
                }
            }

            foreach ($cpEls as $cpEl) {
                $cloneCp            = $cpEl;
                $parentGroup        = $cpEl->parentNode;
                $grandParentGroup   = $cpEl->parentNode->parentNode;

                if ($parentGroup->tagName == 'defs') {
                    continue;
                }

                if (strpos($cloneCp->getAttribute('id'), 'imageCrop') !== FALSE) {
                    continue;
                }

                if ($cloneCp->childNodes->item(1)->nodeName != 'path') {
                    continue;
                }

                if ($has_flag && !$grandParentGroup->hasAttribute('flag-clip')) {
                    continue;
                }

                $cpTm       = $cloneCp->childNodes->item(1)->getAttribute('transform');
                $parentGroup->removeChild($cpEl);
                $defsEl->appendChild($cloneCp);
                $tm         = $parentGroup->getAttribute('transform');
                $cpMtArr    = nbd_get_transform_arr($cpTm, 'matrix');
                $cpTrArr    = nbd_get_transform_arr($cpTm, 'translate');

                $sx         = 1 / $cpMtArr[0];
                $sy         = 1 / $cpMtArr[3];
                $tx         = -$cpMtArr[4] - $cpTrArr[0] * $cpMtArr[0];
                $ty         = -$cpMtArr[5] - $cpTrArr[1] * $cpMtArr[3];
                $newTm      = 'scale( ' . $sx . ', ' . $sy . ' ) translate(' . $tx . ', ' . $ty . ') ' . $tm;
                $parentGroup->setAttribute('transform', $newTm);
            }
            $new_svg = $xdoc->saveXML();
            file_put_contents($new_svg_path, $new_svg);
        }
        public static function get_unit_ratio($dpi, $unit) {
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
        public static function build_font_css($fonts) {
            $google_font_link = '';

            foreach ($fonts as $font) {
                $font_name = str_replace(' ', '+', $font->name);

                if ($font->type == 'google') {
                    $google_font_link .= '<link rel="stylesheet" href="//fonts.googleapis.com/css?family=' . $font_name . ':400,400i,700,700i" />';
                }
            }

            return array(
                'google_font_link'  => $google_font_link,
            );
        }
        public static function build_html_page($folder_design, $key, $svg_path, $page_settings, $font_css) {
            $pdf_temp_path = PRINTCART_PB_CUSTOMER_DIR . '/' . $folder_design . '/pdf-templates';
            if (!file_exists($pdf_temp_path)) {
                wp_mkdir_p($pdf_temp_path);
            }

            $html_path  =  $pdf_temp_path . '/' . $key . '.html';
            $html_url   = PRINTCART_PB_CUSTOMER_URL . '/' . $folder_design . '/pdf-templates/' . $key . '.html';
            $svg_string = file_get_contents($svg_path);
            $svg_string = preg_replace("/<(?:\?xml|!DOCTYPE).*?>/", "", $svg_string);

            ob_start();
            include PRINTCART_PB_PLUGIN_DIR . 'views/pdf-template.php';
            $template    = ob_get_clean();

            file_put_contents($html_path, $template);
            return $html_url;
        }
        public static function request_create_pdf($requests, $folder, $folder_design) {
            $result     = array();
            $mh         = curl_multi_init();
            $multiCurl  = array();
            foreach ($requests as $i => $request) {
                $multiCurl[$i] = curl_init();
                curl_setopt($multiCurl[$i], CURLOPT_URL, $request['url']);
                curl_setopt($multiCurl[$i], CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($multiCurl[$i], CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
                curl_setopt($multiCurl[$i], CURLOPT_TIMEOUT, 30);
                curl_setopt($multiCurl[$i], CURLOPT_HEADER, 0);
                curl_multi_add_handle($mh, $multiCurl[$i]);
            }

            $index = null;
            do {
                curl_multi_exec($mh, $index);
            } while ($index > 0);

            foreach ($multiCurl as $k => $ch) {
                $res            = curl_multi_getcontent($ch);
                $output_file    = $folder . '/' . $folder_design . '_' . $requests[$k]['index'] . '.pdf';
                $download       = self::download_remote_file($res, $output_file);
                if ($download) {
                    $result[$requests[$k]['index']] = $output_file;
                }
            }

            return $result;
        }
        public static function download_remote_file($url, $path) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $data = curl_exec($ch);
            $info = curl_getinfo($ch);
            if ($info['http_code'] == 200) {
                if ($data) {
                    $file = fopen($path, "w+");
                    fputs($file, $data);
                    fclose($file);
                    return true;
                }
                return false;
            }
            curl_close($ch);
            return false;
        }
    }
}
