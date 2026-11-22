<?php
/**
 * A family whose migration is not built yet.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\DataItem;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;

/**
 * A family whose migration task is not built yet (Task 25C): one line with what the site holds for it and the task
 * that will move it. Each family's task replaces its WaitingStep with its own step.
 */
final class WaitingStep implements Step {

	/**
	 * Builds the step.
	 *
	 * @param Family     $family The family.
	 * @param LeftBehind $data   Every kind of Lasso's data and its family.
	 * @param string     $task   The task that moves it (`26F`).
	 */
	public function __construct( private Family $family, private LeftBehind $data, private string $task ) {}

	/**
	 * The family it stands for.
	 */
	public function family(): Family {
		return $this->family;
	}

	/**
	 * One line: found, and by which task.
	 *
	 * @return list<MigrationLine>
	 */
	public function plan(): array {
		$found = array_map( static fn ( DataItem $item ): string => sprintf( '%s %d', $item->name, $item->rows ), $this->data->of( $this->family ) );
		$note  = sprintf( 'found %s; moved by Task %s', array() === $found ? 'nothing' : implode( ', ', $found ), $this->task );

		return array( new MigrationLine( $this->family, '', '', '', '', Action::Waiting, '', $note ) );
	}
}
