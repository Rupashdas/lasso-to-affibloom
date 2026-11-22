<?php
/**
 * Where a converted post is written, with what takes it back.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Posts' content as the conversion writes it (Task LM3; F10 D16, D17): the content before the first conversion is kept
 * once per post, what was written is remembered, and revert puts the kept content back exactly.
 */
interface ContentWriter {

	/** The post is back as it was before its first conversion. */
	public const REVERTED = 'reverted';

	/** The post was changed after its conversion; revert refused it (--force takes it back anyway). */
	public const EDITED = 'edited';

	/** The post has no conversion to revert. */
	public const NONE = 'none';

	/** The post could not be put back exactly; it keeps its copy. */
	public const FAILED = 'failed';

	/**
	 * A post's content now; null for a post that is gone.
	 *
	 * @param int $post The post.
	 */
	public function content( int $post ): ?string;

	/**
	 * Writes the converted content, keeping `$before` when the post has no copy yet. False when the post did not save
	 * exactly as given: it is then left as it was.
	 *
	 * @param int    $post   The post.
	 * @param string $before Its content now.
	 * @param string $after  Its converted content.
	 */
	public function write( int $post, string $before, string $after ): bool;

	/**
	 * The posts with a copy to revert to.
	 *
	 * @return list<int>
	 */
	public function converted(): array;

	/**
	 * Puts a post's copy back and forgets it.
	 *
	 * @param int  $post  The post.
	 * @param bool $force Revert a post changed after its conversion too.
	 * @return string REVERTED, EDITED, NONE or FAILED.
	 */
	public function revert( int $post, bool $force ): string;
}
