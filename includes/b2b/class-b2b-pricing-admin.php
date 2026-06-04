<?php
/**
 * B2B Pricing admin page (M2) — the tier discount ladder.
 *
 * One submenu page that lists the merchant-defined tiers (label, discount %,
 * min order, payment terms, free-ship threshold) as an editable table with an
 * "add tier" row. Only `discount_pct` is enforced at cart time (M2); the other
 * columns are informational labels shown to companies. Config-save pattern:
 * rebuild the whole `spbwc_b2b_tiers` map from the posted rows on save.
 * See docs/SPEC_B2B_CLIENT.md §6.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Pricing_Admin' ) ) {

    class SPBWC_B2B_Pricing_Admin {

        const PAGE_SLUG  = 'storelly-product-builder-for-woocommerce-b2b-pricing';
        const CAPABILITY = 'manage_woocommerce';

        /** @var SPBWC_B2B_Pricing_Admin|null */
        protected static $instance;

        /** @var string Flash code. */
        protected $notice = '';

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function init() {
            add_action( 'admin_menu', array( $this, 'register_menu' ), 22 );
        }

        public function register_menu() {
            add_submenu_page(
                SPBWC_PB_OVERVIEW_SLUG,
                esc_html__( 'B2B Pricing', 'storelly-product-builder-for-woocommerce' ),
                esc_html__( 'B2B Pricing', 'storelly-product-builder-for-woocommerce' ),
                self::CAPABILITY,
                self::PAGE_SLUG,
                array( $this, 'render' )
            );
        }

        public static function page_url() {
            return add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
        }

        /* ── Render ───────────────────────────────────────────────── */

        public function render() {
            if ( ! current_user_can( self::CAPABILITY ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $this->maybe_save();

            $tiers = SPBWC_B2B_Pricing::get_tiers();

            echo '<div class="wrap spbwc-b2b-pricing">';
            echo '<h1>' . esc_html__( 'B2B Pricing', 'storelly-product-builder-for-woocommerce' ) . '</h1>';
            $this->print_notice();
            echo '<p class="description">' . esc_html__( 'Define tier discounts off retail prices. Only the discount % is applied at checkout in this version; min order, payment terms and free-shipping are labels shown to companies. Assign a tier to a company on its detail page.', 'storelly-product-builder-for-woocommerce' ) . '</p>';

            echo '<form method="post" action="' . esc_url( self::page_url() ) . '">';
            wp_nonce_field( 'spbwc_b2b_tiers_save', '_spbwc_tiers_nonce' );
            echo '<input type="hidden" name="spbwc_b2b_tiers_do" value="save" />';

            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
            echo '<th>' . esc_html__( 'Tier label', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Discount %', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Min order', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Payment terms', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Free ship over', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Companies', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Delete', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '</tr></thead><tbody>';

            $i = 0;
            foreach ( $tiers as $slug => $tier ) {
                $this->render_row( $i, $slug, $tier );
                $i++;
            }
            // Blank "add tier" row.
            $this->render_row( $i, '', array() );

            echo '</tbody></table>';
            echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save tiers', 'storelly-product-builder-for-woocommerce' ) . '</button></p>';
            echo '</form>';
            echo '</div>';
        }

        /**
         * One editable tier row.
         *
         * @param int    $i    Row index.
         * @param string $slug Existing slug ('' for the add row).
         * @param array  $tier Tier data.
         */
        protected function render_row( $i, $slug, $tier ) {
            $label = isset( $tier['label'] ) ? $tier['label'] : '';
            $pct   = isset( $tier['discount_pct'] ) ? $tier['discount_pct'] : '';
            $min   = isset( $tier['min_order'] ) ? $tier['min_order'] : '';
            $terms = isset( $tier['terms'] ) ? $tier['terms'] : 'prepaid';
            $ship  = isset( $tier['free_ship_over'] ) ? $tier['free_ship_over'] : '';
            $count = ( '' !== $slug ) ? self::count_companies_on_tier( $slug ) : 0;

            echo '<tr>';
            echo '<td><input type="hidden" name="tier_slug[' . esc_attr( $i ) . ']" value="' . esc_attr( $slug ) . '" />';
            echo '<input type="text" name="tier_label[' . esc_attr( $i ) . ']" value="' . esc_attr( $label ) . '" placeholder="' . esc_attr__( 'e.g. Tier A', 'storelly-product-builder-for-woocommerce' ) . '" /></td>';
            echo '<td><input type="number" min="0" max="100" step="0.1" name="tier_pct[' . esc_attr( $i ) . ']" value="' . esc_attr( $pct ) . '" class="small-text" /></td>';
            echo '<td><input type="number" min="0" step="0.01" name="tier_min[' . esc_attr( $i ) . ']" value="' . esc_attr( $min ) . '" class="small-text" /></td>';
            echo '<td><select name="tier_terms[' . esc_attr( $i ) . ']">';
            foreach ( SPBWC_B2B_Admin::payment_terms() as $tslug => $tlabel ) {
                echo '<option value="' . esc_attr( $tslug ) . '"' . selected( $terms, $tslug, false ) . '>' . esc_html( $tlabel ) . '</option>';
            }
            echo '</select></td>';
            echo '<td><input type="number" min="0" step="0.01" name="tier_ship[' . esc_attr( $i ) . ']" value="' . esc_attr( $ship ) . '" class="small-text" /></td>';
            echo '<td>' . esc_html( '' !== $slug ? number_format_i18n( $count ) : '—' ) . '</td>';
            echo '<td>';
            if ( '' !== $slug ) {
                echo '<label><input type="checkbox" name="tier_delete[' . esc_attr( $i ) . ']" value="1" /> ' . esc_html__( 'Remove', 'storelly-product-builder-for-woocommerce' ) . '</label>';
            }
            echo '</td></tr>';
        }

        /* ── Save ─────────────────────────────────────────────────── */

        protected function maybe_save() {
            if ( ! isset( $_POST['spbwc_b2b_tiers_do'] ) ) {
                return;
            }
            $nonce = isset( $_POST['_spbwc_tiers_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_tiers_nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'spbwc_b2b_tiers_save' ) || ! current_user_can( self::CAPABILITY ) ) {
                $this->notice = 'error';
                return;
            }

            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.
            $labels = isset( $_POST['tier_label'] ) ? (array) wp_unslash( $_POST['tier_label'] ) : array();
            $slugs  = isset( $_POST['tier_slug'] ) ? (array) wp_unslash( $_POST['tier_slug'] ) : array();
            $pcts   = isset( $_POST['tier_pct'] ) ? (array) wp_unslash( $_POST['tier_pct'] ) : array();
            $mins   = isset( $_POST['tier_min'] ) ? (array) wp_unslash( $_POST['tier_min'] ) : array();
            $terms  = isset( $_POST['tier_terms'] ) ? (array) wp_unslash( $_POST['tier_terms'] ) : array();
            $ships  = isset( $_POST['tier_ship'] ) ? (array) wp_unslash( $_POST['tier_ship'] ) : array();
            $dels   = isset( $_POST['tier_delete'] ) ? (array) wp_unslash( $_POST['tier_delete'] ) : array();
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            $out  = array();
            $used = array();
            foreach ( $labels as $i => $raw_label ) {
                if ( isset( $dels[ $i ] ) ) {
                    continue; // Marked for removal.
                }
                $label = sanitize_text_field( $raw_label );
                if ( '' === $label ) {
                    continue; // Empty row (incl. the trailing add row).
                }
                // Preserve an existing slug; derive a unique one for new rows.
                $slug = isset( $slugs[ $i ] ) ? sanitize_key( $slugs[ $i ] ) : '';
                if ( '' === $slug ) {
                    $slug = sanitize_key( sanitize_title( $label ) );
                    if ( '' === $slug ) {
                        $slug = 'tier_' . $i;
                    }
                }
                $base = $slug;
                $n    = 2;
                while ( isset( $used[ $slug ] ) ) {
                    $slug = $base . '_' . $n;
                    $n++;
                }
                $used[ $slug ] = true;

                $out[ $slug ] = array(
                    'label'          => $label,
                    'discount_pct'   => isset( $pcts[ $i ] ) ? max( 0, min( 100, (float) $pcts[ $i ] ) ) : 0,
                    'min_order'      => isset( $mins[ $i ] ) ? max( 0, (float) $mins[ $i ] ) : 0,
                    'terms'          => isset( $terms[ $i ] ) ? sanitize_key( $terms[ $i ] ) : 'prepaid',
                    'free_ship_over' => isset( $ships[ $i ] ) ? max( 0, (float) $ships[ $i ] ) : 0,
                );
            }

            SPBWC_B2B_Pricing::save_tiers( $out );
            $this->notice = 'saved';
        }

        /* ── Helpers ──────────────────────────────────────────────── */

        /**
         * @param string $slug Tier slug.
         * @return int Companies assigned to this tier.
         */
        public static function count_companies_on_tier( $slug ) {
            $ids = get_posts(
                array(
                    'post_type'   => SPBWC_Company::POST_TYPE,
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'fields'      => 'ids',
                    'meta_key'    => SPBWC_Company::META_TIER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value'  => $slug,                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                )
            );
            return count( $ids );
        }

        protected function print_notice() {
            if ( '' === $this->notice ) {
                return;
            }
            if ( 'saved' === $this->notice ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Tiers saved.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
            } elseif ( 'error' === $this->notice ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not save. Please try again.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
            }
        }
    }
}
