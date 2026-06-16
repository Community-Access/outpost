<?php

namespace Outpost\Tests\Unit;

use Outpost\Tests\TestCase;
use OUTPOST_Hashtag_Manager;

class HandleNormalizationTest extends TestCase {

	public function test_strips_leading_at_and_lowercases(): void {
		$this->assertSame( 'news@example.social', OUTPOST_Hashtag_Manager::normalize_handle( '@News@Example.Social' ) );
	}

	public function test_trims_whitespace(): void {
		$this->assertSame( 'alice', OUTPOST_Hashtag_Manager::normalize_handle( '  alice  ' ) );
	}

	public function test_empty_string_stays_empty(): void {
		$this->assertSame( '', OUTPOST_Hashtag_Manager::normalize_handle( '' ) );
	}
}
