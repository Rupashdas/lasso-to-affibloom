# Changelog

All notable changes to this plugin are recorded here. Versions follow semantic versioning.

## [Unreleased]

### Added

- The plugin, beside Affibloom 1.0.0-rc.1 or later through Affibloom's extension API (`affibloom_ready`).
- Affibloom → Migrate from Lasso: an empty page until the dry run is built.
- No Affibloom setup wizard on a site with Lasso's data, as the migration brings its settings.
- `wp affibloom-lasso migrate --dry-run [--only=<families>] [--report=<file.csv>]`: one line per Lasso record with what becomes of it (create, unchanged, skip, retire, trash, error) and a summary; nothing is written. The other families say "not built yet".
- The dry run's settings and display families: every key of Lasso's settings with its Affibloom setting and value, or why it is left; secrets are never read.
- The dry run's other families say what the site holds for them and which task moves them; with every family, it ends with every Lasso table and option group and where it goes, or why it is left.
