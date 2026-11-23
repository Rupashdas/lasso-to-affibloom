<?php
/**
 * Whether anything of Lasso is left in the content.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\AffibloomRecords;
use AffibloomLassoMigrator\Domain\Conversion\ContentPlaces;
use AffibloomLassoMigrator\Domain\Conversion\LassoLeftovers;
use AffibloomLassoMigrator\Domain\Conversion\Leftover;

/**
 * The check before Lasso goes (Task LM9; F10, the user's rule of 2026-11-17): in every place content lives, each of
 * Lasso's markers left (its shortcodes, block, stamps, class names; `.lasso-` in the Additional CSS), and each Affibloom
 * box that names a product, group or table Affibloom does not have. None: nothing of Lasso is left. It only reads.
 */
final class LeftoverCheck {

	/**
	 * Builds the check.
	 *
	 * @param ContentPlaces    $places  Every place the content lives.
	 * @param AffibloomRecords $records Affibloom's products, groups and tables.
	 */
	public function __construct( private ContentPlaces $places, private AffibloomRecords $records ) {}

	/**
	 * Every finding, Lasso's markers first (what still needs Lasso), then the boxes that name nothing, each place by
	 * place; empty when nothing of Lasso is left.
	 *
	 * @return list<Leftover>
	 */
	public function check(): array {
		$found = array();
		$boxes = array();
		foreach ( $this->places->places() as $place ) {
			foreach ( LassoLeftovers::count( $place['text'], $place['css'] ) as $what => $count ) {
				$found[] = new Leftover( $place['title'], $place['edit'], $what, $count );
			}
			if ( $place['css'] ) {
				continue;
			}
			foreach ( LassoLeftovers::references( $place['text'] ) as $box ) {
				if ( ! $this->records->exists( $box['kind'], $box['ref'] ) ) {
					$boxes[] = new Leftover( $place['title'], $place['edit'], "a box names {$box['kind']} {$box['ref']}, which Affibloom does not have", 1 );
				}
			}
		}

		return array_merge( $found, $boxes );
	}
}
