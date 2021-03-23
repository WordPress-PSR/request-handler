<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
	define( 'WPINC', 'wp-includes' );
	define( 'EP_NONE', 0 );
}
return static function ( ContainerConfigurator $container_configurator ): void {
	// get parameters
	$parameters = $container_configurator->parameters();
	//  $parameters->set(Option::SETS, [
	//      SetList::CODE_QUALITY,
	//      SetList::PHP_70,SetList::PHP_71, SetList::PHP_72, SetList::PHP_73, SetList::PHP_74, SetList::PHP_80
	//  ]);
	$parameters->set(
		Option::AUTOLOAD_PATHS,
		array(
			__DIR__ . '/wordpress',
		)
	);
	// get services (needed for register a single rule)
	$services = $container_configurator->services();
	// register a single rule
	$services->set( \Tgc\WordPressPsr\Rector\NoExit::class );
	$services->set( \Tgc\WordPressPsr\Rector\NewHeaderFunction::class );
	$services->set( \Tgc\WordPressPsr\Rector\NewCookieFunction::class );
	$services->set( \Tgc\WordPressPsr\Rector\NewHeaderRemoveFunction::class );

	$parameters->set( Option::IMPORT_DOC_BLOCKS, false );
	$parameters->set( Option::AUTO_IMPORT_NAMES, true );
	$parameters->set(
		Option::SKIP,
		array(
			__DIR__ . '/wordpress/wp-includes/SimplePie/*',
			__DIR__ . '/wordpress/wp-includes/sodium_compat/*',
			__DIR__ . '/wordpress/wp-includes/class-json.php',
			__DIR__ . '/wordpress/wp-content/plugins/akismet/class.akismet-cli.php',
			__DIR__ . '/wordpress/wp-includes/class-wp-feed-cache.php',
			__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-file.php',
			__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-sanitize-kses.php',
			__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-file.php',
			__DIR__ . '/wordpress/wp-includes/class-wp-feed-cache.php',
		)
	);

	$parameters->set(
		Option::PATHS,
		array(
			__DIR__ . '/wordpress/',
		)
	);
};
