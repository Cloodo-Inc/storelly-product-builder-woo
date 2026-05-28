<?php
/**
 * Marketplace IO helper.
 *
 * Minimal port of pc-designer's Nbdesigner_IO class — only the methods that the
 * launcher (designer marketplace) module actually calls:
 *
 *  - instance()                — singleton accessor
 *  - get_list_files()          — recursive file scan
 *  - get_list_images()         — recursive file scan filtered to image extensions
 *  - convert_path_to_url()     — convert filesystem path inside the designs dir to a URL
 *  - wp_convert_path_to_url()  — convert ABSPATH-rooted path to site URL
 *
 * Other Nbdesigner_IO helpers (resize/imagick/pdf2image/etc.) are NOT ported
 * because the marketplace data layer does not need them.
 *
 * A class_alias is registered for `Nbdesigner_IO` only when pc-designer is NOT
 * loaded, so the existing launcher code (which calls Nbdesigner_IO::…) keeps
 * working without clashing if both plugins run side-by-side.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Marketplace_IO' ) ) {

    class SPBWC_Marketplace_IO {

        /**
         * Singleton instance.
         *
         * @var SPBWC_Marketplace_IO|null
         */
        protected static $instance = null;

        /**
         * Singleton accessor — mirrors Nbdesigner_IO::instance().
         *
         * @return SPBWC_Marketplace_IO
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Recursively list every file under $folder, up to $levels deep.
         *
         * @param string $folder Absolute filesystem path.
         * @param int    $levels Max recursion depth.
         * @return array|false Array of absolute file paths, or false on bad input.
         */
        public static function get_list_files( $folder = '', $levels = 100 ) {
            if ( empty( $folder ) ) {
                return false;
            }
            if ( ! $levels ) {
                return false;
            }
            $files = array();
            $dir   = @opendir( $folder );
            if ( $dir ) {
                while ( ( $file = readdir( $dir ) ) !== false ) {
                    if ( in_array( $file, array( '.', '..' ), true ) ) {
                        continue;
                    }
                    if ( is_dir( $folder . '/' . $file ) ) {
                        $files2 = self::get_list_files( $folder . '/' . $file, $levels - 1 );
                        if ( $files2 ) {
                            $files = array_merge( $files, $files2 );
                        } else {
                            $files[] = $folder . '/' . $file . '/';
                        }
                    } else {
                        $files[] = $folder . '/' . $file;
                    }
                }
                @closedir( $dir );
            }
            return $files;
        }

        /**
         * Recursively list image files (jpg/jpeg/png/gif).
         *
         * @param string $path  Absolute filesystem path.
         * @param int    $level Max recursion depth.
         * @return array Filtered list of image paths.
         */
        public static function get_list_images( $path, $level = 100 ) {
            $_list = self::get_list_files( $path, $level );
            if ( ! is_array( $_list ) ) {
                return array();
            }
            $list = preg_grep( '/\.(jpg|jpeg|png|gif)(?:[\?\#].*)?$/i', $_list );
            return is_array( $list ) ? $list : array();
        }

        /**
         * Convert an absolute filesystem path inside the storelly designs dir
         * to a public URL.
         *
         * Mirrors Nbdesigner_IO::convert_path_to_url() but anchored on the
         * storelly base directory name (the parent folder of SPBWC_PB_CUSTOMER_DIR).
         *
         * @param string $path Absolute filesystem path.
         * @return string URL.
         */
        public static function convert_path_to_url( $path ) {
            // Anchor on the "designs" segment inside the storelly data dir.
            $anchor = '/storelly-product-builder/designs';
            $pos    = strpos( $path, $anchor );
            if ( false !== $pos ) {
                return SPBWC_PB_CUSTOMER_URL . substr( $path, $pos + strlen( $anchor ) );
            }
            // Fallback: behave like wp_convert_path_to_url.
            return self::wp_convert_path_to_url( $path );
        }

        /**
         * Convert an ABSPATH-rooted filesystem path to a site URL.
         *
         * @param string $path Absolute filesystem path.
         * @return string URL.
         */
        public static function wp_convert_path_to_url( $path = '' ) {
            $url = str_replace(
                wp_normalize_path( untrailingslashit( ABSPATH ) ),
                site_url(),
                wp_normalize_path( $path )
            );
            return esc_url_raw( $url );
        }

        /**
         * Recursively delete a folder (or file).
         *
         * Provided because some legacy launcher paths may call
         * Nbdesigner_IO::delete_folder() when cleaning a removed design.
         *
         * @param string $path Absolute filesystem path.
         * @return bool
         */
        public static function delete_folder( $path ) {
            if ( is_dir( $path ) === true ) {
                $files = array_diff( scandir( $path ), array( '.', '..' ) );
                foreach ( $files as $file ) {
                    self::delete_folder( realpath( $path ) . '/' . $file );
                }
                global $wp_filesystem;
                if ( ! $wp_filesystem ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                return $wp_filesystem->rmdir( $path );
            } elseif ( is_file( $path ) === true ) {
                wp_delete_file( $path );
                return true;
            }
            return false;
        }

        /**
         * Recursive copy. Provided as a no-op-style helper in case any launcher
         * branch still calls it (the marketplace data layer should not need it).
         *
         * @param string $src Source path.
         * @param string $dst Destination path.
         * @return void
         */
        public static function copy_dir( $src, $dst ) {
            if ( file_exists( $dst ) ) {
                self::delete_folder( $dst );
            }
            if ( is_dir( $src ) ) {
                wp_mkdir_p( $dst );
                $files = scandir( $src );
                foreach ( $files as $file ) {
                    if ( '.' !== $file && '..' !== $file ) {
                        self::copy_dir( "$src/$file", "$dst/$file" );
                    }
                }
            } elseif ( file_exists( $src ) ) {
                copy( $src, $dst );
            }
        }

        /**
         * Stub — preview generation is not part of the minimum marketplace data
         * layer. Returns null so callers can detect the no-op.
         *
         * @return null
         */
        public static function getDesignPreview() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
            return null;
        }

        /**
         * Stub — folder deletion for a removed user design. The launcher's
         * delete path already calls delete_folder() directly when needed; this
         * remains for API compatibility with code that referenced the old name.
         *
         * @param string $folder Design folder slug under SPBWC_PB_CUSTOMER_DIR.
         * @return bool
         */
        public static function deleteResourceUserDesignFolder( $folder ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
            $folder = trim( (string) $folder, '/' );
            if ( '' === $folder ) {
                return false;
            }
            $path = trailingslashit( SPBWC_PB_CUSTOMER_DIR ) . $folder;
            return self::delete_folder( $path );
        }
    }

    /*
     * Coexistence with pc-designer: only register the Nbdesigner_IO alias when
     * pc-designer (which owns that class) is NOT loaded on the site.
     */
    if ( ! class_exists( 'Nbdesigner_IO' ) ) {
        class_alias( 'SPBWC_Marketplace_IO', 'Nbdesigner_IO' );
    }
}
