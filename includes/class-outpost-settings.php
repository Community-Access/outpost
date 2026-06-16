<?php
/**
 * Centralized settings access for OutPost.
 */
class OUTPOST_Settings {

	public static function init() {
		// Nothing to hook here yet; class is used statically throughout the plugin.
	}

	// -------------------------------------------------------------------------
	// General settings
	// -------------------------------------------------------------------------

	public static function get_from_name() {
		return get_option( 'outpost_from_name', get_bloginfo( 'name' ) );
	}

	public static function get_from_email() {
		return get_option( 'outpost_from_email', get_option( 'admin_email' ) );
	}

	public static function get_digest_send_hour() {
		return (int) get_option( 'outpost_digest_send_hour', 8 );
	}

	public static function get_digest_send_minute() {
		return (int) get_option( 'outpost_digest_send_minute', 0 );
	}

	public static function get_posts_per_digest() {
		return (int) get_option( 'outpost_posts_per_digest', 10 );
	}

	public static function get_digest_batch_size() {
		return (int) get_option( 'outpost_digest_batch_size', 50 );
	}

	public static function get_cache_duration() {
		return (int) get_option( 'outpost_cache_duration', 3600 );
	}

	public static function is_double_optin() {
		return (bool) get_option( 'outpost_double_optin', true );
	}

	public static function get_manage_page_id() {
		return (int) get_option( 'outpost_manage_page_id', 0 );
	}

	// -------------------------------------------------------------------------
	// Branding settings
	// -------------------------------------------------------------------------

	public static function get_branding_text() {
		return get_option( 'outpost_branding_text', '' );
	}

	public static function get_branding_url() {
		return get_option( 'outpost_branding_url', '' );
	}

	public static function get_brand_account() {
		return get_option( 'outpost_brand_account', '' );
	}

	/**
	 * Returns rendered branding HTML, or empty string if not configured.
	 * Used in both feed display and email templates.
	 *
	 * @param bool $is_email  True to use plain-text-safe output.
	 */
	public static function get_branding_html( $is_email = false ) {
		$text = self::get_branding_text();
		$url  = self::get_branding_url();

		if ( empty( $text ) ) {
			return '';
		}

		if ( $is_email ) {
			if ( ! empty( $url ) ) {
				return esc_html( $text ) . ' ' . esc_url( $url );
			}
			return esc_html( $text );
		}

		if ( ! empty( $url ) ) {
			return '<p class="outpost-branding"><a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></p>';
		}
		return '<p class="outpost-branding">' . esc_html( $text ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Sanitize and save settings from the admin form.
	 */
	public static function save( array $data ) {
		$allowed_keys = [
			'outpost_from_name',
			'outpost_from_email',
			'outpost_digest_send_hour',
			'outpost_digest_send_minute',
			'outpost_posts_per_digest',
			'outpost_digest_batch_size',
			'outpost_cache_duration',
			'outpost_double_optin',
			'outpost_branding_text',
			'outpost_branding_url',
			'outpost_manage_page_id',
			'outpost_brand_account',
		];

		foreach ( $allowed_keys as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			switch ( $key ) {
				case 'outpost_from_email':
					update_option( $key, sanitize_email( $data[ $key ] ) );
					break;
				case 'outpost_branding_url':
					update_option( $key, esc_url_raw( $data[ $key ] ) );
					break;
				case 'outpost_brand_account':
					update_option( $key, OUTPOST_Hashtag_Manager::normalize_handle( $data[ $key ] ) );
					break;
				case 'outpost_digest_send_hour':
				case 'outpost_digest_send_minute':
				case 'outpost_posts_per_digest':
				case 'outpost_digest_batch_size':
				case 'outpost_cache_duration':
				case 'outpost_manage_page_id':
					update_option( $key, absint( $data[ $key ] ) );
					break;
				case 'outpost_double_optin':
					update_option( $key, (bool) $data[ $key ] );
					break;
				default:
					update_option( $key, sanitize_text_field( $data[ $key ] ) );
					break;
			}
		}
	}
}
