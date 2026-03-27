<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use WordPressPsr\Rector\NewCookieFunction;
use WordPressPsr\Rector\NewHeaderFunction;
use WordPressPsr\Rector\NewHeaderRemoveFunction;
use WordPressPsr\Rector\NoExit;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
	define( 'WPINC', 'wp-includes' );
	define( 'EP_NONE', 0 );
}

// Build paths dynamically — only include directories that exist.
$paths = [ __DIR__ . '/wordpress/' ];
if ( is_dir( __DIR__ . '/wp-content/plugins/' ) ) {
	$paths[] = __DIR__ . '/wp-content/plugins/';
}

return RectorConfig::configure()
	->withPaths( $paths )
	->withSkip( [
		// Third-party libraries bundled in WordPress that should not be transformed.
		__DIR__ . '/wordpress/wp-includes/SimplePie/*',
		__DIR__ . '/wordpress/wp-includes/sodium_compat/*',
		__DIR__ . '/wordpress/wp-includes/class-json.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-feed-cache.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-file.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-sanitize-kses.php',
		__DIR__ . '/wordpress/wp-admin/includes/noop.php',
		// Vendor directories inside plugins should not be transformed.
		'*/vendor/*',
		'*/node_modules/*',
	] )
	->withAutoloadPaths( [
		__DIR__ . '/wordpress',
	] )
	->withRules( [
		NoExit::class,
		NewHeaderFunction::class,
		NewCookieFunction::class,
		NewHeaderRemoveFunction::class,
	] )
	->withImportNames( importDocBlockNames: false )
	->withParallel( 300 );
