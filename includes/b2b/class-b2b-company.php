<?php
/**
 * B2B Company data model (CPT `spbwc_company`).
 *
 * A company is a first-class post that groups one or more WordPress users into a
 * B2B account with its own brand-store profile, tier, team and (later) pricing.
 * This class owns the meta schema, status helpers, slug generation, the
 * user↔company link, pending invitations, and the "company of the current user"
 * resolver. See docs/SPEC_B2B_CLIENT.md §3.
 *
 * Storage notes:
 *   - post_status stays `publish`; the business status lives in meta
 *     (`_spbwc_company_status`) so the public /store/<slug> page can decouple
 *     from WordPress publish state.
 *   - A user belongs to at most ONE company in v1 (user meta `_spbwc_company_id`).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Company' ) ) {

    class SPBWC_Company {

        /** Custom post type slug. */
        const POST_TYPE = 'spbwc_company';

        /** Activity-timeline comment type (reused pattern from quotes). */
        const TIMELINE_COMMENT_TYPE = 'spbwc_company_event';

        /* ── Business statuses (stored in meta, not post_status) ──── */
        const STATUS_PENDING    = 'pending';
        const STATUS_ACTIVE     = 'active';
        const STATUS_SUSPENDED  = 'suspended';
        const STATUS_INCOMPLETE = 'incomplete_profile';

        /* ── Company post meta ────────────────────────────────────── */
        const META_STATUS       = '_spbwc_company_status';
        const META_TIER         = '_spbwc_company_tier';
        const META_SLUG         = '_spbwc_company_slug';
        const META_LOGO         = '_spbwc_company_logo_id';
        const META_BANNER       = '_spbwc_company_banner_id';
        const META_BRAND_PRIMARY   = '_spbwc_company_brand_primary';
        const META_BRAND_SECONDARY = '_spbwc_company_brand_secondary';
        const META_TAGLINE      = '_spbwc_company_tagline';
        const META_DESCRIPTION  = '_spbwc_company_description';
        const META_PROFILE      = '_spbwc_company_profile';
        const META_ADDRESSES    = '_spbwc_company_addresses';
        const META_CONTACT      = '_spbwc_company_contact';
        const META_PAYMENT_TERMS = '_spbwc_company_payment_terms';
        const META_CREDIT_LIMIT = '_spbwc_company_credit_limit';
        const META_SEATS        = '_spbwc_company_seats';
        const META_APPROVAL_THRESHOLD = '_spbwc_company_approval_threshold';
        const META_ALLOWED_PRODUCTS   = '_spbwc_company_allowed_products';
        const META_INVITES      = '_spbwc_company_invites';

        /* ── User meta (the link) ─────────────────────────────────── */
        const USER_COMPANY_ID   = '_spbwc_company_id';
        const USER_ROLE         = '_spbwc_company_role';
        const USER_LIMIT_ORDER  = '_spbwc_company_spend_limit_order';
        const USER_LIMIT_MONTH  = '_spbwc_company_spend_limit_month';
        const USER_INVITED_BY   = '_spbwc_company_invited_by';
        const USER_INVITE_STATUS = '_spbwc_company_invite_status';

        /* ── Roles ────────────────────────────────────────────────── */
        const ROLE_OWNER     = 'owner';
        const ROLE_ADMIN     = 'admin';
        const ROLE_APPROVER  = 'approver';
        const ROLE_REQUESTER = 'requester';
        const ROLE_VIEWER    = 'viewer';

        /** Default free-tier seat cap (spec §11). Filterable. */
        const DEFAULT_SEATS = 5;

        /**
         * Human-readable status labels.
         *
         * @return array<string,string>
         */
        public static function statuses() {
            return array(
                self::STATUS_PENDING    => __( 'Pending approval', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_ACTIVE     => __( 'Active', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_SUSPENDED  => __( 'Suspended', 'storelly-product-builder-for-woocommerce' ),
                self::STATUS_INCOMPLETE => __( 'Incomplete profile', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        /**
         * Human-readable role labels.
         *
         * @return array<string,string>
         */
        public static function roles() {
            return array(
                self::ROLE_OWNER     => __( 'Owner', 'storelly-product-builder-for-woocommerce' ),
                self::ROLE_ADMIN     => __( 'Admin', 'storelly-product-builder-for-woocommerce' ),
                self::ROLE_APPROVER  => __( 'Approver', 'storelly-product-builder-for-woocommerce' ),
                self::ROLE_REQUESTER => __( 'Requester', 'storelly-product-builder-for-woocommerce' ),
                self::ROLE_VIEWER    => __( 'Viewer', 'storelly-product-builder-for-woocommerce' ),
            );
        }

        /**
         * Roles allowed to manage the company (profile, team, pricing display).
         *
         * @return string[]
         */
        public static function manager_roles() {
            return array( self::ROLE_OWNER, self::ROLE_ADMIN );
        }

        /* ── Creation ─────────────────────────────────────────────── */

        /**
         * Create a company and assign $owner_user_id as its owner.
         *
         * @param string $name          Company display name.
         * @param int    $owner_user_id WordPress user becoming the owner.
         * @param array  $args          Optional: tier, status, seats, profile.
         * @return int|WP_Error         Company post ID.
         */
        public static function create( $name, $owner_user_id, array $args = array() ) {
            $name          = sanitize_text_field( $name );
            $owner_user_id = absint( $owner_user_id );
            if ( '' === $name || ! $owner_user_id || ! get_userdata( $owner_user_id ) ) {
                return new WP_Error( 'spbwc_company_invalid', __( 'A company name and a valid owner are required.', 'storelly-product-builder-for-woocommerce' ) );
            }
            // One company per user (v1).
            if ( self::get_user_company_id( $owner_user_id ) ) {
                return new WP_Error( 'spbwc_company_exists', __( 'This customer already belongs to a company.', 'storelly-product-builder-for-woocommerce' ) );
            }

            $post_id = wp_insert_post(
                array(
                    'post_type'      => self::POST_TYPE,
                    'post_status'    => 'publish',
                    'post_title'     => $name,
                    'post_author'    => $owner_user_id,
                    'comment_status' => 'closed',
                ),
                true
            );
            if ( is_wp_error( $post_id ) ) {
                return $post_id;
            }

            $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : self::STATUS_ACTIVE;
            if ( ! isset( self::statuses()[ $status ] ) ) {
                $status = self::STATUS_ACTIVE;
            }
            update_post_meta( $post_id, self::META_STATUS, $status );
            update_post_meta( $post_id, self::META_SLUG, self::unique_slug( $name, $post_id ) );
            update_post_meta( $post_id, self::META_TIER, isset( $args['tier'] ) ? sanitize_key( $args['tier'] ) : '' );
            update_post_meta( $post_id, self::META_SEATS, isset( $args['seats'] ) ? absint( $args['seats'] ) : self::default_seats() );
            update_post_meta( $post_id, self::META_APPROVAL_THRESHOLD, isset( $args['approval_threshold'] ) ? (float) $args['approval_threshold'] : 0 );
            update_post_meta( $post_id, self::META_PAYMENT_TERMS, isset( $args['payment_terms'] ) ? sanitize_key( $args['payment_terms'] ) : 'prepaid' );

            self::link_user( $owner_user_id, $post_id, self::ROLE_OWNER );
            self::add_timeline_event( $post_id, __( 'Company created.', 'storelly-product-builder-for-woocommerce' ) );

            do_action( 'spbwc_company_created', $post_id, $owner_user_id );
            return $post_id;
        }

        /* ── Status ───────────────────────────────────────────────── */

        /**
         * @param int $company_id Company post ID.
         * @return string Status slug.
         */
        public static function get_status( $company_id ) {
            $s = (string) get_post_meta( absint( $company_id ), self::META_STATUS, true );
            return isset( self::statuses()[ $s ] ) ? $s : self::STATUS_PENDING;
        }

        /**
         * @param int    $company_id Company post ID.
         * @param string $status     New status slug.
         * @param string $note       Optional timeline note.
         * @return bool
         */
        public static function set_status( $company_id, $status, $note = '' ) {
            $company_id = absint( $company_id );
            $status     = sanitize_key( $status );
            if ( ! $company_id || ! isset( self::statuses()[ $status ] ) ) {
                return false;
            }
            $from = self::get_status( $company_id );
            update_post_meta( $company_id, self::META_STATUS, $status );
            if ( '' !== $note ) {
                self::add_timeline_event( $company_id, $note );
            }
            do_action( 'spbwc_company_status_changed', $company_id, $from, $status );
            return true;
        }

        /**
         * @param int $company_id Company post ID.
         * @return bool
         */
        public static function is_active( $company_id ) {
            return self::STATUS_ACTIVE === self::get_status( $company_id );
        }

        /* ── Slug ─────────────────────────────────────────────────── */

        /**
         * Build a unique store slug from a base string.
         *
         * @param string $base       Source text (company name).
         * @param int    $company_id Company being saved (excluded from collision check).
         * @return string
         */
        public static function unique_slug( $base, $company_id = 0 ) {
            $slug = sanitize_title( $base );
            if ( '' === $slug ) {
                $slug = 'company-' . absint( $company_id );
            }
            $candidate = $slug;
            $i         = 2;
            while ( self::slug_exists( $candidate, $company_id ) ) {
                $candidate = $slug . '-' . $i;
                $i++;
            }
            return $candidate;
        }

        /**
         * @param string $slug    Candidate slug.
         * @param int    $exclude Company ID to ignore.
         * @return bool
         */
        public static function slug_exists( $slug, $exclude = 0 ) {
            $found = get_posts(
                array(
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'any',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                    'exclude'     => array( absint( $exclude ) ),
                    'meta_key'    => self::META_SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value'  => $slug,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                )
            );
            return ! empty( $found );
        }

        /**
         * Resolve a company by its public store slug.
         *
         * @param string $slug Store slug.
         * @return int Company post ID, or 0.
         */
        public static function get_by_slug( $slug ) {
            $slug  = sanitize_title( $slug );
            if ( '' === $slug ) {
                return 0;
            }
            $found = get_posts(
                array(
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'publish',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                    'meta_key'    => self::META_SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value'  => $slug,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                )
            );
            return ! empty( $found ) ? (int) $found[0] : 0;
        }

        /* ── User ↔ company link ──────────────────────────────────── */

        /**
         * Attach a user to a company with a role.
         *
         * @param int    $user_id    User.
         * @param int    $company_id Company.
         * @param string $role       Role slug.
         * @param int    $invited_by Inviter user ID (0 = none).
         * @return bool
         */
        public static function link_user( $user_id, $company_id, $role, $invited_by = 0 ) {
            $user_id    = absint( $user_id );
            $company_id = absint( $company_id );
            $role       = sanitize_key( $role );
            if ( ! $user_id || ! $company_id || ! isset( self::roles()[ $role ] ) ) {
                return false;
            }
            update_user_meta( $user_id, self::USER_COMPANY_ID, $company_id );
            update_user_meta( $user_id, self::USER_ROLE, $role );
            update_user_meta( $user_id, self::USER_INVITE_STATUS, 'active' );
            if ( $invited_by ) {
                update_user_meta( $user_id, self::USER_INVITED_BY, absint( $invited_by ) );
            }
            do_action( 'spbwc_company_user_linked', $user_id, $company_id, $role );
            return true;
        }

        /**
         * Detach a user from their company.
         *
         * @param int $user_id User.
         * @return void
         */
        public static function unlink_user( $user_id ) {
            $user_id = absint( $user_id );
            delete_user_meta( $user_id, self::USER_COMPANY_ID );
            delete_user_meta( $user_id, self::USER_ROLE );
            update_user_meta( $user_id, self::USER_INVITE_STATUS, 'removed' );
            do_action( 'spbwc_company_user_unlinked', $user_id );
        }

        /**
         * @param int $user_id User (0 = current).
         * @return int Company ID, or 0.
         */
        public static function get_user_company_id( $user_id = 0 ) {
            $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
            if ( ! $user_id ) {
                return 0;
            }
            return (int) get_user_meta( $user_id, self::USER_COMPANY_ID, true );
        }

        /**
         * @param int $user_id User (0 = current).
         * @return string Role slug, or ''.
         */
        public static function get_user_role( $user_id = 0 ) {
            $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
            if ( ! $user_id ) {
                return '';
            }
            return (string) get_user_meta( $user_id, self::USER_ROLE, true );
        }

        /**
         * Whether a user may manage (edit) their company.
         *
         * @param int $user_id User (0 = current).
         * @return bool
         */
        public static function user_can_manage( $user_id = 0 ) {
            return in_array( self::get_user_role( $user_id ), self::manager_roles(), true );
        }

        /**
         * All users attached to a company.
         *
         * @param int $company_id Company.
         * @return WP_User[]
         */
        public static function get_members( $company_id ) {
            $company_id = absint( $company_id );
            if ( ! $company_id ) {
                return array();
            }
            $q = new WP_User_Query(
                array(
                    'meta_key'   => self::USER_COMPANY_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value' => $company_id,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                    'number'     => 200,
                )
            );
            return $q->get_results();
        }

        /**
         * Count of active members (excludes pending invites).
         *
         * @param int $company_id Company.
         * @return int
         */
        public static function count_members( $company_id ) {
            return count( self::get_members( $company_id ) );
        }

        /* ── Seats ────────────────────────────────────────────────── */

        /**
         * Default seat cap. Filterable so Pro / a merchant can raise it.
         *
         * @return int
         */
        public static function default_seats() {
            return (int) apply_filters( 'spbwc_company_default_seats', self::DEFAULT_SEATS );
        }

        /**
         * @param int $company_id Company.
         * @return int Seat cap for this company.
         */
        public static function get_seats( $company_id ) {
            $seats = (int) get_post_meta( absint( $company_id ), self::META_SEATS, true );
            return $seats > 0 ? $seats : self::default_seats();
        }

        /**
         * Whether the company has a free seat for another member/invite.
         *
         * @param int $company_id Company.
         * @return bool
         */
        public static function has_free_seat( $company_id ) {
            $used = self::count_members( $company_id ) + count( self::get_pending_invites( $company_id ) );
            return $used < self::get_seats( $company_id );
        }

        /* ── Invitations (team seats) ─────────────────────────────── */

        /**
         * @param int $company_id Company.
         * @return array[] Pending invite rows.
         */
        public static function get_pending_invites( $company_id ) {
            $invites = get_post_meta( absint( $company_id ), self::META_INVITES, true );
            if ( ! is_array( $invites ) ) {
                return array();
            }
            $out = array();
            foreach ( $invites as $row ) {
                if ( isset( $row['status'] ) && 'pending' === $row['status'] ) {
                    $out[] = $row;
                }
            }
            return $out;
        }

        /**
         * Add a pending team invitation (returns the raw token for the email link).
         *
         * @param int    $company_id Company.
         * @param string $email      Invitee email.
         * @param string $role       Proposed role.
         * @param int    $inviter    Inviter user ID.
         * @return string|WP_Error   Raw token on success.
         */
        public static function add_invite( $company_id, $email, $role, $inviter = 0 ) {
            $company_id = absint( $company_id );
            $email      = sanitize_email( $email );
            $role       = sanitize_key( $role );
            if ( ! $company_id || ! is_email( $email ) || ! isset( self::roles()[ $role ] ) ) {
                return new WP_Error( 'spbwc_company_invite_invalid', __( 'A valid email and role are required.', 'storelly-product-builder-for-woocommerce' ) );
            }
            if ( ! self::has_free_seat( $company_id ) ) {
                return new WP_Error( 'spbwc_company_seat_limit', __( 'No team seats are available. Increase the seat limit first.', 'storelly-product-builder-for-woocommerce' ) );
            }
            $invites = get_post_meta( $company_id, self::META_INVITES, true );
            $invites = is_array( $invites ) ? $invites : array();
            // Block a duplicate pending invite for the same email.
            foreach ( $invites as $row ) {
                if ( isset( $row['email'], $row['status'] ) && $row['email'] === $email && 'pending' === $row['status'] ) {
                    return new WP_Error( 'spbwc_company_invite_dup', __( 'This email already has a pending invitation.', 'storelly-product-builder-for-woocommerce' ) );
                }
            }
            $token        = wp_generate_password( 32, false );
            $invites[]    = array(
                'email'      => $email,
                'role'       => $role,
                'token_hash' => wp_hash_password( $token ),
                'expires_at' => time() + ( 7 * DAY_IN_SECONDS ),
                'invited_by' => absint( $inviter ),
                'status'     => 'pending',
            );
            update_post_meta( $company_id, self::META_INVITES, $invites );
            return $token;
        }

        /* ── Timeline ─────────────────────────────────────────────── */

        /**
         * @param int    $company_id Company.
         * @param string $message    Event text.
         * @param int    $user_id    Actor (0 = current).
         * @return int|false
         */
        public static function add_timeline_event( $company_id, $message, $user_id = 0 ) {
            $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
            return wp_insert_comment(
                array(
                    'comment_post_ID'  => absint( $company_id ),
                    'comment_content'  => wp_kses_post( $message ),
                    'comment_type'     => self::TIMELINE_COMMENT_TYPE,
                    'user_id'          => $user_id,
                    'comment_approved' => 1,
                )
            );
        }

        /**
         * @param int $company_id Company.
         * @return WP_Comment[]
         */
        public static function get_timeline( $company_id ) {
            return get_comments(
                array(
                    'post_id' => absint( $company_id ),
                    'type'    => self::TIMELINE_COMMENT_TYPE,
                    'orderby' => 'comment_date_gmt',
                    'order'   => 'ASC',
                    'status'  => 'approve',
                )
            );
        }

        /* ── Display helpers ──────────────────────────────────────── */

        /**
         * @param int $company_id Company.
         * @return string Public store URL.
         */
        public static function store_url( $company_id ) {
            $slug = (string) get_post_meta( absint( $company_id ), self::META_SLUG, true );
            if ( '' === $slug ) {
                return '';
            }
            // Pretty permalinks → /store/<slug>/. Plain permalinks → query-var form
            // (?spbwc_store=<slug>) which works without a rewrite rule. The query
            // var is registered in SPBWC_B2B_Storefront so both forms resolve.
            if ( get_option( 'permalink_structure' ) ) {
                return home_url( user_trailingslashit( 'store/' . $slug ) );
            }
            return add_query_arg( SPBWC_B2B_Storefront::QUERY_VAR, $slug, home_url( '/' ) );
        }

        /**
         * Brand primary colour (falls back to the Storelly default).
         *
         * @param int $company_id Company.
         * @return string Hex colour.
         */
        public static function brand_primary( $company_id ) {
            $hex = (string) get_post_meta( absint( $company_id ), self::META_BRAND_PRIMARY, true );
            $hex = self::sanitize_hex( $hex );
            return '' !== $hex ? $hex : '#2563eb';
        }

        /**
         * Brand secondary colour (falls back to a darker default).
         *
         * @param int $company_id Company.
         * @return string Hex colour.
         */
        public static function brand_secondary( $company_id ) {
            $hex = (string) get_post_meta( absint( $company_id ), self::META_BRAND_SECONDARY, true );
            $hex = self::sanitize_hex( $hex );
            return '' !== $hex ? $hex : '#1e40af';
        }

        /**
         * Validate a #rrggbb / #rgb hex colour.
         *
         * @param string $hex Candidate.
         * @return string Clean hex or ''.
         */
        public static function sanitize_hex( $hex ) {
            $hex = is_string( $hex ) ? trim( $hex ) : '';
            return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $hex ) ? $hex : '';
        }

        /**
         * Whether the profile has the minimum fields filled (drives the
         * incomplete_profile nudge).
         *
         * @param int $company_id Company.
         * @return bool
         */
        public static function profile_is_complete( $company_id ) {
            $company_id = absint( $company_id );
            $profile    = get_post_meta( $company_id, self::META_PROFILE, true );
            $has_logo   = (int) get_post_meta( $company_id, self::META_LOGO, true ) > 0;
            $has_legal  = is_array( $profile ) && ! empty( $profile['legal_name'] );
            return $has_logo && $has_legal;
        }
    }
}
