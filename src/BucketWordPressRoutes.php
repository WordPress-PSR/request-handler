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
		if ( str_starts_with( $uri, 'wp-cron.php' ) ) {
			$i = 0;
		} elseif ( str_starts_with( $uri, '/wp-admin/network' ) ) {
			$i = 5;
		} elseif ( str_starts_with( $uri, '/wp-admin' ) ) {
			$i = 6;
		} else {
			$i = rand( 7, count( $this->workers ) - 1 );
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
