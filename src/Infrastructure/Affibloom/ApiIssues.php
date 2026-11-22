<?php
/**
 * Affibloom's link issues, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\IssueWriter;

/**
 * Affibloom's link-health rows and closed issue bars, through its extension API only (Task 26K).
 */
final class ApiIssues implements IssueWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * A product's row, or null.
	 *
	 * @param int $product The product.
	 * @return array{issue_type: string, checked_url: string, detection_date: string, resolved: bool, resolved_at: string, last_checked_at: string}|null
	 */
	public function issue( int $product ): ?array {
		return $this->api->link_issue( $product );
	}

	/**
	 * Stores a product's row.
	 *
	 * @param int                  $product The product.
	 * @param array<string, mixed> $row     The row.
	 */
	public function import( int $product, array $row ): void {
		$this->api->import_link_issue( $product, $row );
	}

	/**
	 * Until when a bar stays closed.
	 *
	 * @param int    $product The product.
	 * @param string $bar     The bar.
	 */
	public function dismissed_until( int $product, string $bar ): int {
		return $this->api->issue_dismissed_until( $product, $bar );
	}

	/**
	 * Closes a bar until a time.
	 *
	 * @param int    $product The product.
	 * @param string $bar     The bar.
	 * @param int    $until   The time.
	 */
	public function dismiss( int $product, string $bar, int $until ): void {
		$this->api->dismiss_issue( $product, $bar, $until );
	}
}
