<?php
/**
 * Admin interface for OutPost.
 *
 * Provides:
 *   - Setup wizard (shown on first activation)
 *   - Hashtag management (add, edit, delete)
 *   - General settings (email, branding, digest schedule)
 *   - Subscriber overview per hashtag
 *   - Test digest send button
 */
class OUTPOST_Admin {

	public static function init() {
		add_action( 'admin_menu',         [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_init',         [ __CLASS__, 'maybe_redirect_to_wizard' ] );
		add_action( 'admin_init',         [ __CLASS__, 'handle_form_submissions' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_notices',      [ __CLASS__, 'show_setup_wizard_notice' ] );
	}

	/**
	 * Redirect to the setup wizard once, right after first activation.
	 *
	 * Skips bulk plugin activation so activating several plugins at once does
	 * not hijack the page to OutPost's wizard.
	 */
	public static function maybe_redirect_to_wizard() {
		if ( ! get_transient( 'outpost_redirect_to_wizard' ) ) {
			return;
		}

		// During bulk plugin activation, clear the flag and do not redirect, so a
		// later page load does not unexpectedly redirect either.
		if ( isset( $_GET['activate-multi'] ) ) {
			delete_transient( 'outpost_redirect_to_wizard' );
			return;
		}

		// Leave the flag in place for non-admin admin-area loads so the wizard
		// redirect is preserved until someone who can run it arrives.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Consume the flag and redirect exactly once.
		delete_transient( 'outpost_redirect_to_wizard' );
		wp_safe_redirect( admin_url( 'admin.php?page=outpost-setup' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public static function register_menus() {
		add_menu_page(
			__( 'Hashtag Digest', 'outpost' ),
			__( 'Hashtag Digest', 'outpost' ),
			'manage_options',
			'outpost-dashboard',
			[ __CLASS__, 'page_dashboard' ],
			'dashicons-rss',
			58
		);

		add_submenu_page(
			'outpost-dashboard',
			__( 'Dashboard', 'outpost' ),
			__( 'Dashboard', 'outpost' ),
			'manage_options',
			'outpost-dashboard',
			[ __CLASS__, 'page_dashboard' ]
		);

		add_submenu_page(
			'outpost-dashboard',
			__( 'Hashtags', 'outpost' ),
			__( 'Hashtags', 'outpost' ),
			'manage_options',
			'outpost-hashtags',
			[ __CLASS__, 'page_hashtags' ]
		);

		add_submenu_page(
			'outpost-dashboard',
			__( 'Subscribers', 'outpost' ),
			__( 'Subscribers', 'outpost' ),
			'manage_options',
			'outpost-subscribers',
			[ __CLASS__, 'page_subscribers' ]
		);

		add_submenu_page(
			'outpost-dashboard',
			__( 'Settings', 'outpost' ),
			__( 'Settings', 'outpost' ),
			'manage_options',
			'outpost-settings',
			[ __CLASS__, 'page_settings' ]
		);

		add_submenu_page(
			'outpost-dashboard',
			__( 'Setup Wizard', 'outpost' ),
			__( 'Setup Wizard', 'outpost' ),
			'manage_options',
			'outpost-setup',
			[ __CLASS__, 'page_setup_wizard' ]
		);
	}

	// -------------------------------------------------------------------------
	// Form submission handler
	// -------------------------------------------------------------------------

	public static function handle_form_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add hashtag
		if ( isset( $_POST['outpost_action'] ) && $_POST['outpost_action'] === 'add_hashtag' ) {
			check_admin_referer( 'outpost_add_hashtag' );
			$tag      = sanitize_text_field( $_POST['hashtag'] ?? '' );
			$instance = sanitize_text_field( $_POST['instance_url'] ?? '' );
			$label    = sanitize_text_field( $_POST['label'] ?? '' );
			$result   = OUTPOST_Hashtag_Manager::add( $tag, $instance, $label );
			$redirect = add_query_arg(
				[
					'page' => 'outpost-hashtags',
					'outpost_notice' => is_wp_error( $result ) ? 'add_error' : 'added',
				],
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		// Update hashtag
		if ( isset( $_POST['outpost_action'] ) && $_POST['outpost_action'] === 'update_hashtag' ) {
			check_admin_referer( 'outpost_update_hashtag' );
			$id     = (int) ( $_POST['hashtag_id'] ?? 0 );
			$result = OUTPOST_Hashtag_Manager::update( $id, [
				'hashtag'      => sanitize_text_field( $_POST['hashtag'] ?? '' ),
				'instance_url' => sanitize_text_field( $_POST['instance_url'] ?? '' ),
				'label'        => sanitize_text_field( $_POST['label'] ?? '' ),
				'active'       => ! empty( $_POST['active'] ),
			] );
			wp_safe_redirect( add_query_arg( [ 'page' => 'outpost-hashtags', 'outpost_notice' => 'updated' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		// Delete hashtag
		if ( isset( $_GET['outpost_action'] ) && $_GET['outpost_action'] === 'delete_hashtag' ) {
			check_admin_referer( 'outpost_delete_hashtag_' . absint( $_GET['id'] ) );
			OUTPOST_Hashtag_Manager::delete( absint( $_GET['id'] ) );
			wp_safe_redirect( add_query_arg( [ 'page' => 'outpost-hashtags', 'outpost_notice' => 'deleted' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		// Save settings
		if ( isset( $_POST['outpost_action'] ) && $_POST['outpost_action'] === 'save_settings' ) {
			check_admin_referer( 'outpost_save_settings' );
			OUTPOST_Settings::save( [
				'outpost_from_name'          => $_POST['from_name'] ?? '',
				'outpost_from_email'         => $_POST['from_email'] ?? '',
				'outpost_digest_send_hour'   => $_POST['send_hour'] ?? 8,
				'outpost_digest_send_minute' => $_POST['send_minute'] ?? 0,
				'outpost_posts_per_digest'   => $_POST['posts_per_digest'] ?? 10,
				'outpost_digest_batch_size'  => $_POST['digest_batch_size'] ?? 50,
				'outpost_cache_duration'     => ( (int) ( $_POST['cache_duration'] ?? 60 ) ) * 60,
				'outpost_double_optin'       => ! empty( $_POST['double_optin'] ),
				'outpost_branding_text'      => $_POST['branding_text'] ?? '',
				'outpost_branding_url'       => $_POST['branding_url'] ?? '',
				'outpost_manage_page_id'     => $_POST['manage_page_id'] ?? 0,
			] );
			// Reschedule only the digest cron to reflect the new send time. Do not
			// round-trip through activate(), which would also re-run table creation,
			// re-seed defaults, and re-flag the setup wizard.
			OUTPOST_Activator::reschedule_digest_cron();
			wp_safe_redirect( add_query_arg( [ 'page' => 'outpost-settings', 'outpost_notice' => 'saved' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		// Test digest send
		if ( isset( $_POST['outpost_action'] ) && $_POST['outpost_action'] === 'send_test_digest' ) {
			check_admin_referer( 'outpost_send_test_digest' );
			$hashtag_id = (int) ( $_POST['hashtag_id'] ?? 0 );
			$result     = OUTPOST_Email_Digest::send_digest_now( $hashtag_id );
			$notice     = is_wp_error( $result ) ? 'test_error' : 'test_sent';
			wp_safe_redirect( add_query_arg( [ 'page' => 'outpost-dashboard', 'outpost_notice' => $notice ], admin_url( 'admin.php' ) ) );
			exit;
		}

		// Setup wizard submit
		if ( isset( $_POST['outpost_action'] ) && $_POST['outpost_action'] === 'setup_wizard' ) {
			check_admin_referer( 'outpost_setup_wizard' );

			// Save settings
			OUTPOST_Settings::save( [
				'outpost_from_name'      => $_POST['from_name'] ?? get_bloginfo( 'name' ),
				'outpost_from_email'     => $_POST['from_email'] ?? get_option( 'admin_email' ),
				'outpost_branding_text'  => $_POST['branding_text'] ?? '',
				'outpost_branding_url'   => $_POST['branding_url'] ?? '',
			] );

			// Add hashtags
			$tags      = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $_POST['hashtags'] ?? '' ) ) ) );
			$instance  = sanitize_text_field( $_POST['instance_url'] ?? 'https://mastodon.social' );

			foreach ( $tags as $tag ) {
				OUTPOST_Hashtag_Manager::add( $tag, $instance );
			}

			delete_option( 'outpost_show_setup_wizard' );

			wp_safe_redirect( add_query_arg( [ 'page' => 'outpost-dashboard', 'outpost_notice' => 'setup_complete' ], admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	// -------------------------------------------------------------------------
	// Admin notice for setup wizard
	// -------------------------------------------------------------------------

	public static function show_setup_wizard_notice() {
		if ( ! get_option( 'outpost_show_setup_wizard' ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=outpost-setup' );
		echo '<div class="notice notice-info"><p>';
		printf(
			wp_kses(
				__( 'OutPost is installed. <a href="%s">Run the setup wizard</a> to add your first hashtag.', 'outpost' ),
				[ 'a' => [ 'href' => [] ] ]
			),
			esc_url( $url )
		);
		echo '</p></div>';
	}

	// -------------------------------------------------------------------------
	// Admin notice display helper
	// -------------------------------------------------------------------------

	private static function show_notice() {
		$notice = isset( $_GET['outpost_notice'] ) ? sanitize_key( $_GET['outpost_notice'] ) : '';
		if ( ! $notice ) {
			return;
		}

		$messages = [
			'added'          => [ 'success', __( 'Hashtag added.', 'outpost' ) ],
			'add_error'      => [ 'error',   __( 'Could not add hashtag. It may already exist.', 'outpost' ) ],
			'updated'        => [ 'success', __( 'Hashtag updated.', 'outpost' ) ],
			'deleted'        => [ 'success', __( 'Hashtag deleted.', 'outpost' ) ],
			'saved'          => [ 'success', __( 'Settings saved.', 'outpost' ) ],
			'test_sent'      => [ 'success', __( 'Test digest sent to confirmed subscribers.', 'outpost' ) ],
			'test_error'     => [ 'error',   __( 'Test digest failed. Check there are posts and confirmed subscribers.', 'outpost' ) ],
			'setup_complete' => [ 'success', __( 'Setup complete. Your hashtags are now active.', 'outpost' ) ],
		];

		if ( isset( $messages[ $notice ] ) ) {
			[$type, $msg] = $messages[ $notice ];
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	// -------------------------------------------------------------------------
	// Admin pages
	// -------------------------------------------------------------------------

	public static function page_dashboard() {
		self::show_notice();
		$hashtags = OUTPOST_Hashtag_Manager::get_all();
		include OUTPOST_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function page_hashtags() {
		self::show_notice();
		$edit_id     = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
		$edit_row    = $edit_id ? OUTPOST_Hashtag_Manager::get( $edit_id ) : null;
		$hashtags    = OUTPOST_Hashtag_Manager::get_all();
		include OUTPOST_PLUGIN_DIR . 'admin/views/hashtags.php';
	}

	public static function page_subscribers() {
		self::show_notice();
		$hashtag_id  = isset( $_GET['hashtag_id'] ) ? (int) $_GET['hashtag_id'] : 0;
		$hashtags    = OUTPOST_Hashtag_Manager::get_all();
		include OUTPOST_PLUGIN_DIR . 'admin/views/subscribers.php';
	}

	public static function page_settings() {
		self::show_notice();
		$pages = get_pages();
		include OUTPOST_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public static function page_setup_wizard() {
		include OUTPOST_PLUGIN_DIR . 'admin/views/setup-wizard.php';
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'outpost' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'outpost-admin',
			OUTPOST_PLUGIN_URL . 'admin/outpost-admin.css',
			[],
			OUTPOST_VERSION
		);
	}
}
