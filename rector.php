<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

define( 'ABSPATH', __DIR__ . '/wordpress/' );
define( 'WPINC', 'wp-includes' );

return static function ( ContainerConfigurator $container_configurator ): void {
	// get parameters
	$parameters = $container_configurator->parameters();
	// Define what rule sets will be applied
	//$parameters->set(Option::SETS, [
	//SetList::DEAD_CODE,
	//]);
	$parameters->set(
		Option::AUTOLOAD_PATHS,
		[
			__DIR__ . '/wordpress',
		]
	);
	// get services (needed for register a single rule)
	$services = $container_configurator->services();
	// register a single rule
	$services->set(\Tgc\WordPressPsr\Rector\NoExit::class);
	$services->set(\Tgc\WordPressPsr\Rector\NewHeaderFunction::class);

	//	$services->set(\Rector\DeadCode\Rector\FunctionLike\RemoveCodeAfterReturnRector::class);
};
