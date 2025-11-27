<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('SPBWC_Storelly_Export_PDF')) {
    class SPBWC_Storelly_Export_PDF {
        public function __construct() {
        }
        public static function spbwc_download_google_font($font_name = '') {
            $font_name = sanitize_text_field($font_name);
            $path_dst = array(
                'r' =>  SPBWC_PB_FONT_DIR . '/' . $font_name . '.ttf'
            );
            $google_font_path = SPBWC_PB_DATA_CONFIG_DIR . '/google-fonts-ttf.json';
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
                    $path_dst['i'] = SPBWC_PB_FONT_DIR . '/' . $font_name . 'i.ttf';
                    if (!file_exists($path_dst['i'])) {
                        copy($font->italic, $path_dst['i']);
                    }
                }
                if (isset($font->{"700"})) {
                    $path_dst['b'] = SPBWC_PB_FONT_DIR . '/' . $font_name . 'b.ttf';
                    if (!file_exists($path_dst['b'])) {
                        copy($font->{"700"}, $path_dst['b']);
                    }
                }
                if (isset($font->{"700italic"})) {
                    $path_dst['bi'] = SPBWC_PB_FONT_DIR . '/' . $font_name . 'bi.ttf';
                    if (!file_exists($path_dst['bi'])) {
                        copy($font->{"700italic"}, $path_dst['bi']);
                    }
                }
            }

            return $path_dst;
        }
        public static function spbwc_cloud_export_pdf($folder_design, $include_background = false) {
            $path           = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design;
            $folder         = $path . '/customer-pdfs';
            $result         = array();
            $pages          = array();

            if (!file_exists($folder)) {
                wp_mkdir_p($folder);
            }
            $config         = file_exists($path . '/config.json') ? json_decode(file_get_contents($path . '/config.json')) : '';
            $design_output  = file_exists($path . '/design_output.json') ? json_decode(file_get_contents($path . '/design_output.json')) : '';
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
            $used_fonts     =  file_exists($used_font_path) ? json_decode(file_get_contents($used_font_path)) : array();
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
                        'url'           => 'https://api.cloud2print.net/pdf/' . $url_segment . '/' . $settings_segment,
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
        public static function spbwc_export_pdf($folder_design, $include_background = false) {
            if (!class_exists('TCPDF')) {
                require_once(SPBWC_PB_PLUGIN_DIR . 'build/tcpdf/tcpdf.php');
            }
            require_once(SPBWC_PB_PLUGIN_DIR . 'build/fpdi/autoload.php');

            $path           = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design;
            $folder         = $path . '/customer-pdfs';
            $output_file    = $folder . '/' . $folder_design . '.pdf';
            $result         = array();
            if (!file_exists($folder)) {
                wp_mkdir_p($folder);
            }
            $storelly_pb_settings = get_option('storelly_pb_settings');
            $enable_cloud_export_pdf = $storelly_pb_settings && isset($storelly_pb_settings['enable_cloud2print_api']) && $storelly_pb_settings['enable_cloud2print_api'] == 'yes' ? true : false;
            if ($enable_cloud_export_pdf) {
                self::spbwc_cloud_export_pdf($folder_design, $include_background);
                $output_file    = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design . '/customer-pdfs' . '/' . $folder_design . '.pdf';
                $result = SPBWC_Storelly_IO::spbwc_get_list_files_by_type(SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design . '/customer-pdfs', 'pdf', 1);
            } else {
                $config     = file_exists($path . '/config.json') ? json_decode(file_get_contents($path . '/config.json')) : '';
                $datas = array();
                if (isset($config->views) && count($config->views)) {
                    foreach ($config->views as $side) {
                        $datas[] = (array)$side;
                    }
                };
                $design_output  = file_exists($path . '/design_output.json') ? json_decode(file_get_contents($path . '/design_output.json')) : '';
                $dpi            = isset($design_output->dpi) ? (int) $design_output->dpi : 300;
                $unit           = isset($design_output->dimension_unit) ? $design_output->dimension_unit : 'cm';
                $used_font_path = $path . '/used_font.json';
                $used_font      = file_exists($used_font_path) ? json_decode(file_get_contents($used_font_path)) : array();
                $path_font      = array();
                foreach ($used_font as $font) {
                    $font_name = $font->name;
                    if ($font->type == 'google') {
                        $path_font = self::spbwc_download_google_font($font_name);;
                    }
                    $true_type = self::spbwc_get_truetype_fonts();
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
                switch ($unit) {
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
                        $unitRatio = 25.4 / $dpi;
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
                    $path_bg = (absint($background) > 0) ? get_attached_file($background) : SPBWC_Storelly_IO::spbwc_convert_url_to_path($background);
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

                        $check_img  = SPBWC_Storelly_IO::spbwc_check_file_type(basename($path_bg), $img_ext);
                        $check_svg  = SPBWC_Storelly_IO::spbwc_check_file_type(basename($path_bg), $svg_ext);
                        $check_eps  = SPBWC_Storelly_IO::spbwc_check_file_type(basename($path_bg), $eps_ext);

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
                    self::spbwc_convert_svg_url($path . '/', 'frame_' . $key . '_svg.svg');
                    $pdf->ImageSVG($svg, 0, 0, $bgWidth, $bgHeight, '', '', '', 0, true);
                    $output_file = $folder . '/' . $folder_design . '_' . $key . '.pdf';
                    $pdf->Output($output_file, 'F');
                    $result[] = $output_file;
                }
            }
            
            return $result;
        }
        public static function spbwc_get_truetype_fonts(){
            $true_type = array('Bruum FY', 'CitadelScriptStd', 'DIN Next LT Pro Light', 'DIN Next LT Pro Medium', 'DIN Next LT Pro Regular', 'Gudea', 'Abel', 'Abril Fatface', 'Acme', 'Advent Pro', 'Aguafina Script', 'Aladin', 'Allura', 'Almendra', 'Almendra Display', 'Almendra SC', 'Amiri', 'Antic', 'Antic Didone', 'Anonymous Pro', 'Antic Slab', 'Arbutus', 'Architects Daughter', 'Aref Ruqaa', 'Arizonia', 'Asset', 'Asul', 'Average', 'Average Sans', 'Averia Gruesa Libre', 'Averia Libre', 'Averia Sans Libre', 'Averia Serif Libre', 'Bad Script', 'Balthazar', 'Belgrano', 'Bilbo', 'Bilbo Swash', 'Boogaloo', 'Bowlby One', 'Bree Serif', 'Bubblegum Sans', 'Bubbler One', 'Buenard', 'Butcherman', 'Cagliostro', 'Cambo', 'Cantarell', 'Cardo', 'Caudex', 'Ceviche One', 'Changa One', 'Chango', 'Chau Philomene One', 'Chela One', 'Cherry Swash', 'Chicle', 'Cinzel', 'Cinzel Decorative', 'Coiny', 'Condiment', 'Contrail One', 'Convergence', 'Cookie', 'Corben', 'Covered By Your Grace', 'Creepster', 'Crete Round', 'Croissant One', 'Damion', 'Dawning of a New Day', 'Days One', 'Delius', 'Delius Swash Caps', 'Delius Unicase', 'Della Respira', 'Devonshire', 'Diplomata', 'Diplomata SC', 'Dorsa', 'Dr Sugiyama', 'Economica', 'Enriqueta', 'Erica One', 'Esteban', 'Euphoria Script', 'Ewert', 'Exo', 'Fanwood Text', 'Farsan', 'Faster One', 'Fauna One', 'Fenix', 'Felipa', 'Fjord One', 'Flamenco', 'Fredericka the Great', 'Fredoka One', 'Fresca', 'Fugaz One', 'Gafata', 'Galdeano', 'Geostar', 'Geostar Fill', 'Germania One', 'Glass Antiqua', 'Goblin One', 'Graduate', 'Gravitas One', 'Great Vibes', 'Handlee', 'Harmattan', 'Herr Von Muellerhoff', 'Holtwood One SC', 'IM Fell DW Pica', 'IM Fell DW Pica SC', 'IM Fell Double Pica', 'IM Fell Double Pica SC', 'IM Fell English', 'IM Fell English SC', 'IM Fell French Canon', 'IM Fell French Canon SC', 'IM Fell Great Primer', 'IM Fell Great Primer SC', 'Imprima', 'Inika', 'Italiana', 'Italianno', 'Jockey One', 'omhuria', 'Joti One', 'Jomhuria', 'Julee', 'Just Me Again Down Here', 'Katibeh', 'Kavivanar', 'Keania One', 'Kelly Slab', 'Kite One', 'Knewave', 'Kotta One', 'Kreon', 'Krona One', 'Leckerli One', 'Ledger', 'Lekton', 'Lemon', 'Lilita One', 'Lily Script One', 'Linden Hill', 'Love Ya Like A Sister ', 'Lovers Quarrel', 'Lusitana', 'Lustria', 'Macondo', 'Macondo Swash Caps', 'Magra', 'Marck Script', 'Marko One', 'Marvel', 'Mate', 'Mate SC', 'Medula One', 'Meera Inimai', 'Merienda', 'Merienda One', 'Mina', 'Mirza', 'Miss Fajardose', 'Modern Antiqua', 'Monofett', 'Monoton', 'Monsieur La Doulaise', 'Montaga', 'Montserrat', 'Montserrat Subrayada', 'Mountains of Christmas', 'Mr Bedfort', 'Mr Dafoe', 'Mr De Haviland', 'Mrs Saint Delafield', 'Mrs Sheppards', 'Niconne', 'Nixie One', 'Nobile', 'Norican', 'Nosifer', 'Offside', 'Oldenburg', 'Oleo Script', 'Oleo Script Swash Caps', 'Orbitron', 'Overlock', 'Overlock SC', 'Ovo', 'Paprika', 'Passero One', 'Passion One', 'Pathway Gothic One', 'Piedra', 'Pinyon Script', 'Pirata One', 'Playball', 'Poiret One', 'Poller One', 'Poly', 'Pompiere', 'Poppins', 'Port Lligat Sans', 'Port Lligat Slab', 'Preahvihear', 'Qwigley', 'Rambla', 'Ranga', 'Reem Kufi', 'Rammetto One', 'Ribeye Marrow', 'Righteous', 'Rochester', 'Rosarivo', 'Rouge Script', 'Ruda', 'Rufina', 'Ruge Boogie', 'Ruluko', 'Ruslan Display', 'Russo One', 'Ruthie', 'Sail A', 'Salsa', 'Sanchez', 'Sancreek', 'Sarina', 'Shadows Into Light Two', 'Short Stack', 'Signika Negative', 'Sintony', 'Smokum', 'Snippet', 'Sofia', 'Sonsie One', 'Sorts Mill Goudy', 'Spirax', 'Squada One', 'Strait', 'Sunflower', 'Swanky and Moo Moo', 'Text Me One', 'Tinyhust', 'The Girl Next Door', 'Titan One', 'Trochut', 'Trykker', 'Tulpen One', 'Unica One', 'Unlock', 'Vast Shadow', 'Viga', 'Voltaire', 'Wellfleet', 'Wendy One', 'Zeyada', 'Yellowtail');
            $true_type_setting = array();
            return array_merge($true_type, $true_type_setting);
        }
        public static function spbwc_file_get_contents($url) {
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
            }
            return $result;
        }
        public static function spbwc_convert_svg_url($path, $file) {
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

                $path_image     = SPBWC_Storelly_IO::spbwc_convert_url_to_path($img_src);

                $data   = self::spbwc_file_get_contents($path_image);
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
                wp_enqueue_style('custom-google-fonts', $google_font_url, array(), null);
            }
        }
        
        public static function spbwc_build_html_page($folder_design, $key, $svg_path, $page_settings, $font_css) {
            $pdf_temp_path = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design . '/pdf-templates';
            if (!file_exists($pdf_temp_path)) {
                wp_mkdir_p($pdf_temp_path);
            }

            $html_path  =  $pdf_temp_path . '/' . $key . '.html';
            $html_url   = SPBWC_PB_CUSTOMER_URL . '/' . $folder_design . '/pdf-templates/' . $key . '.html';
            $svg_string = file_get_contents($svg_path);
            $svg_string = preg_replace("/<(?:\?xml|!DOCTYPE).*?>/", "", $svg_string);

            ob_start();
            include SPBWC_PB_PLUGIN_DIR . 'views/pdf-template.php';
            $template    = ob_get_clean();

            file_put_contents($html_path, $template);
            return $html_url;
        }
        public static function spbwc_request_create_pdf($requests, $folder, $folder_design) {
            $result     = array();
            $multiCurl  = array();
            foreach ($requests as $i => $request) {
                $multiCurl[$i] = wp_remote_get($request['url'], array(
                    'timeout' => 30,
                    'User-Agent' => 'Mozilla/4.0 (compatible;)'
                ));
            }

            foreach ($multiCurl as $k => $res) {
                
                $output_file    = $folder . '/' . $folder_design . '_' . $requests[$k]['index'] . '.pdf';
                $download       = self::spbwc_download_remote_file($res, $output_file);
                if ($download) {
                    $result[$requests[$k]['index']] = $output_file;
                }
            }

            return $result;
        }
        public static function spbwc_download_remote_file($url, $path) {
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
