<?php
/**
 * Lasso's blocks replaced in a post's content.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Replaces Lasso's blocks in a post's content by their place in it (Task LM3; F10 D16): the n-th
 * `affiliate-plugin/lasso` block, self-closing or with its closing comment, becomes the n-th replacement, or stays
 * when that is null. Every other byte of the post stays as it is. The places are the blocks in document order, as
 * WordPress's parse_blocks() reads them (ContentHosts).
 */
final class BlockRewriter {

	/** One Lasso block: its opening comment with its attributes, self-closing or up to its closing comment. */
	private const BLOCK = '#<!--\s+wp:affiliate-plugin/lasso(?:\s+\{.*?\})?\s+(/)?-->(?(1)|.*?<!--\s+/wp:affiliate-plugin/lasso\s+-->)#s';

	/**
	 * How many Lasso blocks the content holds.
	 *
	 * @param string $content The post's content.
	 */
	public static function count( string $content ): int {
		return (int) preg_match_all( self::BLOCK, $content );
	}

	/**
	 * The content with its Lasso blocks replaced; null when the replacements are not one per block.
	 *
	 * @param string            $content      The post's content.
	 * @param list<string|null> $replacements One per Lasso block, in order; null keeps that block.
	 */
	public static function rewrite( string $content, array $replacements ): ?string {
		if ( self::count( $content ) !== count( $replacements ) ) {
			return null;
		}
		$place = 0;

		return (string) preg_replace_callback(
			self::BLOCK,
			static function ( array $block ) use ( &$place, $replacements ): string {
				return $replacements[ $place++ ] ?? $block[0];
			},
			$content
		);
	}
}
