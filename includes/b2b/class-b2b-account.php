<?php
/**
 * Company-side Brand Store — My-Account "Brand Store" endpoint (M1).
 *
 * Shown only to users who belong to a company. Owners/admins edit the brand
 * profile (branding, corporate, address, contact); everyone else sees a
 * read-only summary. Logo/banner upload uses media_handle_upload (no JS media
 * library on the storefront). Every action is nonce-protected and scoped to the
 * member's own company. See docs/SPEC_B2B_CLIENT.md §4.2.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Account' ) ) {

    class SPBWC_B2B_Account {

        const ENDPOINT   = 'brand-store';
        const FLUSH_FLAG = 'spbwc_b2b_account_flushed';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
            add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
            add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_save' ) );
            // Notify the new owner when a merchant upgrades them (local mailer, no external service).
            add_action( 'spbwc_company_upgraded', array( __CLASS__, 'notify_owner' ), 10, 2 );
        }

        /**
         * Email the new owner that their B2B Brand Store is ready.
         *
         * @param int $company_id Company.
         * @param int $owner_id   Owner user ID.
         */
        public static function notify_owner( $company_id, $owner_id ) {
            // Routed through the WC email pipeline (SPBWC_Email_B2B_Company_Ready).
            do_action( 'spbwc_b2b_company_ready_notification', $company_id, $owner_id );
        }

        public static function add_endpoint() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
            if ( 'yes' !== get_option( self::FLUSH_FLAG ) ) {
                flush_rewrite_rules( false );
                update_option( self::FLUSH_FLAG, 'yes' );
            }
        }

        /**
         * Insert "Brand Store" before logout, only for company members.
         *
         * @param array $items Menu items.
         * @return array
         */
        public static function add_menu_item( $items ) {
            if ( ! SPBWC_Company::get_user_company_id() ) {
                return $items;
            }
            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
            if ( null !== $logout ) {
                unset( $items['customer-logout'] );
            }
            $items[ self::ENDPOINT ] = __( 'Brand Store', 'storelly-product-builder-for-woocommerce' );
            if ( null !== $logout ) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        }

        /* ── Render ───────────────────────────────────────────────── */

        public static function render_endpoint() {
            if ( ! is_user_logged_in() ) {
                wc_get_template( 'myaccount/form-login.php' );
                return;
            }
            $company_id = SPBWC_Company::get_user_company_id();
            if ( ! $company_id ) {
                echo '<p>' . esc_html__( 'You are not part of a B2B company.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
                return;
            }
            SPBWC_B2B_Assets::storefront();

            self::print_notice();
            self::render_header( $company_id );

            $status = SPBWC_Company::get_status( $company_id );
            if ( SPBWC_Company::STATUS_PENDING === $status ) {
                echo '<div class="woocommerce-info">' . esc_html__( 'Your company is awaiting approval. We will email you when it is active.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            } elseif ( SPBWC_Company::STATUS_SUSPENDED === $status ) {
                echo '<div class="woocommerce-error">' . esc_html__( 'Your company is currently suspended. Please contact the store.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            }

            if ( ! SPBWC_Company::profile_is_complete( $company_id ) ) {
                echo '<div class="woocommerce-message">' . esc_html__( 'Finish your Brand Store profile (logo + legal company name) to make it complete.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            }

            if ( SPBWC_Company::user_can_manage() ) {
                self::render_edit_form( $company_id );
            } else {
                self::render_readonly( $company_id );
            }
        }

        /** Brand Page Header (Pattern 9). */
        protected static function render_header( $company_id ) {
            $name      = get_the_title( $company_id );
            $logo_id   = (int) get_post_meta( $company_id, SPBWC_Company::META_LOGO, true );
            $primary   = SPBWC_Company::brand_primary( $company_id );
            $secondary = SPBWC_Company::brand_secondary( $company_id );
            $store     = SPBWC_Company::store_url( $company_id );
            $style     = '--spbwc-brand-primary:' . esc_attr( $primary ) . ';--spbwc-brand-secondary:' . esc_attr( $secondary ) . ';';

            echo '<div class="spbwc-store__header spbwc-store__header--account" style="' . esc_attr( $style ) . '">';
            echo '<div class="spbwc-store__header-inner">';
            if ( $logo_id ) {
                echo wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'spbwc-store__logo', 'alt' => esc_attr( $name ) ) );
            }
            echo '<div class="spbwc-store__id">';
            echo '<span class="spbwc-store__eyebrow">' . esc_html( $name ) . ' &middot; ' . esc_html__( 'Brand Store', 'storelly-product-builder-for-woocommerce' ) . '</span>';
            echo '<h2 class="spbwc-store__title">' . esc_html__( 'Brand Store profile', 'storelly-product-builder-for-woocommerce' ) . '</h2>';
            if ( '' !== $store ) {
                echo '<a class="spbwc-store__link" href="' . esc_url( $store ) . '" target="_blank" rel="noopener">' . esc_html__( 'View public store →', 'storelly-product-builder-for-woocommerce' ) . '</a>';
            }
            echo '</div></div></div>';
        }

        /** Read-only summary for non-managers. */
        protected static function render_readonly( $company_id ) {
            $profile = (array) get_post_meta( $company_id, SPBWC_Company::META_PROFILE, true );
            $contact = (array) get_post_meta( $company_id, SPBWC_Company::META_CONTACT, true );
            echo '<div class="spbwc-store__body">';
            echo '<p>' . esc_html__( 'Only company owners and admins can edit the Brand Store.', 'storelly-product-builder-for-woocommerce' ) . '</p>';
            echo '<ul>';
            if ( ! empty( $profile['legal_name'] ) ) {
                echo '<li>' . esc_html__( 'Legal name:', 'storelly-product-builder-for-woocommerce' ) . ' ' . esc_html( $profile['legal_name'] ) . '</li>';
            }
            if ( ! empty( $contact['email'] ) ) {
                echo '<li>' . esc_html__( 'Contact:', 'storelly-product-builder-for-woocommerce' ) . ' ' . esc_html( $contact['email'] ) . '</li>';
            }
            echo '</ul></div>';
        }

        /** Owner/admin edit form (branding / corporate / address / contact). */
        protected static function render_edit_form( $company_id ) {
            $tagline   = (string) get_post_meta( $company_id, SPBWC_Company::META_TAGLINE, true );
            $desc      = (string) get_post_meta( $company_id, SPBWC_Company::META_DESCRIPTION, true );
            $primary   = (string) get_post_meta( $company_id, SPBWC_Company::META_BRAND_PRIMARY, true );
            $secondary = (string) get_post_meta( $company_id, SPBWC_Company::META_BRAND_SECONDARY, true );
            $logo_id   = (int) get_post_meta( $company_id, SPBWC_Company::META_LOGO, true );
            $banner_id = (int) get_post_meta( $company_id, SPBWC_Company::META_BANNER, true );
            $profile   = (array) get_post_meta( $company_id, SPBWC_Company::META_PROFILE, true );
            $address   = (array) get_post_meta( $company_id, SPBWC_Company::META_ADDRESSES, true );
            $contact   = (array) get_post_meta( $company_id, SPBWC_Company::META_CONTACT, true );

            $p = function ( $arr, $key ) {
                return isset( $arr[ $key ] ) ? $arr[ $key ] : '';
            };

            echo '<form method="post" enctype="multipart/form-data" class="spbwc-store__form" action="' . esc_url( wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) ) . '">';
            wp_nonce_field( 'spbwc_b2b_save_' . $company_id, '_spbwc_b2b_nonce' );
            echo '<input type="hidden" name="spbwc_b2b_save" value="1" />';
            echo '<input type="hidden" name="company" value="' . esc_attr( $company_id ) . '" />';

            // 1. Branding.
            echo '<fieldset class="spbwc-store__section"><legend>' . esc_html__( 'Branding', 'storelly-product-builder-for-woocommerce' ) . '</legend>';
            self::field_text( 'company_name', __( 'Store name', 'storelly-product-builder-for-woocommerce' ), get_the_title( $company_id ), true );
            self::field_text( 'tagline', __( 'Tagline', 'storelly-product-builder-for-woocommerce' ), $tagline );
            echo '<p class="form-row"><label>' . esc_html__( 'Logo (square, min 400×400)', 'storelly-product-builder-for-woocommerce' ) . '</label>';
            if ( $logo_id ) {
                echo wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'style' => 'display:block;max-width:96px;height:auto;margin-bottom:6px;' ) );
            }
            echo '<input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml" /></p>';
            echo '<p class="form-row"><label>' . esc_html__( 'Banner (≈1920×400)', 'storelly-product-builder-for-woocommerce' ) . '</label>';
            if ( $banner_id ) {
                echo wp_get_attachment_image( $banner_id, 'medium', false, array( 'style' => 'display:block;max-width:240px;height:auto;margin-bottom:6px;' ) );
            }
            echo '<input type="file" name="banner" accept="image/png,image/jpeg" /></p>';
            self::field_color( 'brand_primary', __( 'Primary colour', 'storelly-product-builder-for-woocommerce' ), $primary );
            self::field_color( 'brand_secondary', __( 'Secondary colour', 'storelly-product-builder-for-woocommerce' ), $secondary );
            self::field_textarea( 'description', __( 'Brand description', 'storelly-product-builder-for-woocommerce' ), $desc );
            echo '</fieldset>';

            // 2. Corporate profile.
            echo '<fieldset class="spbwc-store__section"><legend>' . esc_html__( 'Corporate profile', 'storelly-product-builder-for-woocommerce' ) . '</legend>';
            self::field_text( 'legal_name', __( 'Legal company name', 'storelly-product-builder-for-woocommerce' ), $p( $profile, 'legal_name' ), true );
            self::field_text( 'dba', __( 'Trade / DBA name', 'storelly-product-builder-for-woocommerce' ), $p( $profile, 'dba' ) );
            self::field_text( 'industry', __( 'Industry', 'storelly-product-builder-for-woocommerce' ), $p( $profile, 'industry' ) );
            self::field_text( 'employees', __( 'Number of employees', 'storelly-product-builder-for-woocommerce' ), $p( $profile, 'employees' ) );
            self::field_text( 'tax_id', __( 'VAT / Tax ID', 'storelly-product-builder-for-woocommerce' ), $p( $profile, 'tax_id' ) );
            echo '</fieldset>';

            // 3. Address.
            echo '<fieldset class="spbwc-store__section"><legend>' . esc_html__( 'Address', 'storelly-product-builder-for-woocommerce' ) . '</legend>';
            self::field_text( 'addr_street', __( 'Street address', 'storelly-product-builder-for-woocommerce' ), $p( $address, 'street' ) );
            self::field_text( 'addr_city', __( 'City', 'storelly-product-builder-for-woocommerce' ), $p( $address, 'city' ) );
            self::field_text( 'addr_state', __( 'State / Province', 'storelly-product-builder-for-woocommerce' ), $p( $address, 'state' ) );
            self::field_text( 'addr_postcode', __( 'Postcode', 'storelly-product-builder-for-woocommerce' ), $p( $address, 'postcode' ) );
            self::field_text( 'addr_country', __( 'Country', 'storelly-product-builder-for-woocommerce' ), $p( $address, 'country' ) );
            echo '</fieldset>';

            // 4. Contact.
            echo '<fieldset class="spbwc-store__section"><legend>' . esc_html__( 'Official contact', 'storelly-product-builder-for-woocommerce' ) . '</legend>';
            self::field_text( 'contact_name', __( 'Primary contact name', 'storelly-product-builder-for-woocommerce' ), $p( $contact, 'name' ) );
            self::field_text( 'contact_title', __( 'Title / Role', 'storelly-product-builder-for-woocommerce' ), $p( $contact, 'title' ) );
            self::field_text( 'contact_email', __( 'Business email', 'storelly-product-builder-for-woocommerce' ), $p( $contact, 'email' ) );
            self::field_text( 'contact_phone', __( 'Business phone', 'storelly-product-builder-for-woocommerce' ), $p( $contact, 'phone' ) );
            self::field_text( 'contact_website', __( 'Website', 'storelly-product-builder-for-woocommerce' ), $p( $contact, 'website' ) );
            echo '</fieldset>';

            echo '<p class="form-row"><button type="submit" class="button">' . esc_html__( 'Save Brand Store', 'storelly-product-builder-for-woocommerce' ) . '</button></p>';
            echo '</form>';
        }

        /* ── Field helpers ────────────────────────────────────────── */

        protected static function field_text( $name, $label, $value, $required = false ) {
            echo '<p class="form-row"><label for="spbwc-f-' . esc_attr( $name ) . '">' . esc_html( $label ) . ( $required ? ' *' : '' ) . '</label>';
            echo '<input type="text" class="input-text" id="spbwc-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ' /></p>';
        }

        protected static function field_textarea( $name, $label, $value ) {
            echo '<p class="form-row"><label for="spbwc-f-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
            echo '<textarea class="input-text" id="spbwc-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( $value ) . '</textarea></p>';
        }

        protected static function field_color( $name, $label, $value ) {
            echo '<p class="form-row"><label for="spbwc-f-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
            echo '<input type="color" id="spbwc-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( '' !== $value ? $value : '#2563eb' ) . '" /></p>';
        }

        /* ── Save ─────────────────────────────────────────────────── */

        public static function maybe_handle_save() {
            if ( ! isset( $_POST['spbwc_b2b_save'] ) ) {
                return;
            }
            $company_id = isset( $_POST['company'] ) ? absint( wp_unslash( $_POST['company'] ) ) : 0;
            $nonce      = isset( $_POST['_spbwc_b2b_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_b2b_nonce'] ) ) : '';
            if ( ! $company_id || ! wp_verify_nonce( $nonce, 'spbwc_b2b_save_' . $company_id ) ) {
                return;
            }
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }
            // Scope: the editor must belong to THIS company and be a manager.
            if ( SPBWC_Company::get_user_company_id() !== $company_id || ! SPBWC_Company::user_can_manage() ) {
                wp_die( esc_html__( 'You do not have permission.', 'storelly-product-builder-for-woocommerce' ), 403 );
            }

            $get = function ( $key ) {
                return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
            };

            // Title + branding.
            $title = $get( 'company_name' );
            if ( '' !== $title ) {
                wp_update_post( array( 'ID' => $company_id, 'post_title' => $title ) );
            }
            update_post_meta( $company_id, SPBWC_Company::META_TAGLINE, $get( 'tagline' ) );
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
            $desc = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
            update_post_meta( $company_id, SPBWC_Company::META_DESCRIPTION, $desc );
            update_post_meta( $company_id, SPBWC_Company::META_BRAND_PRIMARY, SPBWC_Company::sanitize_hex( $get( 'brand_primary' ) ) );
            update_post_meta( $company_id, SPBWC_Company::META_BRAND_SECONDARY, SPBWC_Company::sanitize_hex( $get( 'brand_secondary' ) ) );

            // Corporate profile.
            update_post_meta( $company_id, SPBWC_Company::META_PROFILE, array(
                'legal_name' => $get( 'legal_name' ),
                'dba'        => $get( 'dba' ),
                'industry'   => $get( 'industry' ),
                'employees'  => $get( 'employees' ),
                'tax_id'     => $get( 'tax_id' ),
            ) );

            // Address.
            update_post_meta( $company_id, SPBWC_Company::META_ADDRESSES, array(
                'street'   => $get( 'addr_street' ),
                'city'     => $get( 'addr_city' ),
                'state'    => $get( 'addr_state' ),
                'postcode' => $get( 'addr_postcode' ),
                'country'  => $get( 'addr_country' ),
            ) );

            // Contact.
            update_post_meta( $company_id, SPBWC_Company::META_CONTACT, array(
                'name'    => $get( 'contact_name' ),
                'title'   => $get( 'contact_title' ),
                'email'   => sanitize_email( $get( 'contact_email' ) ),
                'phone'   => $get( 'contact_phone' ),
                'website' => esc_url_raw( $get( 'contact_website' ) ),
            ) );

            // Uploads (logo / banner).
            self::handle_upload( $company_id, 'logo', SPBWC_Company::META_LOGO );
            self::handle_upload( $company_id, 'banner', SPBWC_Company::META_BANNER );

            // Auto-promote incomplete → active once the profile is complete.
            if ( SPBWC_Company::STATUS_INCOMPLETE === SPBWC_Company::get_status( $company_id )
                && SPBWC_Company::profile_is_complete( $company_id ) ) {
                SPBWC_Company::set_status( $company_id, SPBWC_Company::STATUS_ACTIVE );
            }

            $url = add_query_arg( 'spbwc_b2b_msg', 'saved', wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) );
            wp_safe_redirect( $url );
            exit;
        }

        /**
         * Handle a single image upload into the media library and store its ID.
         *
         * @param int    $company_id Company.
         * @param string $field      $_FILES key.
         * @param string $meta_key   Where to store the attachment ID.
         */
        protected static function handle_upload( $company_id, $field, $meta_key ) {
            if ( empty( $_FILES[ $field ]['name'] ) ) {
                return;
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $allowed = array( 'image/png', 'image/jpeg', 'image/svg+xml' );
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- WP core validates the upload; type checked below.
            $type    = isset( $_FILES[ $field ]['type'] ) ? sanitize_text_field( wp_unslash( $_FILES[ $field ]['type'] ) ) : '';
            if ( ! in_array( $type, $allowed, true ) ) {
                return;
            }
            $attachment_id = media_handle_upload( $field, 0 );
            if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
                update_post_meta( $company_id, $meta_key, (int) $attachment_id );
            }
        }

        protected static function print_notice() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            if ( ! isset( $_GET['spbwc_b2b_msg'] ) ) {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            $code = sanitize_key( wp_unslash( $_GET['spbwc_b2b_msg'] ) );
            if ( 'saved' === $code ) {
                echo '<div class="woocommerce-message" role="alert">' . esc_html__( 'Brand Store saved.', 'storelly-product-builder-for-woocommerce' ) . '</div>';
            }
        }
    }
}
