<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;}
// Variables available: $subscriber, $hashtag_row, $confirm_url, $name
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'Confirm your subscription to #%s', 'outpost' ), $hashtag_row->hashtag ) ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #222; background: #f9f9f9; margin: 0; padding: 0;">
<div style="max-width: 600px; margin: 32px auto; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 32px;">

	<h1 style="font-size: 22px; margin-top: 0;"><?php echo esc_html( sprintf( __( 'Confirm your subscription to #%s', 'outpost' ), $hashtag_row->hashtag ) ); ?></h1>

	<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'outpost' ), $name ) ); ?></p>

	<p><?php echo esc_html( sprintf( __( 'You signed up to receive a daily email digest of posts tagged #%s on Mastodon.', 'outpost' ), $hashtag_row->hashtag ) ); ?></p>

	<p><?php esc_html_e( 'Click the button below to confirm your subscription.', 'outpost' ); ?></p>

	<p style="margin: 32px 0;">
		<a href="<?php echo esc_url( $confirm_url ); ?>" style="display: inline-block; background: #0073aa; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-size: 16px;">
			<?php esc_html_e( 'Confirm my subscription', 'outpost' ); ?>
		</a>
	</p>

	<p style="font-size: 14px; color: #666;">
		<?php esc_html_e( 'If you did not sign up for this, ignore this email. You will not receive any further emails.', 'outpost' ); ?>
	</p>

	<p style="font-size: 14px; color: #666;">
		<?php esc_html_e( 'Or copy and paste this link into your browser:', 'outpost' ); ?><br>
		<?php echo esc_html( $confirm_url ); ?>
	</p>

	<?php
	$branding = OUTPOST_Settings::get_branding_html( false );
	if ( $branding ) :
		?>
	<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;" />
	<div style="font-size: 14px; color: #666;">
		<?php echo $branding; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by get_branding_html(). ?>
	</div>
	<?php endif; ?>

</div>
</body>
</html>
