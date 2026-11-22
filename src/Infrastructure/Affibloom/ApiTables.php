<?php
/**
 * Affibloom's comparison tables, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\TableWriter;

/**
 * Affibloom's comparison tables, through its extension API only (Task 26I).
 */
final class ApiTables implements TableWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * A table whole, or null.
	 *
	 * @param int $id The table.
	 * @return array<string, mixed>|null
	 */
	public function table( int $id ): ?array {
		return $this->api->table( $id );
	}

	/**
	 * Stores a table whole.
	 *
	 * @param int                  $id   The table to replace; 0 for a new one.
	 * @param array<string, mixed> $data The table.
	 */
	public function put( int $id, array $data ): int {
		return $this->api->put_table( $id, $data );
	}
}
