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

            echo '<div class="wrap spbwc-settings-wrap spbwc-b2b-admin spbwc-b2b-pricing">';
            $this->print_notice();

            // Hero.
            echo '<header class="spbwc-page-hero"><div class="spbwc-page-hero__grid"><div class="spbwc-page-hero__body">';
            echo '<div class="spbwc-page-hero__eyebrow"><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>' . esc_html__( 'Storelly · B2B', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            echo '<h1 class="spbwc-page-hero__title"><span class="dashicons dashicons-tag" aria-hidden="true"></span> ' . esc_html__( 'B2B Pricing', 'storelly-product-builder-for-woocommerce' ) . '</h1>';
            echo '<p class="spbwc-page-hero__subtitle">' . esc_html__( 'Tier discounts off retail. Assign a tier to a company on its detail page.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            echo '</div></div></header>';

            echo '<div class="spbwc-notice-banner spbwc-notice-banner--info"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span>' . esc_html__( 'Only the discount % is charged at checkout. Min order, payment terms and free-shipping are labels shown to companies. Quantity breaks stack on top.', 'storelly-product-builder-for-woocommerce' ) . '</span></div>';

            echo '<form method="post" action="' . esc_url( self::page_url() ) . '">';
            wp_nonce_field( 'spbwc_b2b_tiers_save', '_spbwc_tiers_nonce' );
            echo '<input type="hidden" name="spbwc_b2b_tiers_do" value="save" />';

            echo '<div class="spbwc-block"><div class="spbwc-block__head"><h3 class="spbwc-block__title">' . esc_html__( 'Tier discount ladder', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm js-spbwc-add-tier"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Add tier', 'storelly-product-builder-for-woocommerce' ) . '</button>';
            echo '</div><div class="spbwc-block__body spbwc-block__body--flush">';

            // data-next-index = first free row index after the server rows + the
            // trailing blank row, so JS-added rows never collide on save.
            $next = count( $tiers ) + 1;
            echo '<table class="spbwc-admin-table spbwc-tier-table" id="spbwc-tier-table" data-next-index="' . esc_attr( $next ) . '"><thead><tr>';
            echo '<th>' . esc_html__( 'Tier label', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Discount %', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Min order', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Payment terms', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Free ship over', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Companies', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Remove', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '</tr></thead><tbody>';

            $i = 0;
            foreach ( $tiers as $slug => $tier ) {
                echo $this->tier_row_html( $i, $slug, $tier ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
                $i++;
            }
            // Trailing blank row — the no-JS "add tier" surface; "Add tier" clones more.
            echo $this->tier_row_html( $i, '', array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.

            echo '</tbody></table></div>';
            echo '<div class="spbwc-block__foot"><button type="submit" class="spbwc-cta-btn spbwc-cta-btn--solid"><span class="dashicons dashicons-saved" aria-hidden="true"></span> ' . esc_html__( 'Save tiers', 'storelly-product-builder-for-woocommerce' ) . '</button> ';
            echo '<button type="button" class="spbwc-cta-btn spbwc-cta-btn--ghost js-spbwc-add-tier"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Add another tier', 'storelly-product-builder-for-woocommerce' ) . '</button></div></div>';
            echo '</form>';

            // Inert clone source for the "Add tier" buttons. The __INDEX__ token is
            // swapped for a running counter by static/js/b2b-pricing-admin.js.
            echo '<template id="spbwc-tier-row-tpl">' . $this->tier_row_html( '__INDEX__', '', array() ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.

            echo '</div>';
        }

        /**
         * One editable tier row, returned as escaped HTML.
         *
         * Used for the server-rendered rows and — with a `__INDEX__` token in place
         * of $i — as the JS clone template.
         *
         * @param int|string $i    Row index, or the `__INDEX__` token for the template.
         * @param string     $slug Existing slug ('' for an add row).
         * @param array      $tier Tier data.
         * @return string
         */
        protected function tier_row_html( $i, $slug, $tier ) {
            $label  = isset( $tier['label'] ) ? $tier['label'] : '';
            $pct    = isset( $tier['discount_pct'] ) ? $tier['discount_pct'] : '';
            $min    = isset( $tier['min_order'] ) ? $tier['min_order'] : '';
            $terms  = isset( $tier['terms'] ) ? $tier['terms'] : 'prepaid';
            $custom = isset( $tier['terms_custom'] ) ? $tier['terms_custom'] : '';
            $ship   = isset( $tier['free_ship_over'] ) ? $tier['free_ship_over'] : '';
            $count  = ( '' !== $slug ) ? self::count_companies_on_tier( $slug ) : 0;
            $is_new = ( '' === $slug );

            $opts = '';
            foreach ( SPBWC_B2B_Admin::payment_terms() as $tslug => $tlabel ) {
                $opts .= '<option value="' . esc_attr( $tslug ) . '"' . selected( $terms, $tslug, false ) . '>' . esc_html( $tlabel ) . '</option>';
            }

            $html  = '<tr class="spbwc-tier-row' . ( $is_new ? ' is-new' : '' ) . '">';
            $html .= '<td><input type="hidden" name="tier_slug[' . esc_attr( $i ) . ']" value="' . esc_attr( $slug ) . '" />';
            $html .= '<input class="spbwc-input" type="text" name="tier_label[' . esc_attr( $i ) . ']" value="' . esc_attr( $label ) . '" placeholder="' . ( $is_new ? esc_attr__( '+ Add tier…', 'storelly-product-builder-for-woocommerce' ) : esc_attr__( 'e.g. Tier A', 'storelly-product-builder-for-woocommerce' ) ) . '" style="min-width:140px" /></td>';
            $html .= '<td><input class="spbwc-input" type="number" min="0" max="100" step="0.1" name="tier_pct[' . esc_attr( $i ) . ']" value="' . esc_attr( $pct ) . '" style="width:80px" /></td>';
            $html .= '<td><input class="spbwc-input" type="number" min="0" step="0.01" name="tier_min[' . esc_attr( $i ) . ']" value="' . esc_attr( $min ) . '" style="width:90px" /></td>';
            $html .= '<td><select class="spbwc-input js-spbwc-terms-select" name="tier_terms[' . esc_attr( $i ) . ']" style="min-width:150px">' . $opts . '</select>';
            $html .= '<input class="spbwc-input js-spbwc-terms-custom" type="text" name="tier_terms_custom[' . esc_attr( $i ) . ']" value="' . esc_attr( $custom ) . '" placeholder="' . esc_attr__( 'Custom label, e.g. Net 45', 'storelly-product-builder-for-woocommerce' ) . '" style="margin-top:6px;min-width:150px;' . ( 'custom' === $terms ? '' : 'display:none;' ) . '" /></td>';
            $html .= '<td><input class="spbwc-input" type="number" min="0" step="0.01" name="tier_ship[' . esc_attr( $i ) . ']" value="' . esc_attr( $ship ) . '" style="width:90px" /></td>';
            $html .= '<td>' . ( $is_new ? '<span style="color:var(--nbd-st-text-mute)">—</span>' : '<span class="spbwc-pill spbwc-pill--neutral">' . esc_html( number_format_i18n( $count ) ) . '</span>' ) . '</td>';
            $html .= '<td>';
            if ( $is_new ) {
                // New/unsaved row → just drop it from the DOM (nothing persisted yet).
                $html .= '<button type="button" class="spbwc-icon-btn js-spbwc-remove-row" aria-label="' . esc_attr__( 'Remove this row', 'storelly-product-builder-for-woocommerce' ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
            } else {
                $html .= '<label style="white-space:nowrap"><input type="checkbox" name="tier_delete[' . esc_attr( $i ) . ']" value="1" /> ' . esc_html__( 'Remove', 'storelly-product-builder-for-woocommerce' ) . '</label>';
            }
            $html .= '</td></tr>';

            return $html;
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
            $tcusts = isset( $_POST['tier_terms_custom'] ) ? (array) wp_unslash( $_POST['tier_terms_custom'] ) : array();
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

                $tier_terms = isset( $terms[ $i ] ) ? sanitize_key( $terms[ $i ] ) : 'prepaid';

                $out[ $slug ] = array(
                    'label'          => $label,
                    'discount_pct'   => isset( $pcts[ $i ] ) ? max( 0, min( 100, (float) $pcts[ $i ] ) ) : 0,
                    'min_order'      => isset( $mins[ $i ] ) ? max( 0, (float) $mins[ $i ] ) : 0,
                    'terms'          => $tier_terms,
                    'free_ship_over' => isset( $ships[ $i ] ) ? max( 0, (float) $ships[ $i ] ) : 0,
                );

                // Free-text label for the "Custom" payment term (only kept when relevant).
                if ( 'custom' === $tier_terms && isset( $tcusts[ $i ] ) && '' !== trim( (string) $tcusts[ $i ] ) ) {
                    $out[ $slug ]['terms_custom'] = sanitize_text_field( $tcusts[ $i ] );
                }
            }

            SPBWC_B2B_Pricing::save_tiers( $out );
            $this->notice = 'saved';
        }

        /* ── Helpers ──────────────────────────────────────────────── */

        /**
         * Companies-per-tier counts in ONE query, cached for the request (the
         * ladder previously ran a full get_posts() per tier row).
         *
         * @return array<string,int> tier slug => count
         */
        public static function tier_counts() {
            static $cache = null;
            if ( null !== $cache ) {
                return $cache;
            }
            global $wpdb;
            $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT pm.meta_value AS tier, COUNT(*) AS n
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
                     WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value <> ''
                     GROUP BY pm.meta_value",
                    SPBWC_Company::META_TIER,
                    SPBWC_Company::POST_TYPE
                )
            );
            $cache = array();
            foreach ( (array) $rows as $r ) {
                $cache[ (string) $r->tier ] = (int) $r->n;
            }
            return $cache;
        }

        /**
         * @param string $slug Tier slug.
         * @return int Companies assigned to this tier.
         */
        public static function count_companies_on_tier( $slug ) {
            $c = self::tier_counts();
            return isset( $c[ $slug ] ) ? $c[ $slug ] : 0;
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
