<?php
/**
 * Server capability status (Wave 2, item 12 — see docs/SPEC_ADMIN_UX_POLISH_W2.md §A12).
 *
 * A pure-read snapshot of the server capabilities the product builder depends on:
 * an image library (Imagick / GD), enough memory and upload size for design
 * rendering, a supported PHP version, a working WP-Cron, and — only when the
 * merchant has already connected — Storelly Cloud.
 *
 * Each check returns { key, level, label, detail, faq_anchor } so callers can
 * render an in-page warning banner that links to the matching FAQ entry in the
 * Setup Wizard (#faq-<key>).
 *
 * Compliance (CLAUDE.md rule 6): this NEVER makes a network request. The "cloud"
 * check reads the local connect flag only — it does not ping app.storelly.com,
 * so an unconnected store is never phoned home. Results are cached briefly to
 * keep admin paints cheap.
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_System_Status' ) ) {

	class SPBWC_System_Status {

		/** Transient holding the computed checks. */
		const CACHE_KEY = 'spbwc_system_status';

		/** Cache lifetime — short, since admins toggle PHP settings while debugging. */
		const CACHE_TTL = 600; // 10 minutes.

		/** Minimum recommended values. */
		const MIN_PHP        = '7.4';
		const MIN_MEMORY_MB  = 128;
		const MIN_UPLOAD_MB  = 8;

		/**
		 * All capability checks, each: [ key, level(ok|warn|error), label, detail, faq_anchor ].
		 * Cached for CACHE_TTL.
		 *
		 * @param bool $force Recompute, bypassing the cache.
		 * @return array<int,array<string,string>>
		 */
		public static function get_checks( $force = false ) {
			if ( ! $force ) {
				$cached = get_transient( self::CACHE_KEY );
				if ( is_array( $cached ) ) {
					return $cached;
				}
			}
			$checks = array(
				self::check_image_library(),
				self::check_memory(),
				self::check_upload(),
				self::check_php(),
				self::check_cron(),
				self::check_cloud(),
			);
			$checks = array_values( array_filter( $checks ) );
			set_transient( self::CACHE_KEY, $checks, self::CACHE_TTL );
			return $checks;
		}

		/** Drop the cached snapshot (call after a setting that affects it changes). */
		public static function flush() {
			delete_transient( self::CACHE_KEY );
		}

		/** Only the checks that warrant a warning/error banner. */
		public static function get_warnings( $force = false ) {
			return array_values(
				array_filter(
					self::get_checks( $force ),
					static function ( $c ) {
						return isset( $c['level'] ) && in_array( $c['level'], array( 'warn', 'error' ), true );
					}
				)
			);
		}

		/** Whether any check is a hard error. */
		public static function has_error( $force = false ) {
			foreach ( self::get_checks( $force ) as $c ) {
				if ( isset( $c['level'] ) && 'error' === $c['level'] ) {
					return true;
				}
			}
			return false;
		}

		/* ── Individual checks ────────────────────────────────────── */

		protected static function check_image_library() {
			$has_imagick = extension_loaded( 'imagick' ) || class_exists( 'Imagick' );
			$has_gd      = extension_loaded( 'gd' ) || function_exists( 'gd_info' );
			if ( $has_imagick ) {
				$level  = 'ok';
				$detail = __( 'Imagick is available — best quality print rendering.', 'storelly-product-builder-for-woocommerce' );
			} elseif ( $has_gd ) {
				$level  = 'warn';
				$detail = __( 'GD is available but Imagick is not. Designs render, but print quality and PDF output are better with Imagick.', 'storelly-product-builder-for-woocommerce' );
			} else {
				$level  = 'error';
				$detail = __( 'No image library found. Install the PHP Imagick or GD extension so the builder can render designs.', 'storelly-product-builder-for-woocommerce' );
			}
			return array(
				'key'        => 'imagick',
				'level'      => $level,
				'label'      => __( 'Image library (Imagick / GD)', 'storelly-product-builder-for-woocommerce' ),
				'detail'     => $detail,
				'faq_anchor' => 'faq-imagick',
			);
		}

		protected static function check_memory() {
			$bytes = self::bytes( ini_get( 'memory_limit' ) );
			$mb    = $bytes > 0 ? (int) round( $bytes / MB_IN_BYTES ) : 0;
			// -1 / 0 means unlimited; treat as OK.
			$ok = ( $bytes <= 0 ) || ( $mb >= self::MIN_MEMORY_MB );
			return array(
				'key'        => 'memory',
				'level'      => $ok ? 'ok' : 'warn',
				'label'      => __( 'PHP memory limit', 'storelly-product-builder-for-woocommerce' ),
				'detail'     => $ok
					? sprintf(
						/* translators: %s: memory limit, e.g. "256 MB" or "unlimited". */
						__( 'Memory limit: %s.', 'storelly-product-builder-for-woocommerce' ),
						$bytes <= 0 ? __( 'unlimited', 'storelly-product-builder-for-woocommerce' ) : $mb . ' MB'
					)
					: sprintf(
						/* translators: 1: current limit in MB, 2: recommended minimum in MB. */
						__( 'Memory limit is %1$d MB. Rendering large designs may run out of memory below %2$d MB.', 'storelly-product-builder-for-woocommerce' ),
						$mb,
						self::MIN_MEMORY_MB
					),
				'faq_anchor' => 'faq-memory',
			);
		}

		protected static function check_upload() {
			$upload = self::bytes( ini_get( 'upload_max_filesize' ) );
			$post   = self::bytes( ini_get( 'post_max_size' ) );
			$eff    = ( $post > 0 && $post < $upload ) ? $post : $upload; // post_max_size caps uploads.
			$mb     = $eff > 0 ? (int) round( $eff / MB_IN_BYTES ) : 0;
			$ok     = ( $eff <= 0 ) || ( $mb >= self::MIN_UPLOAD_MB );
			return array(
				'key'        => 'upload',
				'level'      => $ok ? 'ok' : 'warn',
				'label'      => __( 'Upload size limit', 'storelly-product-builder-for-woocommerce' ),
				'detail'     => $ok
					? sprintf(
						/* translators: %d: effective upload limit in MB. */
						__( 'Effective upload limit: %d MB.', 'storelly-product-builder-for-woocommerce' ),
						$mb
					)
					: sprintf(
						/* translators: 1: current limit in MB, 2: recommended minimum in MB. */
						__( 'Effective upload limit is %1$d MB. Customers uploading artwork may hit this below %2$d MB.', 'storelly-product-builder-for-woocommerce' ),
						$mb,
						self::MIN_UPLOAD_MB
					),
				'faq_anchor' => 'faq-upload',
			);
		}

		protected static function check_php() {
			$ok = version_compare( PHP_VERSION, self::MIN_PHP, '>=' );
			return array(
				'key'        => 'php',
				'level'      => $ok ? 'ok' : 'error',
				'label'      => __( 'PHP version', 'storelly-product-builder-for-woocommerce' ),
				'detail'     => $ok
					? sprintf(
						/* translators: %s: PHP version. */
						__( 'Running PHP %s.', 'storelly-product-builder-for-woocommerce' ),
						PHP_VERSION
					)
					: sprintf(
						/* translators: 1: current PHP version, 2: minimum version. */
						__( 'PHP %1$s is older than the supported %2$s. Ask your host to upgrade.', 'storelly-product-builder-for-woocommerce' ),
						PHP_VERSION,
						self::MIN_PHP
					),
				'faq_anchor' => 'faq-php',
			);
		}

		protected static function check_cron() {
			$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
			return array(
				'key'        => 'cron',
				'level'      => $disabled ? 'warn' : 'ok',
				'label'      => __( 'WP-Cron', 'storelly-product-builder-for-woocommerce' ),
				'detail'     => $disabled
					? __( 'WP-Cron is disabled (DISABLE_WP_CRON). Background jobs such as PDF rendering and quote expiry need a real system cron to be scheduled.', 'storelly-product-builder-for-woocommerce' )
					: __( 'WP-Cron is enabled.', 'storelly-product-builder-for-woocommerce' ),
				'faq_anchor' => 'faq-cron',
			);
		}

		/**
		 * Cloud reachability — read ONLY. We never ping app.storelly.com here: a
		 * store that has not connected is never phoned home (CLAUDE.md rule 6).
		 * When connected we simply report the local connect flag; an unconnected
		 * store gets an informational note, not a warning.
		 */
		protected static function check_cloud() {
			$connected = class_exists( 'SPBWC_Cloud_Connect' ) && SPBWC_Cloud_Connect::is_connected();
			return array(
				'key'        => 'cloud',
				'level'      => 'ok', // Never a warning — cloud is optional and opt-in.
				'label'      => __( 'Storelly Cloud', 'storelly-product-builder-for-woocommerce' ),
				'detail'     => $connected
					? __( 'Connected to Storelly Cloud.', 'storelly-product-builder-for-woocommerce' )
					: __( 'Not connected. Cloud features (print-ready PDF, sync) are optional — connect when you need them.', 'storelly-product-builder-for-woocommerce' ),
				'faq_anchor' => 'faq-cloud',
			);
		}

		/* ── Helpers ──────────────────────────────────────────────── */

		/**
		 * Parse a PHP shorthand byte value ("256M", "1G", "-1") to bytes.
		 *
		 * @param string $value ini value.
		 * @return int Bytes; <= 0 means unlimited / unset.
		 */
		protected static function bytes( $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return 0;
			}
			if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {
				$n = (int) wp_convert_hr_to_bytes( $value );
				// wp_convert_hr_to_bytes clamps negatives to 0; preserve "unlimited".
				if ( 0 === $n && '0' !== $value && false !== strpos( $value, '-' ) ) {
					return -1;
				}
				return $n;
			}
			$n    = (int) $value;
			$unit = strtolower( substr( $value, -1 ) );
			switch ( $unit ) {
				case 'g':
					$n *= 1024;
					// no break.
				case 'm':
					$n *= 1024;
					// no break.
				case 'k':
					$n *= 1024;
			}
			return $n;
		}
	}
}
