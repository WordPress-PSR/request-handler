<?php

namespace Tgc\WordPressPsr;

use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Tgc\WordPressPsr\Psr7\SimpleStream;

class RequestHandler implements RequestHandlerInterface {

	protected $wordpress_path;

	protected $globals = array(
		// major
		'wp',
		'wp_the_query',
		'wpdb',
		'wp_query',
		'allowedentitynames',
		'wp_db_version',
		// current user stuff

		'user_login',
		'userdata',
		'user_level',
		'user_ID',
		'user_email',
		'user_url',
		'user_identity',
		//other
		'error',
		'concatenate_scripts',
		'wp_scripts',
		'wp_xmlrpc_server',
		//admin
		'pagenow',
		'wp_importers',
		'hook_suffix',
		'plugin_page',
		'typenow',
		'taxnow',
		'title',
		'current_screen',
		'wp_locale',
		'update_title',
		'total_update_count',
		'parent_file',
		'menu',
		'submenu',
		'self',
		'submenu_file',
		'_wp_menu_nopriv',
		'_wp_submenu_nopriv',
		'wp_customize',
	);

	protected $default_filters = array();

	protected $actions_after_bootstrap = array();

	static protected $headers = array();

	/**
	 * @var SetCookies
	 */
	static protected $cookies;

	static protected $status_code = 200;

	protected $response_factory;

	protected $stream_factory;

	protected $bootstrapped = false;

	public function __construct(
		$wordpress_path,
		ResponseFactoryInterface $responseFactory,
		StreamFactoryInterface $stream_factory
	) {
		$this->response_factory = $responseFactory;
		$this->stream_factory   = $stream_factory;
		$this->wordpress_path   = $wordpress_path;
	}

	public function bootstrap() {
		if ( $this->bootstrapped ) {
			return;
		}
		global $wp_filter, $wp_actions;
		define( 'WPMU_PLUGIN_DIR', __DIR__ . '/mu-plugins' );
		//      $_SERVER['HTTP_HOST'] = 'localhost';
		$_SERVER['SERVER_NAME'] = gethostname();
//		define( 'WP_USE_THEMES', true );
		// Load the WordPress library
		require_once $this->wordpress_path . '/wp-load.php';
//		require_once $this->wordpress_path . '/wp-includes/plugin.php';
//		require __DIR__ . '/mu-plugins/fix-wp-die.php';

		$this->default_filters         = $wp_filter;
		$this->actions_after_bootstrap = $wp_actions;
		$this->bootstrapped = true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle( ServerRequestInterface $request ) : ResponseInterface {
		$GLOBALS['swooleBridge'] = $this;
		self::$cookies           = new SetCookies();
		$this->setGlobals( $request );
		ob_start();

		try {
			$this->load_wordpress();
		} catch ( EarlyReturnException $e ) {
			error_log( $e->getMessage() );
		} catch ( \Swoole\ExitException $e ) {
			error_log( $e->getMessage() );
			throw $e;
		}

		$content  = ob_get_clean();
		$headers  = $this->getHeadersToSend();
		$response = $this->response_factory->createResponse( self::$status_code )
			->withBody( new SimpleStream( $content ) );
//		$response->getBody()->write( $content);

		foreach ( $headers as $header => $header_value ) {
			$response = $response->withHeader( $header, $header_value );
		}
		$response = self::$cookies->renderIntoSetCookieHeader( $response );

		$this->cleanUpWordPress();
		return $response;
	}
	public static function addHeader( $header, $value, $extra = null ) {
		self::$headers[ $header ] = $value;
	}

	public static function removeHeader( $header ) {
		unset( self::$headers[ $header ] );
	}

	public static function setCookie( $cookie_name, $value, $expires_or_options = 0, $path = '', $domain = '', $secure = false, $httponly = false ): bool {
		self::$cookies = self::$cookies->with(
			SetCookie::create( $cookie_name )
				->withValue( $value )
				->withExpires( $expires_or_options )
				->withPath( $path )
				->withDomain( $domain )
				->withSecure( $secure )
				->withHttpOnly( $httponly )
				->withSameSite( SameSite::fromString( 'Lax' ) ) // https://github.com/chubbyphp/chubbyphp-swoole-request-handler/issues/3
		);
		return true;
	}

	public static function setStatusCode( int $code ) {
		self::$status_code = $code;
	}

	protected static function getHeadersToSend(): array {
		return self::$headers;
	}

	protected function cleanUpWordPress() {
		global $wp, $wp_actions, $wp_filter, $wp_current_filter;
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( $wp ) {
			$wp->matched_rule = null;
		}
		$wp_actions        = $this->actions_after_bootstrap;
		$wp_filter         = $this->default_filters;
		$wp_current_filter = array();
		self::$headers     = array();
		self::$status_code = 200;
		$user_globals      = array( 'user_login', 'userdata', 'user_level', 'user_ID', 'user_email', 'user_url', 'user_identity' );
		foreach ( $user_globals as $user_global ) {
			unset( $GLOBALS[ $user_global ] );
		}

		unset( $GLOBALS['wp_did_header'] );
		unset( $GLOBALS['wp_scripts'] );
		unset( $GLOBALS['wp_styles'] );
		unset( $GLOBALS['concatenate_scripts'] );
	}

	protected function setGlobals( ServerRequestInterface $request ) {
		foreach ( $request->getServerParams() as $key => $value ) {
			$_SERVER[ strtoupper( $key ) ] = $value;
		}

		foreach ( $request->getHeaders() as $key => $value ) {
			$_SERVER[ 'HTTP_' . str_replace( '-', '_', strtoupper( $key ) ) ] = $value[0];
		}

		$_SERVER['PHP_SELF']    = preg_replace( '/(\?.*)?$/', '', $_SERVER['REQUEST_URI'] );
		$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];

		$_GET    = $request->getQueryParams() ?: array();
		$_POST   = $request->getParsedBody() ?: array();
		$_COOKIE = $request->getCookieParams() ?: array();
	}

	/**
	 * Loads WordPress.
	 *
	 * @throws EarlyReturnException
	 */
	public function load_wordpress() {
		// set current user.


		foreach ( $this->globals as $globalVariable ) {
			global ${$globalVariable};
		}

//		do_action( 'plugins_loaded' );

		$is_php_file_request = strpos( $_SERVER['PHP_SELF'], '.php' ) !== false;

		if ( '/' === $_SERVER['PHP_SELF'] || ( ! $is_php_file_request
			&& ! file_exists( $this->wordpress_path . $_SERVER['PHP_SELF'] . 'index.php' ) ) ) {
			if( ! defined( 'WP_USE_THEMES' ) ) {
				define( 'WP_USE_THEMES', true );
			}
			$this->bootstrap();
			$GLOBALS['wp']->init();
			// Set up the WordPress query.
			\wp();

			// Load the theme template.
			require ABSPATH . WPINC . '/template-loader.php';

//			require $this->wordpress_path . '/index.php';
		} elseif ( $is_php_file_request ) {
			require $this->wordpress_path . $_SERVER['PHP_SELF'];
			if ( 'wp-login.php' === $_SERVER['PHP_SELF'] ) {
				$secure = ( 'https' === parse_url( wp_login_url(), PHP_URL_SCHEME ) );
				self::setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN, $secure );
				if ( SITECOOKIEPATH !== COOKIEPATH ) {
					self::setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN, $secure );
				}
			}
		} else {
			require $this->wordpress_path . $_SERVER['PHP_SELF'] . 'index.php';
		}
	}
}
