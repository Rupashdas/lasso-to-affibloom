<?php
/**
 * Lasso's link issues and closed issue bars.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * Lasso's link issues (F10 R16; `lasso_url_issues`, one row per record, `id` the record) and the issue bars editors
 * closed (`dismiss_broken`, `dismiss_out_of_stock`: a Unix time). Read only.
 */
final class LassoIssues {

	/**
	 * Builds the data.
	 *
	 * @param list<array<string, string>>                       $rows       The rows, columns as Lasso names them.
	 * @param array<int, array{broken: int, out-of-stock: int}> $dismissals By record: until when each bar is closed.
	 */
	public function __construct( public readonly array $rows = array(), public readonly array $dismissals = array() ) {}
}
