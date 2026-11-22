<?php
namespace AffibloomLassoMigrator\Tests\Unit\Domain\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\BlockRewriter;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM3 (F10 D16, AC3): only the Lasso blocks given a replacement change; every other byte of the post stays.
 */
final class BlockRewriterTest extends TestCase {

	private const POST = "<!-- wp:paragraph -->\n<p>Intro [lasso id=\"1\"]</p>\n<!-- /wp:paragraph -->\n\n"
		. "<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"14722\\\"]\",\"id\":\"14722\"} -->\n<div class=\"wp-block-affiliate-plugin-lasso\">[lasso id=\"14722\"]</div>\n<!-- /wp:affiliate-plugin/lasso -->\n\n"
		. "<!-- wp:columns --><div class=\"wp-block-columns\"><!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"5\\\"]\"} /--></div><!-- /wp:columns -->";

	public function test_lasso_blocks_are_counted_in_document_order(): void {
		self::assertSame( 2, BlockRewriter::count( self::POST ) );
	}

	public function test_only_the_replaced_block_changes(): void {
		$after = BlockRewriter::rewrite( self::POST, array( '<!-- wp:affibloom/product-box {"type":"single","productId":22092} /-->', null ) );

		self::assertSame(
			str_replace(
				"<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"14722\\\"]\",\"id\":\"14722\"} -->\n<div class=\"wp-block-affiliate-plugin-lasso\">[lasso id=\"14722\"]</div>\n<!-- /wp:affiliate-plugin/lasso -->",
				'<!-- wp:affibloom/product-box {"type":"single","productId":22092} /-->',
				self::POST
			),
			$after
		);
		self::assertSame( self::POST, BlockRewriter::rewrite( self::POST, array( null, null ) ) );
	}

	public function test_a_count_that_does_not_match_changes_nothing(): void {
		self::assertNull( BlockRewriter::rewrite( self::POST, array( 'x' ) ) );
	}
}
