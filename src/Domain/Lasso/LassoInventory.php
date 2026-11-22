<?php
/**
 * How much of each kind of Lasso's data the site holds.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * How much of each kind of Lasso's data the site holds (F10 analysis tables A-H, AC5): every Lasso table present with
 * its rows, and the counts kept elsewhere (options, terms, post and user meta). Counted, never read row by row.
 */
final class LassoInventory {

	/** Lasso's options `lasso_*`, its settings and blacklist aside. */
	public const OPTIONS = 'options';

	/** Lasso's job-runner options `wp_lasso_*`. */
	public const JOB_OPTIONS = 'job_options';

	/** Lasso's transients. */
	public const TRANSIENTS = 'transients';

	/** Lasso's records (`lasso-urls` posts), whose slugs are their cloaked addresses. */
	public const RECORDS = 'records';

	/** Old slugs kept after a rename (`lasso_old_slug`, `_wp_old_slug`). */
	public const OLD_SLUGS = 'old_slugs';

	/** Lasso's groups (`lasso-cat` terms). */
	public const GROUPS = 'groups';

	/** Users with saved Dashboard views. */
	public const SAVED_VIEWS = 'saved_views';

	/** Link-page notices dismissed (`dismiss_out_of_stock`, `dismiss_broken`). */
	public const DISMISSED = 'dismissed';

	/** Lasso's cloud and hub meta on its records (`boosting_enabled`, `is_aff_plus`, `link_id`, `lasso_shortcode`). */
	public const CLOUD_META = 'cloud_meta';

	/** Users with Lasso's tour or banner state. */
	public const USER_STATE = 'user_state';

	/**
	 * Builds the inventory.
	 *
	 * @param array<string, int> $tables Lasso's tables present, without the site's prefix (`lasso_fields`), => rows.
	 * @param array<string, int> $counts The other counts, by the constants above.
	 */
	public function __construct( public readonly array $tables, private array $counts = array() ) {}

	/**
	 * A table's rows; 0 when the table is not there.
	 *
	 * @param string $table The table, without the prefix.
	 */
	public function rows( string $table ): int {
		return $this->tables[ $table ] ?? 0;
	}

	/**
	 * One of the other counts.
	 *
	 * @param string $what One of the constants.
	 */
	public function count( string $what ): int {
		return $this->counts[ $what ] ?? 0;
	}
}
