<?php

namespace Tgc\WordPressPsr;

use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;
use http\Exception\RuntimeException;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
//use WP_Swoole\EarlyReturnException;
use Psr\Http\Message\ResponseFactoryInterface;
use Tgc\WordPressPsr\Psr17\Psr17Factory;
use Tgc\WordPressPsr\Psr17\Psr17FactoryProvider;
use Tgc\WordPressPsr\Psr17FactoryProviderInterface;

class RequestHandler implements RequestHandlerInterface {


	protected $wordpress_path;

	protected $globals = [
		// major
		'wp', 'wp_the_query', 'wpdb', 'wp_query', 'allowedentitynames', 'wp_db_version',
		// current user stuff

		'user_login', 'userdata', 'user_level', 'user_ID', 'user_email', 'user_url', 'user_identity',
		//other
		'error', 'concatenate_scripts', 'wp_scripts', 'wp_xmlrpc_server',
		//admin
		'pagenow', 'wp_importers', 'hook_suffix', 'plugin_page', 'typenow', 'taxnow',
		'title', 'current_screen', 'wp_locale',
		'update_title', 'total_update_count', 'parent_file',
		'menu', 'submenu', 'self',  'submenu_file',
	];

	protected $defaultFilters = array();

	protected $actionsAfterBootstrap = array();

	static protected $headers = array();

	/**
	 * @var SetCookies
	 */
	static protected $cookies;

	static protected $statusCode = 200;

	protected $responseFactory;

	protected $stream_factory;

	public function __construct(
		$wordpress_path,
		ResponseFactoryInterface $responseFactory,
		StreamFactoryInterface $stream_factory
	) {
		$this->responseFactory = $responseFactory;
		$this->stream_factory = $stream_factory;
		$this->wordpress_path = $wordpress_path;
		$this->bootstrap();
	}

	public function bootstrap()
	{
		$_SERVER['HTTP_HOST'] = 'localhost';
		$_SERVER['SERVER_NAME'] = gethostname();
		define('WP_USE_THEMES', true);
		// Load the WordPress library
		require_once $this->wordpress_path . '/wp-load.php';
		global $wp_filter, $wp_actions;
		$this->defaultFilters = $wp_filter;
		$this->actionsAfterBootstrap = $wp_actions;
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle(ServerRequestInterface $request) : ResponseInterface {
		$GLOBALS['swooleBridge'] = $this;
		self::$cookies = new SetCookies();
		$this->setGlobals($request);
		ob_start();

		try {
			$this->loadWordpress();
		} catch( EarlyReturnException $e ) {
			error_log( $e->getMessage() );
		} catch ( \Swoole\ExitException $e ) {
			error_log( $e->getMessage() );
			throw $e;
		}

		$content =  ob_get_clean();
		$headers = $this->getHeadersToSend();
		$response = $this->responseFactory->createResponse( self::$statusCode );
		$response->withBody( $this->stream_factory->createStream( $content ) );
		$response = new Response($content, self::$statusCode, $headers);
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

	public static function setCookie( $cookieName, $value, $expires_or_options = 0, $path = "", $domain = "", $secure = false, $httponly = false ): bool {
		self::$cookies = self::$cookies->with(
			SetCookie::create($cookieName)
			         ->withValue( $value )
			         ->withExpires( $expires_or_options )
			         ->withPath( $path )
					 ->withDomain( $domain )
					 ->withSecure( $secure )
			         ->withHttpOnly( $httponly )
		);
		return true;
	}

	public static function setStatusCode( int $code ) {
		self::$statusCode = $code;
	}

	protected static function getHeadersToSend(): array
	{
		return self::$headers;
	}

	protected function cleanUpWordPress() {
		global $wp, $wp_actions, $wp_filter, $wp_current_filter;
		wp_cache_flush();
		$wp->matched_rule = null;
		$wp_actions = $this->actionsAfterBootstrap;
		$wp_filter = $this->defaultFilters;
		$wp_current_filter = [];
		self::$headers = [];
		self::$statusCode = 200;
		$user_globals = ['user_login', 'userdata', 'user_level', 'user_ID', 'user_email', 'user_url', 'user_identity'];
		foreach ( $user_globals as $user_global ) {
			unset( $GLOBALS[ $user_global ] );
		}

		unset($GLOBALS['wp_did_header']);
		unset($GLOBALS['wp_scripts']);
		unset($GLOBALS['wp_styles']);
		unset($GLOBALS['concatenate_scripts']);
	}

	protected function setGlobals( ServerRequestInterface $request ) {
		foreach ( $request->getServerParams() as $key => $value ) {
			$_SERVER[ strtoupper( $key ) ] = $value;
		}

		foreach ( $request->getHeaders() as $key => $value ) {
			$_SERVER[ 'HTTP_' . str_replace('-', '_', strtoupper( $key ) ) ] = $value;
		}

		$_SERVER['PHP_SELF'] = preg_replace( '/(\?.*)?$/', '', $_SERVER['REQUEST_URI'] );
		$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];

		$_GET = $request->getQueryParams() ?: [];
		$_POST = $request->getParsedBody() ?: [];
		$_COOKIE = $request->getCookieParams() ?: [];
	}

	/**
	 * Loads Wordpress.
	 */
	public function loadWordpress()
	{
		// set current user.
		$GLOBALS['wp']->init();

		foreach ($this->globals as $globalVariable) {
			global ${$globalVariable};
		}

		if ( strpos( $_SERVER['PHP_SELF'], '.php' ) === false ) {
			// Set up the WordPress query.
			\wp();

			// Load the theme template.
			require ABSPATH . WPINC . '/template-loader.php';
		} else {
			require dirname( __DIR__ ) . '/web' . $_SERVER['PHP_SELF'];
		}
	}
}