# Affibloom Lasso Migrator

A WordPress plugin that moves one site from Lasso to Affibloom: Lasso's links, settings and every other record, then
every `[lasso]` shortcode, Lasso block and Lasso stamp in the content. Each step has a dry run, a report and a way back.
When the move is finished, the plugin is deleted and the site runs on Affibloom alone.

- Needs Affibloom 1.0.0-rc.1 or later, active (`Requires Plugins: affibloom`), PHP 8.3 and WordPress 7.1.4.
- Reads Lasso's data and never changes it. It reaches Affibloom only through Affibloom's extension API (the action
  `affibloom_ready`; Affibloom's `docs/extension-api.md`), never Affibloom's classes or tables.
- Its page is Affibloom → **Migrate from Lasso**, for users who may change Affibloom's settings.

## Development

The plan is Phase M in Affibloom's repository (`docs/plans/phase-m-lasso-migrator.md`). Namespace
`AffibloomLassoMigrator\`, text domain `affibloom-lasso-migrator`, prefix `affibloom_lasso_`, Affibloom's conventions:
four layers (Interface → Application → Domain ← Infrastructure), one composition root (`src/Plugin.php`).

The unit tests and PHPStan read Affibloom's extension API from an Affibloom checkout beside this folder
(`../affibloom`), as in `wp-content/plugins/`.

```sh
composer install
composer lint       # phpcs, WordPress standard
composer analyse    # PHPStan level 6
composer test:unit  # PHPUnit 9.6 with Brain Monkey
```

The acceptance tests run from Affibloom's `tests/E2E` and are skipped when this plugin is not active.
