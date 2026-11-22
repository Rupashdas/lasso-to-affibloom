<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Conversion\ContentWriter;

/**
 * Posts' content in memory, with the conversion's copy and fingerprint as ContentChangeWriter keeps them.
 */
final class FakeContentWriter implements ContentWriter {

	/** @var array<int, string> The posts' content. */
	public array $contents = array();

	/** @var array<int, string> The copies from before the first conversion. */
	public array $copies = array();

	/** @var array<int, string> What each post held after its last conversion. */
	public array $written = array();

	/** @var list<int> write()'s posts, in order. */
	public array $writes = array();

	/** Set to make the next write come back changed, as a plugin's save hook could. */
	public bool $mangle = false;

	public function content( int $post ): ?string {
		return $this->contents[ $post ] ?? null;
	}

	public function write( int $post, string $before, string $after ): bool {
		$this->writes[] = $post;
		// A save that came back changed is undone, as ContentChangeWriter does: the post keeps its content.
		if ( $this->mangle ) {
			return false;
		}
		$this->copies[ $post ]   ??= $before;
		$this->contents[ $post ]   = $after;
		$this->written[ $post ]    = $after;

		return true;
	}

	public function converted(): array {
		return array_keys( $this->copies );
	}

	public function revert( int $post, bool $force ): string {
		if ( ! isset( $this->copies[ $post ] ) ) {
			return self::NONE;
		}
		if ( ! $force && ( $this->contents[ $post ] ?? '' ) !== ( $this->written[ $post ] ?? '' ) ) {
			return self::EDITED;
		}
		$this->contents[ $post ] = $this->copies[ $post ];
		unset( $this->copies[ $post ], $this->written[ $post ] );

		return self::REVERTED;
	}
}
