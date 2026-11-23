<?php
/**
 * Affibloom's products, groups and tables, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Conversion\AffibloomRecords;

/**
 * Whether Affibloom has a product, group or table, through its extension API only (Task LM9); the groups are read once.
 */
final class ApiRecords implements AffibloomRecords {

	/**
	 * The groups' IDs and slugs, once read.
	 *
	 * @var array<string, true>|null
	 */
	private ?array $groups = null;

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Whether it exists.
	 *
	 * @param string $kind `product`, `group` or `table`.
	 * @param string $ref  Its ID, or a group's slug.
	 */
	public function exists( string $kind, string $ref ): bool {
		if ( 'group' === $kind ) {
			if ( null === $this->groups ) {
				$this->groups = array();
				foreach ( $this->api->groups() as $group ) {
					$this->groups[ (string) $group['id'] ] = true;
					$this->groups[ $group['slug'] ]        = true;
				}
			}

			return isset( $this->groups[ $ref ] );
		}
		if ( ! ctype_digit( $ref ) ) {
			return false;
		}

		return 'table' === $kind ? null !== $this->api->table( (int) $ref ) : null !== $this->api->product_fields( (int) $ref );
	}
}
