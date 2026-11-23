<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Conversion\TextStore;

/**
 * Texts in memory, one place per text, with copies and fingerprints as the WordPress stores keep them.
 */
final class FakeTextStore implements TextStore {

	/** @var array<string, string> The texts, by key. */
	public array $texts = array();

	/** @var array<string, int> Each text's post. */
	public array $posts = array();

	/** @var array<string, string> The copies from before the first conversion. */
	public array $copies = array();

	/** @var array<string, string> What each text held after its last conversion. */
	public array $written = array();

	/** @var list<string> write()'s keys, in order. */
	public array $writes = array();

	/** Set to make every write come back changed, as a plugin's save hook could; the text is then left as it was. */
	public bool $mangle = false;

	/**
	 * Builds the store.
	 *
	 * @param string $place  What the report shows as the place ('' for a post's content).
	 * @param string       $status  The texts' status.
	 * @param list<string> $markers What a text holds to be listed.
	 */
	public function __construct( private string $place = '', private string $status = 'publish', private array $markers = array( '[lasso', 'affiliate-plugin/lasso' ) ) {}

	public function name(): string {
		return '' === $this->place ? 'post' : $this->place;
	}

	public function add( string $key, int $post, string $text ): self {
		$this->texts[ $key ] = $text;
		$this->posts[ $key ] = $post;

		return $this;
	}

	public function texts( int $post = 0 ): array {
		$out = array();
		foreach ( $this->texts as $key => $text ) {
			if ( ( 0 === $post || $this->posts[ $key ] === $post ) && array() !== array_filter( $this->markers, static fn ( string $marker ): bool => str_contains( $text, $marker ) ) ) {
				$out[] = array(
					'key'    => (string) $key,
					'post'   => $this->posts[ $key ],
					'place'  => $this->place,
					'status' => $this->status,
					'text'   => $text,
				);
			}
		}

		return $out;
	}

	public function write( string $key, string $before, string $after ): bool {
		$this->writes[] = $key;
		if ( $this->mangle || ( $this->texts[ $key ] ?? null ) !== $before ) {
			return false;
		}
		$this->copies[ $key ] ??= $before;
		$this->texts[ $key ]    = $after;
		$this->written[ $key ]  = $after;

		return true;
	}

	public function converted( int $post = 0 ): array {
		return array_values( array_filter( array_map( 'strval', array_keys( $this->copies ) ), fn ( string $key ): bool => 0 === $post || $this->posts[ $key ] === $post ) );
	}

	public function revert( string $place, bool $force ): string {
		if ( ! isset( $this->copies[ $place ] ) ) {
			return self::NONE;
		}
		if ( ! $force && $this->texts[ $place ] !== $this->written[ $place ] ) {
			return self::EDITED;
		}
		$this->texts[ $place ] = $this->copies[ $place ];
		unset( $this->copies[ $place ], $this->written[ $place ] );

		return self::REVERTED;
	}
}
