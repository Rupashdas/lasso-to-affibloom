<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Migration\SettingsWriter;

/**
 * Affibloom's settings in memory: the values, what each save was given, and one setting it refuses.
 */
final class FakeSettings implements SettingsWriter {

	/** Saves by tab, in order. @var list<array{0: string, 1: array<string, mixed>}> */
	public array $saves = array();

	/**
	 * Builds the settings.
	 *
	 * @param array<string, mixed> $values Setting => value.
	 * @param string               $refuse A setting Affibloom refuses, '' for none.
	 */
	public function __construct( public array $values = array(), public string $refuse = '' ) {}

	public function values(): array {
		return $this->values;
	}

	public function save( string $tab, array $values ): array {
		$this->saves[] = array( $tab, $values );
		unset( $values[ $this->refuse ] );
		$this->values = array_merge( $this->values, $values );

		return array_key_exists( $this->refuse, $this->saves[ count( $this->saves ) - 1 ][1] ) ? array( $this->refuse ) : array();
	}
}
