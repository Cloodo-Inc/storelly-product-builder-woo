<?php
/**
 * Welcome Wizard — standalone, full-screen first-run flow (Onboarding §9).
 *
 * Right after activation the merchant is bounced here (see
 * SPBWC_Onboarding::maybe_redirect_after_activation) instead of into a welcome
 * block bolted onto the Overview. It is a self-contained, step-by-step page
 * rendered without the usual admin chrome (a body class + welcome-wizard.css
 * hide the sidebar/toolbar/footer/notices) so it reads as a focused wizard.
 *
 * Three steps, deliberately light:
 *   1. Pick 1–3 sample pricing options from a random slice of the bundled
 *      template catalog. Each chosen template is forked into an UNATTACHED
 *      "(Sample)" option (SPBWC_Template_Applier::install_sample) — visible in
 *      the Pricing Options list to edit/assign/delete, never on the storefront.
 *   2. A progress/notice step: the light B2B sample (company + sample quotation)
 *      installs on admin_init, and existing quotes auto-sync from WooCommerce &
 *      other plugins in the background (SPBWC_Quote_Import::kick_auto_sync). The
 *      merchant just watches — nothing to configure.
 *   3. Congratulations + two exits: go to the Overview, or continue with the
 *      Setup Wizard.
 *
 * The HEAVY bag demo is intentionally NOT installed here — it is lazy-seeded the
 * first time the Visual Builder screen is opened (see SPBWC_Demo_Seeder).
 *
 * Compliance: every state-changing step is nonce + capability gated; all work is
 * local (no network, no phone-home).
 *
 * @package Storelly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SPBWC_Welcome_Wizard' ) ) {

	class SPBWC_Welcome_Wizard {

		/** Hidden admin page slug. */
		const SLUG = 'storelly-product-builder-for-woocommerce-welcome';

		/** Option storing the option-set ids created as samples (for reference / cleanup). */
		const OPT_SAMPLES = 'spbwc_wizard_samples';

		/** Option mapping sample option_id => template slug (for the live preview). */
		const OPT_SAMPLE_SLUGS = 'spbwc_wizard_sample_slugs';

		/** Option storing the random template slugs offered on step 1 (kept stable across reloads). */
		const OPT_PICK = 'spbwc_wizard_pick';

		/** Nonce action for the step-1 install submit. */
		const NONCE_INSTALL = 'spbwc_wizard_install';

		/** Nonce action for the "skip / dismiss" link (marks the wizard done). */
		const NONCE_SKIP = 'spbwc_wizard_skip';

		/** GET flag that triggers the skip handler. */
		const ARG_SKIP = 'spbwc_wizard_skip';

		/** Nonce action + GET flag for "show different templates" (re-roll the offer). */
		const NONCE_REROLL = 'spbwc_wizard_reroll';
		const ARG_REROLL   = 'spbwc_wizard_reroll';

		/** How many templates to offer on step 1. */
		const OFFER_COUNT = 10;

		/** Min / max samples the merchant must choose. */
		const MIN_PICK = 1;
		const MAX_PICK = 3;

		public static function init() {
			// Register the hidden page once the Storelly top-level menu exists
			// (spbwc_pb_menu fires inside admin_menu). Priority 30 keeps it after
			// the core menu (default) and Template Library (20).
			add_action( 'spbwc_pb_menu', array( __CLASS__, 'register_page' ), 30 );
			add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'admin_head', array( __CLASS__, 'hide_menu_item' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_handle_skip' ) );
			add_action( 'admin_notices', array( __CLASS__, 'maybe_resume_notice' ) );
		}

		/**
		 * Register the wizard as a real submenu under the Storelly menu so WordPress
		 * grants access to admin.php?page=<SLUG>. We deliberately do NOT
		 * remove_submenu_page() it — doing so also breaks WP's access resolution for
		 * the page — and instead hide just the menu LINK via admin CSS
		 * (hide_menu_item), keeping the page reachable but uncluttered.
		 */
		public static function register_page() {
			if ( ! defined( 'SPBWC_PB_OVERVIEW_SLUG' ) ) {
				return;
			}
			add_submenu_page(
				SPBWC_PB_OVERVIEW_SLUG,
				esc_html__( 'Welcome to Storelly', 'storelly-product-builder-for-woocommerce' ),
				esc_html__( 'Welcome', 'storelly-product-builder-for-woocommerce' ),
				'manage_options',
				self::SLUG,
				array( __CLASS__, 'render' )
			);
		}

		/** Hide only the wizard's submenu link from the Storelly nav (page stays reachable). */
		public static function hide_menu_item() {
			echo '<style>#adminmenu a[href$="page=' . esc_attr( self::SLUG ) . '"]{display:none;}</style>';
		}

		/** True when the current request is the wizard screen. */
		protected static function is_wizard_screen() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			return self::SLUG === $page;
		}

		/** Add a body class on the wizard screen so the CSS can hide admin chrome. */
		public static function body_class( $classes ) {
			if ( self::is_wizard_screen() ) {
				$classes .= ' spbwc-wizard-screen';
			}
			return $classes;
		}

		/** Enqueue the wizard stylesheet + picker script only on the wizard screen. */
		public static function enqueue() {
			if ( ! self::is_wizard_screen() ) {
				return;
			}
			if ( ! wp_style_is( 'spbwc-tokens', 'registered' ) ) {
				wp_register_style( 'spbwc-tokens', SPBWC_PB_CSS_URL . '_tokens.css', array(), SPBWC_PB_VERSION );
			}
			wp_enqueue_style( 'dashicons' );
			$css = SPBWC_PB_PLUGIN_DIR . 'static/css/welcome-wizard.css';
			wp_enqueue_style(
				'spbwc-welcome-wizard',
				SPBWC_PB_CSS_URL . 'welcome-wizard.css',
				array( 'spbwc-tokens', 'dashicons' ),
				file_exists( $css ) ? filemtime( $css ) : SPBWC_PB_VERSION
			);
			$js = SPBWC_PB_PLUGIN_DIR . 'static/js/welcome-wizard.js';
			wp_enqueue_script(
				'spbwc-welcome-wizard',
				SPBWC_PB_ASSETS_URL . 'js/welcome-wizard.js',
				array( 'jquery' ),
				file_exists( $js ) ? filemtime( $js ) : SPBWC_PB_VERSION,
				true
			);
			wp_localize_script(
				'spbwc-welcome-wizard',
				'spbwcWizard',
				array(
					'min'      => self::MIN_PICK,
					'max'      => self::MAX_PICK,
					'pickHint' => sprintf(
						/* translators: 1: min count, 2: max count. */
						__( 'Choose %1$d–%2$d to install as samples', 'storelly-product-builder-for-woocommerce' ),
						self::MIN_PICK,
						self::MAX_PICK
					),
					'maxHint'  => sprintf(
						/* translators: %d: max count. */
						__( 'You can pick up to %d. Deselect one to choose another.', 'storelly-product-builder-for-woocommerce' ),
						self::MAX_PICK
					),
					'installing' => __( 'Installing…', 'storelly-product-builder-for-woocommerce' ),
				)
			);
		}

		/* ─────────────────────────────────────────────────────────────────
		 * Routing
		 * ───────────────────────────────────────────────────────────────── */

		/** Page callback. Handles the step-1 POST, then renders the right step. */
		public static function render() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'storelly-product-builder-for-woocommerce' ) );
			}

			// Step-1 install submit (POST). Redirects to step 2 on completion.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- routing flag only; the handler verifies the nonce before any state change.
			$wizard_action = isset( $_POST['spbwc_wizard_action'] ) ? sanitize_key( wp_unslash( $_POST['spbwc_wizard_action'] ) ) : '';
			if ( 'install' === $wizard_action ) {
				self::handle_install_submit();
				return; // handler exits.
			}

			// "Show different templates" — re-roll the offered slice and reload step 1.
			if ( isset( $_GET[ self::ARG_REROLL ] ) && check_admin_referer( self::NONCE_REROLL ) ) {
				delete_option( self::OPT_PICK );
				wp_safe_redirect( self::step_url( 1 ) );
				exit;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only step router; no state change.
			$step = isset( $_GET['step'] ) ? absint( wp_unslash( $_GET['step'] ) ) : 1;
			if ( $step < 1 || $step > 3 ) {
				$step = 1;
			}

			echo '<div class="spbwc-wiz">';
			self::render_topbar( $step );
			echo '<div class="spbwc-wiz__stage">';
			switch ( $step ) {
				case 2:
					self::render_step_setup();
					break;
				case 3:
					self::render_step_done();
					break;
				default:
					self::render_step_pick();
					break;
			}
			echo '</div></div>';
		}

		/* ─────────────────────────────────────────────────────────────────
		 * Step 1 — pick samples
		 * ───────────────────────────────────────────────────────────────── */

		private static function handle_install_submit() {
			check_admin_referer( self::NONCE_INSTALL );
			if ( ! current_user_can( 'spbwc_manage_product_builder' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'storelly-product-builder-for-woocommerce' ) );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via check_admin_referer.
			$raw    = isset( $_POST['samples'] ) ? (array) wp_unslash( $_POST['samples'] ) : array();
			$slugs  = array();
			$offered = array_flip( self::get_offered_slugs() );
			foreach ( $raw as $slug ) {
				$slug = sanitize_key( $slug );
				if ( '' !== $slug && isset( $offered[ $slug ] ) ) {
					$slugs[ $slug ] = true; // de-dupe.
				}
			}
			$slugs = array_keys( $slugs );

			// Enforce the 1–3 contract server-side (JS does the same client-side).
			if ( count( $slugs ) > self::MAX_PICK ) {
				$slugs = array_slice( $slugs, 0, self::MAX_PICK );
			}

			$created   = array();
			$slug_map  = array(); // option_id => template slug (for the live preview).
			if ( class_exists( 'SPBWC_Template_Applier' ) ) {
				$applier = SPBWC_Template_Applier::instance();
				foreach ( $slugs as $slug ) {
					$res = $applier->install_sample( $slug );
					if ( ! empty( $res['success'] ) && ! empty( $res['option_id'] ) ) {
						$oid             = (int) $res['option_id'];
						$created[]       = $oid;
						$slug_map[ $oid ] = $slug;
					}
				}
			}

			if ( ! empty( $created ) ) {
				$existing = (array) get_option( self::OPT_SAMPLES, array() );
				$merged   = array_values( array_unique( array_map( 'absint', array_merge( $existing, $created ) ) ) );
				update_option( self::OPT_SAMPLES, $merged, false );

				$existing_map = (array) get_option( self::OPT_SAMPLE_SLUGS, array() );
				update_option( self::OPT_SAMPLE_SLUGS, $existing_map + $slug_map, false );
			}

			wp_safe_redirect( self::step_url( 2 ) );
			exit;
		}

		private static function render_step_pick() {
			$slugs = self::get_offered_slugs();
			?>
			<div class="spbwc-wiz__head">
				<h1 class="spbwc-wiz__title"><?php esc_html_e( 'Let’s add a few sample pricing options', 'storelly-product-builder-for-woocommerce' ); ?></h1>
				<p class="spbwc-wiz__lede">
					<?php esc_html_e( 'Pick 1–3 ready-made templates to install as samples so you have something to play with. They’re installed as “(Sample)” options you can freely edit, assign to a product, delete, or add more to later.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<?php if ( ! empty( $slugs ) ) : ?>
					<a class="spbwc-wiz__reroll" href="<?php echo esc_url( self::reroll_url() ); ?>">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php esc_html_e( 'Show me different templates', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( empty( $slugs ) ) : ?>
				<div class="spbwc-wiz__empty">
					<p><?php esc_html_e( 'No bundled templates were found, so there’s nothing to install here.', 'storelly-product-builder-for-woocommerce' ); ?></p>
					<a class="spbwc-wiz-btn spbwc-wiz-btn--primary" href="<?php echo esc_url( self::step_url( 2 ) ); ?>">
						<?php esc_html_e( 'Continue', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</div>
			<?php else : ?>
				<form method="post" class="spbwc-wiz__form" id="spbwc-wiz-pick-form">
					<?php wp_nonce_field( self::NONCE_INSTALL ); ?>
					<input type="hidden" name="spbwc_wizard_action" value="install" />

					<div class="spbwc-wiz-grid" role="group" aria-label="<?php esc_attr_e( 'Sample templates', 'storelly-product-builder-for-woocommerce' ); ?>">
						<?php
						$catalog = SPBWC_Template_Catalog::instance();
						foreach ( $slugs as $slug ) :
							$meta = $catalog->get_template_meta( $slug );
							if ( ! is_array( $meta ) ) {
								continue;
							}
							$name  = $catalog->get_display_name( $meta );
							$cat   = $catalog->get_category_label( isset( $meta['category'] ) ? $meta['category'] : '' );
							$fields = isset( $meta['field_count'] ) ? (int) $meta['field_count'] : 0;
							$pmeth  = isset( $meta['pricing_method'] ) ? (string) $meta['pricing_method'] : '';
							$titles = self::template_field_titles( $slug );
							$thumb  = '';
							if ( ! empty( $meta['thumbnail'] ) ) {
								$thumb = SPBWC_PB_PLUGIN_URL . 'storage/print-templates/' . ltrim( (string) $meta['thumbnail'], '/' );
							}
							?>
							<label class="spbwc-wiz-card">
								<input type="checkbox" class="spbwc-wiz-card__cb" name="samples[]" value="<?php echo esc_attr( $slug ); ?>" />
								<span class="spbwc-wiz-card__tick" aria-hidden="true"></span>
								<span class="spbwc-wiz-card__thumb">
									<?php if ( $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" />
									<?php else : ?>
										<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
									<?php endif; ?>
								</span>
								<span class="spbwc-wiz-card__body">
									<span class="spbwc-wiz-card__name"><?php echo esc_html( $name ); ?></span>
									<span class="spbwc-wiz-card__meta">
										<span class="spbwc-wiz-card__cat"><?php echo esc_html( $cat ); ?></span>
										<?php if ( $fields > 0 ) : ?>
											<span class="spbwc-wiz-card__fields">
												<?php
												printf(
													/* translators: %d: number of option fields. */
													esc_html( _n( '%d option', '%d options', $fields, 'storelly-product-builder-for-woocommerce' ) ),
													(int) $fields
												);
												?>
											</span>
										<?php endif; ?>
									</span>
									<?php if ( ! empty( $titles ) ) : ?>
										<button type="button" class="spbwc-wiz-card__preview-btn" aria-expanded="false">
											<?php esc_html_e( 'Preview', 'storelly-product-builder-for-woocommerce' ); ?>
										</button>
										<span class="spbwc-wiz-card__details" hidden>
											<?php if ( '' !== $pmeth ) : ?>
												<span class="spbwc-wiz-card__pmeth">
													<?php
													printf(
														/* translators: %s: pricing method, e.g. "fixed". */
														esc_html__( 'Pricing: %s', 'storelly-product-builder-for-woocommerce' ),
														esc_html( ucfirst( str_replace( '_', ' ', $pmeth ) ) )
													);
													?>
												</span>
											<?php endif; ?>
											<ul class="spbwc-wiz-card__fieldlist">
												<?php foreach ( $titles as $ft ) : ?>
													<li><?php echo esc_html( $ft ); ?></li>
												<?php endforeach; ?>
											</ul>
										</span>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="spbwc-wiz__actions">
						<a class="spbwc-wiz-btn spbwc-wiz-btn--ghost" href="<?php echo esc_url( self::skip_url() ); ?>">
							<?php esc_html_e( 'Skip for now', 'storelly-product-builder-for-woocommerce' ); ?>
						</a>
						<span class="spbwc-wiz__counter" id="spbwc-wiz-counter" aria-live="polite"></span>
						<button type="submit" class="spbwc-wiz-btn spbwc-wiz-btn--primary" id="spbwc-wiz-next" disabled>
							<?php esc_html_e( 'Install samples & continue', 'storelly-product-builder-for-woocommerce' ); ?>
						</button>
					</div>
				</form>
			<?php endif; ?>
			<?php
		}

		/* ─────────────────────────────────────────────────────────────────
		 * Step 2 — background setup
		 * ───────────────────────────────────────────────────────────────── */

		private static function render_step_setup() {
			// Count what got installed in step 1.
			$samples = count( (array) get_option( self::OPT_SAMPLES, array() ) );

			// B2B sample: installs on admin_init; reflect whether it's done yet.
			$b2b_ready = false;
			if ( class_exists( 'SPBWC_B2B_Sample' ) ) {
				$b2b_ready = (int) get_option( SPBWC_B2B_Sample::OPTION_SEEDED ) >= 1;
			}

			// Kick the one-shot quote auto-sync (idempotent + de-duped). Capture the
			// pending count first so the message reflects what's being synced.
			$quote_pending = 0;
			$quote_queued  = 0;
			if ( class_exists( 'SPBWC_Quote_Import' ) ) {
				$quote_pending = (int) SPBWC_Quote_Import::pending_import_count();
				$quote_queued  = (int) SPBWC_Quote_Import::kick_auto_sync();
			}
			?>
			<div class="spbwc-wiz__head">
				<h1 class="spbwc-wiz__title"><?php esc_html_e( 'Setting things up for you', 'storelly-product-builder-for-woocommerce' ); ?></h1>
				<p class="spbwc-wiz__lede">
					<?php esc_html_e( 'No action needed — these are running in the background while you read. You can move on whenever you like.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
			</div>

			<ul class="spbwc-wiz-tasks">
				<li class="spbwc-wiz-task is-done">
					<span class="spbwc-wiz-task__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<span class="spbwc-wiz-task__text">
						<strong><?php esc_html_e( 'Sample pricing options', 'storelly-product-builder-for-woocommerce' ); ?></strong>
						<span>
							<?php
							if ( $samples > 0 ) {
								printf(
									/* translators: %d: number of sample options installed. */
									esc_html( _n( '%d sample option installed — find it under Pricing Options.', '%d sample options installed — find them under Pricing Options.', $samples, 'storelly-product-builder-for-woocommerce' ) ),
									(int) $samples
								);
							} else {
								esc_html_e( 'You can add samples any time from the Template Library.', 'storelly-product-builder-for-woocommerce' );
							}
							?>
						</span>
					</span>
				</li>

				<li class="spbwc-wiz-task <?php echo $b2b_ready ? 'is-done' : 'is-running'; ?>">
					<span class="spbwc-wiz-task__icon dashicons <?php echo $b2b_ready ? 'dashicons-yes-alt' : 'dashicons-update'; ?>" aria-hidden="true"></span>
					<span class="spbwc-wiz-task__text">
						<strong><?php esc_html_e( 'Sample B2B company & quotation', 'storelly-product-builder-for-woocommerce' ); ?></strong>
						<span>
							<?php
							echo $b2b_ready
								? esc_html__( 'Ready — a demo company account and a sample quotation are set up to explore.', 'storelly-product-builder-for-woocommerce' )
								: esc_html__( 'Installing in the background… this is quick.', 'storelly-product-builder-for-woocommerce' );
							?>
						</span>
					</span>
				</li>

				<li class="spbwc-wiz-task <?php echo $quote_pending > 0 ? 'is-running' : 'is-idle'; ?>">
					<span class="spbwc-wiz-task__icon dashicons <?php echo $quote_pending > 0 ? 'dashicons-update' : 'dashicons-minus'; ?>" aria-hidden="true"></span>
					<span class="spbwc-wiz-task__text">
						<strong><?php esc_html_e( 'Sync existing quotes', 'storelly-product-builder-for-woocommerce' ); ?></strong>
						<span>
							<?php
							if ( $quote_pending > 0 ) {
								printf(
									/* translators: %d: number of quotes being synced. */
									esc_html( _n( 'Importing %d quote from WooCommerce & other plugins, in the background.', 'Importing %d quotes from WooCommerce & other plugins, in the background.', $quote_pending, 'storelly-product-builder-for-woocommerce' ) ),
									(int) $quote_pending
								);
							} else {
								esc_html_e( 'No existing quotes found to import — you’re all set.', 'storelly-product-builder-for-woocommerce' );
							}
							unset( $quote_queued );
							?>
						</span>
					</span>
				</li>
			</ul>

			<div class="spbwc-wiz__actions spbwc-wiz__actions--split">
				<a class="spbwc-wiz-btn spbwc-wiz-btn--ghost" href="<?php echo esc_url( self::step_url( 1 ) ); ?>">
					<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Back', 'storelly-product-builder-for-woocommerce' ); ?>
				</a>
				<a class="spbwc-wiz-btn spbwc-wiz-btn--primary" href="<?php echo esc_url( self::step_url( 3 ) ); ?>">
					<?php esc_html_e( 'Continue', 'storelly-product-builder-for-woocommerce' ); ?>
				</a>
			</div>
			<?php
		}

		/* ─────────────────────────────────────────────────────────────────
		 * Step 3 — done
		 * ───────────────────────────────────────────────────────────────── */

		private static function render_step_done() {
			// Reaching the final step finishes onboarding's wizard gate.
			if ( class_exists( 'SPBWC_Onboarding' ) ) {
				SPBWC_Onboarding::mark_wizard_done();
			}

			$samples     = count( (array) get_option( self::OPT_SAMPLES, array() ) );
			$overview    = admin_url( 'admin.php?page=' . SPBWC_PB_OVERVIEW_SLUG );
			$setup_slug  = defined( 'SPBWC_PB_OPTIONS_SLUG' ) ? SPBWC_PB_OPTIONS_SLUG . '/global-import' : '';
			$setup_url   = $setup_slug ? admin_url( 'admin.php?page=' . $setup_slug ) : $overview;
			?>
			<div class="spbwc-wiz__done">
				<span class="spbwc-wiz__done-badge dashicons dashicons-yes" aria-hidden="true"></span>
				<h1 class="spbwc-wiz__title"><?php esc_html_e( 'You’re all set!', 'storelly-product-builder-for-woocommerce' ); ?></h1>
				<p class="spbwc-wiz__lede">
					<?php esc_html_e( 'Storelly is ready. Your samples and demo data are in place — explore them, or jump straight into building your first custom product.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>

				<ul class="spbwc-wiz-summary">
					<?php if ( $samples > 0 ) : ?>
						<li>
							<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
							<?php
							printf(
								/* translators: %d: number of sample options. */
								esc_html( _n( '%d sample pricing option ready to edit', '%d sample pricing options ready to edit', $samples, 'storelly-product-builder-for-woocommerce' ) ),
								(int) $samples
							);
							?>
						</li>
					<?php endif; ?>
					<li>
						<span class="dashicons dashicons-groups" aria-hidden="true"></span>
						<?php esc_html_e( 'A sample B2B company & quotation to explore', 'storelly-product-builder-for-woocommerce' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-art" aria-hidden="true"></span>
						<?php esc_html_e( 'A demo Visual Builder product (installs when you open Visual Builder)', 'storelly-product-builder-for-woocommerce' ); ?>
					</li>
				</ul>

				<?php self::render_sample_preview(); ?>

				<div class="spbwc-wiz__actions spbwc-wiz__actions--center">
					<a class="spbwc-wiz-btn spbwc-wiz-btn--primary" href="<?php echo esc_url( $overview ); ?>">
						<?php esc_html_e( 'Go to Overview', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
					<a class="spbwc-wiz-btn spbwc-wiz-btn--ghost" href="<?php echo esc_url( $setup_url ); ?>">
						<?php esc_html_e( 'Continue with Setup Wizard', 'storelly-product-builder-for-woocommerce' ); ?>
					</a>
				</div>
			</div>
			<?php
		}

		/**
		 * "See it in action" — for each sample just installed, a 1-click WYSIWYG
		 * preview rendered by the real storefront engine
		 * (SPBWC_Template_Preview_Render, by template slug) plus a link to manage /
		 * assign it in the Pricing Options list. Gives the aha-moment right after
		 * install without a product picker. Skipped if the preview engine is absent
		 * or no samples were installed.
		 */
		private static function render_sample_preview() {
			if ( ! class_exists( 'SPBWC_Template_Preview_Render' ) ) {
				return;
			}
			$map = (array) get_option( self::OPT_SAMPLE_SLUGS, array() );
			if ( empty( $map ) ) {
				return;
			}
			$preview_base = SPBWC_Template_Preview_Render::preview_url();
			$builder_url  = defined( 'SPBWC_PB_BUILDER_SLUG' )
				? admin_url( 'admin.php?page=' . SPBWC_PB_BUILDER_SLUG )
				: admin_url( 'admin.php' );
			$catalog = class_exists( 'SPBWC_Template_Catalog' ) ? SPBWC_Template_Catalog::instance() : null;
			?>
			<div class="spbwc-wiz-preview">
				<h2 class="spbwc-wiz-preview__title"><?php esc_html_e( 'See a sample in action', 'storelly-product-builder-for-woocommerce' ); ?></h2>
				<ul class="spbwc-wiz-preview__list">
					<?php
					foreach ( $map as $oid => $slug ) {
						$slug = sanitize_key( $slug );
						if ( '' === $slug ) {
							continue;
						}
						$label = $slug;
						if ( $catalog ) {
							$meta = $catalog->get_template_meta( $slug );
							if ( is_array( $meta ) ) {
								$label = $catalog->get_display_name( $meta );
							}
						}
						$preview_url = add_query_arg( array( 'slug' => $slug ), $preview_base );
						$edit_url    = add_query_arg(
							array(
								'page'   => defined( 'SPBWC_PB_BUILDER_SLUG' ) ? SPBWC_PB_BUILDER_SLUG : '',
								'action' => 'edit',
								'id'     => (int) $oid,
							),
							admin_url( 'admin.php' )
						);
						?>
						<li class="spbwc-wiz-preview__item">
							<span class="spbwc-wiz-preview__name"><?php echo esc_html( $label ); ?></span>
							<span class="spbwc-wiz-preview__actions">
								<a class="spbwc-wiz-btn spbwc-wiz-btn--ghost spbwc-wiz-btn--sm" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">
									<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
									<?php esc_html_e( 'Preview', 'storelly-product-builder-for-woocommerce' ); ?>
								</a>
								<a class="spbwc-wiz-preview__assign" href="<?php echo esc_url( $edit_url ); ?>">
									<?php esc_html_e( 'Edit / assign to a product', 'storelly-product-builder-for-woocommerce' ); ?>
								</a>
							</span>
						</li>
						<?php
					}
					?>
				</ul>
				<p class="spbwc-wiz-preview__hint">
					<?php
					echo esc_html__( 'Samples aren’t shown on your store until you assign them to a product.', 'storelly-product-builder-for-woocommerce' );
					echo ' ';
					printf(
						'<a href="%s">%s</a>',
						esc_url( $builder_url ),
						esc_html__( 'Manage all options →', 'storelly-product-builder-for-woocommerce' )
					);
					?>
				</p>
			</div>
			<?php
		}

		/* ─────────────────────────────────────────────────────────────────
		 * Chrome + helpers
		 * ───────────────────────────────────────────────────────────────── */

		/** Top bar: brand + 3-step progress indicator + skip-out link. */
		private static function render_topbar( $step ) {
			$steps = array(
				1 => __( 'Samples', 'storelly-product-builder-for-woocommerce' ),
				2 => __( 'Set up', 'storelly-product-builder-for-woocommerce' ),
				3 => __( 'Done', 'storelly-product-builder-for-woocommerce' ),
			);
			$logo = SPBWC_PB_ASSETS_URL . 'images/logo.png';
			?>
			<header class="spbwc-wiz__topbar">
				<div class="spbwc-wiz__brand">
					<img class="spbwc-wiz__logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php esc_attr_e( 'Storelly Product Builder', 'storelly-product-builder-for-woocommerce' ); ?>" />
					<span class="spbwc-wiz__brand-name"><?php esc_html_e( 'Storelly', 'storelly-product-builder-for-woocommerce' ); ?></span>
				</div>
				<ol class="spbwc-wiz__steps">
					<?php foreach ( $steps as $n => $label ) : ?>
						<li class="spbwc-wiz__step <?php echo $n === $step ? 'is-current' : ( $n < $step ? 'is-done' : '' ); ?>">
							<span class="spbwc-wiz__step-num"><?php echo (int) $n; ?></span>
							<span class="spbwc-wiz__step-label"><?php echo esc_html( $label ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
				<div class="spbwc-wiz__topbar-end">
					<?php if ( 3 !== (int) $step ) : ?>
						<a class="spbwc-wiz__skip" href="<?php echo esc_url( self::skip_url() ); ?>">
							<?php esc_html_e( 'Skip setup', 'storelly-product-builder-for-woocommerce' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</header>
			<?php
		}

		/** URL of a wizard step. */
		private static function step_url( $step ) {
			return add_query_arg(
				array(
					'page' => self::SLUG,
					'step' => absint( $step ),
				),
				admin_url( 'admin.php' )
			);
		}

		/** Nonce-protected URL that re-rolls the offered template slice. */
		private static function reroll_url() {
			return wp_nonce_url(
				add_query_arg(
					array(
						'page'           => self::SLUG,
						self::ARG_REROLL => 1,
					),
					admin_url( 'admin.php' )
				),
				self::NONCE_REROLL
			);
		}

		/**
		 * First few field titles of a bundled template, for the step-1 preview panel.
		 * Titles may be stored flat or as a { value, … } descriptor — collapse both.
		 *
		 * @param string $slug  Template slug.
		 * @param int    $limit Max titles to return.
		 * @return string[]
		 */
		protected static function template_field_titles( $slug, $limit = 8 ) {
			if ( ! class_exists( 'SPBWC_Template_Catalog' ) ) {
				return array();
			}
			$data = SPBWC_Template_Catalog::instance()->get_template_data( $slug );
			$out  = array();
			if ( is_array( $data ) && ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
				foreach ( $data['fields'] as $f ) {
					if ( ! is_array( $f ) ) {
						continue;
					}
					$t = isset( $f['general']['title'] ) ? $f['general']['title'] : '';
					if ( is_array( $t ) && isset( $t['value'] ) ) {
						$t = $t['value'];
					}
					$t = trim( (string) $t );
					if ( '' !== $t ) {
						$out[] = $t;
					}
					if ( count( $out ) >= $limit ) {
						break;
					}
				}
			}
			return $out;
		}

		/**
		 * "Skip setup" target — a nonce-protected link to the skip handler. It must
		 * NOT mark the wizard done here (this runs just to render the href): doing so
		 * would flag the wizard finished the moment a step is viewed, defeating the
		 * resume nudge. The actual mark happens in maybe_handle_skip() on click.
		 */
		private static function skip_url() {
			return wp_nonce_url(
				add_query_arg(
					array( self::ARG_SKIP => 1 ),
					admin_url( 'admin.php?page=' . SPBWC_PB_OVERVIEW_SLUG )
				),
				self::NONCE_SKIP
			);
		}

		/**
		 * Handle a skip/dismiss click from anywhere (the wizard chrome or the resume
		 * notice): mark the wizard done and bounce to a clean Overview URL. Nonce +
		 * capability gated. Runs on admin_init so it works on any admin screen.
		 */
		public static function maybe_handle_skip() {
			if ( empty( $_GET[ self::ARG_SKIP ] ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! check_admin_referer( self::NONCE_SKIP ) ) {
				return;
			}
			if ( class_exists( 'SPBWC_Onboarding' ) ) {
				SPBWC_Onboarding::mark_wizard_done();
			}
			wp_safe_redirect( admin_url( 'admin.php?page=' . SPBWC_PB_OVERVIEW_SLUG ) );
			exit;
		}

		/**
		 * Gentle "finish your setup" nudge on Storelly admin screens while the wizard
		 * is still unfinished — so a merchant who closed the tab mid-flow (the
		 * one-shot post-activation redirect already spent) can get back in. Shown
		 * only to managers, only with WooCommerce active, never on the wizard screen
		 * itself, and suppressed once the wizard is done or onboarding is complete.
		 */
		public static function maybe_resume_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}
			if ( ! class_exists( 'SPBWC_Onboarding' ) ) {
				return;
			}
			if ( SPBWC_Onboarding::is_wizard_done() || SPBWC_Onboarding::is_onboarding_complete() ) {
				return;
			}
			// Storelly admin screens only, and not the wizard page itself.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			if ( self::SLUG === $page || 0 !== strpos( $page, 'storelly-product-builder-for-woocommerce' ) ) {
				return;
			}

			$resume  = add_query_arg( array( 'page' => self::SLUG ), admin_url( 'admin.php' ) );
			$dismiss = self::skip_url();
			?>
			<div class="notice notice-info is-dismissible spbwc-wiz-resume">
				<p>
					<strong><?php esc_html_e( 'Storelly setup isn’t finished', 'storelly-product-builder-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Pick up the Welcome setup where you left off — it only takes a minute.', 'storelly-product-builder-for-woocommerce' ); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $resume ); ?>"><?php esc_html_e( 'Resume setup', 'storelly-product-builder-for-woocommerce' ); ?></a>
					<a class="button button-link" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Don’t show again', 'storelly-product-builder-for-woocommerce' ); ?></a>
				</p>
			</div>
			<?php
		}

		/**
		 * The random slice of catalog template slugs offered on step 1. Chosen once
		 * and persisted so reloads/back-navigation show the same set; stale slugs
		 * (catalog changed) are dropped and the set is topped up.
		 *
		 * @return string[]
		 */
		public static function get_offered_slugs() {
			if ( ! class_exists( 'SPBWC_Template_Catalog' ) ) {
				return array();
			}
			$catalog = SPBWC_Template_Catalog::instance();
			$all     = array();
			foreach ( $catalog->get_templates() as $tpl ) {
				if ( ! empty( $tpl['slug'] ) ) {
					$all[] = (string) $tpl['slug'];
				}
			}
			if ( empty( $all ) ) {
				return array();
			}

			$saved = (array) get_option( self::OPT_PICK, array() );
			$saved = array_values( array_intersect( array_map( 'strval', $saved ), $all ) );

			if ( count( $saved ) >= min( self::OFFER_COUNT, count( $all ) ) ) {
				return array_slice( $saved, 0, self::OFFER_COUNT );
			}

			// (Re)generate a random slice. wp_rand-based shuffle keeps it varied
			// without Math globals; result is persisted so it's stable thereafter.
			$pool = $all;
			self::shuffle( $pool );
			$pick = array_slice( $pool, 0, min( self::OFFER_COUNT, count( $pool ) ) );
			update_option( self::OPT_PICK, $pick, false );
			return $pick;
		}

		/** Fisher–Yates shuffle using wp_rand (avoids relying on shuffle()'s seeding). */
		private static function shuffle( array &$items ) {
			for ( $i = count( $items ) - 1; $i > 0; $i-- ) {
				$j = wp_rand( 0, $i );
				$tmp         = $items[ $i ];
				$items[ $i ] = $items[ $j ];
				$items[ $j ] = $tmp;
			}
		}
	}
}
