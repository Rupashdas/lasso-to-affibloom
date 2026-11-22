<?php
/**
 * The migration's log, in an option.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;

/**
 * The migration's log in the option `affibloom_lasso_migration` (not autoloaded: only the migrator reads it), `{
 * lasso_version, families: { <family>: { items, errors, at } }, field_map: { <Lasso ID>: <Affibloom ID> } }`. It goes
 * with Finish (LM10).
 */
final class OptionMigrationLog implements MigrationLog {

	/** The option. */
	public const OPTION = 'affibloom_lasso_migration';

	/**
	 * Builds the log.
	 *
	 * @param string $lasso_version The Lasso version whose data is migrated.
	 */
	public function __construct( private string $lasso_version ) {}

	/**
	 * Records a family's run.
	 *
	 * @param Family $family The family.
	 * @param int    $items  Its lines.
	 * @param int    $errors Its error lines.
	 */
	public function ran( Family $family, int $items, int $errors ): void {
		$log = get_option( self::OPTION, array() );
		$log = is_array( $log ) ? $log : array();

		$log['lasso_version']              = $this->lasso_version;
		$log['families'][ $family->value ] = array(
			'items'  => $items,
			'errors' => $errors,
			'at'     => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( self::OPTION, $log, false );
	}

	/**
	 * Whether the family ran before.
	 *
	 * @param Family $family The family.
	 */
	public function has_run( Family $family ): bool {
		$log = get_option( self::OPTION, array() );

		return is_array( $log ) && isset( $log['families'][ $family->value ] );
	}

	/**
	 * One of the ID maps.
	 *
	 * @param string $name The map.
	 * @return array<int, int>
	 */
	public function map( string $name ): array {
		$log = get_option( self::OPTION, array() );
		$map = is_array( $log ) && isset( $log[ $name ] ) && is_array( $log[ $name ] ) ? $log[ $name ] : array();

		return array_map( 'intval', $map );
	}

	/**
	 * Adds a pair to one of the ID maps.
	 *
	 * @param string $name The map.
	 * @param int    $from Lasso's ID.
	 * @param int    $to   Affibloom's.
	 */
	public function remember( string $name, int $from, int $to ): void {
		$log                   = get_option( self::OPTION, array() );
		$log                   = is_array( $log ) ? $log : array();
		$log[ $name ][ $from ] = $to;
		update_option( self::OPTION, $log, false );
	}
}
