<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\BlockRepairer;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeTextStore;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM6A (deviation 160): each post's GenerateBlocks buttons are repaired in one save; a post that agrees is not
 * written; revert puts a post back exactly.
 */
final class BlockRepairerTest extends TestCase {

	private const LASSO = '<!-- wp:generateblocks/button {"uniqueId":"d1","relNoFollow":true} --><a class="gb-button gb-button-d1" href="https://x.example/" target="_blank" rel="nofollow noopener">Buy</a><!-- /wp:generateblocks/button -->';
	private const OWN   = '<!-- wp:generateblocks/button {"uniqueId":"d2","target":true} --><a class="gb-button gb-button-d2" href="https://x.example/" target="_blank" rel="noopener noreferrer">Buy</a><!-- /wp:generateblocks/button -->';

	private static function posts(): FakeTextStore {
		return ( new FakeTextStore( '', 'publish', array( 'wp:generateblocks/button' ) ) )->add( '7', 7, '<p>A</p>' . self::LASSO . self::LASSO )->add( '8', 8, self::OWN );
	}

	public function test_a_post_s_buttons_are_repaired_in_one_save_and_reverted_exactly(): void {
		$posts    = self::posts();
		$repairer = new BlockRepairer( $posts, new FakeConversionLock() );

		$lines = $repairer->plan();
		self::assertSame( array( '7 repair 2 GenerateBlocks buttons' ), array_map( static fn ( StampLine $line ): string => "{$line->post} {$line->action} {$line->item}", $lines ) );
		self::assertSame( array(), $posts->writes );

		self::assertEquals( $lines, $repairer->run() );
		self::assertSame( array( '7' ), $posts->writes );
		self::assertSame( 2, substr_count( $posts->texts['7'], '"relNoFollow":true,"target":true} --><a class="gb-button gb-button-d1" href="https://x.example/" target="_blank" rel="nofollow noopener noreferrer">' ) );
		self::assertSame( array(), $repairer->plan(), 'A second run finds nothing.' );

		self::assertSame( array( 'post 7' => TextStore::REVERTED ), $repairer->revert( 0, false ) );
		self::assertSame( '<p>A</p>' . self::LASSO . self::LASSO, $posts->texts['7'] );
	}

	public function test_a_post_that_does_not_save_is_reported(): void {
		$posts         = self::posts();
		$posts->mangle = true;

		$lines = ( new BlockRepairer( $posts, new FakeConversionLock() ) )->run( 7 );

		self::assertSame( array( StampLine::ERROR ), array_map( static fn ( StampLine $line ): string => $line->action, $lines ) );
	}
}
