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
- `wp affibloom-lasso migrate --only=amazon`: the cached Amazon data of Lasso's products into Affibloom's store, unless Affibloom's own is as new; no secret is copied.
- `--only=views`: saved Dashboard views, the tour flag and the closed education boxes (Task 26M).
- `--only=slugs`: Lasso's cloaked addresses, old slugs and cloaking switches (Task 26G).
- `--only=variants`: each record's URL variants, in Lasso's order (Task 26H).
- `--only=issues`: Lasso's link alerts and closed issue bars (Task 26K).
- `--only=tables`: Lasso's comparison tables, whole (Task 26I).
- `--only=opportunities`: Detect Opportunities switches, dismissed links and retired-ASIN replacements (Task 26N).
- Migrate from Lasso runs the migration from the page, one family per request, with a lock and a resume (Task 26O).
- `--only=locations` ("Original links"): each link Lasso's scan rewrote gets its original URL in Affibloom's link index, so a post's links can turn it back at render time; a link not indexed yet waits, an original Affibloom has is kept (Task 26Q).
- Fixed: dismissed links were looked for in the Lasso record's ID instead of the post they are in (Task 26N).
- `wp affibloom-lasso convert --dry-run [--post=<id>] [--format=<format>]`: every Lasso block, `[lasso …]` and `[lasso-attr …]` in posts, pages, reusable blocks and the types Lasso scanned, with the Affibloom block or `[affibloom …]` it becomes, or why it stays (an unknown product, group or table; something Affibloom's block cannot hold); nothing is written (Task LM2).
- `wp affibloom-lasso convert [--post=<id>]`: Lasso's convertible blocks become Affibloom's Product box block, every other byte of the post kept; each post keeps its content from before the first conversion and a revision; `--revert [--post=<id>] [--force]` puts it back exactly, refusing a post edited since unless forced; one run at a time (Task LM3).
- The conversion also takes bare `[lasso …]` (to `[affibloom …]`), a Shortcode block of one `[lasso …]` (to Affibloom's block), text, custom HTML and block widgets, and Elementor's data (Lasso's widget becomes Affibloom's), each with a copy and revert; a box Affibloom's block cannot hold becomes `[affibloom …]` in a Shortcode block; `[lasso-attr]` is reported (Task LM4).

- `wp affibloom-lasso migrate --only=groups`: every Lasso group with its name, slug and description, and its published products in Lasso's order.