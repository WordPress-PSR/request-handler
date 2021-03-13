<?php

use Tgc\WordPressPsr\RequestHandler;
use Tgc\WordPressPsr\EarlyReturnException;

/**
 * @param $location
 * @param int $status
 * @param string $x_redirect_by
 *
 * @return false
 * @throws EarlyReturnException
 */
function wp_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {

	/**
	 * Filters the redirect location.
	 *
	 * @since 2.1.0
	 *
	 * @param string $location The path or URL to redirect to.
	 * @param int    $status   The HTTP response status code to use.
	 */
	$location = apply_filters( 'wp_redirect', $location, $status );

	/**
	 * Filters the redirect HTTP response status code to use.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $status   The HTTP response status code to use.
	 * @param string $location The path or URL to redirect to.
	 */
	$status = apply_filters( 'wp_redirect_status', $status, $location );

	if ( ! $location ) {
		return false;
	}

	if ( $status < 300 || 399 < $status ) {
		wp_die( __( 'HTTP redirect status code must be a redirection code, 3xx.' ) );
	}

	$location = wp_sanitize_redirect( $location );

	RequestHandler::setStatusCode( (int) $status );

	/**
	 * Filters the X-Redirect-By header.
	 *
	 * Allows applications to identify themselves when they're doing a redirect.
	 *
	 * @since 5.1.0
	 *
	 * @param string $x_redirect_by The application doing the redirect.
	 * @param int    $status        Status code to use.
	 * @param string $location      The path to redirect to.
	 */
	$x_redirect_by = apply_filters( 'x_redirect_by', $x_redirect_by, $status, $location );
	if ( is_string( $x_redirect_by ) ) {
		RequestHandler::addHeader( "X-Redirect-By", $x_redirect_by );
	}

	RequestHandler::addHeader( "Location", $location );

	throw new EarlyReturnException( 'wp_redirect' );
}

/**
 * Sets the authentication cookies based on user ID.
 *
 * The $remember parameter increases the time that the cookie will be kept. The
 * default the cookie is kept without remembering is two days. When $remember is
 * set, the cookies will be kept for 14 days or two weeks.
 *
 * @since 2.5.0
 * @since 4.3.0 Added the `$token` parameter.
 *
 * @param int         $user_id  User ID.
 * @param bool        $remember Whether to remember the user.
 * @param bool|string $secure   Whether the auth cookie should only be sent over HTTPS. Default is an empty
 *                              string which means the value of `is_ssl()` will be used.
 * @param string      $token    Optional. User's session token to use for this cookie.
 */
function wp_set_auth_cookie( $user_id, $remember = false, $secure = '', $token = '' ) {
	if ( $remember ) {
		/**
		 * Filters the duration of the authentication cookie expiration period.
		 *
		 * @since 2.8.0
		 *
		 * @param int  $length   Duration of the expiration period in seconds.
		 * @param int  $user_id  User ID.
		 * @param bool $remember Whether to remember the user login. Default false.
		 */
		$expiration = time() + apply_filters( 'auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember );

		/*
		 * Ensure the browser will continue to send the cookie after the expiration time is reached.
		 * Needed for the login grace period in wp_validate_auth_cookie().
		 */
		$expire = $expiration + ( 12 * HOUR_IN_SECONDS );
	} else {
		/** This filter is documented in wp-includes/pluggable.php */
		$expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember );
		$expire     = 0;
	}

	if ( '' === $secure ) {
		$secure = is_ssl();
	}

	// Front-end cookie is secure when the auth cookie is secure and the site's home URL uses HTTPS.
	$secure_logged_in_cookie = $secure && 'https' === parse_url( get_option( 'home' ), PHP_URL_SCHEME );

	/**
	 * Filters whether the auth cookie should only be sent over HTTPS.
	 *
	 * @since 3.1.0
	 *
	 * @param bool $secure  Whether the cookie should only be sent over HTTPS.
	 * @param int  $user_id User ID.
	 */
	$secure = apply_filters( 'secure_auth_cookie', $secure, $user_id );

	/**
	 * Filters whether the logged in cookie should only be sent over HTTPS.
	 *
	 * @since 3.1.0
	 *
	 * @param bool $secure_logged_in_cookie Whether the logged in cookie should only be sent over HTTPS.
	 * @param int  $user_id                 User ID.
	 * @param bool $secure                  Whether the auth cookie should only be sent over HTTPS.
	 */
	$secure_logged_in_cookie = apply_filters( 'secure_logged_in_cookie', $secure_logged_in_cookie, $user_id, $secure );

	if ( $secure ) {
		$auth_cookie_name = SECURE_AUTH_COOKIE;
		$scheme           = 'secure_auth';
	} else {
		$auth_cookie_name = AUTH_COOKIE;
		$scheme           = 'auth';
	}

	if ( '' === $token ) {
		$manager = WP_Session_Tokens::get_instance( $user_id );
		$token   = $manager->create( $expiration );
	}

	$auth_cookie      = wp_generate_auth_cookie( $user_id, $expiration, $scheme, $token );
	$logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );

	/**
	 * Fires immediately before the authentication cookie is set.
	 *
	 * @since 2.5.0
	 * @since 4.9.0 The `$token` parameter was added.
	 *
	 * @param string $auth_cookie Authentication cookie value.
	 * @param int    $expire      The time the login grace period expires as a UNIX timestamp.
	 *                            Default is 12 hours past the cookie's expiration time.
	 * @param int    $expiration  The time when the authentication cookie expires as a UNIX timestamp.
	 *                            Default is 14 days from now.
	 * @param int    $user_id     User ID.
	 * @param string $scheme      Authentication scheme. Values include 'auth' or 'secure_auth'.
	 * @param string $token       User's session token to use for this cookie.
	 */
	do_action( 'set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, $scheme, $token );

	/**
	 * Fires immediately before the logged-in authentication cookie is set.
	 *
	 * @since 2.6.0
	 * @since 4.9.0 The `$token` parameter was added.
	 *
	 * @param string $logged_in_cookie The logged-in cookie value.
	 * @param int    $expire           The time the login grace period expires as a UNIX timestamp.
	 *                                 Default is 12 hours past the cookie's expiration time.
	 * @param int    $expiration       The time when the logged-in authentication cookie expires as a UNIX timestamp.
	 *                                 Default is 14 days from now.
	 * @param int    $user_id          User ID.
	 * @param string $scheme           Authentication scheme. Default 'logged_in'.
	 * @param string $token            User's session token to use for this cookie.
	 */
	do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in', $token );

	/**
	 * Allows preventing auth cookies from actually being sent to the client.
	 *
	 * @since 4.7.4
	 *
	 * @param bool $send Whether to send auth cookies to the client.
	 */
	if ( ! apply_filters( 'send_auth_cookies', true ) ) {
		return;
	}

	RequestHandler::setCookie( $auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true );
	RequestHandler::setCookie( $auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true );
	RequestHandler::setCookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true );
	if ( COOKIEPATH != SITECOOKIEPATH ) {
		RequestHandler::setCookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true );
	}
}

/**
 * Removes all of the cookies associated with authentication.
 *
 * @since 2.5.0
 */
function wp_clear_auth_cookie() {
	/**
	 * Fires just before the authentication cookies are cleared.
	 *
	 * @since 2.7.0
	 */
	do_action( 'clear_auth_cookie' );

	/** This filter is documented in wp-includes/pluggable.php */
	if ( ! apply_filters( 'send_auth_cookies', true ) ) {
		return;
	}

	// Auth cookies.
	RequestHandler::setCookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( LOGGED_IN_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( LOGGED_IN_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );

	// Settings cookies.
	RequestHandler::setCookie( 'wp-settings-' . get_current_user_id(), ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH );
	RequestHandler::setCookie( 'wp-settings-time-' . get_current_user_id(), ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH );

	// Old cookies.
	RequestHandler::setCookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );

	// Even older cookies.
	RequestHandler::setCookie( USER_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( PASS_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( USER_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
	RequestHandler::setCookie( PASS_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );

	// Post password cookie.
	RequestHandler::setCookie( 'wp-postpass_' . COOKIEHASH, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
}