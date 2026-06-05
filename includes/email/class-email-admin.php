<?php
/**
 * Storelly › Emails — aggregated email management dashboard (E6c / menu).
 *
 * One place to see every Storelly email grouped by area (Quote, B2B, Custom
 * Order, Marketplace), toggle nothing destructively but deep-link to the native
 * WooCommerce email editor for full content editing, fire a branded "Send test"
 * to yourself, and read the delivery log (SPBWC_Email_Log).
 *
 * Design-token styled (static/css/email-admin.css → _tokens.css). Local only.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Email_Admin' ) ) {

    class SPBWC_Email_Admin {

        /** @var string Page hook suffix. */
        protected static $hook = '';

        public static function init() {
            add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
            add_action( 'admin_init', array( __CLASS__, 'maybe_send_test' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        }

        public static function register_menu() {
            // Match the parent Storelly menu's caps so the submenu never orphans.
            if ( ! current_user_can( 'spbwc_manage_product_builder' ) || ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }
            self::$hook = add_submenu_page(
                SPBWC_PB_OVERVIEW_SLUG,
                esc_html__( 'Emails', 'storelly-product-builder-for-woocommerce' ),
                esc_html__( 'Emails', 'storelly-product-builder-for-woocommerce' ),
                'manage_woocommerce',
                defined( 'SPBWC_PB_EMAILS_SLUG' ) ? SPBWC_PB_EMAILS_SLUG : 'storelly-product-builder-for-woocommerce-emails',
                array( __CLASS__, 'render_page' )
            );
        }

        public static function enqueue( $hook ) {
            if ( self::$hook && $hook === self::$hook ) {
                wp_enqueue_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
                if ( ! wp_style_is( 'spbwc-admin-ui', 'registered' ) ) {
                    wp_register_style( 'spbwc-admin-ui', SPBWC_PB_CSS_URL . 'storelly-admin-ui.css', array( 'spbwc-tokens', 'dashicons' ), SPBWC_PB_VERSION );
                }
                wp_enqueue_style( 'spbwc-admin-ui' );
                wp_enqueue_style( 'spbwc-email-admin', SPBWC_PB_CSS_URL . 'email-admin.css', array( 'spbwc-admin-ui' ), SPBWC_PB_VERSION );
            }
        }

        /**
         * Group definitions keyed by id-prefix, in display order.
         *
         * Each entry carries a display label plus the dashicon + block colour
         * variant used by the redesigned cards so the page reads at a glance.
         *
         * @return array<string,array{label:string,icon:string,variant:string}>
         */
        protected static function groups() {
            return array(
                'spbwc_quote_' => array(
                    'label'   => __( 'Quote', 'storelly-product-builder-for-woocommerce' ),
                    'icon'    => 'media-document',
                    'variant' => 'brand',
                ),
                'spbwc_b2b_'   => array(
                    'label'   => __( 'B2B / Team', 'storelly-product-builder-for-woocommerce' ),
                    'icon'    => 'groups',
                    'variant' => 'accent',
                ),
                'spbwc_order_' => array(
                    'label'   => __( 'Custom Order', 'storelly-product-builder-for-woocommerce' ),
                    'icon'    => 'cart',
                    'variant' => 'success',
                ),
                'spbwc_email_' => array(
                    'label'   => __( 'Marketplace / Designer', 'storelly-product-builder-for-woocommerce' ),
                    'icon'    => 'art',
                    'variant' => 'gold',
                ),
            );
        }

        /** @return WC_Email[] Storelly emails keyed by class name. */
        protected static function storelly_emails() {
            $out = array();
            if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
                return $out;
            }
            foreach ( WC()->mailer()->get_emails() as $key => $email ) {
                if ( is_object( $email ) && isset( $email->id ) && 0 === strpos( (string) $email->id, 'spbwc_' ) ) {
                    $out[ $key ] = $email;
                }
            }
            return $out;
        }

        /** Native WooCommerce editor URL for one email. */
        protected static function edit_url( $email ) {
            return add_query_arg(
                array(
                    'page'    => 'wc-settings',
                    'tab'     => 'email',
                    'section' => strtolower( get_class( $email ) ),
                ),
                admin_url( 'admin.php' )
            );
        }

        protected static function test_url( $email ) {
            return wp_nonce_url(
                add_query_arg(
                    array(
                        'page'            => defined( 'SPBWC_PB_EMAILS_SLUG' ) ? SPBWC_PB_EMAILS_SLUG : 'storelly-product-builder-for-woocommerce-emails',
                        'spbwc_send_test' => rawurlencode( $email->id ),
                    ),
                    admin_url( 'admin.php' )
                ),
                'spbwc_send_test_' . $email->id
            );
        }

        /** Handle the "Send test" action. */
        public static function maybe_send_test() {
            if ( ! isset( $_GET['spbwc_send_test'] ) ) {
                return;
            }
            $email_id = sanitize_text_field( wp_unslash( $_GET['spbwc_send_test'] ) );
            $nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'spbwc_send_test_' . $email_id ) || ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }
            $sent = self::send_test_email( $email_id );
            wp_safe_redirect( add_query_arg(
                array(
                    'page'           => defined( 'SPBWC_PB_EMAILS_SLUG' ) ? SPBWC_PB_EMAILS_SLUG : 'storelly-product-builder-for-woocommerce-emails',
                    'spbwc_test_sent' => $sent ? '1' : '0',
                ),
                admin_url( 'admin.php' )
            ) );
            exit;
        }

        /**
         * Send a generic branded test of one email to the current admin.
         *
         * @param string $email_id Email ID.
         * @return bool
         */
        protected static function send_test_email( $email_id ) {
            $target = null;
            foreach ( self::storelly_emails() as $email ) {
                if ( $email->id === $email_id ) {
                    $target = $email;
                    break;
                }
            }
            if ( ! $target ) {
                return false;
            }
            $user = wp_get_current_user();
            $to   = $user ? $user->user_email : get_option( 'admin_email' );
            if ( ! is_email( $to ) ) {
                return false;
            }
            $heading = $target->get_heading();
            $subject = sprintf(
                /* translators: %s: email title. */
                esc_html__( '[Test] %s', 'storelly-product-builder-for-woocommerce' ),
                $target->get_title()
            );
            ob_start();
            wc_get_template( 'emails/email-header.php', array( 'email_heading' => $heading, 'email' => $target ) );
            echo '<p>' . esc_html__( 'This is a test of this Storelly email. The real email will contain live order / quote / company details.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            wc_get_template( 'emails/email-footer.php', array( 'email' => $target ) );
            $content = ob_get_clean();

            // Sent via wp_mail directly so the woocommerce_email_sent hook (which
            // logs real sends) is not triggered for a test; we log it explicitly.
            $headers = "Content-Type: text/html\r\n";
            $ok      = wp_mail( $to, $subject, $content, $headers );
            if ( class_exists( 'SPBWC_Email_Log' ) ) {
                SPBWC_Email_Log::record( $email_id, $to, $subject, SPBWC_Email_Log::STATUS_TEST );
            }
            return (bool) $ok;
        }

        public static function render_page() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }
            $emails = self::storelly_emails();
            $meta   = self::groups();
            // Bucket emails into groups (in declared order so the cards stay stable).
            $buckets = array_fill_keys( array_keys( $meta ), array() );
            foreach ( $emails as $email ) {
                foreach ( $meta as $prefix => $info ) {
                    if ( 0 === strpos( $email->id, $prefix ) ) {
                        $buckets[ $prefix ][] = $email;
                        break;
                    }
                }
            }

            // Summary stats for the KPI row.
            $total_emails = count( $emails );
            $enabled_n    = 0;
            foreach ( $emails as $email ) {
                if ( $email->is_enabled() ) {
                    $enabled_n++;
                }
            }
            $disabled_n = $total_emails - $enabled_n;

            // Delivery log (fetched once, reused for the failed-count stat).
            $rows      = class_exists( 'SPBWC_Email_Log' ) ? SPBWC_Email_Log::get_rows( array( 'limit' => 50 ) ) : array();
            $failed_n  = 0;
            foreach ( $rows as $row ) {
                if ( 'failed' === $row['status'] ) {
                    $failed_n++;
                }
            }

            $from_name = get_option( 'woocommerce_email_from_name' );
            $from_addr = get_option( 'woocommerce_email_from_address' );
            $wc_email_url = admin_url( 'admin.php?page=wc-settings&tab=email' );
            ?>
            <div class="wrap spbwc-email-admin">

                <!-- ── Page hero ──────────────────────────────────────────────── -->
                <header class="spbwc-page-hero">
                    <div class="spbwc-page-hero__grid">
                        <div class="spbwc-page-hero__body">
                            <div class="spbwc-page-hero__eyebrow">
                                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                                <?php esc_html_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>
                            </div>
                            <h1 class="spbwc-page-hero__title">
                                <span class="dashicons dashicons-email" aria-hidden="true"></span>
                                <?php esc_html_e( 'Emails', 'storelly-product-builder-for-woocommerce' ); ?>
                            </h1>
                            <p class="spbwc-page-hero__subtitle">
                                <?php esc_html_e( 'Every notification Storelly sends, in one place — grouped by area. Edit content in WooCommerce, send yourself a test, and review the delivery log.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </p>
                        </div>
                        <div class="spbwc-page-hero__actions">
                            <a class="spbwc-cta-btn spbwc-cta-btn--ghost" href="<?php echo esc_url( $wc_email_url ); ?>">
                                <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                                <?php esc_html_e( 'WooCommerce email settings', 'storelly-product-builder-for-woocommerce' ); ?>
                            </a>
                        </div>
                    </div>
                </header>
                <?php // WP relocates admin notices to right after the first <h1>; this marker
                      // makes them land below the hero instead of on top of the gradient. ?>
                <hr class="wp-header-end" />

                <?php if ( isset( $_GET['spbwc_test_sent'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash. ?>
                    <?php $ok = '1' === sanitize_text_field( wp_unslash( $_GET['spbwc_test_sent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <div class="spbwc-notice-banner spbwc-notice-banner--<?php echo $ok ? 'success' : 'error'; ?>">
                        <span class="dashicons dashicons-<?php echo $ok ? 'yes-alt' : 'warning'; ?>" aria-hidden="true"></span>
                        <div class="spbwc-notice-banner__body">
                            <div class="spbwc-notice-banner__title">
                                <?php echo $ok ? esc_html__( 'Test email sent', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Test email failed', 'storelly-product-builder-for-woocommerce' ); ?>
                            </div>
                            <div class="spbwc-notice-banner__text">
                                <?php echo $ok ? esc_html__( 'Check the inbox of your admin account.', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'WordPress could not hand the message to your mail server. Check your SMTP / mail setup.', 'storelly-product-builder-for-woocommerce' ); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ── KPI stat row ───────────────────────────────────────────── -->
                <div class="spbwc-stat-grid spbwc-email-stats">
                    <div class="spbwc-stat-card spbwc-stat-card--brand">
                        <div class="spbwc-stat-card__head">
                            <div class="spbwc-stat-card__icon"><span class="dashicons dashicons-email" aria-hidden="true"></span></div>
                            <span class="spbwc-stat-card__label"><?php esc_html_e( 'Emails', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </div>
                        <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $total_emails ) ); ?></div>
                        <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Storelly notifications registered', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    </div>
                    <div class="spbwc-stat-card spbwc-stat-card--success">
                        <div class="spbwc-stat-card__head">
                            <div class="spbwc-stat-card__icon"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span></div>
                            <span class="spbwc-stat-card__label"><?php esc_html_e( 'Enabled', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </div>
                        <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $enabled_n ) ); ?></div>
                        <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Currently sending to customers', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    </div>
                    <div class="spbwc-stat-card spbwc-stat-card--warning">
                        <div class="spbwc-stat-card__head">
                            <div class="spbwc-stat-card__icon"><span class="dashicons dashicons-hidden" aria-hidden="true"></span></div>
                            <span class="spbwc-stat-card__label"><?php esc_html_e( 'Disabled', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </div>
                        <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $disabled_n ) ); ?></div>
                        <p class="spbwc-stat-card__hint"><?php esc_html_e( 'Turned off in WooCommerce', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    </div>
                    <div class="spbwc-stat-card spbwc-stat-card--accent">
                        <div class="spbwc-stat-card__head">
                            <div class="spbwc-stat-card__icon"><span class="dashicons dashicons-<?php echo $failed_n > 0 ? 'warning' : 'chart-bar'; ?>" aria-hidden="true"></span></div>
                            <span class="spbwc-stat-card__label"><?php esc_html_e( 'Failed (recent)', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        </div>
                        <div class="spbwc-stat-card__value"><?php echo esc_html( number_format_i18n( $failed_n ) ); ?></div>
                        <p class="spbwc-stat-card__hint"><?php esc_html_e( 'In the last 50 logged deliveries', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    </div>
                </div>

                <!-- ── Sender ─────────────────────────────────────────────────── -->
                <div class="spbwc-email-sender">
                    <div class="spbwc-email-sender__icon"><span class="dashicons dashicons-businessperson" aria-hidden="true"></span></div>
                    <div class="spbwc-email-sender__body">
                        <span class="spbwc-email-sender__label"><?php esc_html_e( 'Sending as', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <span class="spbwc-email-sender__value">
                            <strong><?php echo esc_html( $from_name ); ?></strong>
                            <span class="spbwc-email-sender__addr">&lt;<?php echo esc_html( $from_addr ); ?>&gt;</span>
                        </span>
                    </div>
                    <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( $wc_email_url ); ?>">
                        <?php esc_html_e( 'Change sender', 'storelly-product-builder-for-woocommerce' ); ?>
                    </a>
                </div>

                <!-- ── Email groups ───────────────────────────────────────────── -->
                <?php foreach ( $meta as $prefix => $info ) :
                    if ( empty( $buckets[ $prefix ] ) ) {
                        continue;
                    }
                    $count = count( $buckets[ $prefix ] ); ?>
                    <section class="spbwc-block spbwc-block--<?php echo esc_attr( $info['variant'] ); ?> spbwc-email-group">
                        <div class="spbwc-block__head">
                            <h2 class="spbwc-block__title">
                                <span class="dashicons dashicons-<?php echo esc_attr( $info['icon'] ); ?>" aria-hidden="true"></span>
                                <?php echo esc_html( $info['label'] ); ?>
                            </h2>
                            <span class="spbwc-block__badge">
                                <?php
                                /* translators: %s: number of emails in this group. */
                                echo esc_html( sprintf( _n( '%s email', '%s emails', $count, 'storelly-product-builder-for-woocommerce' ), number_format_i18n( $count ) ) );
                                ?>
                            </span>
                        </div>
                        <div class="spbwc-block__body spbwc-block__body--flush">
                            <table class="spbwc-email-table">
                                <tbody>
                                <?php foreach ( $buckets[ $prefix ] as $email ) :
                                    $enabled = $email->is_enabled(); ?>
                                    <tr>
                                        <td class="spbwc-email-table__main">
                                            <strong class="spbwc-email-table__name"><?php echo esc_html( $email->get_title() ); ?></strong>
                                            <span class="spbwc-email-table__desc"><?php echo esc_html( $email->get_description() ); ?></span>
                                        </td>
                                        <td class="spbwc-email-table__status">
                                            <span class="spbwc-badge spbwc-badge--<?php echo $enabled ? 'on' : 'off'; ?>">
                                                <span class="dashicons dashicons-<?php echo $enabled ? 'yes' : 'minus'; ?>" aria-hidden="true"></span>
                                                <?php echo $enabled ? esc_html__( 'Enabled', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Disabled', 'storelly-product-builder-for-woocommerce' ); ?>
                                            </span>
                                        </td>
                                        <td class="spbwc-email-table__actions">
                                            <a class="spbwc-cta-btn spbwc-cta-btn--ghost spbwc-cta-btn--sm" href="<?php echo esc_url( self::edit_url( $email ) ); ?>">
                                                <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                                <?php esc_html_e( 'Edit', 'storelly-product-builder-for-woocommerce' ); ?>
                                            </a>
                                            <a class="spbwc-cta-btn spbwc-cta-btn--sm" href="<?php echo esc_url( self::test_url( $email ) ); ?>">
                                                <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                                                <?php esc_html_e( 'Send test', 'storelly-product-builder-for-woocommerce' ); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endforeach; ?>

                <!-- ── Delivery log ───────────────────────────────────────────── -->
                <section class="spbwc-block spbwc-email-log">
                    <div class="spbwc-block__head">
                        <h2 class="spbwc-block__title">
                            <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                            <?php esc_html_e( 'Delivery log', 'storelly-product-builder-for-woocommerce' ); ?>
                        </h2>
                        <?php if ( ! empty( $rows ) ) : ?>
                            <span class="spbwc-block__badge"><?php esc_html_e( 'Last 50', 'storelly-product-builder-for-woocommerce' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="spbwc-block__body spbwc-block__body--flush">
                    <?php if ( empty( $rows ) ) : ?>
                        <div class="spbwc-empty-state">
                            <div class="spbwc-empty-state__icon"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span></div>
                            <p class="spbwc-empty-state__title"><?php esc_html_e( 'No emails logged yet', 'storelly-product-builder-for-woocommerce' ); ?></p>
                            <p class="spbwc-empty-state__text"><?php esc_html_e( 'Once Storelly sends a notification or you fire a test, every delivery will be recorded here.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                        </div>
                    <?php else : ?>
                        <table class="spbwc-email-table spbwc-email-table--log">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'When', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Email', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Recipient', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Subject', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $rows as $row ) :
                                $status = $row['status'];
                                $badge  = 'failed' === $status ? 'off' : ( 'test' === $status ? 'test' : 'on' ); ?>
                                <tr>
                                    <td class="spbwc-email-table__when"><?php echo esc_html( $row['sent_at'] ); ?></td>
                                    <td><code class="spbwc-email-table__id"><?php echo esc_html( $row['email_id'] ); ?></code></td>
                                    <td><?php echo esc_html( $row['recipient'] ); ?></td>
                                    <td class="spbwc-email-table__subject"><?php echo esc_html( $row['subject'] ); ?></td>
                                    <td><span class="spbwc-badge spbwc-badge--<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $status ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    </div>
                </section>
            </div>
            <?php
        }
    }
}
