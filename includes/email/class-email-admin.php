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
                wp_enqueue_style( 'spbwc-email-admin', SPBWC_PB_CSS_URL . 'email-admin.css', array( 'spbwc-tokens' ), SPBWC_PB_VERSION );
            }
        }

        /** Group definitions keyed by id-prefix, in display order. */
        protected static function groups() {
            return array(
                'spbwc_quote_'  => __( 'Quote', 'storelly-product-builder-for-woocommerce' ),
                'spbwc_b2b_'    => __( 'B2B / Team', 'storelly-product-builder-for-woocommerce' ),
                'spbwc_order_'  => __( 'Custom Order', 'storelly-product-builder-for-woocommerce' ),
                'spbwc_email_'  => __( 'Marketplace / Designer', 'storelly-product-builder-for-woocommerce' ),
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
            $groups = self::groups();
            // Bucket emails into groups (longest-prefix-first so spbwc_email_ wins over nothing).
            $buckets = array_fill_keys( array_keys( $groups ), array() );
            foreach ( $emails as $email ) {
                foreach ( $groups as $prefix => $label ) {
                    if ( 0 === strpos( $email->id, $prefix ) ) {
                        $buckets[ $prefix ][] = $email;
                        break;
                    }
                }
            }
            ?>
            <div class="wrap spbwc-email-admin">
                <h1 class="spbwc-email-admin__title"><?php esc_html_e( 'Storelly Emails', 'storelly-product-builder-for-woocommerce' ); ?></h1>
                <p class="spbwc-email-admin__lead">
                    <?php esc_html_e( 'Every notification Storelly sends, in one place. Toggle and edit content in WooCommerce, send yourself a test, and review the delivery log below.', 'storelly-product-builder-for-woocommerce' ); ?>
                </p>

                <?php if ( isset( $_GET['spbwc_test_sent'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash. ?>
                    <?php $ok = '1' === sanitize_text_field( wp_unslash( $_GET['spbwc_test_sent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <div class="spbwc-notice-banner spbwc-notice-banner--<?php echo $ok ? 'success' : 'error'; ?>">
                        <?php echo $ok ? esc_html__( 'Test email sent to your account.', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Could not send the test email.', 'storelly-product-builder-for-woocommerce' ); ?>
                    </div>
                <?php endif; ?>

                <div class="spbwc-email-admin__from spbwc-card">
                    <span class="spbwc-card__title"><?php esc_html_e( 'Sender', 'storelly-product-builder-for-woocommerce' ); ?></span>
                    <p>
                        <strong><?php echo esc_html( get_option( 'woocommerce_email_from_name' ) ); ?></strong>
                        &lt;<?php echo esc_html( get_option( 'woocommerce_email_from_address' ) ); ?>&gt;
                    </p>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ); ?>"><?php esc_html_e( 'Change sender in WooCommerce', 'storelly-product-builder-for-woocommerce' ); ?></a>
                </div>

                <?php foreach ( $groups as $prefix => $label ) :
                    if ( empty( $buckets[ $prefix ] ) ) {
                        continue;
                    } ?>
                    <section class="spbwc-card spbwc-email-group">
                        <h2 class="spbwc-card__title spbwc-email-group__title"><?php echo esc_html( $label ); ?></h2>
                        <table class="spbwc-email-group__table widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Email', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'storelly-product-builder-for-woocommerce' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $buckets[ $prefix ] as $email ) :
                                $enabled = $email->is_enabled(); ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $email->get_title() ); ?></strong>
                                        <span class="spbwc-email-row__desc"><?php echo esc_html( $email->get_description() ); ?></span>
                                    </td>
                                    <td>
                                        <span class="spbwc-badge spbwc-badge--<?php echo $enabled ? 'on' : 'off'; ?>">
                                            <?php echo $enabled ? esc_html__( 'Enabled', 'storelly-product-builder-for-woocommerce' ) : esc_html__( 'Disabled', 'storelly-product-builder-for-woocommerce' ); ?>
                                        </span>
                                    </td>
                                    <td class="spbwc-email-row__actions">
                                        <a class="button" href="<?php echo esc_url( self::edit_url( $email ) ); ?>"><?php esc_html_e( 'Edit', 'storelly-product-builder-for-woocommerce' ); ?></a>
                                        <a class="button button-secondary" href="<?php echo esc_url( self::test_url( $email ) ); ?>"><?php esc_html_e( 'Send test', 'storelly-product-builder-for-woocommerce' ); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                <?php endforeach; ?>

                <section class="spbwc-card spbwc-email-log">
                    <h2 class="spbwc-card__title"><?php esc_html_e( 'Delivery log', 'storelly-product-builder-for-woocommerce' ); ?></h2>
                    <?php
                    $rows = class_exists( 'SPBWC_Email_Log' ) ? SPBWC_Email_Log::get_rows( array( 'limit' => 50 ) ) : array();
                    if ( empty( $rows ) ) : ?>
                        <p class="spbwc-email-log__empty"><?php esc_html_e( 'No emails logged yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    <?php else : ?>
                        <table class="spbwc-email-group__table widefat">
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
                            <?php foreach ( $rows as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $row['sent_at'] ); ?></td>
                                    <td><code><?php echo esc_html( $row['email_id'] ); ?></code></td>
                                    <td><?php echo esc_html( $row['recipient'] ); ?></td>
                                    <td><?php echo esc_html( $row['subject'] ); ?></td>
                                    <td><span class="spbwc-badge spbwc-badge--<?php echo 'failed' === $row['status'] ? 'off' : 'on'; ?>"><?php echo esc_html( $row['status'] ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            </div>
            <?php
        }
    }
}
