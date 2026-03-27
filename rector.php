<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
	define( 'WPINC', 'wp-includes' );
	define( 'EP_NONE', 0 );
}

return RectorConfig::configure()
	->withPaths( array(
		__DIR__ . '/wordpress/',
	) )
	->withAutoloadPaths( array(
		__DIR__ . '/wordpress',
	) )
	->withSets( array(
		SetList::CODE_QUALITY,
		LevelSetList::UP_TO_PHP_82,
	) )
	->withRules( array(
		\WordPressPsr\Rector\NoExit::class,
		\WordPressPsr\Rector\NewHeaderFunction::class,
		\WordPressPsr\Rector\NewCookieFunction::class,
		\WordPressPsr\Rector\NewHeaderRemoveFunction::class,
	) )
	->withImportNames( importDocBlockNames: false )
	->withSkip( array(
		__DIR__ . '/wordpress/wp-includes/SimplePie/*',
		__DIR__ . '/wordpress/wp-includes/sodium_compat/*',
		__DIR__ . '/wordpress/wp-includes/class-json.php',
		__DIR__ . '/wordpress/wp-content/plugins/akismet/class.akismet-cli.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-feed-cache.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-file.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-sanitize-kses.php',
		__DIR__ . '/wordpress/wp-admin/includes/noop.php',
	) );
