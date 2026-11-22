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
 * lasso_version, families: { <family>: { items, errors, at } } }`. It goes with Finish (LM10).
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
}
