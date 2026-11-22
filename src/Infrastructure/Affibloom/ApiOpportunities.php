<?php
/**
 * Affibloom's dismissed opportunities, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\OpportunityWriter;

/**
 * Affibloom's dismissed opportunities, through its extension API only (Task 26N).
 */
final class ApiOpportunities implements OpportunityWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Whether a post's link is dismissed; null when the index has none.
	 *
	 * @param int    $post The post.
	 * @param string $url  The link.
	 */
	public function dismissed( int $post, string $url ): ?bool {
		return $this->api->opportunity_dismissed( $post, $url );
	}

	/**
	 * Dismisses a post's link.
	 *
	 * @param int    $post The post.
	 * @param string $url  The link.
	 */
	public function dismiss( int $post, string $url ): int {
		return $this->api->dismiss_opportunity( $post, $url );
	}
}
