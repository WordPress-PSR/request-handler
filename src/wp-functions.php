<?php
/**
 * WordPress PSR wrapper functions.
 *
 * These functions replace direct calls to exit(), header(), header_remove(), and setcookie()
 * throughout WordPress core and plugins. They fire WordPress actions before calling the
 * original PHP functions, allowing the PSR request handler to intercept them.
 *
 * The Rector rules in src/Rector/ automatically transform WordPress core and plugin code
 * to use these functions instead of the PHP builtins.
 *
 * @package WordPressPsr
 */

if ( ! function_exists( 'wp_exit' ) ) {
	/**
	 * Replacement for exit()/die() that fires the 'wp_exit' action first.
	 *
	 * The PSR request handler hooks into this action to throw a PrematureExitException,
	 * which returns control flow to the request handler so it can build a PSR-7 response.
	 *
	 * @param string|int $message Optional exit message or status code.
	 */
	function wp_exit( $message = '' ) {
		do_action( 'wp_exit', $message );
		exit( $message );
	}
}

if ( ! function_exists( 'wp_header' ) ) {
	/**
	 * Replacement for header() that fires the 'wp_header' action first.
	 *
	 * The PSR request handler hooks into this action to capture headers
	 * for the PSR-7 response instead of sending them directly.
	 *
	 * @param string   $header       The header string.
	 * @param bool     $replace      Whether to replace a previous similar header.
	 * @param int|null $response_code Forces the HTTP response code to the specified value.
	 */
	function wp_header( string $header, bool $replace = true, ?int $response_code = null ) {
		do_action( 'wp_header', $header, $replace, $response_code );
		header( $header, $replace, $response_code ?? 0 );
	}
}

if ( ! function_exists( 'wp_header_remove' ) ) {
	/**
	 * Replacement for header_remove() that fires the 'wp_header_remove' action first.
	 *
	 * @param string $header The header name to remove.
	 */
	function wp_header_remove( string $header ) {
		do_action( 'wp_header_remove', $header );
		header_remove( $header );
	}
}

if ( ! function_exists( 'wp_set_cookie' ) ) {
	/**
	 * Replacement for setcookie() that fires the 'wp_set_cookie' action first.
	 *
	 * The PSR request handler hooks into this action to capture cookies
	 * for the PSR-7 response instead of sending them directly.
	 *
	 * Supports both the traditional parameter list and the PHP 8.0+ options array.
	 *
	 * @param string          $name             The cookie name.
	 * @param string          $value            The cookie value.
	 * @param int|array       $expires_or_options Expiry time or options array (PHP 8.0+).
	 * @param string          $path             The cookie path.
	 * @param string          $domain           The cookie domain.
	 * @param bool            $secure           Whether the cookie should only be sent over HTTPS.
	 * @param bool            $httponly          Whether the cookie is accessible only through HTTP.
	 * @return bool
	 */
	function wp_set_cookie( string $name, string $value = '', $expires_or_options = 0, string $path = '', string $domain = '', bool $secure = false, bool $httponly = false ): bool {
		do_action( 'wp_set_cookie', $name, $value, $expires_or_options, $path, $domain, $secure, $httponly );
		if ( is_array( $expires_or_options ) ) {
			return setcookie( $name, $value, $expires_or_options );
		}
		return setcookie( $name, $value, $expires_or_options, $path, $domain, $secure, $httponly );
	}
}
