<?php
/**
 * Affibloom's link index ties, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Conversion\LinkTies;

/**
 * Affibloom's link index ties, through its extension API only (Task LM6).
 */
final class ApiLinkTies implements LinkTies {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Each place a post holds a link.
	 *
	 * @param int    $post The post.
	 * @param string $url  The link.
	 * @return list<array{product: int, assigned: int, original: bool}>
	 */
	public function places( int $post, string $url ): array {
		return $this->api->link_ties( $post, $url );
	}

	/**
	 * Ties a post's link to a product.
	 *
	 * @param int    $post    The post.
	 * @param string $url     The link.
	 * @param int    $product The product.
	 */
	public function tie( int $post, string $url, int $product ): int {
		return $this->api->tie_link( $post, $url, $product );
	}

	/**
	 * Clears a post's link's assignment to a product.
	 *
	 * @param int    $post    The post.
	 * @param string $url     The link.
	 * @param int    $product The product.
	 */
	public function untie( int $post, string $url, int $product ): int {
		return $this->api->untie_link( $post, $url, $product );
	}
}
