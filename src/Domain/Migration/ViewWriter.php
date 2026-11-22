<?php
/**
 * Affibloom's saved Dashboard views, tour flag and closed intro boxes.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * Affibloom's saved Dashboard views, its Dashboard tour and its closed Opportunities intro boxes (Task 26M).
 */
interface ViewWriter {

	/**
	 * A user's views, `{ id, name, filters }`.
	 *
	 * @param int $user The user.
	 * @return list<array{id: string, name: string, filters: array<string, string|int>}>
	 */
	public function views( int $user ): array;

	/**
	 * Saves a view for a user, replacing the one with its ID.
	 *
	 * @param int                                                                 $user The user.
	 * @param array{id: string, name: string, filters: array<string, string|int>} $view The view.
	 * @throws InvalidArgumentException When it is refused.
	 */
	public function save( int $user, array $view ): void;

	/**
	 * Whether the Dashboard tour was ended.
	 */
	public function tour_done(): bool;

	/**
	 * Ends the Dashboard tour for every user.
	 */
	public function finish_tour(): void;

	/**
	 * The intro boxes closed for good: `links`, `domains`, `content`.
	 *
	 * @return list<string>
	 */
	public function closed_boxes(): array;

	/**
	 * Makes these the intro boxes closed for good.
	 *
	 * @param list<string> $boxes The boxes.
	 * @throws InvalidArgumentException When they are refused.
	 */
	public function close_boxes( array $boxes ): void;
}
