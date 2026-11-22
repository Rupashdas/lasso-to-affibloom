<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\ViewsMapper;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 R22, AC20 (Task 26M): a Lasso view in Affibloom's names; the group through the map; a filter Affibloom does not
 * have, a group not migrated and a sort it cannot show dropped and named.
 */
final class ViewsMapperTest extends TestCase {

	private static function row( array $filters ): array {
		return array(
			'id'      => 'abc',
			'name'    => 'Amazon in a group',
			'filters' => $filters,
		);
	}

	public function test_the_filters_keep_their_values_the_group_is_mapped_and_the_sort_becomes_affibloom_s(): void {
		$mapped = ( new ViewsMapper() )->map(
			self::row(
				array(
					'search'                     => 'shoes',
					'filter'                     => 'broken-links',
					'dashboard_group_term_id'    => 512,
					'dashboard_link_kind'        => 'amazon',
					'dashboard_placements'       => 'has_placements',
					'dashboard_comparison_table' => 'not_in_table',
					'order_by'                   => 'count',
					'order_type'                 => '',
					'pageSize'                   => 25,
				)
			),
			array( 512 => 569 )
		);

		self::assertSame( array(), $mapped['dropped'] );
		self::assertSame(
			array(
				's'                => 'shoes',
				'link_type'        => 'amazon',
				'per_page'         => 25,
				'group'            => 569,
				'filter'           => 'broken-links',
				'placements'       => 'has_placements',
				'orderby'          => 'locations',
				'order'            => 'desc',
				'comparison_table' => 'not_in_table',
			),
			$mapped['view']['filters']
		);
	}

	public function test_an_unknown_filter_a_group_not_migrated_and_an_oldest_first_sort_are_dropped(): void {
		$mapped = ( new ViewsMapper() )->map(
			self::row(
				array(
					'dashboard_group_term_id' => 77,
					'order_by'                => 'post_modified',
					'order_type'              => 'asc',
					'dashboard_brand'         => 'mikasa',
				)
			),
			array()
		);

		self::assertSame( array( 'dashboard_brand', 'dashboard_group_term_id (group not migrated)', 'order_by (post_modified asc)' ), $mapped['dropped'] );
		self::assertSame( 0, $mapped['view']['filters']['group'] );
		self::assertSame( '', $mapped['view']['filters']['orderby'] );
	}

	public function test_a_row_without_a_name_is_not_a_view_and_not_in_groups_stays(): void {
		self::assertNull( ( new ViewsMapper() )->map( array( 'id' => 'x', 'filters' => array() ), array() )['view'] );
		self::assertSame( -1, ( new ViewsMapper() )->map( self::row( array( 'dashboard_group_term_id' => -1 ) ), array() )['view']['filters']['group'] );
	}
}
