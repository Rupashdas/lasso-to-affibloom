<?php
/**
 * Affibloom's link issues and closed issue bars.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * A product's link-health row and closed issue bars in Affibloom (Task 26K). Times are UTC, '' for none.
 */
interface IssueWriter {

	/**
	 * A product's row, or null.
	 *
	 * @param int $product The product.
	 * @return array{issue_type: string, checked_url: string, detection_date: string, resolved: bool, resolved_at: string, last_checked_at: string}|null
	 */
	public function issue( int $product ): ?array;

	/**
	 * Stores a product's row.
	 *
	 * @param int                  $product The product.
	 * @param array<string, mixed> $row     The row, in issue()'s keys.
	 * @throws InvalidArgumentException When it is refused.
	 */
	public function import( int $product, array $row ): void;

	/**
	 * Until when a bar stays closed; 0 when open.
	 *
	 * @param int    $product The product.
	 * @param string $bar     `broken` or `out-of-stock`.
	 */
	public function dismissed_until( int $product, string $bar ): int;

	/**
	 * Closes a bar until a time.
	 *
	 * @param int    $product The product.
	 * @param string $bar     `broken` or `out-of-stock`.
	 * @param int    $until   A Unix time.
	 */
	public function dismiss( int $product, string $bar, int $until ): void;
}
