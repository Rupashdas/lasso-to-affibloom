<?php
/**
 * Unit test bootstrap: Composer only, no WordPress. Affibloom's classes (its extension API) come from the Affibloom
 * checkout beside this plugin (composer.json, autoload-dev).
 *
 * @package AffibloomLassoMigrator
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'AFFIBLOOM_LASSO_MIGRATOR_FILE', dirname( __DIR__, 2 ) . '/affibloom-lasso-migrator.php' );
define( 'AFFIBLOOM_LASSO_MIGRATOR_VERSION', '0.0.0-test' );
