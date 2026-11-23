<?php
/**
 * The content conversion from the admin page, one place per request.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application\Conversion;

use AffibloomLassoMigrator\Application\RunLocked;
use AffibloomLassoMigrator\Domain\Conversion\ContentRunState;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use AffibloomLassoMigrator\Domain\Migration\RunState;

/**
 * The conversion of Lasso's content from the admin page (Task LM8; F10 AC28, D13; F34 AC7), as staging has no SSH: the
 * same steps as the commands (convert LM2-LM5, stamps LM6, blocks LM6A, CSS LM7), one place per request. A place is
 * `site` (widgets and the Additional CSS, which are no post's) or `post:<id>` (its content and Elementor data
 * converted, its stamps removed, its buttons repaired). The places are listed when a run starts and kept, so a run cut
 * off continues on the next press. One request at a time under the migration's lock (Task 26O), and never beside a
 * command (each step's own lock): either is "already running". Revert goes the other way, one place per request.
 */
final class ContentRun {

	/** Seconds after which a lock is taken as abandoned: a request killed mid-place. */
	public const LOCK_TTL = 600;

	/** The place that is no post's: widgets and the Additional CSS. */
	public const SITE = 'site';

	/**
	 * Builds the run.
	 *
	 * @param ContentConverter $converter Lasso's boxes and shortcodes (LM2-LM5).
	 * @param StampCleaner     $stamps    Lasso's stamps (LM6).
	 * @param BlockRepairer    $blocks    The buttons Lasso rewrote (LM6A).
	 * @param CssTranslator    $css       The site's Lasso CSS rules (LM7).
	 * @param RunState         $lock      The migration's lock (26O).
	 * @param ContentRunState  $state     The places still to do.
	 */
	public function __construct( private ContentConverter $converter, private StampCleaner $stamps, private BlockRepairer $blocks, private CssTranslator $css, private RunState $lock, private ContentRunState $state ) {}

	/**
	 * The dry run's figures for the page: boxes to convert, items blocked (with their lines), posts with stamps, links
	 * to tie, buttons to repair, CSS rules to translate, and the places a run would do.
	 *
	 * @return array{boxes: int, blocked: list<ConversionLine>, stamps: int, ties: int, buttons: int, css: int, places: list<string>}
	 */
	public function plan(): array {
		$convert = $this->converter->plan();
		$stamps  = $this->stamps->plan();
		$blocks  = $this->blocks->plan();
		$css     = $this->css->plan();
		$of      = static fn ( array $lines, string $action ): array => array_values( array_filter( $lines, static fn ( ConversionLine|StampLine $line ): bool => $action === $line->action ) );

		return array(
			'boxes'   => count( $of( $convert, ConversionLine::CONVERT ) ),
			'blocked' => $of( $convert, ConversionLine::BLOCKED ),
			'stamps'  => count( $of( $stamps, StampLine::REMOVE ) ),
			'ties'    => count( $of( $stamps, StampLine::TIE ) ),
			'buttons' => array_sum( array_map( static fn ( StampLine $line ): int => (int) $line->item, $of( $blocks, StampLine::REPAIR ) ) ),
			'css'     => count( $of( $css, StampLine::TRANSLATE ) ),
			'places'  => self::places( array_merge( $of( $convert, ConversionLine::CONVERT ), $of( $stamps, StampLine::REMOVE ), $of( $blocks, StampLine::REPAIR ) ), array() !== $of( $css, StampLine::TRANSLATE ) ),
		);
	}

	/**
	 * Does the next place of the run going on, or starts one.
	 *
	 * @param int $now The Unix time.
	 * @return array{place: string, done: bool, position: int, total: int, errors: int}
	 * @throws RunLocked When another request, or a command, is running.
	 */
	public function step( int $now ): array {
		$this->take( $now );
		try {
			$current = $this->state->current();
			if ( null === $current ) {
				$places  = $this->plan()['places'];
				$current = array(
					'places' => $places,
					'total'  => count( $places ),
				);
			}
			$pending = $current['places'];
			$place   = (string) array_shift( $pending );
			$errors  = '' === $place ? 0 : $this->convert( $place );
			$this->state->save( $pending, $current['total'] );

			return array(
				'place'    => $place,
				'done'     => array() === $pending,
				'position' => $current['total'] - count( $pending ),
				'total'    => $current['total'],
				'errors'   => $errors,
			);
		} catch ( ConversionLocked ) {
			throw new RunLocked();
		} finally {
			$this->lock->unlock();
		}
	}

	/**
	 * How far a run cut off got, for the page after a reload; null when none is going on.
	 *
	 * @return array{position: int, total: int}|null
	 */
	public function progress(): ?array {
		$current = $this->state->current();

		return null === $current ? null : array(
			'position' => $current['total'] - count( $current['places'] ),
			'total'    => $current['total'],
		);
	}

	/**
	 * The places with something to revert: `site` first, then each post.
	 *
	 * @return list<string>
	 */
	public function converted(): array {
		$posts = array();
		$site  = array() !== $this->css->converted();
		foreach ( array_merge( $this->converter->converted(), $this->stamps->converted(), $this->blocks->converted() ) as $place ) {
			if ( ctype_digit( $place ) ) {
				$posts[] = 'post:' . $place;
			} else {
				$site = true;
			}
		}
		$posts = array_values( array_unique( $posts ) );
		sort( $posts, SORT_NATURAL );

		return array_merge( $site ? array( self::SITE ) : array(), $posts );
	}

	/**
	 * Puts one place back, its steps in the other order; a place changed since is kept.
	 *
	 * @param string $place `site` or `post:<id>`.
	 * @param int    $now   The Unix time.
	 * @return array<string, string> Each part's result (TextStore's REVERTED, EDITED, NONE or FAILED), by a label.
	 * @throws RunLocked When another request, or a command, is running.
	 */
	public function revert( string $place, int $now ): array {
		$this->take( $now );
		try {
			if ( self::SITE === $place ) {
				return array_merge( self::labelled( 'CSS', $this->css->revert( false ) ), self::labelled( 'boxes', $this->converter->revert( 0, false, true ) ) );
			}
			$post = self::post( $place );

			return 0 === $post ? array() : array_merge( self::labelled( 'buttons', $this->blocks->revert( $post, false ) ), self::labelled( 'stamps', $this->stamps->revert( $post, false ) ), self::labelled( 'boxes', $this->converter->revert( $post, false ) ) );
		} catch ( ConversionLocked ) {
			throw new RunLocked();
		} finally {
			$this->lock->unlock();
		}
	}

	/**
	 * Converts one place.
	 *
	 * @param string $place `site` or `post:<id>`.
	 * @return int The lines that could not be written.
	 */
	private function convert( string $place ): int {
		if ( self::SITE === $place ) {
			$lines = array_merge( $this->converter->run( 0, true ), $this->css->run() );
		} else {
			$post  = self::post( $place );
			$lines = 0 === $post ? array() : array_merge( $this->converter->run( $post ), $this->stamps->run( $post ), $this->blocks->run( $post ) );
		}

		return count( array_filter( $lines, static fn ( ConversionLine|StampLine $line ): bool => 'error' === $line->action ) );
	}

	/**
	 * The places of the lines, `site` first when anything is no post's (or the CSS changes), then each post in order.
	 *
	 * @param list<ConversionLine|StampLine> $lines The lines with something to do.
	 * @param bool                           $css   Whether the CSS has rules to translate.
	 * @return list<string>
	 */
	private static function places( array $lines, bool $css ): array {
		$posts = array();
		$site  = $css;
		foreach ( $lines as $line ) {
			if ( $line->post > 0 ) {
				$posts[ $line->post ] = 'post:' . $line->post;
			} else {
				$site = true;
			}
		}
		ksort( $posts );

		return array_merge( $site ? array( self::SITE ) : array(), array_values( $posts ) );
	}

	/**
	 * A step's revert results, each label led by the step, so the steps of one post do not overwrite each other.
	 *
	 * @param string                $step    The step: `boxes`, `stamps`, `buttons`, `CSS`.
	 * @param array<string, string> $results The results by place.
	 * @return array<string, string>
	 */
	private static function labelled( string $step, array $results ): array {
		$out = array();
		foreach ( $results as $label => $result ) {
			$out[ "{$step}: {$label}" ] = $result;
		}

		return $out;
	}

	/**
	 * A post's ID from its place; 0 for anything else.
	 *
	 * @param string $place The place.
	 */
	private static function post( string $place ): int {
		return preg_match( '/^post:(\d+)$/', $place, $id ) ? (int) $id[1] : 0;
	}

	/**
	 * Takes the migration's lock.
	 *
	 * @param int $now The Unix time.
	 * @throws RunLocked When another request holds it.
	 */
	private function take( int $now ): void {
		if ( ! $this->lock->lock( $now, self::LOCK_TTL ) ) {
			throw new RunLocked();
		}
	}
}
