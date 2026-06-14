<?php

namespace Outpost\Tests\Unit;

use Brain\Monkey\Functions;
use Outpost\Tests\TestCase;
use OUTPOST_Blocks;

class OutpostBlocksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'esc_attr' )->alias( function ( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES );
		} );

		Functions\when( 'absint' )->alias( function ( $value ) {
			return abs( (int) $value );
		} );

		Functions\when( '__' )->returnArg( 1 );
	}

	public function test_returns_empty_string_when_tag_is_missing(): void {
		Functions\expect( 'do_shortcode' )->never();

		$this->assertSame( '', OUTPOST_Blocks::render_feed_block( [] ) );
	}

	public function test_returns_empty_string_when_tag_is_blank(): void {
		Functions\expect( 'do_shortcode' )->never();

		$this->assertSame( '', OUTPOST_Blocks::render_feed_block( [ 'tag' => '   ' ] ) );
	}

	public function test_builds_feed_shortcode_with_tag_and_default_limit(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_feed tag="bitstips" limit="20"]' )
			->andReturn( '<section>feed</section>' );

		$result = OUTPOST_Blocks::render_feed_block( [ 'tag' => 'bitstips' ] );

		$this->assertSame( '<section>feed</section>', $result );
	}

	public function test_builds_feed_shortcode_with_custom_limit(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_feed tag="bitstips" limit="5"]' )
			->andReturn( '<section>feed</section>' );

		$result = OUTPOST_Blocks::render_feed_block( [ 'tag' => 'bitstips', 'limit' => 5 ] );

		$this->assertSame( '<section>feed</section>', $result );
	}

	public function test_escapes_tag_for_shortcode_attribute(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_feed tag="bits&quot;tips" limit="20"]' )
			->andReturn( '<section>feed</section>' );

		$result = OUTPOST_Blocks::render_feed_block( [ 'tag' => 'bits"tips' ] );

		$this->assertSame( '<section>feed</section>', $result );
	}

	public function test_appends_subscribe_shortcode_when_show_subscribe_true(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_feed tag="bitstips" limit="20"]' )
			->andReturn( '<section>feed</section>' );

		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_subscribe tag="bitstips"]' )
			->andReturn( '<div>subscribe</div>' );

		$result = OUTPOST_Blocks::render_feed_block( [ 'tag' => 'bitstips', 'showSubscribe' => true ] );

		$this->assertSame( '<section>feed</section><div>subscribe</div>', $result );
	}

	public function test_omits_subscribe_shortcode_by_default(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_feed tag="bitstips" limit="20"]' )
			->andReturn( '<section>feed</section>' );

		$result = OUTPOST_Blocks::render_feed_block( [ 'tag' => 'bitstips' ] );

		$this->assertSame( '<section>feed</section>', $result );
	}

	public function test_get_hashtag_options_returns_only_placeholder_when_no_hashtags(): void {
		$options = OUTPOST_Blocks::get_hashtag_options( [] );

		$this->assertSame( [
			[ 'label' => 'Select a hashtag…', 'value' => '' ],
		], $options );
	}

	public function test_get_hashtag_options_maps_hashtag_rows(): void {
		$rows = [
			(object) [ 'hashtag' => 'bitstips', 'label' => 'BITS Tips' ],
			(object) [ 'hashtag' => 'blindtech', 'label' => 'Blind Tech' ],
		];

		$options = OUTPOST_Blocks::get_hashtag_options( $rows );

		$this->assertSame( [
			[ 'label' => 'Select a hashtag…', 'value' => '' ],
			[ 'label' => 'BITS Tips', 'value' => 'bitstips' ],
			[ 'label' => 'Blind Tech', 'value' => 'blindtech' ],
		], $options );
	}

	public function test_get_hashtag_options_falls_back_to_hashtag_when_label_empty(): void {
		$rows = [
			(object) [ 'hashtag' => 'bitstips', 'label' => '' ],
		];

		$options = OUTPOST_Blocks::get_hashtag_options( $rows );

		$this->assertSame( [
			[ 'label' => 'Select a hashtag…', 'value' => '' ],
			[ 'label' => '#bitstips', 'value' => 'bitstips' ],
		], $options );
	}
}
