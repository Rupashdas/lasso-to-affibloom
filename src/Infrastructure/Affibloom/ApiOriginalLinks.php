<?php
/**
 * Affibloom's original links, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\OriginalLinkWriter;

/**
 * Affibloom's original links, through its extension API only (Task 26Q).
 */
final class ApiOriginalLinks implements OriginalLinkWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * The original a post's link has now; null when Affibloom's index has none.
	 *
	 * @param int    $post The post.
	 * @param string $url  The link as the content has it now.
	 */
	public function original( int $post, string $url ): ?string {
		return $this->api->original_link( $post, $url );
	}

	/**
	 * Gives a post's link its original.
	 *
	 * @param int    $post     The post.
	 * @param string $url      The link as the content has it now.
	 * @param string $original The link before Lasso rewrote it.
	 */
	public function set( int $post, string $url, string $original ): int {
		return $this->api->set_original_link( $post, $url, $original );
	}
}
