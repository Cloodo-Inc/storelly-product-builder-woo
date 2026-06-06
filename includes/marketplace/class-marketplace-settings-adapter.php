<?php
/**
 * Marketplace Settings Adapter — minimal renderer for the launcher settings
 * that the pc-designer marketplace module expects to live behind the
 * Nbdesigner_Settings framework. Storelly does not ship that framework, so
 * this adapter renders fields registered via the
 * apply_filters('nbdesigner_settings_options', ...) chain into a plain WP
 * settings form and persists each one as a top-level option.
 *
 * Guarded for coexistence with pc-designer on the same site.
 *
 * @package Storelly
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SPBWC_Marketplace_Settings_Adapter' ) ) {
    class SPBWC_Marketplace_Settings_Adapter {

        const MENU_SLUG  = 'spbwc-marketplace-settings';
        const NONCE_NAME = 'spbwc_marketplace_settings_nonce';
        const CAPABILITY = 'spbwc_manage_product_builder';

        /**
         * Singleton-ish boot.
         */
        public static function init() {
            add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ), 200 );
            add_action( 'admin_init', array( __CLASS__, 'maybe_save' ) );
        }

        /**
         * Register the submenu under the Storelly overview parent.
         */
        public static function register_submenu() {
            if ( ! defined( 'SPBWC_PB_OVERVIEW_SLUG' ) ) {
                return;
            }
            // Legacy designer-marketplace settings page. Only surface it when the
            // marketplace module is actually enabled — otherwise it is dead clutter
            // that also collides with the real "B2B Companies" hub. Hidden by
            // default (marketplace ships off); re-appears if the marketplace is on.
            if ( function_exists( 'spbwc_marketplace_is_enabled' ) && ! spbwc_marketplace_is_enabled() ) {
                return;
            }
            add_submenu_page(
                SPBWC_PB_OVERVIEW_SLUG,
                esc_html__( 'Marketplace Settings', 'storelly-product-builder-for-woocommerce' ),
                esc_html__( 'Marketplace Settings', 'storelly-product-builder-for-woocommerce' ),
                self::CAPABILITY,
                self::MENU_SLUG,
                array( __CLASS__, 'render_page' )
            );
        }

        /**
         * Pull the merged options structure from the filter chain.
         *
         * Expected shape (PCDesigner convention):
         *   array(
         *     'tab_id' => array(
         *        array( 'id' => 'option_key', 'type' => 'text|select|radio|checkbox|number|textarea', ... ),
         *        ...
         *     ),
         *     ...
         *   )
         */
        public static function get_options_tree() {
            $tree = apply_filters( 'nbdesigner_settings_options', array() );
            return is_array( $tree ) ? $tree : array();
        }

        /**
         * Handle form submission. One option per field, stored by its id.
         */
        public static function maybe_save() {
            if ( ! isset( $_POST['spbwc_marketplace_settings_submit'] ) ) {
                return;
            }
            if ( ! current_user_can( self::CAPABILITY ) ) {
                return;
            }
            $nonce = isset( $_POST[ self::NONCE_NAME ] )
                ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) )
                : '';
            if ( ! wp_verify_nonce( $nonce, self::NONCE_NAME ) ) {
                return;
            }

            $tree = self::get_options_tree();
            foreach ( $tree as $tab_id => $fields ) {
                if ( ! is_array( $fields ) ) {
                    continue;
                }
                foreach ( $fields as $field ) {
                    if ( empty( $field['id'] ) ) {
                        continue;
                    }
                    $id    = sanitize_key( $field['id'] );
                    $type  = isset( $field['type'] ) ? $field['type'] : 'text';
                    $raw   = isset( $_POST[ $id ] ) ? wp_unslash( $_POST[ $id ] ) : '';
                    $value = self::sanitize_value( $raw, $type );
                    update_option( $id, $value );
                }
            }

            add_action(
                'admin_notices',
                function () {
                    echo '<div class="notice notice-success is-dismissible"><p>'
                        . esc_html__( 'Marketplace settings saved.', 'storelly-product-builder-for-woocommerce' )
                        . '</p></div>';
                }
            );
        }

        /**
         * Sanitize a posted value based on its declared type.
         *
         * @param mixed  $raw   Raw POST value (already wp_unslash'd).
         * @param string $type  Field type from the options tree.
         * @return mixed
         */
        protected static function sanitize_value( $raw, $type ) {
            switch ( $type ) {
                case 'number':
                    return is_numeric( $raw ) ? 0 + $raw : 0;
                case 'textarea':
                    return sanitize_textarea_field( is_string( $raw ) ? $raw : '' );
                case 'checkbox':
                    return $raw ? 'yes' : 'no';
                case 'multicheckbox':
                    $out = array();
                    if ( is_array( $raw ) ) {
                        foreach ( $raw as $k => $v ) {
                            $out[ sanitize_key( $k ) ] = $v ? 1 : 0;
                        }
                    }
                    return wp_json_encode( $out );
                case 'select':
                case 'radio':
                case 'text':
                default:
                    if ( is_array( $raw ) ) {
                        return array_map( 'sanitize_text_field', $raw );
                    }
                    return sanitize_text_field( (string) $raw );
            }
        }

        /**
         * Render the settings page.
         */
        public static function render_page() {
            if ( ! current_user_can( self::CAPABILITY ) ) {
                return;
            }
            $tree = self::get_options_tree();
            ?>
            <div class="wrap">
                <h1><?php echo esc_html__( 'Marketplace Settings', 'storelly-product-builder-for-woocommerce' ); ?></h1>
                <form method="post" action="">
                    <?php wp_nonce_field( self::NONCE_NAME, self::NONCE_NAME ); ?>

                    <?php if ( empty( $tree ) ) : ?>
                        <p><?php echo esc_html__( 'No marketplace settings registered yet.', 'storelly-product-builder-for-woocommerce' ); ?></p>
                    <?php else : ?>
                        <?php foreach ( $tree as $tab_id => $fields ) : ?>
                            <h2><?php echo esc_html( ucwords( str_replace( array( '-', '_' ), ' ', (string) $tab_id ) ) ); ?></h2>
                            <table class="form-table" role="presentation">
                                <tbody>
                                <?php if ( is_array( $fields ) ) : ?>
                                    <?php foreach ( $fields as $field ) : ?>
                                        <?php self::render_field( $field ); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <p class="submit">
                        <button type="submit"
                                name="spbwc_marketplace_settings_submit"
                                class="button button-primary">
                            <?php echo esc_html__( 'Save Changes', 'storelly-product-builder-for-woocommerce' ); ?>
                        </button>
                    </p>
                </form>
            </div>
            <?php
        }

        /**
         * Render a single field row.
         */
        protected static function render_field( $field ) {
            if ( empty( $field['id'] ) ) {
                return;
            }
            $id          = sanitize_key( $field['id'] );
            $type        = isset( $field['type'] ) ? $field['type'] : 'text';
            $title       = isset( $field['title'] ) ? $field['title'] : $id;
            $description = isset( $field['description'] ) ? $field['description'] : '';
            $default     = isset( $field['default'] ) ? $field['default'] : '';
            $options     = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
            $value       = get_option( $id, $default );
            ?>
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></label>
                </th>
                <td>
                    <?php
                    switch ( $type ) {
                        case 'select':
                            ?>
                            <select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>">
                                <?php foreach ( $options as $opt_value => $opt_label ) : ?>
                                    <option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>>
                                        <?php echo esc_html( $opt_label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php
                            break;

                        case 'radio':
                            foreach ( $options as $opt_value => $opt_label ) :
                                ?>
                                <label style="margin-right:12px;">
                                    <input type="radio"
                                           name="<?php echo esc_attr( $id ); ?>"
                                           value="<?php echo esc_attr( $opt_value ); ?>"
                                           <?php checked( $value, $opt_value ); ?> />
                                    <?php echo esc_html( $opt_label ); ?>
                                </label>
                                <?php
                            endforeach;
                            break;

                        case 'checkbox':
                            ?>
                            <input type="checkbox"
                                   id="<?php echo esc_attr( $id ); ?>"
                                   name="<?php echo esc_attr( $id ); ?>"
                                   value="yes"
                                   <?php checked( $value, 'yes' ); ?> />
                            <?php
                            break;

                        case 'multicheckbox':
                            $decoded = is_string( $value ) ? json_decode( $value, true ) : $value;
                            if ( ! is_array( $decoded ) ) {
                                $decoded = array();
                            }
                            foreach ( $options as $opt_value => $opt_label ) :
                                $checked = ! empty( $decoded[ $opt_value ] );
                                ?>
                                <label style="display:block;">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $id ); ?>[<?php echo esc_attr( $opt_value ); ?>]"
                                           value="1"
                                           <?php checked( $checked, true ); ?> />
                                    <?php echo esc_html( $opt_label ); ?>
                                </label>
                                <?php
                            endforeach;
                            break;

                        case 'number':
                            ?>
                            <input type="number"
                                   id="<?php echo esc_attr( $id ); ?>"
                                   name="<?php echo esc_attr( $id ); ?>"
                                   value="<?php echo esc_attr( (string) $value ); ?>"
                                   class="regular-text" />
                            <?php
                            break;

                        case 'textarea':
                            ?>
                            <textarea id="<?php echo esc_attr( $id ); ?>"
                                      name="<?php echo esc_attr( $id ); ?>"
                                      class="large-text" rows="4"><?php echo esc_textarea( (string) $value ); ?></textarea>
                            <?php
                            break;

                        case 'text':
                        default:
                            ?>
                            <input type="text"
                                   id="<?php echo esc_attr( $id ); ?>"
                                   name="<?php echo esc_attr( $id ); ?>"
                                   value="<?php echo esc_attr( is_scalar( $value ) ? (string) $value : '' ); ?>"
                                   class="regular-text" />
                            <?php
                            break;
                    }

                    if ( $description ) {
                        echo '<p class="description">' . wp_kses_post( $description ) . '</p>';
                    }
                    ?>
                </td>
            </tr>
            <?php
        }
    }

    SPBWC_Marketplace_Settings_Adapter::init();
}
