<?php
/**
 * A family of Lasso's data.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * A family of Lasso's data, migrated as one step (F10 D3), in the fixed order the migration runs them: settings first,
 * the products before everything that points at them, their field values included (the cutover rehearsal's F3: on a
 * site without products the values found none).
 * Clicks are not a family (decision E1: start from zero). Lasso's original links come last: they need Affibloom's link
 * index (Task 26Q).
 */
enum Family: string {

	case Settings      = 'settings';
	case Display       = 'display';
	case Products      = 'products';
	case Fields        = 'fields';
	case Groups        = 'groups';
	case Slugs         = 'slugs';
	case Variants      = 'variants';
	case Tables        = 'tables';
	case Issues        = 'issues';
	case Amazon        = 'amazon';
	case Views         = 'views';
	case Opportunities = 'opportunities';
	case Locations     = 'locations';

	/**
	 * The families named in `--only` (comma-separated), in the fixed order; every family when the list is empty.
	 *
	 * @param string $only The option's value.
	 * @return list<self>
	 * @throws InvalidArgumentException When a name is not a family.
	 */
	public static function parse( string $only ): array {
		$names = array_filter( array_map( 'trim', explode( ',', $only ) ), static fn ( string $name ): bool => '' !== $name );
		if ( array() === $names ) {
			return self::cases();
		}
		foreach ( $names as $name ) {
			if ( null === self::tryFrom( $name ) ) {
				throw new InvalidArgumentException( "Unknown family: $name" );
			}
		}

		return array_values( array_filter( self::cases(), static fn ( self $family ): bool => in_array( $family->value, $names, true ) ) );
	}
}
