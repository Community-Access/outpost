<?php

namespace Outpost\Tests\Unit;

use Brain\Monkey\Functions;
use Outpost\Tests\TestCase;
use OUTPOST_Feed_Fetcher;

class AccountPostsTest extends TestCase {

	public function test_returns_empty_when_no_brand_account(): void {
		Functions\when( 'get_option' )->justReturn( '' );
		$this->assertSame( [], OUTPOST_Feed_Fetcher::get_account_posts() );
	}

	public function test_returns_empty_when_handle_has_no_host(): void {
		Functions\when( 'get_option' )->justReturn( 'aliceonly' );
		$this->assertSame( [], OUTPOST_Feed_Fetcher::get_account_posts() );
	}
}
