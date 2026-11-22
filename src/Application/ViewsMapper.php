<?php
/**
 * A Lasso saved view in Affibloom's names.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

/**
 * A Lasso saved Dashboard view in Affibloom's stored shape (F10 R22; Task 26M). The tab, link kind, placement and table
 * filters keep their values (Lasso's and Affibloom's are the same); the group goes through `group_map`; Lasso's sorts
 * become Affibloom's columns. A filter Affibloom does not have, a group that was not migrated and a sort it cannot
 * show are dropped, each named, and the view keeps its other filters.
 */
final class ViewsMapper {

	/** Lasso's sort → Affibloom's column; Lasso's "last changed" is Affibloom's default order. */
	private const ORDER = array(
		''                  => '',
		'post_modified'     => '',
		'detection_date'    => 'alert',
		'count'             => 'locations',
		'clicks'            => 'clicks',
		'opportunity_count' => 'opportunity_count',
	);

	/** Each Affibloom column's first direction (ListOrder), for a Lasso sort saved without one. */
	private const FIRST = array(
		'alert'             => 'asc',
		'locations'         => 'desc',
		'clicks'            => 'desc',
		'opportunity_count' => 'desc',
	);

	/** The filters Lasso saves (Dashboard_Saved_Views::sanitize_filters()). */
	private const KNOWN = array( 'search', 'filter', 'dashboard_group_term_id', 'dashboard_link_kind', 'dashboard_placements', 'dashboard_comparison_table', 'order_by', 'order_type', 'pageSize' );

	/**
	 * The view in Affibloom's shape and the filters dropped; no view for a row Lasso does not count as one.
	 *
	 * @param mixed          $row    Lasso's stored row.
	 * @param array<int,int> $groups Lasso group term ID → Affibloom group ID.
	 * @return array{view: array{id: string, name: string, filters: array<string, string|int>}|null, dropped: list<string>}
	 */
	public function map( mixed $row, array $groups ): array {
		// Lasso's normalize_record() skips a row without an ID, a name or filters.
		if ( ! is_array( $row ) || ! isset( $row['id'], $row['name'], $row['filters'] ) || '' === (string) $row['id'] || '' === (string) $row['name'] || ! is_array( $row['filters'] ) ) {
			return array(
				'view'    => null,
				'dropped' => array(),
			);
		}
		$lasso   = $row['filters'];
		$text    = static fn ( string $key ): string => isset( $lasso[ $key ] ) && is_scalar( $lasso[ $key ] ) ? (string) $lasso[ $key ] : '';
		$dropped = array_values( array_map( 'strval', array_diff( array_keys( $lasso ), self::KNOWN ) ) );

		$group = (int) $text( 'dashboard_group_term_id' );
		if ( $group > 0 ) {
			if ( isset( $groups[ $group ] ) ) {
				$group = $groups[ $group ];
			} else {
				$dropped[] = 'dashboard_group_term_id (group not migrated)';
				$group     = 0;
			}
		}
		$group = max( -1, $group );

		$sort      = $text( 'order_by' );
		$direction = strtolower( $text( 'order_type' ) );
		$column    = self::ORDER[ $sort ] ?? null;
		if ( null === $column || ( 'post_modified' === $sort && 'asc' === $direction ) ) {
			$dropped[] = 'order_by (' . $sort . ( '' === $direction ? '' : ' ' . $direction ) . ')';
			$column    = '';
		}
		$direction = '' === $column ? '' : ( in_array( $direction, array( 'asc', 'desc' ), true ) ? $direction : self::FIRST[ $column ] );

		return array(
			'view'    => array(
				'id'      => (string) $row['id'],
				'name'    => (string) $row['name'],
				'filters' => array(
					's'                => $text( 'search' ),
					'link_type'        => $text( 'dashboard_link_kind' ),
					'per_page'         => abs( (int) $text( 'pageSize' ) ),
					'group'            => $group,
					'filter'           => $text( 'filter' ),
					'placements'       => $text( 'dashboard_placements' ),
					'orderby'          => $column,
					'order'            => $direction,
					'comparison_table' => $text( 'dashboard_comparison_table' ),
				),
			),
			'dropped' => $dropped,
		);
	}
}
