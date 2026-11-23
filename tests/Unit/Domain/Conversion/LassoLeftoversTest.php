<?php
namespace AffibloomLassoMigrator\Tests\Unit\Domain\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\LassoLeftovers;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM9: each of Lasso's markers is counted where content keeps it, and each Affibloom box's product, group or table
 * is read, so nothing that needs Lasso is missed before it goes.
 */
final class LassoLeftoversTest extends TestCase {

	/** A backslash, built here so no tool reads the fixture's escapes as its own. */
	private static function bs(): string {
		return chr( 92 );
	}

	public function test_each_marker_is_counted_once(): void {
		$text = '<!-- wp:affiliate-plugin/lasso {"short_code":"[lasso id=\"5\"]"} /-->'
			. '<!-- wp:affiliate-plugin/lasso {"button_text":"x"} --><div></div><!-- /wp:affiliate-plugin/lasso -->'
			. '<p>[lasso id="7"] and [lasso-attr id="7" attr_name="x"]</p>'
			. '<a href="/x/" data-lasso-id="3" class="btn lasso-button-1">x</a><img class="1 lasso-scan-ignore">'
			. '<!-- wp:aawp/box {"className":"lasso-ll-do-not-delete-4"} /-->'
			. '<p>The lasso-tool class and a .lasso-badge rule, in prose.</p>';

		self::assertSame(
			array(
				LassoLeftovers::SHORTCODE  => 2,
				LassoLeftovers::ATTR       => 1,
				LassoLeftovers::BLOCK      => 2,
				LassoLeftovers::STAMP      => 1,
				LassoLeftovers::CLASS_NAME => 3,
			),
			LassoLeftovers::count( $text )
		);
	}

	public function test_a_class_in_json_encoded_html_is_counted(): void {
		$q = self::bs() . 'u0022';

		self::assertSame( array( LassoLeftovers::CLASS_NAME => 1 ), LassoLeftovers::count( '{' . $q . 'className' . $q . ':' . $q . 'x lasso-cutter' . $q . '}' ) );
	}

	public function test_converted_content_counts_nothing(): void {
		$text = '<!-- wp:affibloom/product-box {"type":"single","productId":22092} /-->'
			. '<a class="affibloom-button-1 my-lasso-thing" href="/x/" data-affibloom-id="4">x</a>[affibloom id="22092"]';

		self::assertSame( array(), LassoLeftovers::count( $text ) );
	}

	public function test_css_counts_only_lasso_selectors(): void {
		self::assertSame( array( LassoLeftovers::CSS => 2 ), LassoLeftovers::count( '.lasso-badge{x:1}.affibloom-badge,.lasso-cutter .x{y:2}/* lasso */', true ) );
		self::assertSame( array(), LassoLeftovers::count( '.affibloom-badge{x:1}', true ) );
	}

	public function test_the_boxes_name_their_products_groups_and_tables(): void {
		$q    = self::bs() . '"';
		$text = '<!-- wp:affibloom/product-box {"type":"single","productId":22092} /-->'
			. '<!-- wp:affibloom/product-box {"type":"grid","groupId":12,"productId":0} /-->'
			. '<!-- wp:affibloom/product-box {"type":"table","tableId":3} /-->'
			. '[affibloom id="22092"][affibloom type="grid" group="best-shoes"][affibloom type="table" id="4"][affibloom prompt="x"]'
			// Elementor keeps the shortcode in JSON, with escaped quotes.
			. '{"affibloom_shortcode":"[affibloom id=' . $q . '99' . $q . ']"}';

		self::assertSame(
			array(
				array( 'kind' => 'product', 'ref' => '22092' ),
				array( 'kind' => 'group', 'ref' => '12' ),
				array( 'kind' => 'table', 'ref' => '3' ),
				array( 'kind' => 'group', 'ref' => 'best-shoes' ),
				array( 'kind' => 'table', 'ref' => '4' ),
				array( 'kind' => 'product', 'ref' => '99' ),
			),
			LassoLeftovers::references( $text )
		);
	}
}
