<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;}
// Variables available: $rows (subscriber rows), $branding_html.
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php esc_html_e( 'Your subscriptions', 'outpost' ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #222; background: #f9f9f9; margin: 0; padding: 0;">
<div style="max-width: 600px; margin: 32px auto; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 32px;">

	<h1 style="font-size: 22px; margin-top: 0;"><?php esc_html_e( 'Your subscriptions', 'outpost' ); ?></h1>

	<p><?php esc_html_e( 'You asked to manage the digests you receive at this address. Here are your current subscriptions.', 'outpost' ); ?></p>

	<ul style="padding-left: 20px;">
		<?php
		foreach ( $rows as $row ) :
			$status_label = $row->status === 'pending'
				? __( 'Pending confirmation', 'outpost' )
				: __( 'Active', 'outpost' );
			$unsub_url    = OUTPOST_Subscriber::unsubscribe_url( $row );
			?>
		<li style="margin-bottom: 8px;">
			<strong>#<?php echo esc_html( $row->hashtag ); ?></strong>
			&mdash; <?php echo esc_html( $status_label ); ?>
			&middot;
			<a href="<?php echo esc_url( $unsub_url ); ?>" style="color: #0073aa;">
				<?php esc_html_e( 'Unsubscribe', 'outpost' ); ?>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>

	<p style="font-size: 14px; color: #595959;">
		<?php esc_html_e( 'If you did not request this, you can ignore this email. No changes have been made.', 'outpost' ); ?>
	</p>

	<?php if ( $branding_html ) : ?>
	<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;" />
	<div style="font-size: 14px; color: #595959;">
		<?php echo $branding_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by get_branding_html(). ?>
	</div>
	<?php endif; ?>

</div>
</body>
</html>
