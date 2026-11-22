<?php
/**
 * Lasso's opportunity switches, dismissals and ASIN replacements.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * Lasso's opportunity data (F10 R18): each record's Detect Opportunities switch (`lasso_url_details.is_opportunity`),
 * the links editors dismissed (`lasso_link_locations.is_ignored`) and the retired-ASIN replacements
 * (`lasso_link_updates`). Read only.
 */
final class LassoOpportunities {

	/**
	 * Builds the data.
	 *
	 * @param array<int, bool>                                                     $switches     By record: Detect Opportunities.
	 * @param list<array{post: int, url: string}>                                  $dismissed    The dismissed links, by post.
	 * @param list<array{asin: string, country: string, source: int, target: int}> $replacements The retired ASINs.
	 */
	public function __construct( public readonly array $switches = array(), public readonly array $dismissed = array(), public readonly array $replacements = array() ) {}
}
