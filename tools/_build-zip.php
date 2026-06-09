<?php
/**
 * Dev-only: build the wordpress.org-ready distribution zip.
 *
 * Packs the plugin into  dist/<slug>-<version>.zip  with the slug folder as the
 * single top-level directory, honouring .distignore. No WordPress bootstrap
 * needed — run with plain PHP:
 *
 *   php tools/_build-zip.php
 *
 * @package Storelly
 */

$slug = 'storelly-product-builder-for-woocommerce';
$root = dirname( __DIR__ );               // plugin root
$main = $root . '/' . $slug . '.php';

$src  = file_get_contents( $main );
if ( ! preg_match( '/^\s*Version:\s*([0-9.]+)/mi', $src, $m ) ) {
	fwrite( STDERR, "Could not read Version from header.\n" );
	exit( 1 );
}
$version = $m[1];

$dist = $root . '/dist';
if ( ! is_dir( $dist ) ) {
	mkdir( $dist, 0755, true );
}
$zip_path = $dist . '/' . $slug . '-' . $version . '.zip';
if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}

// .distignore: path-prefix dirs/files + filename globs.
$prefixes = array( '.git', '.github', '.claude', '.remember', 'dist', 'docs', 'node_modules', 'tests', 'tools', 'static/mockups' );
$exact    = array( '.distignore', '.gitattributes', 'CLAUDE.md', 'AUDIT-WPORG.md', '.editorconfig', '.gitignore', '.phpcs.xml', 'phpcs.xml', 'phpcs.xml.dist', 'phpunit.xml', 'phpunit.xml.dist', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'webpack.config.js', 'README.md', '_tmp_inspect.php' );
$globs    = array( '*.map', '_tmp_*', '.DS_Store' );

$is_excluded = static function ( $rel ) use ( $prefixes, $exact, $globs ) {
	foreach ( $prefixes as $p ) {
		if ( $rel === $p || 0 === strpos( $rel, $p . '/' ) ) {
			return true;
		}
	}
	$base = basename( $rel );
	if ( in_array( $base, $exact, true ) ) {
		return true;
	}
	foreach ( $globs as $g ) {
		if ( fnmatch( $g, $base ) ) {
			return true;
		}
	}
	return false;
};

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Cannot create zip: $zip_path\n" );
	exit( 1 );
}

$it = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);
$count = 0;
$bytes = 0;
foreach ( $it as $file ) {
	$abs = $file->getPathname();
	$rel = ltrim( str_replace( '\\', '/', substr( $abs, strlen( $root ) ) ), '/' );
	if ( '' === $rel || $is_excluded( $rel ) ) {
		continue;
	}
	$inzip = $slug . '/' . $rel;
	if ( $file->isDir() ) {
		$zip->addEmptyDir( $inzip );
	} else {
		$zip->addFile( $abs, $inzip );
		++$count;
		$bytes += $file->getSize();
	}
}
$zip->close();

printf( "Built %s\n  %d files, %.2f MB source, zip %.2f MB\n", $zip_path, $count, $bytes / 1048576, filesize( $zip_path ) / 1048576 );
