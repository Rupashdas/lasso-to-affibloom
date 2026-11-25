# Changelog

All notable changes to this plugin are recorded here. Versions follow semantic versioning.

## [Unreleased]

## [0.2.0] - 2026-11-26

The build for the live cutover (Task 41), beside Affibloom 1.0.0-rc.2.

### Changed

- The settings family no longer carries Lasso's Commission Upgrades (`auto_upgrade_eligible_links`), Mobile Deep Linking and Send Readers to Their Local Store (`international_click`): Affibloom removed them, as they worked only through Lasso's cloud; the dry run lists them as left with that reason (2026-11-25).
- Nor Lasso's Performance Mode, Restrict Prefix, Disable AAWP Description, the two notification switches, `show_disclosure_list` and Force changes from link details, which Affibloom removed (`show_disclosure_table` and the CPU level are still carried).

### Removed

- `WaitingStep` and the "Comes with Task …" card state: every family has its own step.

## [0.1.0] - 2026-11-24

The build staging ran (Task 40, `04ecf8d`).

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
- Lasso's other displays convert to Affibloom's of the same type (button, image, link, grid, list, gallery, table, SiteStripe, `amazon_url`) with groups and tables mapped; an AI text box keeps Lasso's cached text, copied into Affibloom's store when the box is written, and is reported when there is none (Task LM5).
- `wp affibloom-lasso stamps [--dry-run] [--post=<id>] [--revert [--force]]`: Lasso's `data-lasso-id`, `data-lasso-lid` and `data-lasso-name` stamps (in HTML and in a block's JSON) and AAWP's `lasso-ll-do-not-delete-*` class leave the posts, and `lasso-scan-ignore` becomes `affibloom-scan-ignore`, every other byte kept; a link Affibloom's index ties to another product than Lasso's stamp, or to none, is tied to Lasso's product, an editor's choice in Affibloom kept; its own copy and revert, which also clears the ties (Task LM6).
- `wp affibloom-lasso blocks [--dry-run] [--post=<id>] [--revert [--force]]`: the GenerateBlocks buttons Lasso left with settings and HTML that disagree (its editor dropped `"target":true`, its scan rewrote `rel`), which the block editor flags once Lasso is off, are written as GenerateBlocks saves them, what readers get kept; its own copy and revert (Task LM6A).
- `wp affibloom-lasso css [--dry-run] [--revert [--force]]`: each `.lasso-*` rule of the active theme's Additional CSS with its Affibloom selector (Affibloom's class map: `lasso-container` is `affibloom-box`, …; variables and `data-` attributes too), and each name Affibloom prints no equivalent of, left and reported; the CSS keeps a copy and reverts exactly (Task LM7).
- Migrate from Lasso → **Content**: the conversion's figures (places, Lasso boxes, blocked items with a link to each post, stamped posts, links to tie, buttons, CSS rules), a backup tick and **Convert**, one place per request (widgets and the Additional CSS, then each post: its boxes, stamps and buttons) with "N of M", continuing after a reload; then the converted places with **Revert** each and **Revert All**; `POST /convert` and `/convert/revert`, refused with "Another migration or conversion is running." beside a migration, a conversion or a command (Task LM8).
- `wp affibloom-lasso verify` and Migrate from Lasso → **Check**: Lasso's shortcodes, `[lasso-attr]`, blocks, `data-lasso-` stamps and class names left in posts of every type and status, reusable blocks, Elementor data, the ACF fields Affibloom scans and widgets, and `.lasso-` in the Additional CSS; then every Affibloom box that names a product, group or table Affibloom does not have; each place with its edit link. None: "Nothing of Lasso is left in your content." The command ends with an error status while anything is left (Task LM9).
- Migrate from Lasso → **Finish migration** and `wp affibloom-lasso finish [--yes]`: once the Check card finds nothing, removes the conversion's copies, the migration's map and marks, and the migrator's options, logs and locks, after a dialog that says the way back is the database backup only; the page then only says to deactivate and delete the plugin, and nothing can run again. Deleting the plugin (`uninstall.php`) removes anything left. Lasso's data is not touched (Task LM10).
- Fixed: on a site without Affibloom products, every product's field values (ratings among them) were skipped and later runs kept them empty; the products are migrated before their field values, and a later run gives values to a product the family never gave any (Task 26E; the cutover rehearsal's F3).
- Fixed: on a migration from zero, two records with the same title could keep each other's addresses when one of them is deleted in Lasso (its product retired): a retired product now gives up a live record's address and takes its own record's after the live records, or stays where it is when another product holds that (Task 26G; the cutover rehearsal's F2; needs Affibloom's API of 2026-11-24).

- `--update --force` also gives a product adopted from Affibloom's one-time import Lasso's values where they differ, its Amazon price kept, once (Task 29A, deviation 161).

- Fixed: while Lasso and Affibloom are both on, the links Lasso's own boxes draw are left as Lasso drew them (Affibloom's render-time link rules rewrote their New Window, `rel` and link, and counted them against the text's links) (Task 29D).

- `wp affibloom-lasso migrate --only=groups`: every Lasso group with its name, slug and description, and its published products in Lasso's order.