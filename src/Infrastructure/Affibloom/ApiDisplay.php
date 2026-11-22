<?php
/**
 * Affibloom's Display settings, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\DisplayWriter;

/**
 * Affibloom's Display settings, through its extension API only (Task 26D).
 */
final class ApiDisplay implements DisplayWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Every type's settings.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array {
		return $this->api->display_settings();
	}

	/**
	 * Saves one type's settings.
	 *
	 * @param string               $type   The type.
	 * @param array<string, mixed> $config Its settings.
	 * @return list<string> The colours and labels refused.
	 */
	public function save( string $type, array $config ): array {
		return $this->api->save_display( $type, $config );
	}
}
