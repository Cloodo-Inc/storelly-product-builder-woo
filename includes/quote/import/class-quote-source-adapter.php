<?php
/**
 * Base class for a Quote import source adapter (Quote Import & Sync, M1).
 *
 * Each adapter knows how to read quote-shaped data out of one external source
 * (WooCommerce orders, YITH RAQ, a contact form, …) and map a raw row onto the
 * canonical request payload understood by SPBWC_Quote::create(). Adapters are
 * collected by SPBWC_Quote_Import via the `spbwc_quote_source_adapters` filter.
 *
 * Dedupe is two-sided: the created quote stores `_spbwc_imported_from` =
 * "<adapter id>:<source ref>", and the adapter also marks the source row as done
 * (mark_imported) so count_importable()/fetch_batch() naturally drain without an
 * offset that would drift as rows are consumed.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Quote_Source_Adapter' ) ) {

    abstract class SPBWC_Quote_Source_Adapter {

        /** Stable machine id, e.g. 'woo_orders'. */
        abstract public function id();

        /** Human-readable name for the import screen. */
        abstract public function label();

        /** Short description of what gets imported. */
        public function description() {
            return '';
        }

        /** Is the source present on this site? */
        abstract public function is_available();

        /** How many rows could still be imported (already-imported excluded). */
        abstract public function count_importable();

        /**
         * Next N not-yet-imported raw rows (objects/arrays the other methods
         * understand). Adapters self-drain via mark_imported(), so no offset.
         *
         * @param int $limit Max rows.
         * @return array
         */
        abstract public function fetch_batch( $limit );

        /**
         * Map one raw row onto a quote payload.
         *
         * @param mixed $row Raw row from fetch_batch().
         * @return array {
         *   @type array  $request Canonical request payload for SPBWC_Quote::create().
         *   @type int    $user_id Customer user id (0 if guest).
         *   @type array  $lines   Line items [ label, desc, qty, unit_price ].
         *   @type string $status  Landing post status (default STATUS_NEW).
         *   @type string $date    Optional 'Y-m-d H:i:s' to backdate the quote.
         *   @type int    $product_id Optional source product id.
         * }
         */
        abstract public function map_to_quote( $row );

        /** Stable per-row id used for dedupe (e.g. order id, entry id). */
        abstract public function source_ref( $row );

        /** Record that this row has been imported (set a flag on the source). */
        abstract public function mark_imported( $row, $quote_id );

        /** Can this source mirror new submissions live (M5)? */
        public function supports_sync() {
            return false;
        }

        /** Attach the live-capture listener (M5). No-op by default. */
        public function register_sync() {}
    }
}
