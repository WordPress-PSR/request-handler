<?php

namespace Tgc\WordPressPsr;

use http\Exception\RuntimeException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Tgc\WordPressPsr\Psr17\Psr17Factory;
use Tgc\WordPressPsr\Psr17\Psr17FactoryProvider;

class RequestHandlerFactory {
	/**
	 * @var Psr17FactoryProviderInterface|null
	 */
	protected static $psr17FactoryProvider;

	/**
	 * @var ResponseFactoryInterface|null
	 */
	protected static $responseFactory;

	/**
	 * @var StreamFactoryInterface|null
	 */
	protected static $streamFactory;

	static public function create( $wordpress_path, ResponseFactoryInterface $responseFactory = null) {
		static::$responseFactory = $responseFactory ?? static::$responseFactory;
		return new RequestHandler( $wordpress_path, self::determineResponseFactory(), self::determineStreamFactory() );
	}

	/**
	 * @param Psr17FactoryProviderInterface $psr17FactoryProvider
	 */
	public static function setPsr17FactoryProvider(Psr17FactoryProviderInterface $psr17FactoryProvider): void
	{
		static::$psr17FactoryProvider = $psr17FactoryProvider;
	}

	/**
	 * @param ResponseFactoryInterface $responseFactory
	 */
	public static function setResponseFactory(ResponseFactoryInterface $responseFactory): void
	{
		static::$responseFactory = $responseFactory;
	}

	/**
	 * @param StreamFactoryInterface $streamFactory
	 */
	public static function setStreamFactory(StreamFactoryInterface $streamFactory): void
	{
		static::$streamFactory = $streamFactory;
	}

	/**
	 * @return StreamFactoryInterface
	 * @throws RuntimeException
	 */
	public static function determineStreamFactory(): ResponseFactoryInterface
	{
		if (static::$streamFactory) {
			return static::$streamFactory;
		}

		$psr17FactoryProvider = static::$psr17FactoryProvider ?? new Psr17FactoryProvider();

		/** @var Psr17Factory $psr17factory */
		foreach ($psr17FactoryProvider->getFactories() as $psr17factory) {
			if ($psr17factory::isStreamFactoryAvailable()) {
				return $psr17factory::getStreamFactory();
			}
		}

		throw new RuntimeException(
			"Could not detect any PSR-17 StreamFactory implementations. " .
			"Please install a supported implementation in order to use `RequestHandlerFactory::create()`. "
		);
	}
	/**
	 * @return ResponseFactoryInterface
	 * @throws RuntimeException
	 */
	public static function determineResponseFactory(): ResponseFactoryInterface
	{
		if (static::$responseFactory) {
			return static::$responseFactory;
		}

		$psr17FactoryProvider = static::$psr17FactoryProvider ?? new Psr17FactoryProvider();

		/** @var Psr17Factory $psr17factory */
		foreach ($psr17FactoryProvider->getFactories() as $psr17factory) {
			if ($psr17factory::isResponseFactoryAvailable()) {
				return $psr17factory::getResponseFactory();
			}
		}

		throw new RuntimeException(
			"Could not detect any PSR-17 ResponseFactory implementations. " .
			"Please install a supported implementation in order to use `RequestHandlerFactory::create()`. "
		);
	}
}