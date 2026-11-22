<?php
namespace AffibloomLassoMigrator\Tests\Unit\Domain\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\Blocked;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Conversion\LassoAttributes;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM2 (F10 D16; F31 D2, A1-A54): `[lasso]`'s attributes as `[affibloom]`'s. Products, groups and tables through
 * the migration's maps; Lasso's location and other plugins' names dropped; `lasso-br-code` as Affibloom's `-br-`.
 * Examples from the site's boxes (Affibloom's tests/fixtures/inventory/boxes.json).
 */
final class LassoAttributesTest extends TestCase {

	private static function maps(): IdMaps {
		return new IdMaps( array( 14722 => 22092 ), array( 'pair-grid' => 'pair-grid-2' ), array( 9 => 31 ) );
	}

	/**
	 * @param array<string, string> $lasso The Lasso attributes.
	 * @return array<string, string>|Blocked
	 */
	private static function map( array $lasso ): array|Blocked {
		return LassoAttributes::to_affibloom( $lasso, self::maps() );
	}

	public function test_a_single_box_keeps_its_overrides_and_loses_lassos_ids(): void {
		$box = self::map(
			array(
				'badge'       => 'Best Overall',
				'description' => "adidas' newest volleyball flagship.lasso-br-codeAvailable in a Mid.",
				'id'          => '14722',
				'link_id'     => '13137',
				'ref'         => 'amzn-adidas-womens-crazyflight-7',
			)
		);

		self::assertSame(
			array(
				'badge'       => 'Best Overall',
				'description' => "adidas' newest volleyball flagship.-br-Available in a Mid.",
				'id'          => '22092',
			),
			$box
		);
	}

	public function test_a_product_the_migration_did_not_copy_blocks_the_box(): void {
		$box = self::map( array( 'id' => '999' ) );

		self::assertInstanceOf( Blocked::class, $box );
		self::assertSame( 'Lasso record 999 has no Affibloom product', $box->reason );
	}

	public function test_a_group_display_takes_affibloom_s_group_slug(): void {
		self::assertSame( array( 'type' => 'grid', 'limit' => '3', 'group' => 'pair-grid-2' ), self::map( array( 'type' => 'grid', 'category' => 'pair-grid', 'limit' => '3', 'link_id' => '15860' ) ) );
		self::assertSame( 'Lasso group "gone" has no Affibloom group (it is not one of Lasso\'s groups, or the groups family has not run)', self::map( array( 'type' => 'grid', 'categories' => 'gone' ) )->reason ?? null );
		self::assertSame( 'its group is empty (category="")', self::map( array( 'type' => 'grid', 'category' => '', 'limit' => '3' ) )->reason ?? null );
	}

	public function test_a_table_takes_affibloom_s_table_id_and_table_id_wins(): void {
		self::assertSame( array( 'type' => 'table', 'id' => '31' ), self::map( array( 'type' => 'table', 'id' => '4', 'table_id' => '9' ) ) );
		self::assertSame( 'Lasso table 4 has no Affibloom table', self::map( array( 'type' => 'TABLE', 'id' => '4' ) )->reason ?? null );
	}

	public function test_lasso_only_and_no_op_attributes_are_dropped(): void {
		self::assertSame(
			array( 'id' => '22092' ),
			self::map(
				array(
					'id'                   => '14722',
					'rel'                  => 'x',
					'origin'               => 'widget',
					'for_url_details'      => 'true',
					'compact'              => 'hide',
					'ga'                   => 'false',
					'schema_review_author' => 'Harvey Meale',
					'schema_rating'        => '5',
				)
			)
		);
	}

	public function test_lassos_inline_field_becomes_affibloom_s_field_display(): void {
		self::assertSame( array( 'id' => '22092', 'type' => 'field', 'value' => 'rating' ), self::map( array( 'id' => '14722', 'fields' => 'any', 'value' => 'rating' ) ) );
		self::assertSame( array( 'id' => '22092', 'fields' => 'hide' ), self::map( array( 'id' => '14722', 'fields' => 'hide' ) ) );
	}

	public function test_a_box_that_names_nothing_affibloom_can_find_is_blocked(): void {
		self::assertSame( "it names no product, group or table (only Lasso's link_id or ref)", self::map( array( 'link_id' => '5', 'ref' => 'x' ) )->reason ?? null );
	}
}
