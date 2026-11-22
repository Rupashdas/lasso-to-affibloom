<?php
/**
 * Whether the site has Lasso's data.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Lasso;

/**
 * Whether the site has Lasso's data: Lasso's own version option, which Lasso sets on activation and never removes (it
 * has no uninstall, F10 R1-R2). One autoloaded option read; never written.
 */
final class LassoInstall {

	/** Lasso's version option. */
	private const VERSION_OPTION = 'lasso_version';

	/**
	 * Whether Lasso was ever installed here, so its data is.
	 */
	public function has_data(): bool {
		return false !== get_option( self::VERSION_OPTION, false );
	}
}
