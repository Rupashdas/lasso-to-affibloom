<?php
/**
 * The site's Lasso CSS rules as Affibloom's.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\ConversionLock;
use AffibloomLassoMigrator\Domain\Conversion\LassoCssMap;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;

/**
 * The translation of the site's `.lasso-*` CSS rules (Task LM7; ADR-6, decision C1): the active theme's Additional
 * CSS with Lasso's names as Affibloom's (LassoCssMap), one line per rule, and a blocked line for each name Affibloom
 * prints no equivalent of (left as it was). The CSS keeps a copy; revert puts it back exactly. One run or revert at a
 * time, with the conversion's lock.
 */
final class CssTranslator {

	/**
	 * Builds the translation.
	 *
	 * @param TextStore      $css  The Additional CSS, with its copy.
	 * @param ConversionLock $lock One run or revert at a time.
	 */
	public function __construct( private TextStore $css, private ConversionLock $lock ) {}

	/**
	 * Each rule and what it becomes; nothing written.
	 *
	 * @return list<StampLine>
	 */
	public function plan(): array {
		$lines = array();
		foreach ( $this->css->texts() as $text ) {
			$lines = array_merge( $lines, self::lines( $text, LassoCssMap::translate( $text['text'] ) ) );
		}

		return $lines;
	}

	/**
	 * Writes the translated CSS.
	 *
	 * @return list<StampLine>
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function run(): array {
		$this->take();
		try {
			$lines = array();
			foreach ( $this->css->texts() as $text ) {
				$translated = LassoCssMap::translate( $text['text'] );
				$written    = $translated['css'] === $text['text'] || $this->css->write( $text['key'], $text['text'], $translated['css'] );
				foreach ( self::lines( $text, $translated ) as $line ) {
					$lines[] = $written || StampLine::TRANSLATE !== $line->action ? $line : new StampLine( $line->post, $line->status, StampLine::ERROR, $line->item, 'the CSS did not save as written; it was left as it was' );
				}
			}

			return $lines;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * Puts the CSS back as it was before its first translation.
	 *
	 * @param bool $force Also CSS edited after its translation.
	 * @return array<string, string> Each theme's result (TextStore's REVERTED, EDITED, NONE or FAILED), by a label.
	 * @throws ConversionLocked When another run or revert is going on.
	 */
	public function revert( bool $force ): array {
		$this->take();
		try {
			$results = array();
			foreach ( $this->css->converted() as $place ) {
				$results[ $this->css->name() . ' ' . $place ] = $this->css->revert( $place, $force );
			}

			return $results;
		} finally {
			$this->lock->release();
		}
	}

	/**
	 * The themes whose CSS was translated, with a copy to revert to.
	 *
	 * @return list<string>
	 */
	public function converted(): array {
		return $this->css->converted();
	}

	/**
	 * Takes the lock.
	 *
	 * @throws ConversionLocked When another run or revert holds it.
	 */
	private function take(): void {
		if ( ! $this->lock->take() ) {
			throw new ConversionLocked( 'A conversion is already running.' );
		}
	}

	/**
	 * A rule per line, then each name with no equivalent.
	 *
	 * @param array{key: string, post: int, place: string, status: string, text: string}                      $text       The CSS.
	 * @param array{css: string, rules: list<array{lasso: string, affibloom: string}>, unknown: list<string>} $translated Its translation.
	 * @return list<StampLine>
	 */
	private static function lines( array $text, array $translated ): array {
		$lines = array();
		foreach ( $translated['rules'] as $rule ) {
			$lines[] = new StampLine( $text['post'], $text['place'], StampLine::TRANSLATE, $rule['lasso'], $rule['affibloom'] );
		}
		foreach ( $translated['unknown'] as $name ) {
			$lines[] = new StampLine( $text['post'], $text['place'], StampLine::BLOCKED, $name, "Affibloom prints no equivalent of {$name}: the rule keeps it, so it styles nothing once Lasso is gone" );
		}

		return $lines;
	}
}
