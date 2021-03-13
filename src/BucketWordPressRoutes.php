<?php

namespace Tgc\WordPressPsr;

use Psr\Http\Message\ServerRequestInterface;

class BucketWordPressRoutes {

	protected $workers = [];

	public function addWorker( $identifier ) {
		$this->workers[] = $identifier;
	}

	public function getWorkerForRequest( ServerRequestInterface $request ) {
		if ( str_starts_with( $request->getUri(), '/wp-admin' ) ) {
			$i = rand( 5, 7 );
		} else {
			$i = rand( 0, 4 );
		}
		return $this->workers[$i];
	}
}