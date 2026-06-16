<?php

namespace Outpost\Tests\Unit;

use Brain\Monkey\Functions;
use Outpost\Tests\TestCase;
use OUTPOST_Blocks;

class AccountFeedBlockTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'absint' )->alias( function ( $v ) { return abs( (int) $v ); } );
	}

	public function test_builds_account_feed_shortcode_with_default_limit(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_account_feed limit="20"]' )
			->andReturn( '<section>account</section>' );

		$this->assertSame( '<section>account</section>', OUTPOST_Blocks::render_account_feed_block( [] ) );
	}

	public function test_builds_account_feed_shortcode_with_custom_limit(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_account_feed limit="5"]' )
			->andReturn( '<section>account</section>' );

		$this->assertSame( '<section>account</section>', OUTPOST_Blocks::render_account_feed_block( [ 'limit' => 5 ] ) );
	}
}
