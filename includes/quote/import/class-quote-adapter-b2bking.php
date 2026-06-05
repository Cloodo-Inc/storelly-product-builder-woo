<?php
/**
 * Import adapter: B2BKing → quotes (Quote Import M4).
 *
 * In B2BKing a quote request opens a "Conversation": a thread of `b2bking_message`
 * posts. We import one quote per conversation by grouping the messages on their
 * thread/conversation meta and taking the opening message — mapping the customer
 * (the message author's WP account) and the message body. Dedupe is keyed on the
 * conversation, not the individual message.
 *
 * NOTE: B2BKing is a commercial plugin and its conversation/thread meta is not
 * publicly documented; this adapter tries the likely thread-grouping meta keys
 * and maps contact + message defensively. The quoted line items live in the
 * B2BKing cart/quote structure (undocumented), so they are left for the merchant
 * to fill in the pricing reply. Validate the thread meta + extend item mapping
 * against a live B2BKing install.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Adapter_B2bking' ) ) {

    class SPBWC_Quote_Adapter_B2bking extends SPBWC_Quote_Source_Adapter {

        const CPT = 'b2bking_message';

        /** Candidate meta keys that group messages into a conversation/thread. */
        protected function thread_keys() {
            return array( 'b2bking_message_conversation', 'b2bking_conversation', 'b2bking_thread_id', 'b2bking_thread', 'conversation_id' );
        }

        public function id() {
            return 'b2bking';
        }

        public function label() {
            return __( 'B2BKing', 'storelly-product-builder-for-woocommerce' );
        }

        public function description() {
            return __( 'Quote-request conversations from the B2BKing plugin.', 'storelly-product-builder-for-woocommerce' );
        }

        public function is_available() {
            return ( class_exists( 'B2bking' ) || class_exists( 'B2BKing' ) ) && post_type_exists( self::CPT );
        }

        /** Resolve the conversation/thread id of a message post. */
        protected function thread_of( $post_id ) {
            foreach ( $this->thread_keys() as $key ) {
                $val = get_post_meta( $post_id, $key, true );
                if ( '' !== $val && null !== $val ) {
                    return (string) $val;
                }
            }
            // No thread meta found → treat the message itself as its own thread.
            return 'msg' . $post_id;
        }

        /** One opening message per conversation, minus already-imported. */
        protected function collect( $limit ) {
            if ( ! $this->is_available() ) {
                return array();
            }
            $seen  = SPBWC_Quote_Import::imported_refs( $this->id() );
            $posts = get_posts(
                array(
                    'post_type'      => self::CPT,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'date',
                    'order'          => 'ASC',
                )
            );
            $rows  = array();
            $first = array(); // thread => true once its opener is taken.
            foreach ( $posts as $post ) {
                $thread = $this->thread_of( $post->ID );
                if ( isset( $first[ $thread ] ) ) {
                    continue; // only the opening message of each conversation.
                }
                $first[ $thread ] = true;
                $ref              = 'thread:' . $thread;
                if ( isset( $seen[ $ref ] ) ) {
                    continue;
                }
                $rows[] = array( 'ref' => $ref, 'post' => $post );
                if ( $limit > 0 && count( $rows ) >= $limit ) {
                    break;
                }
            }
            return $rows;
        }

        public function count_importable() {
            return count( $this->collect( -1 ) );
        }

        public function fetch_batch( $limit ) {
            return $this->collect( max( 1, (int) $limit ) );
        }

        public function source_ref( $row ) {
            return isset( $row['ref'] ) ? $row['ref'] : '';
        }

        public function map_to_quote( $row ) {
            if ( empty( $row['post'] ) ) {
                return array();
            }
            $post    = $row['post'];
            $request = array( 'message' => wp_strip_all_tags( (string) $post->post_content ) );

            $author = (int) $post->post_author;
            if ( $author ) {
                $user = get_userdata( $author );
                if ( $user ) {
                    $request['email']      = $user->user_email;
                    $request['first_name'] = $user->first_name ? $user->first_name : $user->display_name;
                    $request['last_name']  = $user->last_name;
                    $company               = get_user_meta( $author, 'billing_company', true );
                    if ( $company ) {
                        $request['company'] = $company;
                    }
                    $phone = get_user_meta( $author, 'billing_phone', true );
                    if ( $phone ) {
                        $request['phone'] = $phone;
                    }
                }
            }

            return array(
                'request' => $request,
                'user_id' => $author,
                'lines'   => array(), // conversation cart items are undocumented — priced in the reply.
                'status'  => SPBWC_Quote::STATUS_NEW,
                'date'    => $post->post_date ? $post->post_date : '',
            );
        }

        public function mark_imported( $row, $quote_id ) {}
    }
}
