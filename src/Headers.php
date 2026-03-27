<?php

namespace WordPressPsr;

use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;

class Headers {

	protected array $headers = array();

	protected int $status_code = 200;

	protected SetCookies $cookies;

	/**
	 * Per-request current instance registry.
	 *
	 * In a standard PHP-FPM / sequential Swoole worker this holds a single
	 * instance.  When Swoole coroutines are enabled each coroutine gets its
	 * own entry via Swoole\Coroutine::getContext(), so concurrent requests
	 * within the same worker never share state.
	 *
	 * @var Headers|null
	 */
	private static ?Headers $current = null;

	// -------------------------------------------------------------------------
	// Static registry helpers (used by RequestHandler)
	// -------------------------------------------------------------------------

	/**
	 * Register $instance as the active Headers for the current request /
	 * coroutine context.
	 */
	public static function setCurrent( Headers $instance ): void {
		if ( \extension_loaded( 'swoole' ) && \Swoole\Coroutine::getCid() >= 0 ) {
			$ctx = \Swoole\Coroutine::getContext();
			$ctx['__wordpress_psr_headers'] = $instance;
		} else {
			self::$current = $instance;
		}
	}

	/**
	 * Return the active Headers instance for the current request / coroutine
	 * context, or null when called outside a request.
	 */
	public static function getCurrent(): ?Headers {
		if ( \extension_loaded( 'swoole' ) && \Swoole\Coroutine::getCid() >= 0 ) {
			$ctx = \Swoole\Coroutine::getContext();
			return $ctx['__wordpress_psr_headers'] ?? null;
		}
		return self::$current;
	}

	/**
	 * Clear the active instance for the current request / coroutine context.
	 * Called by RequestHandler::clean_up().
	 */
	public static function clearCurrent(): void {
		if ( \extension_loaded( 'swoole' ) && \Swoole\Coroutine::getCid() >= 0 ) {
			$ctx = \Swoole\Coroutine::getContext();
			unset( $ctx['__wordpress_psr_headers'] );
		} else {
			self::$current = null;
		}
	}

	// -------------------------------------------------------------------------
	// Instance API
	// -------------------------------------------------------------------------

	public function add_header( $header_string, $replace = true, $status_code = 0 ): void {
		$header = strstr( $header_string, ':', true );
		$value  = substr( $header_string, strlen( $header ) + 1 );

		if ( false === $replace && isset( $this->headers[ $header ] ) ) {
			return;
		}
		if ( 'location' === strtolower( $header ) && 200 === $this->status_code ) {
			$this->status_code = 302;
		}

		if ( $this->validate_header( $header, $value ) ) {
			$this->headers[ $header ] = $value;
		}
		if ( $status_code ) {
			$this->status_code = $status_code;
		}
	}

	public function remove_header( $header ): void {
		unset( $this->headers[ $header ] );
	}

	public function get_headers(): array {
		return $this->headers;
	}

	public function set_status_code( $code ): void {
		$this->status_code = (int) $code;
	}

	public function get_status_code(): int {
		return $this->status_code;
	}

	public function set_cookie( $cookie_name, $value, $expires_or_options = 0, $path = '', $domain = '', $secure = false, $httponly = false ): bool {
		if ( ! isset( $this->cookies ) ) {
			$this->init_cookies();
		}
		$this->cookies = $this->cookies->with(
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

	protected function init_cookies(): void {
		$this->cookies = new SetCookies();
	}

	public function reset(): void {
		$this->cookies     = new SetCookies();
		$this->headers     = array();
		$this->status_code = 200;
	}

	public function get_cookies(): SetCookies {
		if ( ! isset( $this->cookies ) ) {
			$this->init_cookies();
		}
		return $this->cookies;
	}

	private function validate_header( $header, $values ): bool {
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
