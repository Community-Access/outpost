<?php
/**
 * Front-end subscriber management page.
 *
 * Lets subscribers manage which hashtag digests they receive.
 * This page is created by the admin on a WordPress page using the shortcode
 * [outpost_manage_subscriptions], or you can assign any page in Settings.
 */
class OUTPOST_Public_Page {

	public static function init() {
		add_shortcode( 'outpost_manage_subscriptions', [ __CLASS__, 'render_manage_page' ] );

		// Registered here (not inside the shortcode render) so the handler is
		// available during admin-ajax.php requests, where the shortcode never runs.
		add_action( 'wp_ajax_nopriv_outpost_lookup', [ __CLASS__, 'handle_lookup_ajax' ] );
		add_action( 'wp_ajax_outpost_lookup',        [ __CLASS__, 'handle_lookup_ajax' ] );
	}

	/**
	 * Render the subscription management page.
	 *
	 * Handles:
	 *  - Status messages (confirmed, unsubscribed, errors)
	 *  - Subscribe to additional hashtags
	 *  - View current subscriptions (by email lookup)
	 */
	public static function render_manage_page( $atts ) {
		$status = isset( $_GET['outpost_status'] ) ? sanitize_key( $_GET['outpost_status'] ) : '';

		$messages = [
			'confirmed'    => [
				'type' => 'success',
				'text' => __( 'You are confirmed. Your first digest will arrive tomorrow morning.', 'outpost' ),
			],
			'unsubscribed' => [
				'type' => 'success',
				'text' => __( 'You have been unsubscribed. You will not receive any more emails for that hashtag.', 'outpost' ),
			],
			'confirm_error' => [
				'type' => 'error',
				'text' => __( 'That confirmation link is not valid or has already been used.', 'outpost' ),
			],
			'unsub_error'  => [
				'type' => 'error',
				'text' => __( 'That unsubscribe link is not valid.', 'outpost' ),
			],
		];

		$active_hashtags = OUTPOST_Hashtag_Manager::get_all( true );
		$branding        = OUTPOST_Settings::get_branding_html();

		ob_start();
		?>
		<div class="outpost-manage-page">

			<?php if ( $status && isset( $messages[ $status ] ) ) : ?>
			<div class="outpost-alert outpost-alert--<?php echo esc_attr( $messages[ $status ]['type'] ); ?>" role="alert">
				<?php echo esc_html( $messages[ $status ]['text'] ); ?>
			</div>
			<?php endif; ?>

			<section class="outpost-manage-section" aria-labelledby="outpost-subscribe-heading">
				<h2 id="outpost-subscribe-heading"><?php esc_html_e( 'Subscribe to a digest', 'outpost' ); ?></h2>
				<p><?php esc_html_e( 'Choose a hashtag below and enter your email to receive a daily digest.', 'outpost' ); ?></p>

				<?php if ( empty( $active_hashtags ) ) : ?>
					<p><?php esc_html_e( 'No digests are available yet. Check back soon.', 'outpost' ); ?></p>
				<?php else : ?>
					<?php foreach ( $active_hashtags as $row ) : ?>
					<div class="outpost-manage-hashtag">
						<h3>#<?php echo esc_html( $row->hashtag ); ?></h3>
						<?php echo do_shortcode( '[outpost_subscribe tag="' . esc_attr( $row->hashtag ) . '"]' ); ?>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<section class="outpost-manage-section" aria-labelledby="outpost-lookup-heading">
				<h2 id="outpost-lookup-heading"><?php esc_html_e( 'Manage existing subscriptions', 'outpost' ); ?></h2>
				<p>
					<?php esc_html_e( 'Enter your email address and we will email you a list of your subscriptions, each with an unsubscribe link. Every digest email also contains an unsubscribe link at the bottom.', 'outpost' ); ?>
				</p>

				<div class="outpost-lookup">
					<div class="outpost-lookup__messages" aria-live="polite" aria-atomic="true"></div>

					<form class="outpost-lookup__form" novalidate>
						<?php wp_nonce_field( 'outpost_lookup_nonce', 'outpost_lookup_nonce' ); ?>

						<div class="outpost-field">
							<label for="outpost-lookup-email" class="outpost-field__label">
								<?php esc_html_e( 'Your email address', 'outpost' ); ?>
								<span class="outpost-field__required" aria-hidden="true">*</span>
							</label>
							<input
								type="email"
								id="outpost-lookup-email"
								name="outpost_lookup_email"
								class="outpost-field__input"
								required
								aria-required="true"
								autocomplete="email"
							/>
						</div>

						<button type="submit" class="outpost-btn outpost-btn--secondary">
							<?php esc_html_e( 'Email me my management links', 'outpost' ); ?>
						</button>
					</form>
				</div>
			</section>

			<?php if ( $branding ) : ?>
			<div class="outpost-manage-page__branding">
				<?php echo $branding; ?>
			</div>
			<?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: look up subscriptions by email and email the management links to that
	 * address.
	 *
	 * The links (which contain secret unsubscribe tokens) are sent to the address
	 * on file rather than returned to the requester, and the response is identical
	 * whether or not the address has any subscriptions. This prevents a third party
	 * from harvesting another person's tokens or enumerating who is subscribed.
	 */
	public static function handle_lookup_ajax() {
		check_ajax_referer( 'outpost_lookup_nonce', 'outpost_nonce' );

		$email = sanitize_email( $_POST['email'] ?? '' );

		if ( ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'outpost' ) ] );
		}

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.*, h.hashtag, h.label
			 FROM {$wpdb->prefix}outpost_subscribers s
			 JOIN {$wpdb->prefix}outpost_hashtags h ON h.id = s.hashtag_id
			 WHERE s.email = %s AND s.status != 'unsubscribed'",
			$email
		) );

		if ( ! empty( $rows ) ) {
			self::send_manage_links_email( $email, $rows );
		}

		// Always return the same message, regardless of whether the address was
		// found, so the endpoint never confirms or denies a subscription and never
		// exposes a token to the requester.
		wp_send_json_success( [
			'message' => __( 'If that email address has any subscriptions, we have just emailed you your management links. Please check your inbox.', 'outpost' ),
		] );
	}

	/**
	 * Email a subscriber the list of their active subscriptions, each with its
	 * unsubscribe link.
	 *
	 * @param string $email Recipient address (already validated).
	 * @param array  $rows  Subscriber rows joined with hashtag data.
	 */
	private static function send_manage_links_email( $email, $rows ) {
		$subject       = __( 'Your subscriptions', 'outpost' );
		$branding_html = OUTPOST_Settings::get_branding_html( false );

		ob_start();
		include OUTPOST_PLUGIN_DIR . 'templates/email/manage-links.php';
		$body = ob_get_clean();

		OUTPOST_Subscriber::send_email( $email, $subject, $body );
	}
}
