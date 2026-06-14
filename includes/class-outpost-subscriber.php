<?php
/**
 * Manages subscribers for each hashtag digest.
 */
class OUTPOST_Subscriber {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'handle_token_actions' ] );
	}

	// -------------------------------------------------------------------------
	// Subscription flow
	// -------------------------------------------------------------------------

	/**
	 * Add a new subscriber (pending). Sends confirmation email if double opt-in is on.
	 *
	 * @param int    $hashtag_id
	 * @param string $email
	 * @param string $name
	 * @return true|WP_Error
	 */
	public static function subscribe( $hashtag_id, $email, $name = '' ) {
		global $wpdb;

		$email      = sanitize_email( $email );
		$hashtag_id = (int) $hashtag_id;

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'outpost' ) );
		}

		$hashtag_row = OUTPOST_Hashtag_Manager::get( $hashtag_id );
		if ( ! $hashtag_row || ! $hashtag_row->active ) {
			return new WP_Error( 'invalid_hashtag', __( 'This subscription is not available.', 'outpost' ) );
		}

		// Check existing subscription
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}outpost_subscribers WHERE email = %s AND hashtag_id = %d",
			$email, $hashtag_id
		) );

		if ( $existing ) {
			if ( $existing->status === 'confirmed' ) {
				return new WP_Error( 'already_subscribed', __( 'This email is already subscribed.', 'outpost' ) );
			}
			if ( $existing->status === 'unsubscribed' ) {
				// Re-subscribe
				$wpdb->update(
					$wpdb->prefix . 'outpost_subscribers',
					[ 'status' => 'pending', 'token' => self::generate_token() ],
					[ 'id' => $existing->id ]
				);
				$existing = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}outpost_subscribers WHERE id = %d",
					$existing->id
				) );
			}
			// Resend confirmation
			self::send_confirmation_email( $existing, $hashtag_row );
			return true;
		}

		$token  = self::generate_token();
		$status = OUTPOST_Settings::is_double_optin() ? 'pending' : 'confirmed';

		$result = $wpdb->insert(
			$wpdb->prefix . 'outpost_subscribers',
			[
				'hashtag_id'   => $hashtag_id,
				'email'        => $email,
				'name'         => sanitize_text_field( $name ),
				'status'       => $status,
				'token'        => $token,
				'confirmed_at' => ( $status === 'confirmed' ) ? current_time( 'mysql' ) : null,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( $result === false ) {
			return new WP_Error( 'db_error', __( 'Could not save subscription. Please try again.', 'outpost' ) );
		}

		$subscriber = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}outpost_subscribers WHERE id = %d",
			$wpdb->insert_id
		) );

		if ( OUTPOST_Settings::is_double_optin() ) {
			self::send_confirmation_email( $subscriber, $hashtag_row );
		} else {
			self::send_welcome_email( $subscriber, $hashtag_row );
		}

		return true;
	}

	/**
	 * Confirm a subscription via token.
	 *
	 * @param string $token
	 * @return true|WP_Error
	 */
	public static function confirm( $token ) {
		global $wpdb;

		$subscriber = self::get_by_token( $token );
		if ( ! $subscriber ) {
			return new WP_Error( 'invalid_token', __( 'This confirmation link is invalid or has already been used.', 'outpost' ) );
		}

		if ( $subscriber->status === 'confirmed' ) {
			return true; // Idempotent
		}

		$wpdb->update(
			$wpdb->prefix . 'outpost_subscribers',
			[ 'status' => 'confirmed', 'confirmed_at' => current_time( 'mysql' ) ],
			[ 'id' => $subscriber->id ]
		);

		$hashtag_row = OUTPOST_Hashtag_Manager::get( $subscriber->hashtag_id );
		self::send_welcome_email( $subscriber, $hashtag_row );

		return true;
	}

	/**
	 * Unsubscribe via token.
	 *
	 * @param string $token
	 * @return true|WP_Error
	 */
	public static function unsubscribe( $token ) {
		global $wpdb;

		$subscriber = self::get_by_token( $token );
		if ( ! $subscriber ) {
			return new WP_Error( 'invalid_token', __( 'This unsubscribe link is invalid.', 'outpost' ) );
		}

		$wpdb->update(
			$wpdb->prefix . 'outpost_subscribers',
			[ 'status' => 'unsubscribed' ],
			[ 'id' => $subscriber->id ]
		);

		return true;
	}

	// -------------------------------------------------------------------------
	// Handle URL-based token actions
	// -------------------------------------------------------------------------

	/**
	 * Listens for ?outpost_action= in the URL and processes confirm/unsubscribe.
	 * Fires on 'init' so it can redirect before any output.
	 */
	public static function handle_token_actions() {
		$action = isset( $_GET['outpost_action'] ) ? sanitize_key( $_GET['outpost_action'] ) : '';
		$token  = isset( $_GET['outpost_token'] )  ? sanitize_text_field( $_GET['outpost_token'] ) : '';

		if ( ! $action || ! $token ) {
			return;
		}

		$manage_page = get_permalink( OUTPOST_Settings::get_manage_page_id() );
		if ( ! $manage_page ) {
			$manage_page = home_url();
		}

		if ( $action === 'confirm' ) {
			$result = self::confirm( $token );
			$status = is_wp_error( $result ) ? 'confirm_error' : 'confirmed';
		} elseif ( $action === 'unsubscribe' ) {
			$result = self::unsubscribe( $token );
			$status = is_wp_error( $result ) ? 'unsub_error' : 'unsubscribed';
		} else {
			return;
		}

		wp_safe_redirect( add_query_arg( 'outpost_status', $status, $manage_page ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Query helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all confirmed subscribers for a hashtag.
	 *
	 * @param int $hashtag_id
	 * @return array
	 */
	public static function get_confirmed( $hashtag_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}outpost_subscribers WHERE hashtag_id = %d AND status = 'confirmed'",
			(int) $hashtag_id
		) );
	}

	/**
	 * Get a subscriber by token.
	 *
	 * @param string $token
	 * @return object|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}outpost_subscribers WHERE token = %s",
			sanitize_text_field( $token )
		) );
	}

	/**
	 * Count subscribers per hashtag.
	 *
	 * @param int    $hashtag_id
	 * @param string $status  'confirmed', 'pending', 'unsubscribed', or 'all'
	 * @return int
	 */
	public static function count( $hashtag_id, $status = 'confirmed' ) {
		global $wpdb;
		if ( $status === 'all' ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}outpost_subscribers WHERE hashtag_id = %d",
				(int) $hashtag_id
			) );
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}outpost_subscribers WHERE hashtag_id = %d AND status = %s",
			(int) $hashtag_id, $status
		) );
	}

	// -------------------------------------------------------------------------
	// Email helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the confirmation URL for a subscriber.
	 *
	 * @param object $subscriber
	 * @return string
	 */
	public static function confirmation_url( $subscriber ) {
		return add_query_arg( [
			'outpost_action' => 'confirm',
			'outpost_token'  => $subscriber->token,
		], home_url() );
	}

	/**
	 * Build the unsubscribe URL for a subscriber.
	 *
	 * @param object $subscriber
	 * @return string
	 */
	public static function unsubscribe_url( $subscriber ) {
		return add_query_arg( [
			'outpost_action' => 'unsubscribe',
			'outpost_token'  => $subscriber->token,
		], home_url() );
	}

	/**
	 * Send a double opt-in confirmation email.
	 */
	private static function send_confirmation_email( $subscriber, $hashtag_row ) {
		$subject = sprintf(
			__( 'Confirm your subscription to #%s', 'outpost' ),
			$hashtag_row->hashtag
		);

		$confirm_url = self::confirmation_url( $subscriber );
		$name        = $subscriber->name ? $subscriber->name : __( 'there', 'outpost' );

		ob_start();
		include OUTPOST_PLUGIN_DIR . 'templates/email/confirmation.php';
		$body = ob_get_clean();

		self::send_email( $subscriber->email, $subject, $body );
	}

	/**
	 * Send a welcome email after confirmation.
	 */
	private static function send_welcome_email( $subscriber, $hashtag_row ) {
		$subject = sprintf(
			__( 'Welcome! You are subscribed to #%s', 'outpost' ),
			$hashtag_row->hashtag
		);

		$unsubscribe_url = self::unsubscribe_url( $subscriber );
		$name            = $subscriber->name ? $subscriber->name : __( 'there', 'outpost' );

		ob_start();
		include OUTPOST_PLUGIN_DIR . 'templates/email/welcome.php';
		$body = ob_get_clean();

		self::send_email( $subscriber->email, $subject, $body );
	}

	/**
	 * Send an email using wp_mail() (routes through Postmark or configured mailer).
	 */
	public static function send_email( $to, $subject, $html_body ) {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . OUTPOST_Settings::get_from_name() . ' <' . OUTPOST_Settings::get_from_email() . '>',
		];

		wp_mail( $to, $subject, $html_body, $headers );
	}

	// -------------------------------------------------------------------------
	// Token generation
	// -------------------------------------------------------------------------

	private static function generate_token() {
		return bin2hex( random_bytes( 32 ) );
	}
}
