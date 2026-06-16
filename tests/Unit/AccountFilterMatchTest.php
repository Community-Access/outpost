<?php

namespace Outpost\Tests\Unit;

use Brain\Monkey\Functions;
use Outpost\Tests\TestCase;
use OUTPOST_Hashtag_Manager;

class AccountFilterMatchTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( function ( $url, $component ) {
			return parse_url( $url, $component );
		} );
	}

	public function test_blank_filter_matches_everything(): void {
		$this->assertTrue( OUTPOST_Hashtag_Manager::post_matches_filter( '', 'anyone', 'https://x.social/@anyone' ) );
	}

	public function test_exact_acct_match(): void {
		$this->assertTrue( OUTPOST_Hashtag_Manager::post_matches_filter( 'news@example.social', 'news@example.social', 'https://example.social/@news' ) );
	}

	public function test_non_match(): void {
		$this->assertFalse( OUTPOST_Hashtag_Manager::post_matches_filter( 'news@example.social', 'someoneelse', 'https://example.social/@someoneelse' ) );
	}

	public function test_local_acct_with_host_only_on_filter(): void {
		$this->assertTrue( OUTPOST_Hashtag_Manager::post_matches_filter( 'alice@example.social', 'alice', 'https://example.social/@alice' ) );
	}

	public function test_local_acct_wrong_host_does_not_match(): void {
		$this->assertFalse( OUTPOST_Hashtag_Manager::post_matches_filter( 'alice@other.social', 'alice', 'https://example.social/@alice' ) );
	}
}
