<?php

namespace Tgc\WordPressPsr;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Tgc\WordPressPsr\Psr7\SimpleStream;

class RequestHandler implements RequestHandlerInterface {

	protected string $wordpress_path;

	protected array $globals = array(
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

	protected array $default_filters = array();

	protected array $actions_after_bootstrap = array();

	protected ResponseFactoryInterface $response_factory;

	protected StreamFactoryInterface $stream_factory;

	protected bool $bootstrapped = false;

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
			return $this->bootstrapped;
		}
		global $wp_filter, $wp_actions;
//		define( 'WPMU_PLUGIN_DIR', __DIR__ . '/mu-plugins' );
		//      $_SERVER['HTTP_HOST'] = 'localhost';
		$_SERVER['SERVER_NAME'] = gethostname();
//		define( 'WP_USE_THEMES', true );
		// Load the WordPress library
		require $this->wordpress_path . '/wp-load.php';
		if ( ! isset( $GLOBALS['wp'] ) ) {
			return false; // Bootstrap failed.
		}

		$this->default_filters         = $wp_filter;
		$this->actions_after_bootstrap = $wp_actions;
		return $this->bootstrapped     = true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle( ServerRequestInterface $request ) : ResponseInterface {
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
		$headers  = Headers::get_headers();
		$response = $this->response_factory->createResponse( Headers::get_status_code() )
			->withBody( new SimpleStream( $content ) );

		foreach ( $headers as $header => $header_value ) {
			$response = $response->withHeader( $header, $header_value );
		}
		$response = Headers::get_cookies()->renderIntoSetCookieHeader( $response );

		$this->cleanUpWordPress();
		return $response;
	}

	protected function cleanUpWordPress() {
		global $wp, $wp_actions, $wp_filter, $wp_current_filter;

		Headers::reset();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( $wp ) {
			$wp->matched_rule = null;
		}
		$wp_actions        = $this->actions_after_bootstrap;
		$wp_filter         = $this->default_filters;
		$wp_current_filter = array();
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
			if ( ! $this->bootstrap() ) {
				return; // WP not setup yet. wp-config.php probably doesn't exist.
			}
			if ( ! isset( $GLOBALS['wp'] ) ) {
				return; // WP not setup yet. wp-config.php probably doesn't exist.
			}
			$GLOBALS['wp']->init();
			// Set up the WordPress query.
			\wp();

			// Load the theme template.
			require ABSPATH . WPINC . '/template-loader.php';

		} elseif ( $is_php_file_request ) {
			if ( file_exists( $this->wordpress_path . $_SERVER['PHP_SELF'] ) ) {
				require $this->wordpress_path . $_SERVER['PHP_SELF'];
			}
		} else {
			require $this->wordpress_path . $_SERVER['PHP_SELF'] . 'index.php';
		}
	}
}

function require_file($fileIdentifier, $file)
{
	require $file;
}
