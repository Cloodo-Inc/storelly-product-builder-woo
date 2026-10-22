<?php
/**
 * Watermarked local PDF (free plan fallback for "Download PDF").
 *
 * Print-ready PDF rendering is a Storelly Cloud feature. So that a free store is
 * never left at a dead end, this builds a plain preview PDF on the merchant's own
 * server from the design's saved frame images, with "Made with Storelly" on every
 * page. Nothing is sent off-site and no library is needed: the file is written
 * directly (one JPEG image per page plus a few lines of text), using GD to turn the
 * saved PNG frames into JPEG.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Local_PDF' ) ) {

    class SPBWC_Local_PDF {

        /** Sub-folder of a design that holds the preview PDF (never mixed with the cloud print files). */
        const SUBFOLDER = 'watermarked-pdf';

        /** Printed on every page. "\267" is the middle dot in the PDF's WinAnsi encoding. */
        const WATERMARK = 'Made with Storelly \\267 storelly.com';

        /**
         * Build the watermarked preview PDF of a design.
         *
         * @param string $folder_design Design folder id (basename inside SPBWC_PB_CUSTOMER_DIR).
         * @return string[] Absolute path of the PDF (one file per design), or an empty array.
         */
        public static function render( $folder_design ) {
            $folder_design = is_string( $folder_design ) ? $folder_design : '';
            if ( '' === $folder_design || $folder_design !== basename( $folder_design ) ) {
                return array();
            }
            if ( ! function_exists( 'imagecreatefrompng' ) || ! function_exists( 'imagejpeg' ) ) {
                return array();
            }

            $path   = SPBWC_PB_CUSTOMER_DIR . '/' . $folder_design;
            $frames = glob( $path . '/frame_*.png' );
            if ( empty( $frames ) ) {
                return array();
            }
            natsort( $frames );

            $dpi = 300;
            if ( file_exists( $path . '/design_output.json' ) ) {
                $output = json_decode( (string) file_get_contents( $path . '/design_output.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local design file, not a remote URL.
                if ( is_array( $output ) && ! empty( $output['dpi'] ) && (int) $output['dpi'] >= 72 ) {
                    $dpi = (int) $output['dpi'];
                }
            }

            $pages = array();
            foreach ( $frames as $frame ) {
                $page = self::frame_to_page( $frame, $dpi );
                if ( $page ) {
                    $pages[] = $page;
                }
            }
            if ( empty( $pages ) ) {
                return array();
            }

            $dir = $path . '/' . self::SUBFOLDER;
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
            $file = $dir . '/' . $folder_design . '_preview.pdf';
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing the preview PDF into the plugin's own uploads folder.
            if ( false === file_put_contents( $file, self::build( $pages ) ) ) {
                return array();
            }
            return array( $file );
        }

        /**
         * One saved frame as a JPEG plus its page size in PDF points.
         *
         * @return array|null
         */
        protected static function frame_to_page( $png, $dpi ) {
            $src = @imagecreatefrompng( $png ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- GD warns on a corrupt file; a skipped frame is handled below.
            if ( ! $src ) {
                return null;
            }
            $w = imagesx( $src );
            $h = imagesy( $src );
            if ( $w < 1 || $h < 1 ) {
                imagedestroy( $src );
                return null;
            }
            // JPEG has no transparency: flatten the frame onto white.
            $flat = imagecreatetruecolor( $w, $h );
            imagefill( $flat, 0, 0, imagecolorallocate( $flat, 255, 255, 255 ) );
            imagecopy( $flat, $src, 0, 0, 0, 0, $w, $h );
            imagedestroy( $src );
            ob_start();
            imagejpeg( $flat, null, 90 );
            $jpeg = (string) ob_get_clean();
            imagedestroy( $flat );
            if ( '' === $jpeg ) {
                return null;
            }
            // Saved frames are screen-size renders, so the page would be a stamp at the design's
            // print DPI; scale it up until the longer side is a readable 400 pt.
            $scale = max( 1, 400 / max( 1, max( $w, $h ) * 72 / $dpi ) );
            return array(
                'jpeg' => $jpeg,
                'px_w' => $w,
                'px_h' => $h,
                'w'    => round( $w * 72 / $dpi * $scale, 2 ),
                'h'    => round( $h * 72 / $dpi * $scale, 2 ),
            );
        }

        /**
         * Serialize pages into a PDF 1.4 document.
         *
         * Objects: 1 catalog, 2 page tree, 3 font, 4 and 5 translucency levels; then
         * per page an image, its content stream and the page itself.
         *
         * @param array $pages Output of frame_to_page().
         * @return string
         */
        protected static function build( $pages ) {
            $objects = array();
            $kids    = array();
            $next    = 6;

            foreach ( $pages as $i => $p ) {
                $img_id  = $next++;
                $cont_id = $next++;
                $page_id = $next++;
                $kids[]  = $page_id . ' 0 R';
                $w       = $p['w'];
                $h       = $p['h'];

                $objects[ $img_id ] = '<< /Type /XObject /Subtype /Image /Width ' . $p['px_w'] . ' /Height ' . $p['px_h']
                    . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $p['jpeg'] ) . " >>\nstream\n"
                    . $p['jpeg'] . "\nendstream";

                // Diagonal mark across the page, plus a small line at the foot.
                $cos    = 0.8660;
                $sin    = 0.5;
                // ~31 glyphs of Helvetica-Bold at ~0.56 em each; keep the rotated line inside the page.
                $text_w = 0.8 * min( $w / $cos, $h / $sin );
                $size   = round( $text_w / ( 31 * 0.56 ), 1 );
                $cx     = round( $w / 2 - ( $text_w / 2 ) * $cos, 2 );
                $cy     = round( $h / 2 - ( $text_w / 2 ) * $sin, 2 );
                $foot   = max( 8, round( $w / 55, 1 ) );
                $stream = 'q ' . $w . ' 0 0 ' . $h . " 0 0 cm /Im0 Do Q\n"
                    . 'q /GS0 gs 1 1 1 rg 0.15 0.15 0.15 RG ' . round( $size / 28, 2 ) . ' w BT 2 Tr /F1 ' . $size . ' Tf ' . $cos . ' ' . $sin . ' ' . -$sin . ' ' . $cos . ' ' . $cx . ' ' . $cy . ' Tm (' . self::WATERMARK . ") Tj ET Q\n"
                    . 'q /GS1 gs 1 1 1 rg 0 0 ' . $w . ' ' . round( $foot * 2.2, 1 ) . ' re f 0.1 0.1 0.1 rg BT /F1 ' . $foot . ' Tf 1 0 0 1 ' . round( $foot, 1 ) . ' ' . round( $foot * 0.8, 1 ) . ' Tm (' . self::WATERMARK . ") Tj ET Q\n";

                $objects[ $cont_id ] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . 'endstream';
                $objects[ $page_id ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $w . ' ' . $h . '] /Contents ' . $cont_id
                    . ' 0 R /Resources << /XObject << /Im0 ' . $img_id . ' 0 R >> /Font << /F1 3 0 R >> /ExtGState << /GS0 4 0 R /GS1 5 0 R >> >> >>';
            }

            return self::assemble( $objects, $kids );
        }

        /**
         * Lay the objects out with a cross-reference table.
         */
        protected static function assemble( $objects, $kids ) {
            $fixed = array(
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                2 => '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>',
                3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
                4 => '<< /Type /ExtGState /ca 0.6 /CA 0.6 >>',
                5 => '<< /Type /ExtGState /ca 0.9 /CA 0.9 >>',
            );
            $all = $fixed + $objects;
            ksort( $all );

            $pdf     = "%PDF-1.4\n";
            $offsets = array();
            foreach ( $all as $id => $body ) {
                $offsets[ $id ] = strlen( $pdf );
                $pdf           .= $id . " 0 obj\n" . $body . "\nendobj\n";
            }
            $xref = strlen( $pdf );
            $max  = max( array_keys( $all ) );
            $pdf .= "xref\n0 " . ( $max + 1 ) . "\n0000000000 65535 f \n";
            for ( $id = 1; $id <= $max; $id++ ) {
                $pdf .= sprintf( "%010d 00000 n \n", isset( $offsets[ $id ] ) ? $offsets[ $id ] : 0 );
            }
            $pdf .= "trailer\n<< /Size " . ( $max + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
            return $pdf;
        }
    }
}
