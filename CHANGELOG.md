# Changelog

All notable changes to this plugin are recorded here. Versions follow semantic versioning.

## [Unreleased]

### Added

- The plugin, beside Affibloom 1.0.0-rc.1 or later through Affibloom's extension API (`affibloom_ready`).
- Affibloom → Migrate from Lasso: the dry run's report as a card per family, Lasso's data with where it goes, and Download report (CSV); nothing is written. Without Lasso's data the menu hides it.
- `wp affibloom-lasso migrate --only=products`: one Affibloom product per Lasso record with a link, with its dates; retired and trashed records keep their status; the one-time import's products are adopted; a second run changes nothing; Lasso's data is only read (`tools/lasso-fingerprint.php`).
- `wp affibloom-lasso migrate --only=settings`: Lasso's General, Display-wide and Amazon settings and the tracking-ID blacklist through Affibloom's Settings rules; a later run keeps Affibloom's differing values unless `--update`.
- `wp affibloom-lasso migrate --only=display`: Lasso's theme, colours and labels, elements, density and layout for Single, Grid and List, and the grid's columns, through Affibloom's Display rules.
- `wp affibloom-lasso migrate --only=fields`: Lasso's custom fields (a renamed built-in keeps its name, a custom field is created once) and each product's field values, order, visibility and "show field name".
- `--update [--force]`: a record changed in Lasso since its migration updates its product, unless the product was changed in Affibloom too (then it is kept and reported; `--force` takes Lasso's values); a record deleted in Lasso is reported and its product kept.
- No Affibloom setup wizard on a site with Lasso's data, as the migration brings its settings.
- `wp affibloom-lasso migrate --dry-run [--only=<families>] [--report=<file.csv>]`: one line per Lasso record with what becomes of it (create, unchanged, skip, retire, trash, error) and a summary; nothing is written. The other families say "not built yet".
- The dry run's settings and display families: every key of Lasso's settings with its Affibloom setting and value, or why it is left; secrets are never read.
- The dry run's other families say what the site holds for them and which task moves them; with every family, it ends with every Lasso table and option group and where it goes, or why it is left.

- `wp affibloom-lasso migrate --only=groups`: every Lasso group with its name, slug and description, and its published products in Lasso's order.