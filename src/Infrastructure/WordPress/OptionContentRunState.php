<?php
/**
 * The content conversion's places still to do, in an option.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\ContentRunState;

/**
 * The content conversion run from the admin page (Task LM8) in the option `affibloom_lasso_content_run`: the places
 * still to do and how many there were; deleted when the run ends.
 */
final class OptionContentRunState implements ContentRunState {

	public const OPTION = 'affibloom_lasso_content_run';

	/**
	 * The run going on; null when none is.
	 *
	 * @return array{places: list<string>, total: int}|null
	 */
	public function current(): ?array {
		$run = get_option( self::OPTION );
		if ( ! is_array( $run ) || ! is_array( $run['places'] ?? null ) || array() === $run['places'] ) {
			return null;
		}

		return array(
			'places' => array_values( array_map( 'strval', $run['places'] ) ),
			'total'  => (int) ( $run['total'] ?? count( $run['places'] ) ),
		);
	}

	/**
	 * Keeps the places still to do; none ends the run.
	 *
	 * @param list<string> $places The places, in order.
	 * @param int          $total  How many the run had.
	 */
	public function save( array $places, int $total ): void {
		if ( array() === $places ) {
			delete_option( self::OPTION );
			return;
		}
		update_option(
			self::OPTION,
			array(
				'places' => $places,
				'total'  => $total,
			),
			false
		);
	}
}
