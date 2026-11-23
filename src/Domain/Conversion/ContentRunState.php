<?php
/**
 * The places a content conversion from the admin page still has to do.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * The content conversion run from the admin page (Task LM8; F10 AC28): the places still to convert, in order, and how
 * many there were, so a run cut off by a reload continues where it stopped.
 */
interface ContentRunState {

	/**
	 * The run going on: the places still to do and how many there were; null when none is.
	 *
	 * @return array{places: list<string>, total: int}|null
	 */
	public function current(): ?array;

	/**
	 * Keeps the places still to do; none ends the run.
	 *
	 * @param list<string> $places The places, in order.
	 * @param int          $total  How many the run had.
	 */
	public function save( array $places, int $total ): void;
}
