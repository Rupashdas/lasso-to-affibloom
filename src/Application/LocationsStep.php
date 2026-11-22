<?php
/**
 * The locations family: Lasso's original links.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\OriginalLinkWriter;

/**
 * The locations family (Task 26Q; F22 N3, E17): Lasso's scan rewrote links in posts and kept what was there before
 * (`lasso_link_locations.original_link_slug`). Each such link of a post gets that original in Affibloom's link index,
 * so the post's links list can turn it back at render time (Affibloom's GF6). Only ever given: a link Affibloom's index
 * does not hold yet waits (index the post, then run again), an original Affibloom already has is kept, and an original
 * that is not a link is skipped. Affibloom keys a link by post and URL, so where Lasso rewrote one link of a post from
 * several originals (two short links to one product), every place takes the first and the others are skip lines.
 * Nothing else of Lasso's rows is copied: Affibloom builds its own index (D15).
 */
final class LocationsStep implements RunStep {

	/**
	 * Builds the step.
	 *
	 * @param LassoSource        $source Lasso's data.
	 * @param OriginalLinkWriter $writer Affibloom's original links.
	 */
	public function __construct( private LassoSource $source, private OriginalLinkWriter $writer ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Locations;
	}

	/**
	 * One line per post and link, nothing written.
	 *
	 * @param bool $update Unused: an original is only ever given.
	 * @param bool $force  Unused, as $update.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( false );
	}

	/**
	 * Gives each link its original.
	 *
	 * @param bool $update Unused, as in plan().
	 * @param bool $force  Unused, as in plan().
	 * @return list<MigrationLine>
	 */
	public function run( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( true );
	}

	/**
	 * Decides each line and, when writing, carries it out.
	 *
	 * @param bool $write The run, not the dry run.
	 * @return list<MigrationLine>
	 */
	private function work( bool $write ): array {
		$lines = array();
		// The first original of each post's link in this pass: Affibloom keys a link by post and URL, so every place of
		// it takes one original, and the dry run must say so as the run does.
		$first = array();
		foreach ( $this->source->original_links() as $link ) {
			$line = static fn ( Action $action, string $note ): MigrationLine => new MigrationLine( Family::Locations, 'post ' . $link['post'], $link['url'], 'original link', '', $action, $link['original'], $note );
			if ( ! self::is_link( $link['original'] ) ) {
				$lines[] = $line( Action::Skip, 'the original is not a link' );
				continue;
			}
			$key = $link['post'] . ' ' . rtrim( $link['url'], '/' );
			if ( isset( $first[ $key ] ) ) {
				$lines[] = $line( Action::Skip, 'Lasso rewrote this link in this post from several originals; every place takes the first, ' . $first[ $key ] );
				continue;
			}
			$first[ $key ] = $link['original'];
			$now           = $this->writer->original( $link['post'], $link['url'] );
			if ( null === $now ) {
				$lines[] = $line( Action::Skip, "not in Affibloom's link index yet: index the post, then run again" );
			} elseif ( self::same( $now, $link['original'] ) ) {
				$lines[] = $line( Action::Unchanged, '' );
			} elseif ( ! self::same( $now, $link['url'] ) ) {
				$lines[] = $line( Action::Skip, "Affibloom has another original ($now): kept" );
			} elseif ( ! $write ) {
				$lines[] = $line( Action::Update, '' );
			} else {
				$places  = $this->writer->set( $link['post'], $link['url'], $link['original'] );
				$lines[] = 0 === $places ? $line( Action::Error, 'Affibloom took it in no place' ) : $line( Action::Update, 1 === $places ? '' : "$places places" );
			}
		}

		return $lines;
	}

	/**
	 * Whether an address is a link with a host (`http(s)://` or protocol-relative).
	 *
	 * @param string $url The address.
	 */
	private static function is_link( string $url ): bool {
		return 1 === preg_match( '#^(?:https?:)?//[^/\s]+#i', trim( $url ) );
	}

	/**
	 * Two addresses alike but for a trailing slash, as Lasso and Affibloom key a link (LocationKey::md5()).
	 *
	 * @param string $a One address.
	 * @param string $b The other.
	 */
	private static function same( string $a, string $b ): bool {
		return rtrim( trim( $a ), '/' ) === rtrim( trim( $b ), '/' );
	}
}
