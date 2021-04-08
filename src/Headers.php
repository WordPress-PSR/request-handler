<?php

namespace WordPressPsr;

use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;

class Headers {

	static protected array $headers = array();

	static protected int $status_code = 200;

	static protected SetCookies $cookies;

	static public function add_header( $header_string, $replace = true, $status_code = 0 ) {
		$header = strstr( $header_string, ':', true );
		$value  = substr( $header_string, strlen( $header ) + 1 );

		if ( false === $replace && isset( self::$headers[ $header ] ) ) {
			return;
		}
		if ( 'location' === strtolower( $header ) && 200 === self::$status_code ) {
			self::$status_code = 302;
		}

		if ( self::validate_header( $header, $value ) ) {
			self::$headers[ $header ] = $value;
		}
		if ( $status_code ) {
			self::$status_code = $status_code;
		}
	}

	static public function remove_header( $header ) {
		unset( self::$headers[ $header ] );
	}

	static public function get_headers(): array {
		return self::$headers;
	}

	static public function set_status_code( $code ) {
		self::$status_code = (int) $code;
	}

	static public function get_status_code(): int {
		return self::$status_code;
	}

	public static function set_cookie( $cookie_name, $value, $expires_or_options = 0, $path = '', $domain = '', $secure = false, $httponly = false ): bool {
		if ( ! isset( self::$cookies ) ) {
			self::init_cookies();
		}
		self::$cookies = self::$cookies->with(
			SetCookie::create( $cookie_name )
				->withValue( $value )
				->withExpires( $expires_or_options )
				->withPath( $path )
				->withDomain( $domain )
				->withSecure( $secure )
				->withHttpOnly( $httponly )
		);
		return true;
	}

	protected static function init_cookies() {
		self::$cookies = new SetCookies();
	}

	public static function reset() {
		self::$cookies     = new SetCookies();
		self::$headers     = array();
		self::$status_code = 200;
	}

	public static function get_cookies(): SetCookies {
		if ( ! isset( self::$cookies ) ) {
			self::init_cookies();
		}
		return self::$cookies;
	}


	private static function validate_header( $header, $values ): bool {
		if ( ! \is_string( $header ) || 1 !== \preg_match( "@^[!#$%&'*+.^_`|~0-9A-Za-z-]+$@", $header ) ) {
			return false;
		}

		// This is simple, just one value.
		if ( ( ! \is_numeric( $values ) && ! \is_string( $values ) ) || 1 !== \preg_match( "@^[ \t\x21-\x7E\x80-\xFF]*$@", (string) $values ) ) {
			return false;
		}
		return true;
	}
}
