<?php
/**
 * B2B Team management — My-Account "Team" endpoint (M5).
 *
 * Company owners/admins manage their team: invite members (email + role), set
 * each member's role and per-order spending limit, remove members, and revoke
 * pending invites. Invitees accept via a tokenised link that attaches their
 * logged-in account to the company. Non-managers see a read-only roster.
 *
 * Every action is nonce-protected and scoped to the manager's OWN company.
 * See docs/SPEC_B2B_CLIENT.md §8.1.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_B2B_Team' ) ) {

    class SPBWC_B2B_Team {

        const ENDPOINT   = 'team';
        const FLUSH_FLAG = 'spbwc_b2b_team_flushed';

        public static function init() {
            add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
            add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
            add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
        }

        public static function add_endpoint() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
            if ( 'yes' !== get_option( self::FLUSH_FLAG ) ) {
                flush_rewrite_rules( false );
                update_option( self::FLUSH_FLAG, 'yes' );
            }
        }

        /** Show "Team" before logout, only for company members. */
        public static function add_menu_item( $items ) {
            if ( ! SPBWC_Company::get_user_company_id() ) {
                return $items;
            }
            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null;
            if ( null !== $logout ) {
                unset( $items['customer-logout'] );
            }
            $items[ self::ENDPOINT ] = __( 'Team', 'storelly-product-builder-for-woocommerce' );
            if ( null !== $logout ) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        }

        /* ── Assignable roles (owner excluded — it is fixed) ──────── */

        protected static function assignable_roles() {
            $roles = SPBWC_Company::roles();
            unset( $roles[ SPBWC_Company::ROLE_OWNER ] );
            return $roles;
        }

        /** Circular initials avatar for a user. */
        protected static function avatar( $user ) {
            $name     = $user ? $user->display_name : '?';
            $initials = '';
            foreach ( preg_split( '/\s+/', trim( (string) $name ) ) as $p ) {
                if ( '' !== $p ) {
                    $initials .= function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 1 ) : substr( $p, 0, 1 );
                }
                if ( strlen( $initials ) >= 2 ) {
                    break;
                }
            }
            return '<span class="spbwc-avatar">' . esc_html( '' !== $initials ? strtoupper( $initials ) : '?' ) . '</span>';
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

            $can_manage = SPBWC_Company::user_can_manage();
            $members    = SPBWC_Company::get_members( $company_id );
            $seats      = SPBWC_Company::get_seats( $company_id );
            $roles      = SPBWC_Company::roles();
            $sym        = get_woocommerce_currency_symbol();
            $owner_id   = (int) get_post_field( 'post_author', $company_id );

            $pending_n  = count( SPBWC_Company::get_pending_invites( $company_id ) );
            $approver_n = 0;
            foreach ( $members as $mm ) {
                if ( SPBWC_Company::user_can_approve( $mm->ID ) ) {
                    $approver_n++;
                }
            }

            echo '<div class="spbwc-team">';

            // Stats.
            echo '<div class="spbwc-rfq-stats">';
            $stats = array(
                array( count( $members ) . ' / ' . $seats, __( 'Members', 'storelly-product-builder-for-woocommerce' ) ),
                array( number_format_i18n( $pending_n ), __( 'Pending invites', 'storelly-product-builder-for-woocommerce' ) ),
                array( number_format_i18n( $approver_n ), __( 'Approvers', 'storelly-product-builder-for-woocommerce' ) ),
            );
            foreach ( $stats as $s ) {
                echo '<div class="spbwc-rfq-stat"><span class="spbwc-rfq-stat__value">' . esc_html( $s[0] ) . '</span><span class="spbwc-rfq-stat__label">' . esc_html( $s[1] ) . '</span></div>';
            }
            echo '</div>';

            echo '<div class="spbwc-rfq-card"><h3>' . esc_html__( 'Team members', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            echo '<table class="spbwc-rfq-table spbwc-team__table"><thead><tr>';
            echo '<th>' . esc_html__( 'Member', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Role', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            echo '<th>' . esc_html__( 'Per-order limit', 'storelly-product-builder-for-woocommerce' ) . '</th>';
            if ( $can_manage ) {
                echo '<th></th>';
            }
            echo '</tr></thead><tbody>';

            foreach ( $members as $m ) {
                $role     = SPBWC_Company::get_user_role( $m->ID );
                $limit    = SPBWC_Company::get_order_limit( $m->ID );
                $is_owner = ( (int) $m->ID === $owner_id );

                echo '<tr>';
                echo '<td><span class="spbwc-row spbwc-row--sm">' . self::avatar( $m ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- avatar escapes.
                    . '<span><strong>' . esc_html( $m->display_name ) . '</strong>' . ( $is_owner ? ' <span class="spbwc-role-chip spbwc-role-chip--owner">' . esc_html__( 'You', 'storelly-product-builder-for-woocommerce' ) . '</span>' : '' )
                    . '<br /><small>' . esc_html( $m->user_email ) . '</small></span></span></td>';

                if ( $can_manage && ! $is_owner ) {
                    echo '<td colspan="' . ( $can_manage ? 2 : 1 ) . '">';
                    echo '<form method="post" class="spbwc-team__row" action="' . esc_url( self::url() ) . '">';
                    wp_nonce_field( 'spbwc_team_member_' . $m->ID, '_spbwc_team_nonce' );
                    echo '<input type="hidden" name="spbwc_team_do" value="update_member" />';
                    echo '<input type="hidden" name="member_id" value="' . esc_attr( $m->ID ) . '" />';
                    echo '<select name="role">';
                    foreach ( self::assignable_roles() as $rslug => $rlabel ) {
                        echo '<option value="' . esc_attr( $rslug ) . '"' . selected( $role, $rslug, false ) . '>' . esc_html( $rlabel ) . '</option>';
                    }
                    echo '</select> ';
                    echo $sym . '<input type="number" name="order_limit" min="0" step="0.01" value="' . esc_attr( $limit ?: '' ) . '" class="spbwc-team__limit" /> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sym is a currency symbol string.
                    echo '<button type="submit" class="button">' . esc_html__( 'Save', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                    echo '</form></td>';
                    echo '<td><form method="post" action="' . esc_url( self::url() ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove this member?', 'storelly-product-builder-for-woocommerce' ) ) . '\');">';
                    wp_nonce_field( 'spbwc_team_member_' . $m->ID, '_spbwc_team_nonce' );
                    echo '<input type="hidden" name="spbwc_team_do" value="remove_member" />';
                    echo '<input type="hidden" name="member_id" value="' . esc_attr( $m->ID ) . '" />';
                    echo '<button type="submit" class="button-link delete">' . esc_html__( 'Remove', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                    echo '</form></td>';
                } else {
                    echo '<td><span class="spbwc-role-chip spbwc-role-chip--' . esc_attr( $role ) . '">' . esc_html( isset( $roles[ $role ] ) ? $roles[ $role ] : $role ) . '</span></td>';
                    echo '<td>' . esc_html( $limit > 0 ? $sym . number_format( $limit, 2 ) : __( 'No limit', 'storelly-product-builder-for-woocommerce' ) ) . '</td>';
                    if ( $can_manage ) {
                        echo '<td></td>';
                    }
                }
                echo '</tr>';
            }
            echo '</tbody></table></div>';

            if ( $can_manage ) {
                self::render_invites( $company_id );
            }
            echo '</div>';
        }

        /** Pending invites + invite form (managers only). */
        protected static function render_invites( $company_id ) {
            $pending = SPBWC_Company::get_pending_invites( $company_id );
            $roles   = SPBWC_Company::roles();

            if ( ! empty( $pending ) ) {
                echo '<div class="spbwc-rfq-card"><h3>' . esc_html__( 'Pending invitations', 'storelly-product-builder-for-woocommerce' ) . '</h3><ul class="spbwc-team__pending">';
                foreach ( $pending as $inv ) {
                    echo '<li class="spbwc-row spbwc-row--sm spbwc-row--between">';
                    echo '<span>' . esc_html( $inv['email'] ) . ' <span class="spbwc-role-chip spbwc-role-chip--' . esc_attr( $inv['role'] ) . '">' . esc_html( isset( $roles[ $inv['role'] ] ) ? $roles[ $inv['role'] ] : $inv['role'] ) . '</span></span>';
                    echo '<form method="post" style="display:inline" action="' . esc_url( self::url() ) . '">';
                    wp_nonce_field( 'spbwc_team_revoke', '_spbwc_team_nonce' );
                    echo '<input type="hidden" name="spbwc_team_do" value="revoke" /><input type="hidden" name="email" value="' . esc_attr( $inv['email'] ) . '" />';
                    echo '<button type="submit" class="spbwc-rfq-btn spbwc-rfq-btn--danger" style="width:auto;padding:6px 12px;">' . esc_html__( 'Revoke', 'storelly-product-builder-for-woocommerce' ) . '</button>';
                    echo '</form></li>';
                }
                echo '</ul></div>';
            }

            echo '<div class="spbwc-rfq-card"><h3>' . esc_html__( 'Invite a teammate', 'storelly-product-builder-for-woocommerce' ) . '</h3>';
            if ( ! SPBWC_Company::has_free_seat( $company_id ) ) {
                echo '<p class="description">' . esc_html__( 'No team seats left. Ask the store to raise your seat limit.', 'storelly-product-builder-for-woocommerce' ) . '</p></div>';
                return;
            }
            echo '<form method="post" class="spbwc-team__invite" action="' . esc_url( self::url() ) . '">';
            wp_nonce_field( 'spbwc_team_invite', '_spbwc_team_nonce' );
            echo '<input type="hidden" name="spbwc_team_do" value="invite" />';
            echo '<input type="email" name="email" placeholder="' . esc_attr__( 'teammate@company.com', 'storelly-product-builder-for-woocommerce' ) . '" required /> ';
            echo '<select name="role">';
            foreach ( self::assignable_roles() as $rslug => $rlabel ) {
                echo '<option value="' . esc_attr( $rslug ) . '"' . selected( SPBWC_Company::ROLE_REQUESTER, $rslug, false ) . '>' . esc_html( $rlabel ) . '</option>';
            }
            echo '</select> ';
            echo '<button type="submit" class="spbwc-rfq-btn" style="width:auto;">' . esc_html__( 'Send invite', 'storelly-product-builder-for-woocommerce' ) . '</button>';
            echo '</form></div>';
        }

        /* ── Actions ──────────────────────────────────────────────── */

        public static function handle_actions() {
            // Accept invite (GET link).
            if ( isset( $_GET['spbwc_accept_invite'] ) ) {
                self::handle_accept();
                return;
            }
            if ( ! isset( $_POST['spbwc_team_do'] ) ) {
                return;
            }
            $do = sanitize_key( wp_unslash( $_POST['spbwc_team_do'] ) );
            if ( ! is_user_logged_in() ) {
                return;
            }
            $company_id = SPBWC_Company::get_user_company_id();

            if ( 'invite' === $do ) {
                self::do_invite( $company_id );
            } elseif ( 'revoke' === $do ) {
                self::do_revoke( $company_id );
            } elseif ( 'update_member' === $do ) {
                self::do_update_member( $company_id );
            } elseif ( 'remove_member' === $do ) {
                self::do_remove_member( $company_id );
            }
        }

        protected static function do_invite( $company_id ) {
            $nonce = isset( $_POST['_spbwc_team_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_team_nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'spbwc_team_invite' ) || ! SPBWC_Company::user_can_manage() ) {
                self::redirect( 'error' );
            }
            $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $role  = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : SPBWC_Company::ROLE_REQUESTER;
            if ( ! isset( self::assignable_roles()[ $role ] ) ) {
                $role = SPBWC_Company::ROLE_REQUESTER;
            }
            $token = SPBWC_Company::add_invite( $company_id, $email, $role, get_current_user_id() );
            if ( is_wp_error( $token ) ) {
                self::redirect( 'invite_error' );
            }
            self::email_invite( $company_id, $email, $token );
            self::redirect( 'invited' );
        }

        protected static function do_revoke( $company_id ) {
            $nonce = isset( $_POST['_spbwc_team_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_team_nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'spbwc_team_revoke' ) || ! SPBWC_Company::user_can_manage() ) {
                self::redirect( 'error' );
            }
            $email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $invites = get_post_meta( $company_id, SPBWC_Company::META_INVITES, true );
            $invites = is_array( $invites ) ? $invites : array();
            foreach ( $invites as $i => $row ) {
                if ( isset( $row['email'] ) && $row['email'] === $email && 'pending' === $row['status'] ) {
                    $invites[ $i ]['status'] = 'revoked';
                }
            }
            update_post_meta( $company_id, SPBWC_Company::META_INVITES, $invites );
            self::redirect( 'revoked' );
        }

        protected static function do_update_member( $company_id ) {
            $member_id = isset( $_POST['member_id'] ) ? absint( wp_unslash( $_POST['member_id'] ) ) : 0;
            $nonce     = isset( $_POST['_spbwc_team_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_team_nonce'] ) ) : '';
            if ( ! $member_id || ! wp_verify_nonce( $nonce, 'spbwc_team_member_' . $member_id ) || ! SPBWC_Company::user_can_manage() ) {
                self::redirect( 'error' );
            }
            // Scope: target must be in the manager's company and not the owner.
            if ( SPBWC_Company::get_user_company_id( $member_id ) !== $company_id
                || (int) get_post_field( 'post_author', $company_id ) === $member_id ) {
                self::redirect( 'error' );
            }
            $role  = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
            $limit = isset( $_POST['order_limit'] ) ? max( 0, (float) wp_unslash( $_POST['order_limit'] ) ) : 0;
            if ( isset( self::assignable_roles()[ $role ] ) ) {
                update_user_meta( $member_id, SPBWC_Company::USER_ROLE, $role );
            }
            update_user_meta( $member_id, SPBWC_Company::USER_LIMIT_ORDER, $limit );
            self::redirect( 'member_saved' );
        }

        protected static function do_remove_member( $company_id ) {
            $member_id = isset( $_POST['member_id'] ) ? absint( wp_unslash( $_POST['member_id'] ) ) : 0;
            $nonce     = isset( $_POST['_spbwc_team_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_spbwc_team_nonce'] ) ) : '';
            if ( ! $member_id || ! wp_verify_nonce( $nonce, 'spbwc_team_member_' . $member_id ) || ! SPBWC_Company::user_can_manage() ) {
                self::redirect( 'error' );
            }
            if ( SPBWC_Company::get_user_company_id( $member_id ) !== $company_id
                || (int) get_post_field( 'post_author', $company_id ) === $member_id ) {
                self::redirect( 'error' );
            }
            SPBWC_Company::unlink_user( $member_id );
            self::redirect( 'member_removed' );
        }

        /** Accept an invitation: attach the current user to the company. */
        protected static function handle_accept() {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Token IS the credential.
            $token      = isset( $_GET['spbwc_accept_invite'] ) ? sanitize_text_field( wp_unslash( $_GET['spbwc_accept_invite'] ) ) : '';
            $company_id = isset( $_GET['company'] ) ? absint( wp_unslash( $_GET['company'] ) ) : 0;
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            if ( '' === $token || ! $company_id ) {
                return;
            }
            if ( ! is_user_logged_in() ) {
                wc_add_notice( __( 'Please log in or create an account to accept your team invitation.', 'storelly-product-builder-for-woocommerce' ), 'notice' );
                wp_safe_redirect( add_query_arg(
                    array( 'spbwc_accept_invite' => $token, 'company' => $company_id ),
                    wc_get_page_permalink( 'myaccount' )
                ) );
                exit;
            }
            $user_id = get_current_user_id();
            if ( SPBWC_Company::get_user_company_id( $user_id ) ) {
                self::redirect( 'already_member' );
            }
            $invites = get_post_meta( $company_id, SPBWC_Company::META_INVITES, true );
            $invites = is_array( $invites ) ? $invites : array();
            foreach ( $invites as $i => $row ) {
                if ( ! isset( $row['status'], $row['token_hash'] ) || 'pending' !== $row['status'] ) {
                    continue;
                }
                if ( ! empty( $row['expires_at'] ) && (int) $row['expires_at'] < time() ) {
                    continue;
                }
                if ( wp_check_password( $token, $row['token_hash'] ) ) {
                    SPBWC_Company::link_user( $user_id, $company_id, $row['role'], (int) ( isset( $row['invited_by'] ) ? $row['invited_by'] : 0 ) );
                    $invites[ $i ]['status'] = 'accepted';
                    update_post_meta( $company_id, SPBWC_Company::META_INVITES, $invites );
                    SPBWC_Company::add_timeline_event( $company_id, sprintf(
                        /* translators: %s: member email. */
                        __( '%s joined the team.', 'storelly-product-builder-for-woocommerce' ),
                        $row['email']
                    ), $user_id );
                    self::redirect( 'accepted' );
                }
            }
            self::redirect( 'invite_invalid' );
        }

        /* ── Helpers ──────────────────────────────────────────────── */

        protected static function email_invite( $company_id, $email, $token ) {
            if ( ! is_email( $email ) ) {
                return;
            }
            // Routed through the WC email pipeline (SPBWC_Email_B2B_Invite).
            do_action( 'spbwc_b2b_invite_notification', $company_id, $email, $token );
        }

        protected static function url() {
            return wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) );
        }

        protected static function redirect( $code ) {
            wp_safe_redirect( add_query_arg( 'spbwc_team_msg', $code, self::url() ) );
            exit;
        }

        protected static function print_notice() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            if ( ! isset( $_GET['spbwc_team_msg'] ) ) {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash.
            $code = sanitize_key( wp_unslash( $_GET['spbwc_team_msg'] ) );
            $map  = array(
                'invited'        => array( 'message', __( 'Invitation sent.', 'storelly-product-builder-for-woocommerce' ) ),
                'revoked'        => array( 'message', __( 'Invitation revoked.', 'storelly-product-builder-for-woocommerce' ) ),
                'member_saved'   => array( 'message', __( 'Member updated.', 'storelly-product-builder-for-woocommerce' ) ),
                'member_removed' => array( 'message', __( 'Member removed.', 'storelly-product-builder-for-woocommerce' ) ),
                'accepted'       => array( 'message', __( 'Welcome to the team!', 'storelly-product-builder-for-woocommerce' ) ),
                'already_member' => array( 'error', __( 'You already belong to a company.', 'storelly-product-builder-for-woocommerce' ) ),
                'invite_invalid' => array( 'error', __( 'That invitation is invalid or has expired.', 'storelly-product-builder-for-woocommerce' ) ),
                'invite_error'   => array( 'error', __( 'Could not send the invite (seat limit or duplicate).', 'storelly-product-builder-for-woocommerce' ) ),
                'error'          => array( 'error', __( 'Something went wrong. Please try again.', 'storelly-product-builder-for-woocommerce' ) ),
            );
            if ( ! isset( $map[ $code ] ) ) {
                return;
            }
            $cls = 'error' === $map[ $code ][0] ? 'woocommerce-error' : 'woocommerce-message';
            echo '<div class="' . esc_attr( $cls ) . '" role="alert">' . esc_html( $map[ $code ][1] ) . '</div>';
        }
    }
}
