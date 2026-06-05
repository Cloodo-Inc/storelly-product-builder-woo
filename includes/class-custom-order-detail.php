<?php
/**
 * Custom Order Detail — Storelly-native admin workspace for a single custom order.
 *
 * The primary place a print/POD shop works an order: artwork previews, print-file
 * download/regenerate, option specs, order summary, history/timeline, customer activity,
 * a production checklist and a files panel. The WooCommerce order-edit screen becomes a
 * secondary process (a "Open in WooCommerce" link). See docs/SPEC_CUSTOM_ORDER_DETAIL.md.
 *
 * Rendered by SPBWC_Storelly_Admin_Options::spbwc_orders_manager() when ?view={id} is set.
 * HPOS-safe; nonce + capability on every action; reuses the shipped download (storelly-general.js
 * via #post_ID) and regenerate (SPBWC_Order_PDF) handlers.
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
        //  Action handlers (admin_init): add note, save production checklist
        // ==================================================================
        public static function handle_actions() {
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
            }

            wp_safe_redirect( self::url( $order_id ) );
            exit;
        }

        // ==================================================================
        //  Render
        // ==================================================================
        public static function render( $order_id ) {
            $order = wc_get_order( absint( $order_id ) );
            if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Order not found.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
                echo '<a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="' . esc_url( admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG ) ) . '">' . esc_html__( '← Back to Custom Orders', 'storelly-product-builder-for-woocommerce' ) . '</a></div>';
                return;
            }

            $items   = self::design_items( $order );
            $list_url = admin_url( 'admin.php?page=' . SPBWC_PB_ORDERS_SLUG );
            ?>
            <div class="wrap spbwc-co-detail">
                <p style="margin:8px 0;">
                    <a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to Custom Orders', 'storelly-product-builder-for-woocommerce' ); ?></a>
                </p>
                <input type="hidden" id="post_ID" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />

                <?php self::section_header( $order, $items ); ?>

                <div class="spbwc-co-detail__grid" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:var(--nbd-space-6,24px);align-items:start;margin-top:var(--nbd-space-4,16px);">
                    <div class="spbwc-co-detail__main">
                        <?php
                        self::section_design_items( $order, $items );
                        self::section_summary( $order );
                        self::section_history( $order );
                        ?>
                    </div>
                    <div class="spbwc-co-detail__side">
                        <?php
                        self::section_customer( $order );
                        self::section_production( $order );
                        self::section_files( $order, $items );
                        ?>
                    </div>
                </div>
            </div>
            <style>
                @media (max-width: 1024px){ .spbwc-co-detail__grid{ grid-template-columns:1fr !important; } }
                .spbwc-co-detail__card{ background:var(--nbd-st-bg,#fff); border:1px solid var(--nbd-st-border-light,#dcdcde); border-radius:var(--nbd-radius-lg,8px); padding:var(--nbd-space-5,20px); margin-bottom:var(--nbd-space-4,16px); }
                .spbwc-co-detail__card h2{ margin:0 0 var(--nbd-space-3,12px); font-size:var(--text-xl,16px); }
                .spbwc-co-detail__spec{ list-style:none; margin:8px 0 0; padding:0; }
                .spbwc-co-detail__spec li{ display:flex; justify-content:space-between; gap:12px; padding:6px 0; border-bottom:1px solid var(--nbd-st-border-subtle,#f0f0f1); font-size:13px; }
                .spbwc-co-detail__spec li:last-child{ border-bottom:0; }
                .spbwc-co-detail__spec .lbl{ color:var(--nbd-st-text-soft,#50575e); }
                .spbwc-co-detail__gallery{ display:flex; flex-wrap:wrap; gap:8px; }
                .spbwc-co-detail__gallery img{ width:120px; height:120px; object-fit:contain; border:1px solid var(--nbd-st-border-light,#dcdcde); border-radius:var(--nbd-radius,6px); background:var(--nbd-st-bg-soft,#f6f7f7); }
                .spbwc-co-detail__item{ display:grid; grid-template-columns:auto 1fr; gap:var(--nbd-space-4,16px); padding:var(--nbd-space-4,16px) 0; border-bottom:1px solid var(--nbd-st-border-subtle,#f0f0f1); }
                .spbwc-co-detail__item:last-child{ border-bottom:0; }
                .spbwc-co-detail__pill{ display:inline-block; padding:2px 10px; border-radius:var(--nbd-radius-full,999px); font-size:11px; font-weight:600; }
            </style>
            <?php
        }

        // ---- S1 Header --------------------------------------------------
        protected static function section_header( $order, $items ) {
            $agg = self::aggregate_pdf_status( $order );
            $name = trim( $order->get_formatted_billing_full_name() );
            ?>
            <header class="spbwc-page-hero">
                <div class="spbwc-page-hero__grid">
                    <div class="spbwc-page-hero__body">
                        <div class="spbwc-page-hero__eyebrow">
                            <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                            <?php esc_html_e( 'Custom Order', 'storelly-product-builder-for-woocommerce' ); ?>
                        </div>
                        <h1 class="spbwc-page-hero__title">#<?php echo esc_html( (string) $order->get_id() ); ?>
                            <span class="spbwc-pill spbwc-pill--<?php echo esc_attr( self::status_pill( $order->get_status() ) ); ?>" style="vertical-align:middle;"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                        </h1>
                        <p class="spbwc-page-hero__subtitle">
                            <?php echo esc_html( $name ? $name : __( 'Guest', 'storelly-product-builder-for-woocommerce' ) ); ?>
                            <?php if ( $order->get_billing_email() ) : ?> · <?php echo esc_html( $order->get_billing_email() ); ?><?php endif; ?>
                            · <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
                            · <strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
                            · <?php echo wp_kses_post( self::pdf_badge( $agg ) ); ?>
                        </p>
                        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                            <?php if ( class_exists( 'SPBWC_Order_PDF' ) && SPBWC_Order_PDF::is_enabled() && ! empty( $items ) ) : ?>
                                <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( SPBWC_Order_PDF::regenerate_url( $order->get_id() ) ); ?>">
                                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                    <?php esc_html_e( 'Regenerate print PDFs', 'storelly-product-builder-for-woocommerce' ); ?>
                                </a>
                            <?php endif; ?>
                            <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
                                <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                <?php esc_html_e( 'Open in WooCommerce', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            <?php
        }

        // ---- S2 Design items -------------------------------------------
        protected static function section_design_items( $order, $items ) {
            ?>
            <div class="spbwc-co-detail__card">
                <h2><span class="dashicons dashicons-art" aria-hidden="true"></span> <?php esc_html_e( 'Design items', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <?php if ( empty( $items ) ) : ?>
                    <p><?php esc_html_e( 'No custom designs in this order.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $items as $it ) : ?>
                        <div class="spbwc-co-detail__item">
                            <div>
                                <label style="display:block;margin-bottom:6px;font-size:12px;">
                                    <input type="checkbox" class="storelly_order_item_id" name="_storelly_order_item_id[]" value="<?php echo esc_attr( (string) $it['item_id'] ); ?>" checked />
                                    <?php esc_html_e( 'Include', 'storelly-product-builder-for-woocommerce' ); ?>
                                </label>
                                <div class="spbwc-co-detail__gallery">
                                    <?php foreach ( $it['previews'] as $src ) : ?>
                                        <a href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $src ); ?>" alt="" loading="lazy" /></a>
                                    <?php endforeach; ?>
                                    <?php if ( empty( $it['previews'] ) ) : ?>
                                        <span class="dashicons dashicons-format-image" aria-hidden="true" style="font-size:48px;color:var(--nbd-st-text-mute,#8c8f94);"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <strong><?php echo esc_html( $it['name'] ); ?></strong> &times; <?php echo esc_html( (string) $it['qty'] ); ?>
                                <?php echo wp_kses_post( self::pdf_badge( $it['pdf_status'] ) ); ?>
                                <?php if ( ! empty( $it['specs'] ) ) : ?>
                                    <div style="margin-top:6px;font-size:12px;color:var(--nbd-st-text-soft,#50575e);"><?php echo esc_html( $it['specs'] ); ?></div>
                                <?php endif; ?>
                                <?php if ( ! empty( $it['options'] ) ) : ?>
                                    <ul class="spbwc-co-detail__spec">
                                        <?php foreach ( $it['options'] as $opt ) : ?>
                                            <li><span class="lbl"><?php echo esc_html( $opt['name'] ); ?></span><span><?php echo esc_html( $opt['value'] ); ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if ( '' !== $it['designer_url'] ) : ?>
                                    <p style="margin-top:8px;"><a class="button button-small" href="<?php echo esc_url( $it['designer_url'] ); ?>"><?php esc_html_e( 'View in designer', 'storelly-product-builder-for-woocommerce' ); ?></a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Download controls (reuses storelly-general.js via #post_ID) -->
                    <div style="margin-top:var(--nbd-space-3,12px);display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <label><input type="checkbox" id="storelly_order_design_check_all" checked /> <small><?php esc_html_e( 'All items', 'storelly-product-builder-for-woocommerce' ); ?></small></label>
                        <select name="storelly_design_type_download">
                            <option value="pdf"><?php esc_html_e( 'PDF (print)', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="pdf-preview"><?php esc_html_e( 'PDF preview', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="png"><?php esc_html_e( 'PNG', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="png-preview"><?php esc_html_e( 'PNG preview', 'storelly-product-builder-for-woocommerce' ); ?></option>
                            <option value="svg"><?php esc_html_e( 'SVG', 'storelly-product-builder-for-woocommerce' ); ?></option>
                        </select>
                        <img src="<?php echo esc_url( SPBWC_PB_ASSETS_URL . 'images/loading.gif' ); ?>" class="storelly_loaded" id="storelly_order_submit_loading" />
                        <a href="#" class="button button-primary" id="storelly_download_design_by_type"><?php esc_html_e( 'Download', 'storelly-product-builder-for-woocommerce' ); ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        // ---- S3 Summary + addresses ------------------------------------
        protected static function section_summary( $order ) {
            ?>
            <div class="spbwc-co-detail__card">
                <h2><span class="dashicons dashicons-list-view" aria-hidden="true"></span> <?php esc_html_e( 'Order summary', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <ul class="spbwc-co-detail__spec">
                    <?php foreach ( $order->get_items() as $line ) : ?>
                        <li><span class="lbl"><?php echo esc_html( $line->get_name() . ' × ' . $line->get_quantity() ); ?></span><span><?php echo wp_kses_post( wc_price( $line->get_total() ) ); ?></span></li>
                    <?php endforeach; ?>
                    <li><span class="lbl"><?php esc_html_e( 'Shipping', 'storelly-product-builder-for-woocommerce' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></span></li>
                    <li><span class="lbl"><strong><?php esc_html_e( 'Total', 'storelly-product-builder-for-woocommerce' ); ?></strong></span><span><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></span></li>
                </ul>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
                    <div><strong><?php esc_html_e( 'Billing', 'storelly-product-builder-for-woocommerce' ); ?></strong><br /><?php echo wp_kses_post( $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : '—' ); ?></div>
                    <div><strong><?php esc_html_e( 'Shipping', 'storelly-product-builder-for-woocommerce' ); ?></strong><br /><?php echo wp_kses_post( $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : '—' ); ?></div>
                </div>
            </div>
            <?php
        }

        // ---- S4 History / timeline -------------------------------------
        protected static function section_history( $order ) {
            $notes = function_exists( 'wc_get_order_notes' ) ? wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) : array();
            ?>
            <div class="spbwc-co-detail__card">
                <h2><span class="dashicons dashicons-backup" aria-hidden="true"></span> <?php esc_html_e( 'Order history', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <?php if ( empty( $notes ) ) : ?>
                    <p style="color:var(--nbd-st-text-soft,#50575e);"><?php esc_html_e( 'No notes yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <?php else : ?>
                    <ul style="list-style:none;margin:0;padding:0;">
                        <?php foreach ( $notes as $n ) : ?>
                            <li style="padding:8px 0;border-bottom:1px solid var(--nbd-st-border-subtle,#f0f0f1);">
                                <div style="font-size:13px;"><?php echo wp_kses_post( wpautop( wptexturize( $n->content ) ) ); ?></div>
                                <div style="font-size:11px;color:var(--nbd-st-text-mute,#8c8f94);"><?php echo esc_html( $n->added_by . ' · ' . ( $n->date_created ? $n->date_created->date_i18n( 'M j, Y H:i' ) : '' ) ); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" style="margin-top:12px;">
                    <?php wp_nonce_field( 'spbwc_co_detail_' . $order->get_id(), 'spbwc_co_detail_nonce' ); ?>
                    <input type="hidden" name="spbwc_co_detail_action" value="add_note" />
                    <input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
                    <textarea name="spbwc_note" rows="2" style="width:100%;" placeholder="<?php esc_attr_e( 'Add an internal note…', 'storelly-product-builder-for-woocommerce' ); ?>"></textarea>
                    <button type="submit" class="button" style="margin-top:6px;"><?php esc_html_e( 'Add note', 'storelly-product-builder-for-woocommerce' ); ?></button>
                </form>
            </div>
            <?php
        }

        // ---- S5 Customer activity (sidebar) ----------------------------
        protected static function section_customer( $order ) {
            $cid = (int) $order->get_customer_id();
            $stats = self::customer_stats( $order, $cid );
            ?>
            <div class="spbwc-co-detail__card">
                <h2><span class="dashicons dashicons-admin-users" aria-hidden="true"></span> <?php esc_html_e( 'Customer activity', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <ul class="spbwc-co-detail__spec">
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

        // ---- S6 Production checklist (sidebar) --------------------------
        protected static function section_production( $order ) {
            $saved = $order->get_meta( self::PRODUCTION_STEPS_META );
            $saved = is_array( $saved ) ? $saved : array();
            ?>
            <div class="spbwc-co-detail__card">
                <h2><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Production', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'spbwc_co_detail_' . $order->get_id(), 'spbwc_co_detail_nonce' ); ?>
                    <input type="hidden" name="spbwc_co_detail_action" value="production" />
                    <input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
                    <?php foreach ( self::production_steps() as $key => $label ) : ?>
                        <label style="display:block;padding:5px 0;">
                            <input type="checkbox" name="spbwc_production[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $saved[ $key ] ) ); ?> />
                            <?php echo esc_html( $label ); ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="button" style="margin-top:6px;"><?php esc_html_e( 'Save', 'storelly-product-builder-for-woocommerce' ); ?></button>
                </form>
            </div>
            <?php
        }

        // ---- S7 Files panel (sidebar) ----------------------------------
        protected static function section_files( $order, $items ) {
            $files = array();
            foreach ( $items as $it ) {
                if ( '' === $it['folder'] ) { continue; }
                $dir = SPBWC_PB_CUSTOMER_DIR . '/' . $it['folder'];
                foreach ( SPBWC_Storelly_IO::spbwc_get_list_files_by_type( $dir, 'png|jpg|jpeg|svg|pdf', 2 ) as $f ) {
                    $files[] = $f;
                }
            }
            $files = array_values( array_unique( $files ) );
            ?>
            <div class="spbwc-co-detail__card">
                <h2><span class="dashicons dashicons-media-default" aria-hidden="true"></span> <?php esc_html_e( 'Files', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                <?php if ( empty( $files ) ) : ?>
                    <p style="color:var(--nbd-st-text-soft,#50575e);"><?php esc_html_e( 'No files yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                <?php else : ?>
                    <ul style="list-style:none;margin:0;padding:0;font-size:12px;max-height:280px;overflow:auto;">
                        <?php foreach ( $files as $f ) : ?>
                            <?php $u = SPBWC_Storelly_IO::spbwc_convert_path_to_url( $f ); $sz = @filesize( $f ); ?>
                            <li style="padding:4px 0;display:flex;justify-content:space-between;gap:8px;border-bottom:1px solid var(--nbd-st-border-subtle,#f0f0f1);">
                                <a href="<?php echo esc_url( $u ); ?>" target="_blank" rel="noopener" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( basename( $f ) ); ?></a>
                                <span style="color:var(--nbd-st-text-mute,#8c8f94);flex:0 0 auto;"><?php echo esc_html( $sz ? size_format( $sz ) : '' ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php
        }

        // ==================================================================
        //  Data helpers
        // ==================================================================

        /** Build the per-item design data array. */
        protected static function design_items( $order ) {
            $out = array();
            foreach ( $order->get_items() as $item_id => $item ) {
                $folder = is_callable( array( $item, 'get_meta' ) ) ? (string) $item->get_meta( '_pcpb_folder' ) : '';
                if ( '' === $folder ) { continue; }
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
                    'designer_url' => add_query_arg( array( 'nbd_item_key' => $folder ), SPBWC_Storelly_PB_Util::spbwc_get_url_page( 'product_builder' ) ),
                );
            }
            return $out;
        }

        /** Human print-spec line from the design config.json, when present. */
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
            return '<span class="spbwc-pill spbwc-pill--' . esc_attr( $map[ $status ][0] ) . '" style="margin-left:6px;">' . esc_html( $map[ $status ][1] ) . '</span>';
        }

        protected static function status_pill( $status ) {
            if ( in_array( $status, array( 'completed', 'processing' ), true ) ) { return 'ok'; }
            if ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) { return 'off'; }
            if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) { return 'warn'; }
            return 'neutral';
        }

        /** Customer lifetime + design stats (HPOS-safe). */
        protected static function customer_stats( $order, $cid ) {
            $stats = array( 'orders' => 0, 'spent' => 0.0, 'saved' => 0, 'downloads' => 0, 'other_orders' => array() );
            if ( $cid <= 0 || ! function_exists( 'wc_get_orders' ) ) {
                return $stats;
            }
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
                if ( $has_design && $co->get_id() !== $order->get_id() && count( $stats['other_orders'] ) < 8 ) {
                    $stats['other_orders'][] = $co->get_id();
                }
            }
            $saved = get_posts( array( 'post_type' => 'spbwc_saved_design', 'post_status' => 'publish', 'author' => $cid, 'numberposts' => -1, 'fields' => 'ids' ) );
            $stats['saved'] = count( $saved );
            return $stats;
        }
    }
}
