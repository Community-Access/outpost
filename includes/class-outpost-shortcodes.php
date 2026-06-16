<?php
/**
 * Registers shortcodes for displaying feeds and subscription forms.
 *
 * Usage:
 *   [outpost_feed tag="bitstips"]
 *   [outpost_feed tag="bitstips" limit="10"]
 *   [outpost_subscribe tag="bitstips"]
 */
class OUTPOST_Shortcodes {

	public static function init() {
		add_shortcode( 'outpost_feed',      [ __CLASS__, 'render_feed' ] );
		add_shortcode( 'outpost_subscribe', [ __CLASS__, 'render_subscribe_form' ] );

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_nopriv_outpost_subscribe', [ __CLASS__, 'handle_subscribe_ajax' ] );
		add_action( 'wp_ajax_outpost_subscribe',        [ __CLASS__, 'handle_subscribe_ajax' ] );
	}

	// -------------------------------------------------------------------------
	// Feed shortcode
	// -------------------------------------------------------------------------

	/**
	 * [outpost_feed tag="bitstips" limit="20"]
	 */
	public static function render_feed( $atts ) {
		$atts = shortcode_atts( [
			'tag'   => '',
			'limit' => 20,
		], $atts, 'outpost_feed' );

		$tag = OUTPOST_Hashtag_Manager::normalize_tag( $atts['tag'] );
		if ( ! $tag ) {
			return '<p class="outpost-error">' . esc_html__( 'No hashtag specified.', 'outpost' ) . '</p>';
		}

		// Find the hashtag row - use first match (any instance)
		global $wpdb;
		$hashtag_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}outpost_hashtags WHERE hashtag = %s AND active = 1 LIMIT 1",
			$tag
		) );

		if ( ! $hashtag_row ) {
			return '<p class="outpost-error">' . esc_html__( 'Hashtag not found or not active.', 'outpost' ) . '</p>';
		}

		$posts = OUTPOST_Feed_Fetcher::get_posts( $hashtag_row->id, (int) $atts['limit'] );
		$branding = OUTPOST_Settings::get_branding_html();

		// Unique per render so multiple feeds for the same hashtag on one page do
		// not produce duplicate IDs (WCAG 1.3.1 / 4.1.1).
		$heading_id = wp_unique_id( 'outpost-feed-heading-' );

		ob_start();
		?>
		<section class="outpost-feed" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<h2 class="outpost-feed__heading" id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( '#' . $hashtag_row->hashtag ); ?></h2>

			<?php if ( empty( $posts ) ) : ?>
				<p class="outpost-feed__empty"><?php esc_html_e( 'No posts found yet. Check back soon.', 'outpost' ); ?></p>
			<?php else : ?>
				<ul class="outpost-feed__list" role="list">
					<?php foreach ( $posts as $post ) :
						$text    = OUTPOST_Feed_Fetcher::post_to_plain_text( $post->content );
						$date    = OUTPOST_Feed_Fetcher::format_date( $post->created_at );
						$url     = esc_url( $post->url );
						$account = isset( $post->account->acct ) ? '@' . esc_html( $post->account->acct ) : '';
					?>
					<li class="outpost-feed__item">
						<article class="outpost-post">
							<?php if ( $account ) : ?>
							<h3 class="outpost-post__heading">
								<?php printf( esc_html__( 'Post by %s', 'outpost' ), $account ); ?>
							</h3>
							<?php endif; ?>

							<div class="outpost-post__content">
								<?php echo wp_kses_post( wpautop( esc_html( $text ) ) ); ?>
							</div>

							<footer class="outpost-post__footer">
								<?php if ( $date ) : ?>
								<time class="outpost-post__date" datetime="<?php echo esc_attr( $post->created_at ); ?>">
									<?php echo esc_html( $date ); ?>
								</time>
								<?php endif; ?>
								<a class="outpost-post__link" href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'View on Mastodon', 'outpost' ); ?>
									<span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'outpost' ); ?></span>
								</a>
							</footer>
						</article>
					</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $branding ) : ?>
			<div class="outpost-feed__branding">
				<?php echo $branding; ?>
			</div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Subscribe shortcode
	// -------------------------------------------------------------------------

	/**
	 * [outpost_subscribe tag="bitstips"]
	 */
	public static function render_subscribe_form( $atts ) {
		$atts = shortcode_atts( [ 'tag' => '' ], $atts, 'outpost_subscribe' );

		$tag = OUTPOST_Hashtag_Manager::normalize_tag( $atts['tag'] );
		if ( ! $tag ) {
			return '<p class="outpost-error">' . esc_html__( 'No hashtag specified.', 'outpost' ) . '</p>';
		}

		global $wpdb;
		$hashtag_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}outpost_hashtags WHERE hashtag = %s AND active = 1 LIMIT 1",
			$tag
		) );

		if ( ! $hashtag_row ) {
			return '<p class="outpost-error">' . esc_html__( 'This subscription is not available.', 'outpost' ) . '</p>';
		}

		// Unique per render so two subscribe forms for the same hashtag on one page
		// (e.g. two outpost/feed blocks with showSubscribe=true) do not produce
		// duplicate IDs or cross-wired label associations (WCAG 1.3.1 / 4.1.1).
		// Note: wp_unique_id() is per-request; a fragment cache that stamps one
		// rendered form into several spots could reintroduce duplicates.
		$form_id = wp_unique_id( 'outpost-subscribe-' );

		ob_start();
		?>
		<div class="outpost-subscribe" id="<?php echo esc_attr( $form_id ); ?>">
			<div class="outpost-subscribe__form-wrap" role="region" aria-label="<?php echo esc_attr( sprintf( __( 'Subscribe to #%s digest', 'outpost' ), $hashtag_row->hashtag ) ); ?>">

				<div class="outpost-subscribe__messages" aria-live="polite" aria-atomic="true" tabindex="-1"></div>

				<form class="outpost-subscribe__form" novalidate data-hashtag-id="<?php echo esc_attr( $hashtag_row->id ); ?>">
					<?php wp_nonce_field( 'outpost_subscribe_nonce', 'outpost_nonce' ); ?>

					<p class="outpost-field__legend">
						<?php esc_html_e( 'Fields marked with * are required.', 'outpost' ); ?>
					</p>

					<div class="outpost-field">
						<label for="<?php echo esc_attr( $form_id ); ?>-name" class="outpost-field__label">
							<?php esc_html_e( 'Your name', 'outpost' ); ?>
							<span class="outpost-field__optional"><?php esc_html_e( '(optional)', 'outpost' ); ?></span>
						</label>
						<input
							type="text"
							id="<?php echo esc_attr( $form_id ); ?>-name"
							name="outpost_name"
							class="outpost-field__input"
							autocomplete="name"
						/>
					</div>

					<div class="outpost-field">
						<label for="<?php echo esc_attr( $form_id ); ?>-email" class="outpost-field__label">
							<?php esc_html_e( 'Email address', 'outpost' ); ?>
							<span class="outpost-field__required" aria-hidden="true">*</span>
						</label>
						<input
							type="email"
							id="<?php echo esc_attr( $form_id ); ?>-email"
							name="outpost_email"
							class="outpost-field__input"
							required
							aria-required="true"
							autocomplete="email"
						/>
					</div>

					<?php
					// Honeypot: kept in the DOM for bots but visually hidden via CSS and
					// removed from the tab order. No aria-hidden, since a focusable field
					// inside an aria-hidden subtree fails WCAG 4.1.2. The "leave blank"
					// label covers the rare screen-reader user who navigates onto it.
					?>
					<div class="outpost-hp">
						<label for="<?php echo esc_attr( $form_id ); ?>-hp"><?php esc_html_e( 'Leave this field blank', 'outpost' ); ?></label>
						<input
							type="text"
							id="<?php echo esc_attr( $form_id ); ?>-hp"
							name="outpost_hp_check"
							tabindex="-1"
							autocomplete="off"
						/>
					</div>

					<button type="submit" class="outpost-btn outpost-btn--primary">
						<?php echo esc_html( sprintf( __( 'Subscribe to #%s', 'outpost' ), $hashtag_row->hashtag ) ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	public static function handle_subscribe_ajax() {
		check_ajax_referer( 'outpost_subscribe_nonce', 'outpost_nonce' );

		// Honeypot: a filled hidden field means an automated submission. Trim first
		// so an autofilled space is not mistaken for a real value. Return a generic
		// success so bots get no signal, but do not process the request.
		$hp = isset( $_POST['outpost_hp_check'] ) ? trim( wp_unslash( $_POST['outpost_hp_check'] ) ) : '';
		if ( '' !== $hp ) {
			wp_send_json_success( [ 'message' => __( 'Thanks! Please check your email.', 'outpost' ) ] );
		}

		// Rate limit per IP so the endpoint cannot be used to send confirmation
		// emails to arbitrary addresses in bulk.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( $ip ) {
			$rl_key = 'outpost_sub_rl_' . md5( $ip );
			$count  = (int) get_transient( $rl_key );
			if ( $count >= 5 ) {
				wp_send_json_error( [ 'message' => __( 'Too many attempts. Please wait a few minutes and try again.', 'outpost' ) ] );
			}
			set_transient( $rl_key, $count + 1, 10 * MINUTE_IN_SECONDS );
		}

		$hashtag_id = isset( $_POST['hashtag_id'] ) ? (int) $_POST['hashtag_id'] : 0;
		$email      = isset( $_POST['email'] )      ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name       = isset( $_POST['name'] )       ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		$result = OUTPOST_Subscriber::subscribe( $hashtag_id, $email, $name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$message = OUTPOST_Settings::is_double_optin()
			? __( 'Almost there! Check your email and click the confirmation link to complete your subscription.', 'outpost' )
			: __( 'You are subscribed. Your first digest will arrive tomorrow morning.', 'outpost' );

		wp_send_json_success( [ 'message' => $message ] );
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public static function enqueue_assets() {
		wp_enqueue_style(
			'outpost-public',
			OUTPOST_PLUGIN_URL . 'public/outpost-public.css',
			[],
			OUTPOST_VERSION
		);

		wp_enqueue_script(
			'outpost-public',
			OUTPOST_PLUGIN_URL . 'public/outpost-public.js',
			[ 'jquery' ],
			OUTPOST_VERSION,
			true
		);

		wp_localize_script( 'outpost-public', 'outpostData', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'outpost_subscribe_nonce' ),
		] );
	}
}
