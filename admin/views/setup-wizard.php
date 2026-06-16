<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div class="wrap outpost-admin">
	<h1><?php esc_html_e( 'OutPost: Setup', 'outpost' ); ?></h1>

	<p class="outpost-intro">
		<?php esc_html_e( 'Welcome. Complete this form to get started. You can change all of these settings later.', 'outpost' ); ?>
	</p>

	<form method="post">
		<?php wp_nonce_field( 'outpost_setup_wizard' ); ?>
		<input type="hidden" name="outpost_action" value="setup_wizard" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="outpost-instance-url"><?php esc_html_e( 'Mastodon instance URL', 'outpost' ); ?></label>
				</th>
				<td>
					<input
						type="url"
						id="outpost-instance-url"
						name="instance_url"
						value="https://mastodon.social"
						class="regular-text"
						required
					/>
					<p class="description">
						<?php esc_html_e( 'The Mastodon server your account is on. Example: https://dragonscave.space', 'outpost' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="outpost-hashtags"><?php esc_html_e( 'Hashtags to follow', 'outpost' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="outpost-hashtags"
						name="hashtags"
						value=""
						class="regular-text"
						placeholder="BitsTips, BlindTech"
						required
					/>
					<p class="description">
						<?php esc_html_e( 'Comma-separated list of hashtags (without the # symbol). Each one gets its own feed, widget, and subscriber list.', 'outpost' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="outpost-from-name"><?php esc_html_e( 'Email sender name', 'outpost' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="outpost-from-name"
						name="from_name"
						value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
						class="regular-text"
					/>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="outpost-from-email"><?php esc_html_e( 'Email sender address', 'outpost' ); ?></label>
				</th>
				<td>
					<input
						type="email"
						id="outpost-from-email"
						name="from_email"
						value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
						class="regular-text"
					/>
					<p class="description">
						<?php esc_html_e( 'This address must be authorized in your Postmark sender signature.', 'outpost' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="outpost-branding-text"><?php esc_html_e( 'Branding link text', 'outpost' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="outpost-branding-text"
						name="branding_text"
						value=""
						class="regular-text"
						placeholder="<?php esc_attr_e( 'Brought to you by BITS. Click here to learn more.', 'outpost' ); ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'Optional. Appears at the bottom of every feed and email. Leave blank to disable.', 'outpost' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="outpost-branding-url"><?php esc_html_e( 'Branding link URL', 'outpost' ); ?></label>
				</th>
				<td>
					<input
						type="url"
						id="outpost-branding-url"
						name="branding_url"
						value=""
						class="regular-text"
						placeholder="https://example.com/join"
					/>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Finish setup', 'outpost' ) ); ?>
	</form>
</div>
