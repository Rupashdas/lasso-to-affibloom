<?php
/**
 * Affibloom's settings, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\SettingsWriter;

/**
 * Affibloom's settings, through its extension API only (Task 26C).
 */
final class ApiSettings implements SettingsWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Every setting's current value.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array {
		return $this->api->settings();
	}

	/**
	 * Saves some settings of one tab.
	 *
	 * @param string               $tab    The tab.
	 * @param array<string, mixed> $values Setting => value.
	 * @return list<string> The settings refused.
	 */
	public function save( string $tab, array $values ): array {
		return $this->api->save_settings( $tab, $values );
	}
}
