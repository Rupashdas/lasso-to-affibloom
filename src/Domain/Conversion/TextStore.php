<?php
/**
 * A place that holds text with Lasso's markup.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * A kind of place the conversion reads and writes (Tasks LM2-LM4; F10 D16, D17): posts' content, widgets, Elementor's
 * data. Each text has a key; a write keeps the place's content from before its first conversion once, and revert puts
 * that back exactly, refusing a place changed since its conversion unless forced. A place is what revert works on (a
 * post, a widget option, a post's Elementor data); it may hold several texts.
 */
interface TextStore {

	/** The place is back as it was before its first conversion. */
	public const REVERTED = 'reverted';

	/** The place was changed after its conversion; revert refused it (--force takes it back anyway). */
	public const EDITED = 'edited';

	/** The place has no conversion to revert. */
	public const NONE = 'none';

	/** The place could not be put back exactly; it keeps its copy. */
	public const FAILED = 'failed';

	/**
	 * What a place is, for the report: `post`, `widget option`, `Elementor data of post`.
	 */
	public function name(): string;

	/**
	 * The texts with Lasso's markup, in a stable order: each one's key, its post (0 for none), where it is (shown in the
	 * report: '' for a post's content), its status and its text.
	 *
	 * @param int $post One post's texts only; 0 for all.
	 * @return list<array{key: string, post: int, place: string, status: string, text: string}>
	 */
	public function texts( int $post = 0 ): array;

	/**
	 * Writes a converted text, keeping its place's content from before the first conversion. False when the text did not
	 * store exactly as given: its place is then left as it was.
	 *
	 * @param string $key    The text's key.
	 * @param string $before The text now.
	 * @param string $after  The converted text.
	 */
	public function write( string $key, string $before, string $after ): bool;

	/**
	 * The places with a copy to revert to.
	 *
	 * @param int $post One post's places only; 0 for all.
	 * @return list<string>
	 */
	public function converted( int $post = 0 ): array;

	/**
	 * Puts a place's copy back and forgets it.
	 *
	 * @param string $place The place (converted()'s).
	 * @param bool   $force Revert a place changed after its conversion too.
	 * @return string REVERTED, EDITED, NONE or FAILED.
	 */
	public function revert( string $place, bool $force ): string;
}
