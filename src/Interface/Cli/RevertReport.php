<?php
/**
 * What a revert did, printed.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use WP_CLI;

/**
 * A revert's results for WP-CLI (Tasks LM3, LM6, LM6A): a warning for each place kept or not put back, then the counts;
 * a place that failed ends the command with an error status.
 */
final class RevertReport {

	/**
	 * Prints the results.
	 *
	 * @param array<string, string> $results Each place's result (TextStore's REVERTED, EDITED, NONE or FAILED), by a label.
	 * @param string                $change  What was done to the places: `conversion`, `cleaning`, `repair`.
	 */
	public static function print( array $results, string $change ): void {
		$counts = array_count_values( $results );
		foreach ( $results as $one => $result ) {
			if ( TextStore::EDITED === $result ) {
				WP_CLI::warning( ucfirst( $one ) . " was changed after its {$change}: kept (--force reverts it)." );
			} elseif ( TextStore::FAILED === $result ) {
				WP_CLI::warning( ucfirst( $one ) . ' could not be put back exactly: it keeps its copy.' );
			}
		}
		$line = sprintf(
			'%d reverted, %d edited since (kept), %d with nothing to revert, %d failed.',
			$counts[ TextStore::REVERTED ] ?? 0,
			$counts[ TextStore::EDITED ] ?? 0,
			$counts[ TextStore::NONE ] ?? 0,
			$counts[ TextStore::FAILED ] ?? 0
		);
		if ( isset( $counts[ TextStore::FAILED ] ) ) {
			WP_CLI::warning( $line );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( $line );
	}
}
