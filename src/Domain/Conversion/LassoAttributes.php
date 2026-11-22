<?php
/**
 * `[lasso]`'s attributes as `[affibloom]`'s.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Lasso's attribute language to Affibloom's (F31 D2: `[affibloom]` keeps A1-A52's names, values and effects; only the
 * addressing differs). `id` becomes the Affibloom product's (or, for a table, the table's: `table_id` wins, A50);
 * `category`/`categories` the Affibloom group's slug as `group`; Lasso's inline field (`fields` + `value`, A14) the field
 * display. Lasso's location IDs and other plugins' names (`link_id`, `ref`, `rel`, `link`, `identifier`, `align`,
 * `origin`, `for_url_details`; D2), the names Lasso reads to no effect (A53: `ga`, `width`, `show_toc`,
 * `hide_header`), the schema attributes its block writes and its output never reads (A52) and `compact="hide"` (A45,
 * F31 N3) are dropped. In every value Lasso's line break `lasso-br-code` becomes Affibloom's `-br-` (LC2).
 */
final class LassoAttributes {

	/** Names that mean nothing in Affibloom. */
	private const DROPPED = array( 'link_id', 'ref', 'rel', 'link', 'identifier', 'align', 'origin', 'for_url_details', 'ga', 'width', 'show_toc', 'hide_header', 'schema_review_author', 'schema_rating', 'schema_pros', 'schema_cons', 'table_id', 'category', 'categories' );

	/** Names that name a product without its ID (A2, A6, A7, A9). */
	private const OTHER_TARGETS = array( 'slug', 'amazon_url', 'geni_url', 'prompt' );

	/**
	 * The attributes in Affibloom's language, or why the box cannot be converted.
	 *
	 * @param array<string, string> $lasso The box's attributes, as WordPress parses them.
	 * @param IdMaps                $maps  The migration's maps.
	 * @return array<string, string>|Blocked
	 */
	public static function to_affibloom( array $lasso, IdMaps $maps ): array|Blocked {
		$out = array();
		foreach ( $lasso as $name => $value ) {
			$name = strtolower( (string) $name );
			if ( in_array( $name, self::DROPPED, true ) || ( 'compact' === $name && 'hide' === strtolower( trim( $value ) ) ) ) {
				continue;
			}
			$out[ $name ] = str_replace( 'lasso-br-code', '-br-', $value );
		}

		$type  = strtolower( trim( $lasso['type'] ?? '' ) );
		$group = trim( $lasso['categories'] ?? $lasso['category'] ?? '' );
		if ( 'table' === $type ) {
			$table = (int) trim( $lasso['table_id'] ?? $lasso['id'] ?? '' );
			if ( ! isset( $maps->tables[ $table ] ) ) {
				return new Blocked( "Lasso table $table has no Affibloom table" );
			}
			$out['type'] = 'table';
			$out['id']   = (string) $maps->tables[ $table ];
		} elseif ( '' === $group && ( isset( $lasso['categories'] ) || isset( $lasso['category'] ) ) ) {
			return new Blocked( 'its group is empty (' . ( isset( $lasso['categories'] ) ? 'categories' : 'category' ) . '="")' );
		} elseif ( '' !== $group ) {
			if ( ! isset( $maps->groups[ $group ] ) ) {
				return new Blocked( "Lasso group \"$group\" has no Affibloom group (it is not one of Lasso's groups, or the groups family has not run)" );
			}
			$out['group'] = $maps->groups[ $group ];
		} elseif ( isset( $lasso['id'] ) ) {
			$id = (int) trim( $lasso['id'] );
			if ( ! isset( $maps->products[ $id ] ) ) {
				return new Blocked( "Lasso record $id has no Affibloom product" );
			}
			$out['id'] = (string) $maps->products[ $id ];
		} elseif ( array() === array_intersect( self::OTHER_TARGETS, array_keys( $out ) ) ) {
			return new Blocked( "it names no product, group or table (only Lasso's link_id or ref)" );
		}

		// Lasso's inline field (A14) is Affibloom's field display (F31 D2); `fields="hide"` keeps its meaning.
		if ( isset( $out['fields'] ) && 'hide' !== strtolower( trim( $out['fields'] ) ) && isset( $out['value'] ) ) {
			unset( $out['fields'] );
			$out = array_merge(
				array_diff_key( $out, array( 'value' => '' ) ),
				array(
					'type'  => 'field',
					'value' => $lasso['value'],
				)
			);
		}

		return $out;
	}
}
