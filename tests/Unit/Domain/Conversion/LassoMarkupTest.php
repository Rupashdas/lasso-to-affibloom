<?php
namespace AffibloomLassoMigrator\Tests\Unit\Domain\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\LassoItem;
use AffibloomLassoMigrator\Domain\Conversion\LassoMarkup;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Tasks LM3, LM4 (F10 D16, AC3, AC26): Lasso's markup found in document order, and replaced there; every other byte of
 * the text stays.
 */
final class LassoMarkupTest extends TestCase {

	private const LASSO_BLOCK = "<!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"14722\\\"]\",\"id\":\"14722\"} -->\n<div class=\"wp-block-affiliate-plugin-lasso\">[lasso id=\"14722\"]</div>\n<!-- /wp:affiliate-plugin/lasso -->";

	private const SHORTCODE_BLOCK = "<!-- wp:shortcode -->\n[lasso id=\"7\" badge=\"Top\"]\n<!-- /wp:shortcode -->";

	private const POST = "<!-- wp:paragraph -->\n<p>Intro [lasso id=\"1\"] and [lasso-attr id=\"2\" attr_name=\"x\"]</p>\n<!-- /wp:paragraph -->\n\n"
		. self::LASSO_BLOCK . "\n\n" . self::SHORTCODE_BLOCK . "\n\n"
		. "<!-- wp:columns --><div class=\"wp-block-columns\"><!-- wp:affiliate-plugin/lasso {\"short_code\":\"[lasso id=\\\"5\\\"]\"} /--></div><!-- /wp:columns -->";

	public function test_every_lasso_item_is_found_in_document_order_once(): void {
		$found = LassoMarkup::scan( self::POST );

		self::assertSame(
			array( LassoItem::SHORTCODE, LassoItem::ATTR, LassoItem::BLOCK, LassoItem::SHORTCODE_BLOCK, LassoItem::BLOCK ),
			array_column( $found, 'kind' )
		);
		self::assertSame( array( ' id="1"', ' id="2" attr_name="x"', ' id="14722"', ' id="7" badge="Top"', ' id="5"' ), array_column( $found, 'attributes' ) );
		self::assertSame( array( 'short_code' => '[lasso id="14722"]', 'id' => '14722' ), $found[2]['block'] );
		self::assertSame( self::SHORTCODE_BLOCK, $found[3]['markup'] );
	}

	public function test_only_the_replaced_items_change(): void {
		$after = LassoMarkup::rewrite( self::POST, array( '[affibloom id="9"]', null, '<!-- wp:affibloom/product-box {"productId":22092} /-->', null, null ) );

		self::assertSame( str_replace( array( '[lasso id="1"]', self::LASSO_BLOCK ), array( '[affibloom id="9"]', '<!-- wp:affibloom/product-box {"productId":22092} /-->' ), self::POST ), $after );
		self::assertSame( self::POST, LassoMarkup::rewrite( self::POST, array_fill( 0, 5, null ) ) );
	}

	public function test_a_count_that_does_not_match_changes_nothing(): void {
		self::assertNull( LassoMarkup::rewrite( self::POST, array( 'x' ) ) );
	}

	public function test_text_without_lasso_has_nothing(): void {
		self::assertSame( array(), LassoMarkup::scan( '<p>[affibloom id="1"] [lassoo]</p>' ) );
	}
}
