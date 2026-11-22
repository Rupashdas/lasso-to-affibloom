<?php
/**
 * Lasso's saved Dashboard views, its tour flag and its closed education boxes.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * Lasso's saved Dashboard views (F10 R22), each user's as `lasso_dashboard_saved_views` holds them; whether its
 * Dashboard tour was ended (`lasso_dashboard_tour_done`, one for the site); and which education boxes were closed for
 * good (`lasso_settings[<box>-opportunities] = 'hide'`). Read only.
 */
final class LassoViews {

	/** Lasso's education boxes, as the `<box>-opportunities` keys name them. */
	public const BOXES = array( 'link', 'keyword', 'content', 'domain' );

	/**
	 * Builds the data.
	 *
	 * @param array<int, list<mixed>> $views     Each user's saved views as Lasso stores them, by user ID.
	 * @param bool                    $tour_done Whether Lasso's Dashboard tour was ended.
	 * @param list<string>            $closed    The boxes closed, of BOXES.
	 */
	public function __construct( public readonly array $views = array(), public readonly bool $tour_done = false, public readonly array $closed = array() ) {}
}
