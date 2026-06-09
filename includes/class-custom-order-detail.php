<?php
/**
 * Custom Order Detail — Storelly-native admin workspace for a single custom order.
 *
 * Primary place a print/POD shop works an order: artwork, print files, options, history,
 * customer activity, production checklist, files. Tabbed (instant client-side switch), token-driven
 * (custom-order-admin.css/js), HPOS-safe; the WooCommerce order-edit screen is a secondary link.
 * See docs/SPEC_CUSTOM_ORDER_DETAIL.md (D1–D6 + §8 R1–R5).
 *
 * Rendered by SPBWC_Storelly_Admin_Options::spbwc_orders_manager() when ?view={id} is set.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Custom_Order_Detail' ) ) {

    class SPBWC_Custom_Order_Detail {

        const PRODUCTION_STEPS_META = '_spbwc_production_steps';

        public static function init() {
            add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        }

        /** Are we on the detail view? */
        protected static function is_detail_screen() {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            $view = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0;
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            return ( SPBWC_PB_ORDERS_SLUG === $page && $view > 0 );
        }

        /** Token-driven CSS + instant-tab JS, only on the detail view. */
        public static function enqueue() {
            if ( ! self::is_detail_screen() ) {
                return;
            }
            $css = SPBWC_PB_PLUGIN_DIR . 'static/css/custom-order-admin.css';
            $js  = SPBWC_PB_PLUGIN_DIR . 'static/js/custom-order-admin.js';
            wp_enqueue_style( 'spbwc-custom-order-admin', SPBWC_PB_CSS_URL . 'custom-order-admin.css', array( 'spbwc-admin-ui' ), file_exists( $css ) ? filemtime( $css ) : SPBWC_PB_VERSION );
            wp_enqueue_script( 'spbwc-custom-order-admin', SPBWC_PB_JS_URL . 'custom-order-admin.js', array(), file_exists( $js ) ? filemtime( $js ) : SPBWC_PB_VERSION, true );
        }

        /** Admin URL of the native detail screen for an order. */
        public static function url( $order_id ) {
            return add_query_arg(
                array( 'page' => SPBWC_PB_ORDERS_SLUG, 'view' => (int) $order_id ),
                admin_url( 'admin.php' )
            );
        }

        /** Production checklist steps (fixed, OD-D2). key => label. */
        protected static function production_steps() {
            return array(
                'artwork_approved' => __( 'Artwork approved', 'storelly-product-builder-for-woocommerce' ),
                'files_ready'      => __( 'Print files ready', 'storelly-product-builder-for-woocommerce' ),
                'sent_to_print'    => __( 'Sent to print', 'storelly-product-builder-for-woocommerce' ),
                'shipped'          => __( 'Shipped', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        // ==================================================================
        //  Action handlers (admin_init): note, production, re-order, download-all
        // ==================================================================
        public static function handle_actions() {
            // Download-all (GET, streams + exits).
            if ( isset( $_GET['spbwc_co_dlall'] ) ) {
                self::handle_download_all();
                return;
            }
            if ( ! isset( $_POST['spbwc_co_detail_action'] ) ) {
                return;
            }
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                return;
            }
            $action   = sanitize_text_field( wp_unslash( $_POST['spbwc_co_detail_action'] ) );
            $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
            $nonce    = isset( $_POST['spbwc_co_detail_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spbwc_co_detail_nonce'] ) ) : '';
            if ( ! $order_id || ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_co_detail_' . $order_id ) ) {
                return;
            }
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }

            if ( 'add_note' === $action ) {
                $note = isset( $_POST['spbwc_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['spbwc_note'] ) ) : '';
                if ( '' !== $note ) {
                    $order->add_order_note( $note );
                }
                self::redirect( $order_id, 'history' );
            } elseif ( 'production' === $action ) {
                $steps   = array();
                $checked = isset( $_POST['spbwc_production'] ) && is_array( $_POST['spbwc_production'] )
                    ? array_map( 'sanitize_key', wp_unslash( $_POST['spbwc_production'] ) )
                    : array();
                foreach ( array_keys( self::production_steps() ) as $k ) {
                    $steps[ $k ] = in_array( $k, $checked, true ) ? 1 : 0;
                }
                $order->update_meta_data( self::PRODUCTION_STEPS_META, $steps );
                $order->save();
                self::redirect( $order_id, 'production' );
            } elseif ( 'reorder' === $action ) {
                $new_id = self::reorder( $order );
                if ( $new_id ) {
                    wp_safe_redirect( add_query_arg( 'spbwc_reordered', 1, self::url( $new_id ) ) );
                    exit;
                }
                self::redirect( $order_id, 'design' );
            }
        }

        protected static function redirect( $order_id, $tab = '' ) {
            $url = self::url( $order_id ) . ( $tab ? '#tab-' . $tab : '' );
            wp_safe_redirect( $url );
            exit;
        }

        /** Clone the order's designs into a NEW draft order for the same customer (OD-R1). */
        protected static function reorder( $order ) {
            if ( ! function_exists( 'wc_create_order' ) ) {
                return 0;
            }
            $new = wc_create_order( array( 'customer_id' => $order->get_customer_id() ) );
            if ( is_wp_error( $new ) || ! $new ) {
                return 0;
            }
            $copied = false;
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                if ( ! $product ) {
                    continue;
                }
                $new_item_id = $new->add_product( $product, $item->get_quantity() );
                if ( ! $new_item_id ) {
                    continue;
                }
                // Copy every meta from the source item (human lines + _pcpb_*), then
                // override the design folder with a fresh copy-on-write clone.
                foreach ( $item->get_meta_data() as $meta ) {
                    $data = $meta->get_data();
                    if ( isset( $data['key'], $data['value'] ) ) {
                        wc_update_order_item_meta( $new_item_id, $data['key'], $data['value'] );
                    }
                }
                $folder = (string) $item->get_meta( '_pcpb_folder' );
                if ( '' !== $folder ) {
                    $clone = SPBWC_Storelly_IO::spbwc_clone_design_folder( $folder );
                    if ( '' !== $clone ) {
                        wc_update_order_item_meta( $new_item_id, '_pcpb_folder', $clone );
                    }
                    wc_delete_order_item_meta( $new_item_id, '_pcpb_pdf_status' );
                    wc_delete_order_item_meta( $new_item_id, '_pcpb_preview_downloads' );
                    $copied = true;
                }
            }
            if ( ! $copied ) {
                $new->delete( true );
                return 0;
            }
            $new->set_address( $order->get_address( 'billing' ), 'billing' );
            $new->set_address( $order->get_address( 'shipping' ), 'shipping' );
            $new->add_order_note( sprintf( /* translators: %d: source order id. */ __( 'Re-order created from #%d (Storelly Custom Order).', 'storelly-product-builder-for-woocommerce' ), $order->get_id() ) );
            $new->calculate_totals();
            $new->set_status( 'pending' );
            $new->save();
            return (int) $new->get_id();
        }

        /** Stream a zip of every design file in the order (OD-R2: all files). */
        protected static function handle_download_all() {
            $order_id = absint( wp_unslash( $_GET['spbwc_co_dlall'] ) );
            $nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! $order_id || ! $nonce || ! wp_verify_nonce( $nonce, 'spbwc_co_dlall_' . $order_id ) ) {
                return;
            }
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }
            $files = array();
            foreach ( $order->get_items() as $item ) {
                $folder = is_callable( array( $item, 'get_meta' ) ) ? (string) $item->get_meta( '_pcpb_folder' ) : '';
                if ( '' === $folder ) {
                    continue;
                }
                foreach ( SPBWC_Storelly_IO::spbwc_get_list_files_by_type( SPBWC_PB_CUSTOMER_DIR . '/' . $folder, 'png|jpg|jpeg|svg|pdf', 3 ) as $f ) {
                    $files[] = $f;
                }
            }
            $files = array_values( array_unique( $files ) );
            if ( empty( $files ) ) {
                wp_die( esc_html__( 'No design files to download.', 'storelly-product-builder-for-woocommerce' ), 404 );
            }
            $zip_name = 'order_' . $order_id . '_designs.zip';
            $zip_path = SPBWC_PB_DATA_DIR . '/download/' . $zip_name;
            if ( ! SPBWC_Storelly_PB_Util::spbwc_zip_files( $files, $zip_path ) ) {
                wp_die( esc_html__( 'Could not prepare the download.', 'storelly-product-builder-for-woocommerce' ), 500 );
            }
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                WP_Filesystem();
            }
            $data = $wp_filesystem ? $wp_filesystem->get_contents( $zip_path ) : false;
            wp_delete_file( $zip_path );
            if ( false === $data ) {
                wp_die( esc_html__( 'Could not read the download.', 'storelly-product-builder-for-woocommerce' ), 500 );
            }
            nocache_headers();
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $zip_name ) . '"' );
            header( 'Content-Length: ' . strlen( $data ) );
            echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw binary zip stream.
            exit;
        }

        public static function dlall_url( $order_id ) {
            return wp_nonce_url( add_query_arg( 'spbwc_co_dlall', (int) $order_id, self::url( $order_id ) ), 'spbwc_co_dlall_' . (int) $order_id );
        }

        // ==================================================================
        //  Render
        // ==================================================================
        public static function render( $order_id ) {
            $order = wc_get_order( absint( $order_id ) );
            $list_url = admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG );
            if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Order not found.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
                echo '<a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="' . esc_url( $list_url ) . '">' . esc_html__( '← Back to Custom Orders', 'storelly-product-builder-for-woocommerce' ) . '</a></div>';
                return;
            }

            $items = self::design_items( $order );
            $files = self::order_files( $items );
            $agg   = self::aggregate_pdf_status( $order );
            $name  = trim( $order->get_formatted_billing_full_name() );
            ?>
            <div class="wrap spbwc-co-detail">
                <p class="spbwc-co-detail__back"><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to Custom Orders', 'storelly-product-builder-for-woocommerce' ); ?></a></p>
                <input type="hidden" id="post_ID" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />

                <?php if ( isset( $_GET['spbwc_reordered'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flash ?>
                    <div class="notice notice-success"><p><?php esc_html_e( 'Re-order created — you are viewing the new draft order.', 'storelly-product-builder-for-woocommerce' ); ?></p></div>
                <?php endif; ?>

                <?php if ( class_exists( 'SPBWC_Custom_Order_Sample' ) && '1' === (string) $order->get_meta( '_spbwc_is_sample' ) ) : ?>
                    <div class="spbwc-co-sample-note">
                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                        <div class="spbwc-co-sample-note__body">
                            <strong><?php esc_html_e( 'Sample custom order', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                            <?php esc_html_e( 'Storelly created this so you can explore the workspace. Your real orders are untouched — remove it when you are done.', 'storelly-product-builder-for-woocommerce' ); ?>
                        </div>
                        <?php echo SPBWC_Custom_Order_Sample::remove_cta_html( 'banner' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper output is fully escaped. ?>
                    </div>
                <?php endif; ?>

                <!-- Sticky header + CTA -->
                <div class="spbwc-co-head">
                    <div class="spbwc-co-head__row">
                        <div>
                            <h2 class="spbwc-co-head__title">
                                <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                                <?php echo esc_html( sprintf( /* translators: %d: order id. */ __( 'Order #%d', 'storelly-product-builder-for-woocommerce' ), $order->get_id() ) ); ?>
                                <span class="spbwc-co-pill spbwc-co-pill--<?php echo esc_attr( self::status_pill( $order->get_status() ) ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                                <?php echo wp_kses_post( self::pdf_badge( $agg ) ); ?>
                            </h2>
                            <p class="spbwc-co-head__meta">
                                <?php echo esc_html( $name ? $name : __( 'Guest', 'storelly-product-builder-for-woocommerce' ) ); ?>
                                <?php if ( $order->get_billing_email() ) : ?> · <?php echo esc_html( $order->get_billing_email() ); ?><?php endif; ?>
                                · <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
                                · <strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
                            </p>
                        </div>
                        <div class="spbwc-co-head__actions">
                            <?php if ( ! empty( $files ) ) : ?>
                                <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( self::dlall_url( $order->get_id() ) ); ?>">
                                    <span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'Download all files', 'storelly-product-builder-for-woocommerce' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( class_exists( 'SPBWC_Order_PDF' ) && SPBWC_Order_PDF::is_enabled() && ! empty( $items ) ) : ?>
                                <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( SPBWC_Order_PDF::regenerate_url( $order->get_id() ) ); ?>">
                                    <span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Regenerate PDFs', 'storelly-product-builder-for-woocommerce' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( ! empty( $items ) ) : ?>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'spbwc_co_detail_' . $order->get_id(), 'spbwc_co_detail_nonce' ); ?>
                                    <input type="hidden" name="spbwc_co_detail_action" value="reorder" />
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
                                    <button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost"><span class="dashicons dashicons-update-alt" aria-hidden="true"></span> <?php esc_html_e( 'Re-order', 'storelly-product-builder-for-woocommerce' ); ?></button>
                                </form>
                            <?php endif; ?>
                            <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
                                <span class="dashicons dashicons-external" aria-hidden="true"></span> <?php esc_html_e( 'Open in WooCommerce', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="spbwc-co-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Custom order sections', 'storelly-product-builder-for-woocommerce' ); ?>">
                    <?php
                    self::tab_btn( 'design', __( 'Design', 'storelly-product-builder-for-woocommerce' ), count( $items ) );
                    self::tab_btn( 'summary', __( 'Summary', 'storelly-product-builder-for-woocommerce' ), null );
                    self::tab_btn( 'history', __( 'History', 'storelly-product-builder-for-woocommerce' ), null );
                    self::tab_btn( 'customer', __( 'Customer', 'storelly-product-builder-for-woocommerce' ), null );
                    self::tab_btn( 'production', __( 'Production', 'storelly-product-builder-for-woocommerce' ), null );
                    self::tab_btn( 'files', __( 'Files', 'storelly-product-builder-for-woocommerce' ), count( $files ) );
                    ?>
                </div>

                <div class="spbwc-co-tab is-active" id="tab-design" role="tabpanel"><?php self::tab_design( $order, $items ); ?></div>
                <div class="spbwc-co-tab" id="tab-summary" role="tabpanel"><?php self::tab_summary( $order ); ?></div>
                <div class="spbwc-co-tab" id="tab-history" role="tabpanel"><?php self::tab_history( $order ); ?></div>
                <div class="spbwc-co-tab" id="tab-customer" role="tabpanel"><?php self::tab_customer( $order ); ?></div>
                <div class="spbwc-co-tab" id="tab-production" role="tabpanel"><?php self::tab_production( $order ); ?></div>
                <div class="spbwc-co-tab" id="tab-files" role="tabpanel"><?php self::tab_files( $files ); ?></div>
            </div>
            <?php
        }

        protected static function tab_btn( $id, $label, $count ) {
            echo '<button type="button" class="spbwc-co-tab-btn" role="tab" data-tab="' . esc_attr( $id ) . '" aria-controls="tab-' . esc_attr( $id ) . '">';
            echo esc_html( $label );
            if ( null !== $count ) {
                echo ' <span class="spbwc-co-tab-btn__count">' . esc_html( (string) (int) $count ) . '</span>';
            }
            echo '</button>';
        }

        protected static function note( $text ) {
            echo '<p class="spbwc-co-note">' . esc_html( $text ) . '</p>';
        }

        // ---- Tab: Design -----------------------------------------------
        protected static function tab_design( $order, $items ) {
            ?>
            <div class="spbwc-co-card">
                <h2><span class="dashicons dashicons-art" aria-hidden="true"></span> <?php esc_html_e( 'Design items', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <?php self::note( __( 'Artwork and print-ready files for this order. Use Download to grab the files to send to your printer.', 'storelly-product-builder-for-woocommerce' ) ); ?>
                <?php if ( empty( $items ) ) : ?>
                    <p><?php esc_html_e( 'No custom designs in this order.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <?php else : ?>
                    <?php if ( class_exists( 'SPBWC_Order_PDF' ) && ! SPBWC_Order_PDF::is_enabled() ) : ?>
                        <div class="spbwc-co-cloud-cta" style="display:flex;align-items:center;gap:12px;padding:12px 14px;margin-bottom:14px;border:1px solid var(--st-pill-pending-bg,#fde68a);background:var(--st-cloud-cta-bg,#fffbeb);border-radius:var(--nbd-radius-md,8px);">
                            <span class="dashicons dashicons-cloud" aria-hidden="true" style="color:var(--st-pill-pending-text,#92400e);"></span>
                            <div style="flex:1;">
                                <strong><?php esc_html_e( 'Print-ready PDF needs Storelly Cloud', 'storelly-product-builder-for-woocommerce' ); ?></strong>
                                <div class="spbwc-co-note" style="margin:2px 0 0;"><?php esc_html_e( 'Connect your store to Storelly to generate and download print PDFs. Setup runs automatically inside wp-admin — no need to leave for storelly.com.', 'storelly-product-builder-for-woocommerce' ); ?></div>
                            </div>
                            <a class="spbwc-cta-btn spbwc-cta-btn--solid" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_OVERVIEW_SLUG ) ); ?>">
                                <?php esc_html_e( 'Connect Storelly', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php foreach ( $items as $it ) : ?>
                        <div class="spbwc-co-item">
                            <div>
                                <label style="display:block;margin-bottom:6px;font-size:12px;">
                                    <input type="checkbox" class="storelly_order_item_id" name="_storelly_order_item_id[]" value="<?php echo esc_attr( (string) $it['item_id'] ); ?>" checked /> <?php esc_html_e( 'Include', 'storelly-product-builder-for-woocommerce' ); ?>
                                </label>
                                <div class="spbwc-co-gallery">
                                    <?php foreach ( $it['previews'] as $src ) : ?>
                                        <a href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $src ); ?>" alt="" loading="lazy" /></a>
                                    <?php endforeach; ?>
                                    <?php if ( empty( $it['previews'] ) ) : ?><span class="dashicons dashicons-format-image" aria-hidden="true" style="font-size:48px;color:var(--nbd-st-text-mute,#8c8f94);"></span><?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <strong><?php echo esc_html( $it['name'] ); ?></strong> &times; <?php echo esc_html( (string) $it['qty'] ); ?>
                                <?php echo wp_kses_post( self::pdf_badge( $it['pdf_status'] ) ); ?>
                                <?php if ( '' !== $it['specs'] ) : ?><div style="margin-top:6px;" class="spbwc-co-note"><?php echo esc_html( $it['specs'] ); ?></div><?php endif; ?>
                                <?php if ( ! empty( $it['options'] ) ) : ?>
                                    <ul class="spbwc-co-spec">
                                        <?php foreach ( $it['options'] as $opt ) : ?>
                                            <li><span class="lbl"><?php echo esc_html( $opt['name'] ); ?></span><span><?php echo esc_html( $opt['value'] ); ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if ( '' !== $it['designer_url'] ) : ?><p style="margin-top:8px;"><a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $it['designer_url'] ); ?>"><?php esc_html_e( 'View in designer', 'storelly-product-builder-for-woocommerce' ); ?></a></p><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="spbwc-co-dlbar">
                        <label><input type="checkbox" id="storelly_order_design_check_all" checked /> <small><?php esc_html_e( 'All items', 'storelly-product-builder-for-woocommerce' ); ?></small></label>
                        <select name="storelly_design_type_download">
                            <option value="pdf"><?php esc_html_e( 'PDF (print)', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="pdf-preview"><?php esc_html_e( 'PDF preview', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="png"><?php esc_html_e( 'PNG', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="png-preview"><?php esc_html_e( 'PNG preview', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="svg"><?php esc_html_e( 'SVG', 'storelly-product-builder-for-woocommerce' ); ?></option>
                        </select>
                        <img src="<?php echo esc_url( SPBWC_PB_ASSETS_URL . 'images/loading.gif' ); ?>" class="storelly_loaded" id="storelly_order_submit_loading" />
                        <a href="#" class="spbwc-cta-btn spbwc-cta-btn--solid" id="storelly_download_design_by_type"><?php esc_html_e( 'Download selected', 'storelly-product-builder-for-woocommerce' ); ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        // ---- Tab: Summary ----------------------------------------------
        protected static function tab_summary( $order ) {
            ?>
            <div class="spbwc-co-card">
                <h2><span class="dashicons dashicons-list-view" aria-hidden="true"></span> <?php esc_html_e( 'Order summary', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <ul class="spbwc-co-spec">
                    <?php foreach ( $order->get_items() as $line ) : ?>
                        <li><span class="lbl"><?php echo esc_html( $line->get_name() . ' × ' . $line->get_quantity() ); ?></span><span><?php echo wp_kses_post( wc_price( $line->get_total() ) ); ?></span></li>
                    <?php endforeach; ?>
                    <li><span class="lbl"><?php esc_html_e( 'Shipping', 'storelly-product-builder-for-woocommerce' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></span></li>
                    <li><span class="lbl"><strong><?php esc_html_e( 'Total', 'storelly-product-builder-for-woocommerce' ); ?></strong></span><span><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></span></li>
                </ul>
                <div class="spbwc-co-addr">
                    <div><strong><?php esc_html_e( 'Billing', 'storelly-product-builder-for-woocommerce' ); ?></strong><br /><?php echo wp_kses_post( $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : '—' ); ?></div>
                    <div><strong><?php esc_html_e( 'Shipping', 'storelly-product-builder-for-woocommerce' ); ?></strong><br /><?php echo wp_kses_post( $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : '—' ); ?></div>
                </div>
            </div>
            <?php
        }

        // ---- Tab: History ----------------------------------------------
        protected static function tab_history( $order ) {
            $notes = function_exists( 'wc_get_order_notes' ) ? wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) : array();
            ?>
            <div class="spbwc-co-card">
                <h2><span class="dashicons dashicons-backup" aria-hidden="true"></span> <?php esc_html_e( 'Order history', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <?php self::note( __( 'Status changes and internal notes. Notes are private to your team.', 'storelly-product-builder-for-woocommerce' ) ); ?>
                <?php if ( empty( $notes ) ) : ?>
                    <p class="spbwc-co-note"><?php esc_html_e( 'No notes yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <?php else : ?>
                    <ul class="spbwc-co-timeline">
                        <?php foreach ( $notes as $n ) : ?>
                            <li>
                                <div style="font-size:13px;"><?php echo wp_kses_post( wpautop( wptexturize( $n->content ) ) ); ?></div>
                                <div class="meta"><?php echo esc_html( $n->added_by . ' · ' . ( $n->date_created ? $n->date_created->date_i18n( 'M j, Y H:i' ) : '' ) ); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" style="margin-top:12px;">
                    <?php wp_nonce_field( 'spbwc_co_detail_' . $order->get_id(), 'spbwc_co_detail_nonce' ); ?>
                    <input type="hidden" name="spbwc_co_detail_action" value="add_note" />
                    <input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
                    <textarea name="spbwc_note" rows="2" style="width:100%;" placeholder="<?php esc_attr_e( 'Add an internal note…', 'storelly-product-builder-for-woocommerce' ); ?>"></textarea>
                    <button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" style="margin-top:6px;"><?php esc_html_e( 'Add note', 'storelly-product-builder-for-woocommerce' ); ?></button>
                </form>
            </div>
            <?php
        }

        // ---- Tab: Customer ---------------------------------------------
        protected static function tab_customer( $order ) {
            $stats = self::customer_stats( $order, (int) $order->get_customer_id() );
            ?>
            <div class="spbwc-co-card">
                <h2><span class="dashicons dashicons-admin-users" aria-hidden="true"></span> <?php esc_html_e( 'Customer activity', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <?php self::note( __( 'A quick read on this customer — lifetime value and their design behaviour.', 'storelly-product-builder-for-woocommerce' ) ); ?>
                <ul class="spbwc-co-spec">
                    <li><span class="lbl"><?php esc_html_e( 'Total orders', 'storelly-product-builder-for-woocommerce' ); ?></span><span><?php echo esc_html( number_format_i18n( $stats['orders'] ) ); ?></span></li>
                    <li><span class="lbl"><?php esc_html_e( 'Total spent', 'storelly-product-builder-for-woocommerce' ); ?></span><span><?php echo wp_kses_post( wc_price( $stats['spent'] ) ); ?></span></li>
                    <li><span class="lbl"><?php esc_html_e( 'Saved designs', 'storelly-product-builder-for-woocommerce' ); ?></span><span><?php echo esc_html( number_format_i18n( $stats['saved'] ) ); ?></span></li>
                    <li><span class="lbl"><?php esc_html_e( 'Preview downloads', 'storelly-product-builder-for-woocommerce' ); ?></span><span><?php echo esc_html( number_format_i18n( $stats['downloads'] ) ); ?></span></li>
                </ul>
                <?php if ( ! empty( $stats['other_orders'] ) ) : ?>
                    <p style="margin:12px 0 4px;font-weight:600;font-size:12px;"><?php esc_html_e( 'Other custom orders', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    <ul style="list-style:none;margin:0;padding:0;font-size:13px;">
                        <?php foreach ( $stats['other_orders'] as $oid ) : ?>
                            <li style="padding:3px 0;"><a href="<?php echo esc_url( self::url( $oid ) ); ?>">#<?php echo esc_html( (string) $oid ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php
        }

        // ---- Tab: Production -------------------------------------------
        protected static function tab_production( $order ) {
            $saved = $order->get_meta( self::PRODUCTION_STEPS_META );
            $saved = is_array( $saved ) ? $saved : array();
            ?>
            <div class="spbwc-co-card">
                <h2><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Production', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <?php self::note( __( 'Track fulfilment independently of the WooCommerce status: approve artwork, prep files, send to print, ship.', 'storelly-product-builder-for-woocommerce' ) ); ?>
                <form method="post" class="spbwc-co-prod">
                    <?php wp_nonce_field( 'spbwc_co_detail_' . $order->get_id(), 'spbwc_co_detail_nonce' ); ?>
                    <input type="hidden" name="spbwc_co_detail_action" value="production" />
                    <input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
                    <?php foreach ( self::production_steps() as $key => $label ) : ?>
                        <label><input type="checkbox" name="spbwc_production[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $saved[ $key ] ) ); ?> /> <?php echo esc_html( $label ); ?></label>
                    <?php endforeach; ?>
                    <button type="submit" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" style="margin-top:6px;"><?php esc_html_e( 'Save', 'storelly-product-builder-for-woocommerce' ); ?></button>
                </form>
            </div>
            <?php
        }

        // ---- Tab: Files ------------------------------------------------
        protected static function tab_files( $files ) {
            ?>
            <div class="spbwc-co-card">
                <h2><span class="dashicons dashicons-media-default" aria-hidden="true"></span> <?php esc_html_e( 'Files', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <?php self::note( __( 'Every generated and uploaded asset for this order. Use “Download all files” in the header to grab them as a zip.', 'storelly-product-builder-for-woocommerce' ) ); ?>
                <?php if ( empty( $files ) ) : ?>
                    <p class="spbwc-co-note"><?php esc_html_e( 'No files yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <?php else : ?>
                    <ul class="spbwc-co-files">
                        <?php foreach ( $files as $f ) : ?>
                            <?php $u = SPBWC_Storelly_IO::spbwc_convert_path_to_url( $f ); $sz = is_file( $f ) ? filesize( $f ) : 0; ?>
                            <li><a href="<?php echo esc_url( $u ); ?>" target="_blank" rel="noopener"><?php echo esc_html( basename( $f ) ); ?></a><span class="sz"><?php echo esc_html( $sz ? size_format( $sz ) : '' ); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php
        }

        // ==================================================================
        //  Data helpers
        // ==================================================================
        protected static function design_items( $order ) {
            $out = array();
            foreach ( $order->get_items() as $item_id => $item ) {
                $folder = is_callable( array( $item, 'get_meta' ) ) ? (string) $item->get_meta( '_pcpb_folder' ) : '';
                if ( '' === $folder ) {
                    continue;
                }
                $previews = array();
                foreach ( SPBWC_Storelly_IO::spbwc_get_list_images( SPBWC_PB_CUSTOMER_DIR . '/' . $folder . '/preview', 1 ) as $img ) {
                    $previews[] = SPBWC_Storelly_IO::spbwc_convert_path_to_url( $img );
                }
                sort( $previews );
                $options = array();
                $op = $item->get_meta( '_pcpb_option_price' );
                if ( is_array( $op ) && ! empty( $op['fields'] ) && is_array( $op['fields'] ) ) {
                    foreach ( $op['fields'] as $f ) {
                        if ( isset( $f['name'], $f['value_name'] ) ) {
                            $options[] = array( 'name' => (string) $f['name'], 'value' => wp_strip_all_tags( (string) $f['value_name'] ) );
                        }
                    }
                }
                $out[] = array(
                    'item_id'      => (int) $item_id,
                    'name'         => $item->get_name(),
                    'qty'          => (int) $item->get_quantity(),
                    'folder'       => $folder,
                    'previews'     => $previews,
                    'options'      => $options,
                    'specs'        => self::print_specs( $folder ),
                    'pdf_status'   => (string) $item->get_meta( '_pcpb_pdf_status' ),
                    'designer_url' => self::designer_url( $item, $folder ),
                );
            }
            return $out;
        }

        /**
         * Build the "View in designer" URL for an order line item.
         *
         * Opens the storefront builder pre-loaded with the buyer's saved design
         * folder (config.json + design.json). Mirrors the established builder
         * preview link (oid + pid + spbwc_builder_preview_action nonce, see
         * SPBWC_Visual_Builder_Admin / SPBWC_B2B_Reorders) and adds
         * spbwc_view_folder so js_config.php loads this exact design rather than
         * the option-set template. Empty when the builder page is unpublished or
         * the product no longer maps to an option set.
         *
         * @param WC_Order_Item_Product $item   Order line item.
         * @param string                $folder Design folder name.
         * @return string Designer URL, or '' when unavailable.
         */
        public static function designer_url( $item, $folder ) {
            if ( '' === $folder || ! class_exists( 'STORELLY_FRONTEND_OPTIONS' ) ) {
                return '';
            }
            $pb_page = SPBWC_Storelly_PB_Util::spbwc_get_url_page( 'product_builder' );
            if ( '' === $pb_page || '#' === $pb_page ) {
                return '';
            }
            $pid = is_callable( array( $item, 'get_variation_id' ) ) && (int) $item->get_variation_id()
                ? (int) $item->get_variation_id()
                : (int) $item->get_product_id();
            $oid = (int) STORELLY_FRONTEND_OPTIONS::get_product_option( $pid );
            if ( $oid <= 0 ) {
                return '';
            }
            return add_query_arg(
                array(
                    'oid'               => $oid,
                    'pid'               => $pid,
                    'spbwc_view_folder' => rawurlencode( $folder ),
                    '_wpnonce'          => wp_create_nonce( 'spbwc_builder_preview_action' ),
                ),
                $pb_page
            );
        }

        protected static function order_files( $items ) {
            $files = array();
            foreach ( $items as $it ) {
                if ( '' === $it['folder'] ) {
                    continue;
                }
                foreach ( SPBWC_Storelly_IO::spbwc_get_list_files_by_type( SPBWC_PB_CUSTOMER_DIR . '/' . $it['folder'], 'png|jpg|jpeg|svg|pdf', 3 ) as $f ) {
                    $files[] = $f;
                }
            }
            return array_values( array_unique( $files ) );
        }

        protected static function print_specs( $folder ) {
            $path = SPBWC_PB_CUSTOMER_DIR . '/' . $folder . '/config.json';
            if ( ! file_exists( $path ) ) {
                return '';
            }
            $json = SPBWC_Storelly_IO::spbwc_get_local_file_contents( $path );
            if ( false === $json ) {
                return '';
            }
            $cfg = json_decode( $json, true );
            if ( ! is_array( $cfg ) ) {
                return '';
            }
            $parts = array();
            if ( isset( $cfg['dpi'] ) ) { $parts[] = (int) $cfg['dpi'] . ' DPI'; }
            if ( isset( $cfg['unit'] ) ) { $parts[] = sanitize_text_field( (string) $cfg['unit'] ); }
            if ( isset( $cfg['views'] ) && is_array( $cfg['views'] ) ) { $parts[] = sprintf( /* translators: %d: number of views. */ _n( '%d view', '%d views', count( $cfg['views'] ), 'storelly-product-builder-for-woocommerce' ), count( $cfg['views'] ) ); }
            return implode( ' · ', $parts );
        }

        protected static function aggregate_pdf_status( $order ) {
            $done = false; $failed = false; $pending = false; $any = false;
            foreach ( $order->get_items() as $item ) {
                if ( ! is_callable( array( $item, 'get_meta' ) ) || '' === (string) $item->get_meta( '_pcpb_folder' ) ) { continue; }
                $any = true;
                $s = (string) $item->get_meta( '_pcpb_pdf_status' );
                if ( 'done' === $s ) { $done = true; } elseif ( 'failed' === $s ) { $failed = true; } elseif ( '' !== $s ) { $pending = true; }
            }
            if ( ! $any ) { return ''; }
            if ( $failed ) { return 'failed'; }
            if ( $done && ! $pending ) { return 'done'; }
            if ( $done || $pending ) { return 'partial'; }
            return 'none';
        }

        protected static function pdf_badge( $status ) {
            $map = array(
                'done'    => array( 'ok', __( 'PDF ready', 'storelly-product-builder-for-woocommerce' ) ),
                'failed'  => array( 'off', __( 'PDF failed', 'storelly-product-builder-for-woocommerce' ) ),
                'partial' => array( 'warn', __( 'PDF partial', 'storelly-product-builder-for-woocommerce' ) ),
            );
            if ( ! isset( $map[ $status ] ) ) {
                return '';
            }
            return '<span class="spbwc-co-pill spbwc-co-pill--' . esc_attr( $map[ $status ][0] ) . '" style="margin-left:6px;">' . esc_html( $map[ $status ][1] ) . '</span>';
        }

        protected static function status_pill( $status ) {
            if ( in_array( $status, array( 'completed', 'processing' ), true ) ) { return 'ok'; }
            if ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) { return 'off'; }
            if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) { return 'warn'; }
            return 'neutral';
        }

        /** Customer lifetime + design stats, cached 5 min (R5 perf). */
        protected static function customer_stats( $order, $cid ) {
            $empty = array( 'orders' => 0, 'spent' => 0.0, 'saved' => 0, 'downloads' => 0, 'other_orders' => array() );
            if ( $cid <= 0 || ! function_exists( 'wc_get_orders' ) ) {
                return $empty;
            }
            $cache_key = 'spbwc_co_cust_' . $cid;
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                // other_orders may include current; filter at render so cache is shareable.
                $cached['other_orders'] = array_values( array_filter( $cached['other_orders'], function ( $oid ) use ( $order ) { return (int) $oid !== $order->get_id(); } ) );
                return $cached;
            }
            $stats = $empty;
            $cust_orders = wc_get_orders( array( 'customer_id' => $cid, 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
            foreach ( $cust_orders as $co ) {
                $stats['orders']++;
                $stats['spent'] += (float) $co->get_total();
                $has_design = false;
                foreach ( $co->get_items() as $ci ) {
                    if ( is_callable( array( $ci, 'get_meta' ) ) && '' !== (string) $ci->get_meta( '_pcpb_folder' ) ) {
                        $has_design = true;
                        $stats['downloads'] += (int) $ci->get_meta( '_pcpb_preview_downloads' );
                    }
                }
                if ( $has_design && count( $stats['other_orders'] ) < 9 ) {
                    $stats['other_orders'][] = $co->get_id();
                }
            }
            $stats['saved'] = count( get_posts( array( 'post_type' => 'spbwc_saved_design', 'post_status' => 'publish', 'author' => $cid, 'numberposts' => -1, 'fields' => 'ids' ) ) );
            set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
            $stats['other_orders'] = array_values( array_filter( $stats['other_orders'], function ( $oid ) use ( $order ) { return (int) $oid !== $order->get_id(); } ) );
            return $stats;
        }
    }
}
