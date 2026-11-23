<?php
/**
 * One line of the stamps report.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * What the stamp cleaning does in a post (Task LM6; F34 AC6): its stamps removed, a link tied to the product Lasso's
 * stamp named, a tie Affibloom keeps as it is, a post left as it was (blocked, or an error) with the reason; and what
 * the block repair does (Task LM6A): its buttons repaired, or one left with the reason (blocked).
 */
final class StampLine {

	/** The post's stamps go: the item says how many of each. */
	public const REMOVE = 'remove';

	/** A link is tied to the product Lasso's stamp named: the item is its URL. */
	public const TIE = 'tie';

	/** A link keeps what Affibloom has for it, which differs from Lasso's stamp: the item is its URL. */
	public const KEEP = 'keep';

	/** The post keeps its stamps: a tie could not be kept. */
	public const BLOCKED = 'blocked';

	/** The post could not be written; it was left as it was. */
	public const ERROR = 'error';

	/** The post's GenerateBlocks buttons are written as GenerateBlocks saves them (Task LM6A): the item says how many. */
	public const REPAIR = 'repair';

	/**
	 * Builds the line.
	 *
	 * @param int    $post   The post.
	 * @param string $status Its status.
	 * @param string $action REMOVE, TIE, KEEP, BLOCKED or ERROR.
	 * @param string $item   The stamps' counts, or a link's URL.
	 * @param string $detail The product a link is tied to, or why.
	 */
	public function __construct( public readonly int $post, public readonly string $status, public readonly string $action, public readonly string $item, public readonly string $detail ) {}
}
