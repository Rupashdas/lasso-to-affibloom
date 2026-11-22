<?php
/**
 * Affibloom's saved views, tour and intro boxes, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\ViewWriter;
use InvalidArgumentException;

/**
 * Affibloom's saved Dashboard views, Dashboard tour and closed intro boxes, through its extension API only (Task 26M).
 */
final class ApiViews implements ViewWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * A user's views.
	 *
	 * @param int $user The user.
	 * @return list<array{id: string, name: string, filters: array<string, string|int>}>
	 */
	public function views( int $user ): array {
		return $this->api->views( $user );
	}

	/**
	 * Saves a view for a user.
	 *
	 * @param int                                                                 $user The user.
	 * @param array{id: string, name: string, filters: array<string, string|int>} $view The view.
	 */
	public function save( int $user, array $view ): void {
		$this->api->save_view( $user, $view );
	}

	/**
	 * Whether the Dashboard tour was ended.
	 */
	public function tour_done(): bool {
		return $this->api->tour_done();
	}

	/**
	 * Ends the Dashboard tour.
	 */
	public function finish_tour(): void {
		$this->api->finish_tour();
	}

	/**
	 * The intro boxes closed for good.
	 *
	 * @return list<string>
	 */
	public function closed_boxes(): array {
		$hidden = $this->api->settings()['education_hidden'] ?? array();

		return is_array( $hidden ) ? array_values( array_map( 'strval', $hidden ) ) : array();
	}

	/**
	 * Makes these the intro boxes closed for good, through the settings' hidden import tab.
	 *
	 * @param list<string> $boxes The boxes.
	 * @throws InvalidArgumentException When they are refused.
	 */
	public function close_boxes( array $boxes ): void {
		if ( array() !== $this->api->save_settings( 'import', array( 'education_hidden' => $boxes ) ) ) {
			throw new InvalidArgumentException( 'education_hidden: refused' );
		}
	}
}
