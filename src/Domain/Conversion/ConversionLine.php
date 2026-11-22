<?php
/**
 * One line of the conversion's report.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * One Lasso item and what the conversion does with it (Task LM2; F10 AC25): converted, or blocked with its reason.
 */
final class ConversionLine {

	/** The item becomes Affibloom's. */
	public const CONVERT = 'convert';

	/** The item stays as it is. */
	public const BLOCKED = 'blocked';

	/** A later task converts the item (bare shortcodes: LM4). */
	public const LATER = 'later';

	/** The item could not be written; its post was left as it was. */
	public const ERROR = 'error';

	/**
	 * Builds the line.
	 *
	 * @param int    $post   The post.
	 * @param string $status Its status.
	 * @param string $kind   The item's kind (LassoItem).
	 * @param string $lasso  The item as written.
	 * @param string $action CONVERT or BLOCKED.
	 * @param string $target What it becomes; '' when blocked.
	 * @param string $reason Why it is blocked; '' when converted.
	 */
	public function __construct( public readonly int $post, public readonly string $status, public readonly string $kind, public readonly string $lasso, public readonly string $action, public readonly string $target, public readonly string $reason ) {}
}
