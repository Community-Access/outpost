<?php if ( ! defined( 'ABSPATH' ) ) exit;
// Variables available: $subscriber, $hashtag_row, $posts, $unsubscribe_url, $name, $branding_html
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'Daily digest: #%s', 'outpost' ), $hashtag_row->hashtag ) ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #222; background: #f9f9f9; margin: 0; padding: 0;">
<div style="max-width: 600px; margin: 32px auto; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 32px;">

	<header style="border-bottom: 2px solid #eee; padding-bottom: 16px; margin-bottom: 24px;">
		<h1 style="font-size: 22px; margin: 0;">
			<?php echo esc_html( sprintf( __( 'Daily digest: #%s', 'outpost' ), $hashtag_row->hashtag ) ); ?>
		</h1>
		<p style="margin: 4px 0 0; font-size: 14px; color: #666;">
			<?php echo esc_html( date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) ) ); ?>
		</p>
	</header>

	<?php if ( $name ) : ?>
	<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'outpost' ), $name ) ); ?></p>
	<?php endif; ?>

	<p><?php echo esc_html( sprintf( __( "Here are today's posts tagged #%s.", 'outpost' ), $hashtag_row->hashtag ) ); ?></p>

	<?php foreach ( $posts as $post ) :
		$text    = OUTPOST_Feed_Fetcher::post_to_plain_text( $post->content );
		$date    = OUTPOST_Feed_Fetcher::format_date( $post->created_at );
		$url     = $post->url;
		$account = isset( $post->account->acct ) ? '@' . $post->account->acct : '';
	?>
	<article style="border: 1px solid #eee; border-radius: 4px; padding: 16px; margin-bottom: 16px;">

		<?php if ( $account ) : ?>
		<p style="margin: 0 0 8px; font-size: 14px; color: #555;">
			<strong><?php echo esc_html( $account ); ?></strong>
		</p>
		<?php endif; ?>

		<div style="margin: 0 0 12px;">
			<?php echo nl2br( esc_html( $text ) ); ?>
		</div>

		<p style="margin: 0; font-size: 14px; color: #666;">
			<?php if ( $date ) : ?>
			<span><?php echo esc_html( $date ); ?></span>
			&nbsp;&middot;&nbsp;
			<?php endif; ?>
			<a href="<?php echo esc_url( $url ); ?>" style="color: #0073aa;">
				<?php esc_html_e( 'View on Mastodon', 'outpost' ); ?>
			</a>
		</p>
	</article>
	<?php endforeach; ?>

	<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;" />

	<footer style="font-size: 13px; color: #888;">
		<?php if ( $branding_html ) : ?>
		<p><?php echo $branding_html; ?></p>
		<?php endif; ?>
		<p>
			<?php esc_html_e( 'You are receiving this because you subscribed to this digest.', 'outpost' ); ?>
			<a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="color: #888;">
				<?php esc_html_e( 'Unsubscribe', 'outpost' ); ?>
			</a>
		</p>
	</footer>

</div>
</body>
</html>
