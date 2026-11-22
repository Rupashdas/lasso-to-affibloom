<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Migration\DisplayWriter;

/**
 * Affibloom's Display settings in memory: each save merged into its type, and one colour it refuses.
 */
final class FakeDisplay implements DisplayWriter {

	/** Saves, in order. @var list<array{0: string, 1: array<string, mixed>}> */
	public array $saves = array();

	/**
	 * Builds the settings.
	 *
	 * @param array<string, mixed> $values Every type's settings.
	 * @param string               $refuse A colour key Affibloom refuses, '' for none.
	 */
	public function __construct( public array $values = array(), public string $refuse = '' ) {}

	public function values(): array {
		return $this->values;
	}

	public function save( string $type, array $config ): array {
		$this->saves[] = array( $type, $config );
		$refused       = isset( $config['palette'][ $this->refuse ] ) ? array( $this->refuse ) : array();
		unset( $config['palette'][ $this->refuse ] );
		if ( isset( $config['columns'] ) ) {
			$this->values['columns'] = $config['columns'];
			unset( $config['columns'] );
		}
		$this->values[ $type ] = array_replace_recursive( $this->values[ $type ] ?? array(), $config );

		return $refused;
	}
}
