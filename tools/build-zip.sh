#!/usr/bin/env bash
# The migrator's release zip (Phase M exit check): the committed code (HEAD) without what .gitattributes marks
# export-ignore, with Composer's production autoloader and no composer.lock, zipped as affibloom-lasso-migrator/ for
# Plugins → Add New → Upload Plugin. Commit first: uncommitted changes are not in the zip.
#
#   bash tools/build-zip.sh [<out.zip>]     (default: affibloom-lasso-migrator.zip in the plugin's folder, gitignored)
set -euo pipefail

cd "$(dirname "$0")/.."
out="${1:-$PWD/affibloom-lasso-migrator.zip}"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

git archive --format=tar --prefix=affibloom-lasso-migrator/ HEAD | tar -x -C "$work"
(
	cd "$work/affibloom-lasso-migrator"
	composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --quiet
	rm -f composer.lock
)

# Forward-slash entry names, as WordPress's unzip expects (PowerShell 5.1's Compress-Archive writes backslashes).
php -r '
	[ , $root, $out ] = $argv;
	$zip = new ZipArchive();
	if ( true !== $zip->open( $out, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		fwrite( STDERR, "Cannot write $out\n" );
		exit( 1 );
	}
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		$zip->addFile( $file->getPathname(), str_replace( "\\", "/", substr( $file->getPathname(), strlen( $root ) + 1 ) ) );
	}
	echo $zip->numFiles, " files\n";
	$zip->close();
' "$work" "$out"
echo "Built $out"
