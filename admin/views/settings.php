<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap outpost-admin">
	<h1><?php esc_html_e( 'Hashtag Digest Settings', 'outpost' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( 'outpost_save_settings' ); ?>
		<input type="hidden" name="outpost_action" value="save_settings" />

		<h2><?php esc_html_e( 'Email sender', 'outpost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="s-from-name"><?php esc_html_e( 'From name', 'outpost' ); ?></label></th>
				<td>
					<input type="text" id="s-from-name" name="from_name" value="<?php echo esc_attr( OUTPOST_Settings::get_from_name() ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s-from-email"><?php esc_html_e( 'From email', 'outpost' ); ?></label></th>
				<td>
					<input type="email" id="s-from-email" name="from_email" value="<?php echo esc_attr( OUTPOST_Settings::get_from_email() ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Must be authorized in your Postmark sender signature.', 'outpost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Digest schedule', 'outpost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="s-send-hour"><?php esc_html_e( 'Send hour (0-23)', 'outpost' ); ?></label></th>
				<td>
					<input type="number" id="s-send-hour" name="send_hour" value="<?php echo esc_attr( OUTPOST_Settings::get_digest_send_hour() ); ?>" min="0" max="23" class="small-text" />
					<p class="description"><?php esc_html_e( 'Uses your WordPress timezone. Changing this requires deactivating and reactivating the plugin.', 'outpost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s-posts-per-digest"><?php esc_html_e( 'Max posts per digest', 'outpost' ); ?></label></th>
				<td>
					<input type="number" id="s-posts-per-digest" name="posts_per_digest" value="<?php echo esc_attr( OUTPOST_Settings::get_posts_per_digest() ); ?>" min="1" max="40" class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s-batch-size"><?php esc_html_e( 'Subscribers per batch', 'outpost' ); ?></label></th>
				<td>
					<input type="number" id="s-batch-size" name="digest_batch_size" value="<?php echo esc_attr( OUTPOST_Settings::get_digest_batch_size() ); ?>" min="10" max="500" class="small-text" />
					<p class="description"><?php esc_html_e( 'How many subscribers receive emails per batch. Lower this if you hit timeout errors. Default is 50.', 'outpost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s-cache-duration"><?php esc_html_e( 'Feed cache duration (minutes)', 'outpost' ); ?></label></th>
				<td>
					<input type="number" id="s-cache-duration" name="cache_duration" value="<?php echo esc_attr( OUTPOST_Settings::get_cache_duration() / 60 ); ?>" min="5" max="1440" class="small-text" />
					<p class="description"><?php esc_html_e( 'How long to cache Mastodon API responses. 60 minutes is a good default.', 'outpost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Subscriptions', 'outpost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Double opt-in', 'outpost' ); ?></th>
				<td>
					<input type="checkbox" id="s-double-optin" name="double_optin" value="1" <?php checked( OUTPOST_Settings::is_double_optin() ); ?> />
					<label for="s-double-optin"><?php esc_html_e( 'Require email confirmation before adding subscribers', 'outpost' ); ?></label>
					<p class="description"><?php esc_html_e( 'Recommended. Keeps your list clean and reduces spam complaints.', 'outpost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s-manage-page"><?php esc_html_e( 'Subscription management page', 'outpost' ); ?></label></th>
				<td>
					<select id="s-manage-page" name="manage_page_id" class="regular-text">
						<option value="0"><?php esc_html_e( '-- Select a page --', 'outpost' ); ?></option>
						<?php foreach ( $pages as $page_item ) : ?>
						<option value="<?php echo esc_attr( $page_item->ID ); ?>" <?php selected( OUTPOST_Settings::get_manage_page_id(), $page_item->ID ); ?>>
							<?php echo esc_html( $page_item->post_title ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						printf(
							wp_kses(
								__( 'Create a page with the shortcode <code>[outpost_manage_subscriptions]</code> and select it here. Confirmation and unsubscribe links will redirect here.', 'outpost' ),
								array( 'code' => array() )
							)
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Brand account', 'outpost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="s-brand-account"><?php esc_html_e( 'Mastodon account', 'outpost' ); ?></label></th>
				<td>
					<input type="text" id="s-brand-account" name="brand_account" value="<?php echo esc_attr( OUTPOST_Settings::get_brand_account() ); ?>" class="regular-text" placeholder="user@instance.social" />
					<p class="description"><?php esc_html_e( 'Optional. Used by the account feed. Format: user@instance.social.', 'outpost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Branding', 'outpost' ); ?></h2>
		<p><?php esc_html_e( 'Add a line at the bottom of every feed display and digest email to credit your organization.', 'outpost' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="s-branding-text"><?php esc_html_e( 'Branding text', 'outpost' ); ?></label></th>
				<td>
					<input type="text" id="s-branding-text" name="branding_text" value="<?php echo esc_attr( OUTPOST_Settings::get_branding_text() ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Brought to you by BITS. Click here to join.', 'outpost' ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave blank to disable branding.', 'outpost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s-branding-url"><?php esc_html_e( 'Branding URL', 'outpost' ); ?></label></th>
				<td>
					<input type="url" id="s-branding-url" name="branding_url" value="<?php echo esc_attr( OUTPOST_Settings::get_branding_url() ); ?>" class="regular-text" placeholder="https://example.com/join" />
					<p class="description"><?php esc_html_e( 'The text above becomes a link to this URL. Leave blank to show plain text with no link.', 'outpost' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'outpost' ) ); ?>
	</form>
</div>
