<?php

namespace Tgc\WordPressPsr;

use Psr\Http\Message\ServerRequestInterface;

/**
 * This class segments the worker pool so certain WordPress routes are always sent to the same worker.
 */
class BucketWordPressRoutes {

	const MIN_REQUIRED_WORKERS = 7;

	protected $workers = array();

	protected $special_routes = array(
		'/wp-cron.php'             => 0, // TODO: make a worker for cron
		'/wp-admin/customizer.php' => 1,
		'/wp-admin/admin-ajax.php' => 2,
		'/xmlrpc.php'              => 3,
	);

	public const DO_NOT_USE_WORKER = -1;

	public function addWorker( $identifier ) {
		$this->workers[] = $identifier;
	}

	public function getWorkerForRequest( ServerRequestInterface $request ) {
		$uri = $request->getUri();
		$path = parse_url( $uri, PHP_URL_PATH );

		if ( isset( $this->special_routes[ $path ] ) ) {
			$i = $this->special_routes[ $path ];
		} elseif ( str_starts_with( $uri, '/wp-admin/user' ) ) {
			// TODO: Only check when using multisite to free up more workers
			$i = 4;
		} elseif ( str_starts_with( $uri, '/wp-admin/network' ) ) {
			// TODO: Only check when using multisite to free up more workers
			$i = 5;
		} elseif ( str_starts_with( $uri, '/wp-admin' ) ) {
			$i = 6;
		} else {
			// Request is for front end and need no special handling.
			return self::DO_NOT_USE_WORKER;
		}

		return $this->workers[ $i ];
	}

	public function shouldShutdownAfter( ServerRequestInterface $request ) {
		$uri = $request->getUri();
		return
			str_starts_with( $uri, '/wp-admin/setup-config.php' )
			|| str_starts_with( $uri, '/wp-admin/install.php' );
	}
}
