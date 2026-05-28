<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Media grouping for imported builder images.
 *
 * Registers a private `spbwc_media_group` taxonomy on attachments so imported product images can
 * be grouped per product (in addition to native post_parent linkage), de-cluttering the global
 * Media Library and letting the option-builder image picker filter to one product's media.
 */
if (!class_exists('SPBWC_Media_Group')) {
    class SPBWC_Media_Group {

        /** Taxonomy name. */
        const TAXONOMY = 'spbwc_media_group';

        /** Source-URL meta key set on imported attachments. */
        const SOURCE_URL_META = '_spbwc_source_url';

        protected static $instance;

        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Attach WordPress hooks.
         *
         * @return void
         */
        public function spbwc_init() {
            add_action('init', array($this, 'spbwc_register_taxonomy'));
            // Media Library (upload.php) list mode: dropdown filter + query handler.
            add_action('restrict_manage_posts', array($this, 'spbwc_render_media_filter'));
            add_action('pre_get_posts', array($this, 'spbwc_filter_media_query'));
            // wp.media grid (AJAX) filter so the picker can scope to a group.
            add_filter('ajax_query_attachments_args', array($this, 'spbwc_filter_ajax_attachments'));
        }

        /**
         * Register the private per-product media-group taxonomy on attachments.
         *
         * @return void
         */
        public function spbwc_register_taxonomy() {
            $labels = array(
                'name'          => esc_html__('Builder Media Group', 'storelly-product-builder-for-woocommerce'),
                'singular_name' => esc_html__('Builder Media Group', 'storelly-product-builder-for-woocommerce'),
                'menu_name'     => esc_html__('Builder Media Group', 'storelly-product-builder-for-woocommerce'),
            );
            register_taxonomy(
                self::TAXONOMY,
                'attachment',
                array(
                    'labels'            => $labels,
                    'public'            => false,
                    'show_ui'           => true,
                    'show_in_menu'      => false,
                    'show_admin_column' => false,
                    'hierarchical'      => false,
                    'query_var'         => false,
                    'rewrite'           => false,
                )
            );
        }

        /**
         * Get (or create) the per-product term for a product.
         *
         * Term slug is EXACTLY `spbwc-prod-{product_id}`; name is the product title (fallback
         * `Product {id}`).
         *
         * @param int $product_id WooCommerce product ID.
         * @return int Term ID, or 0 on failure.
         */
        public function spbwc_get_or_create_product_term($product_id) {
            $product_id = (int) $product_id;
            if ($product_id <= 0) {
                return 0;
            }
            $slug = 'spbwc-prod-' . $product_id;
            $existing = get_term_by('slug', $slug, self::TAXONOMY);
            if ($existing && !is_wp_error($existing)) {
                return (int) $existing->term_id;
            }
            $name = get_the_title($product_id);
            if ('' === trim((string) $name)) {
                /* translators: %d: product ID used as a fallback media-group name. */
                $name = sprintf(esc_html__('Product %d', 'storelly-product-builder-for-woocommerce'), $product_id);
            }
            $term = wp_insert_term($name, self::TAXONOMY, array('slug' => $slug));
            if (is_wp_error($term)) {
                // Possible race: term created between the lookup and insert.
                $existing = get_term_by('slug', $slug, self::TAXONOMY);
                if ($existing && !is_wp_error($existing)) {
                    return (int) $existing->term_id;
                }
                return 0;
            }
            return isset($term['term_id']) ? (int) $term['term_id'] : 0;
        }

        /**
         * Tag attachments as belonging to a product: set post_parent and assign the per-product
         * media-group term. Reusable by both the importer and the backfill routine.
         *
         * @param array $attachment_ids Attachment IDs to tag.
         * @param int   $product_id     WooCommerce product ID.
         * @return int Number of attachments tagged.
         */
        public static function spbwc_assign_media_group($attachment_ids, $product_id) {
            $product_id = (int) $product_id;
            if ($product_id <= 0 || !is_array($attachment_ids) || empty($attachment_ids)) {
                return 0;
            }
            $self = self::instance();
            $term_id = $self->spbwc_get_or_create_product_term($product_id);
            $tagged = 0;
            foreach ($attachment_ids as $att_id) {
                $att_id = (int) $att_id;
                if ($att_id <= 0 || 'attachment' !== get_post_type($att_id)) {
                    continue;
                }
                $current_parent = (int) wp_get_post_parent_id($att_id);
                if ($current_parent !== $product_id) {
                    wp_update_post(array(
                        'ID'          => $att_id,
                        'post_parent' => $product_id,
                    ));
                }
                if ($term_id > 0) {
                    wp_set_object_terms($att_id, $term_id, self::TAXONOMY, true);
                }
                $tagged++;
            }
            return $tagged;
        }

        /**
         * Render a Media Library (upload.php) dropdown to filter by media group.
         *
         * @param string $post_type Current list-table post type.
         * @return void
         */
        public function spbwc_render_media_filter($post_type = '') {
            global $pagenow;
            if ('upload.php' !== $pagenow) {
                return;
            }
            $terms = get_terms(array(
                'taxonomy'   => self::TAXONOMY,
                'hide_empty' => false,
            ));
            if (is_wp_error($terms) || empty($terms)) {
                return;
            }
            $selected = isset($_GET[self::TAXONOMY]) ? sanitize_title(wp_unslash($_GET[self::TAXONOMY])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, no state change.
            echo '<select name="' . esc_attr(self::TAXONOMY) . '" id="spbwc-media-group-filter">';
            echo '<option value="">' . esc_html__('All builder media groups', 'storelly-product-builder-for-woocommerce') . '</option>';
            foreach ($terms as $term) {
                printf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr($term->slug),
                    selected($selected, $term->slug, false),
                    esc_html($term->name)
                );
            }
            echo '</select>';
        }

        /**
         * Apply the chosen media-group term to the Media Library list query.
         *
         * @param WP_Query $query Query object.
         * @return void
         */
        public function spbwc_filter_media_query($query) {
            global $pagenow;
            if (!is_admin() || 'upload.php' !== $pagenow || !$query->is_main_query()) {
                return;
            }
            if (empty($_GET[self::TAXONOMY])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, no state change.
                return;
            }
            $slug = sanitize_title(wp_unslash($_GET[self::TAXONOMY])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, no state change.
            if ('' === $slug) {
                return;
            }
            $tax_query = (array) $query->get('tax_query');
            $tax_query[] = array(
                'taxonomy' => self::TAXONOMY,
                'field'    => 'slug',
                'terms'    => array($slug),
            );
            $query->set('tax_query', $tax_query);
        }

        /**
         * Filter the wp.media (AJAX grid) attachment query by media group when requested.
         *
         * The JS frame may pass `query[spbwc_media_group]` as either a term slug
         * (`spbwc-prod-123`) or a bare product id (`123`).
         *
         * @param array $args WP_Query args.
         * @return array
         */
        public function spbwc_filter_ajax_attachments($args) {
            if (!isset($_REQUEST['query']) || !is_array($_REQUEST['query'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core media AJAX nonce-checked upstream; read-only filter.
                return $args;
            }
            if (empty($_REQUEST['query'][self::TAXONOMY])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core media AJAX nonce-checked upstream; read-only filter.
                return $args;
            }
            $raw = sanitize_text_field(wp_unslash($_REQUEST['query'][self::TAXONOMY])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core media AJAX nonce-checked upstream; read-only filter.
            if ('' === $raw) {
                return $args;
            }
            if (is_numeric($raw)) {
                $slug = 'spbwc-prod-' . (int) $raw;
            } else {
                $slug = sanitize_title($raw);
            }
            if ('' === $slug) {
                return $args;
            }
            $tax_query = isset($args['tax_query']) && is_array($args['tax_query']) ? $args['tax_query'] : array();
            $tax_query[] = array(
                'taxonomy' => self::TAXONOMY,
                'field'    => 'slug',
                'terms'    => array($slug),
            );
            $args['tax_query'] = $tax_query;
            return $args;
        }

        /**
         * Recursively collect positive-integer attachment IDs found under known media keys in a
         * builder fields array.
         *
         * @param mixed $fields Unserialized fields array (or any nested value).
         * @return array Unique int attachment IDs.
         */
        public static function spbwc_collect_attachment_ids($fields) {
            $media_keys = array(
                'image',
                'base',
                'product_image',
                'component_icon',
                'frame_image',
                'overlay_image',
                'bg_image',
                'img_src',
                'img_overlay',
            );
            $ids = array();
            self::spbwc_collect_attachment_ids_walk($fields, $media_keys, $ids);
            $ids = array_filter(array_map('intval', $ids), function ($id) {
                return $id > 0;
            });
            return array_values(array_unique($ids));
        }

        /**
         * Recursion helper for spbwc_collect_attachment_ids().
         *
         * @param mixed $node       Current node.
         * @param array $media_keys Keys whose scalar/array int values are attachment IDs.
         * @param array $ids        Accumulator (by reference).
         * @return void
         */
        private static function spbwc_collect_attachment_ids_walk($node, $media_keys, &$ids) {
            if (!is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                if (in_array($key, $media_keys, true)) {
                    if (is_array($value)) {
                        foreach ($value as $maybe_id) {
                            if (is_int($maybe_id) || (is_string($maybe_id) && ctype_digit($maybe_id))) {
                                $ids[] = (int) $maybe_id;
                            }
                        }
                    } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
                        $ids[] = (int) $value;
                    }
                }
                if (is_array($value)) {
                    self::spbwc_collect_attachment_ids_walk($value, $media_keys, $ids);
                }
            }
        }

        /**
         * Backfill media groups for already-imported products. DO NOT call automatically — meant to
         * be run manually by an administrator.
         *
         * Iterates every row of the options table, collects attachment IDs from each row's `fields`
         * plus the linked product's `_thumbnail_id` and `_designer_setting` images, and (when not a
         * dry run) tags them via spbwc_assign_media_group(). Idempotent.
         *
         * @param bool $dry_run When true (default), report only — no writes.
         * @return array { option_sets_processed, products, attachments_tagged }
         */
        public function spbwc_backfill_media_groups($dry_run = true) {
            global $wpdb;
            $report = array(
                'option_sets_processed' => 0,
                'products'              => 0,
                'attachments_tagged'    => 0,
            );
            $table = $wpdb->prefix . 'storelly_product_builder_options';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off manual backfill over the custom options table.
            $rows = $wpdb->get_results("SELECT product_ids, fields FROM `{$table}`", ARRAY_A);
            if (empty($rows)) {
                return $report;
            }
            $products_seen = array();
            foreach ($rows as $row) {
                $report['option_sets_processed']++;
                $product_ids = maybe_unserialize($row['product_ids']);
                if (!is_array($product_ids) || empty($product_ids)) {
                    continue;
                }
                $product_id = (int) reset($product_ids);
                if ($product_id <= 0) {
                    continue;
                }
                $fields = maybe_unserialize($row['fields']);
                $ids = self::spbwc_collect_attachment_ids($fields);

                $thumbnail_id = (int) get_post_thumbnail_id($product_id);
                if ($thumbnail_id > 0) {
                    $ids[] = $thumbnail_id;
                }
                $designer_setting = maybe_unserialize(get_post_meta($product_id, '_designer_setting', true));
                if (is_array($designer_setting)) {
                    $ids = array_merge($ids, self::spbwc_collect_attachment_ids($designer_setting));
                }
                $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
                    return $id > 0;
                })));
                if (empty($ids)) {
                    continue;
                }
                if (!isset($products_seen[$product_id])) {
                    $products_seen[$product_id] = true;
                    $report['products']++;
                }
                if (!$dry_run) {
                    $report['attachments_tagged'] += self::spbwc_assign_media_group($ids, $product_id);
                } else {
                    $report['attachments_tagged'] += count($ids);
                }
            }
            return $report;
        }
    }
}
