<?php
/**
 * Every place the site's content lives.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Every place the site's content lives, for the check before Lasso goes (Task LM9): posts of every type and status
 * (revisions aside), reusable blocks, widgets, Elementor data, the ACF fields Affibloom scans (35D), and the Additional
 * CSS. Only places that name Lasso or Affibloom need be listed.
 */
interface ContentPlaces {

	/**
	 * The places, each with a name and a link to change it.
	 *
	 * @return iterable<array{title: string, edit: string, text: string, css: bool}>
	 */
	public function places(): iterable;
}
