<?php

namespace Tgc\WordPressPsr;

use Psr\Http\Message\ServerRequestInterface;

class BucketWordPressRoutes {

	protected $workers = array();

	protected $special_routes = array(
		'/wp-cron.php'             => 0,
		'/wp-admin/customizer.php' => 1,
		'/wp-admin/admin-ajax.php' => 2,
		'/xmlrpc.php'              => 3,
	);

	public function addWorker( $identifier ) {
		$this->workers[] = $identifier;
	}

	public function getWorkerForRequest( ServerRequestInterface $request ) {
		$uri = $request->getUri();
		$path = parse_url( $uri, PHP_URL_PATH );

		if ( isset( $this->special_routes[ $path ] ) ) {
			$i = $this->special_routes[ $path ];
		} elseif ( str_starts_with( $uri, '/wp-admin/network' ) ) {
			$i = 4;
		} elseif ( str_starts_with( $uri, '/wp-admin' ) ) {
			$i = 5;
		} else {
			$i = rand( 6, count( $this->workers ) - 1 );
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
