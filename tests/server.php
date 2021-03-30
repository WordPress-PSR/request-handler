<?php

use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;

use Tgc\WordPressPsr\Psr17\Psr17Factory;
use Tgc\WordPressPsr\Psr17\Psr17FactoryProvider;

require __DIR__ .  '/../vendor/autoload.php';

$request_handler = \Tgc\WordPressPsr\RequestHandlerFactory::create( dirname(__DIR__ ). '/wordpress' );

$psr17FactoryProvider = new Psr17FactoryProvider();

/** @var Psr17Factory $psr17factory */
foreach ($psr17FactoryProvider->getFactories() as $psr17factory) {
	if ($psr17factory::isServerRequestCreatorAvailable()) {
		$request_creator =  $psr17factory::getServerRequestCreator();
	}
}

$request = $request_creator->createServerRequestFromGlobals();
$response = $request_handler->handle( $request );
(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
