<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\CssTranslator;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeTextStore;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM7 (ADR-6, C1): one line per Lasso rule and per name with no equivalent; the run writes the translated CSS
 * once; revert puts it back exactly.
 */
final class CssTranslatorTest extends TestCase {

	private const CSS = "/* LASSO ALTERATIONS */\n.lasso-cutter .lasso-box-5 { line-height: 0.7 !important; }\n@media (max-width: 767.98px) {\n.lasso-display.lasso-cutter a.lasso-button-1 { font-size: 14px; }\n}\n.lasso-gallery-slide { x: 1; }\nfigcaption { font-size: 16px; }";

	private static function css(): FakeTextStore {
		return ( new FakeTextStore( 'Additional CSS of', 'css', array( 'lasso-' ) ) )->add( 'generatepress_child', 0, self::CSS );
	}

	/**
	 * @param list<StampLine> $lines The lines.
	 * @return list<string>
	 */
	private static function lines( array $lines ): array {
		return array_map( static fn ( StampLine $line ): string => "{$line->action}: {$line->item} | {$line->detail}", $lines );
	}

	public function test_each_rule_is_listed_written_once_and_reverted_exactly(): void {
		$css        = self::css();
		$translator = new CssTranslator( $css, new FakeConversionLock() );

		$plan = self::lines( $translator->plan() );
		self::assertSame(
			array(
				'translate: .lasso-cutter .lasso-box-5 | .affibloom-cutter .affibloom-box-5',
				'translate: .lasso-display.lasso-cutter a.lasso-button-1 | .affibloom-display.affibloom-cutter a.affibloom-button-1',
				'blocked: .lasso-gallery-slide | Affibloom prints no equivalent of .lasso-gallery-slide: the rule keeps it, so it styles nothing once Lasso is gone',
			),
			$plan
		);
		self::assertSame( array(), $css->writes );

		self::assertSame( $plan, self::lines( $translator->run() ) );
		self::assertSame( str_replace( array( '.lasso-cutter', '.lasso-box-5', '.lasso-display', '.lasso-button-1' ), array( '.affibloom-cutter', '.affibloom-box-5', '.affibloom-display', '.affibloom-button-1' ), self::CSS ), $css->texts['generatepress_child'] );

		self::assertSame( array( 'Additional CSS of generatepress_child' => TextStore::REVERTED ), $translator->revert( false ) );
		self::assertSame( self::CSS, $css->texts['generatepress_child'] );
	}

	public function test_css_that_does_not_save_is_reported(): void {
		$css         = self::css();
		$css->mangle = true;

		$actions = array_map( static fn ( StampLine $line ): string => $line->action, ( new CssTranslator( $css, new FakeConversionLock() ) )->run() );

		self::assertSame( array( StampLine::ERROR, StampLine::ERROR, StampLine::BLOCKED ), $actions );
	}
}
