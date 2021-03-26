<?php

namespace Tgc\WordPressPsr;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Tgc\WordPressPsr\Psr7\SimpleStream;

class RequestHandler implements RequestHandlerInterface {

	const ENDPOINT_OVERRIDES = array(
		'/wp-admin/load-scripts.php' => __DIR__ . '/endpoint-overrides/wp-admin/load-scripts.php', // These rely on noop.php which is hard to avoid.
		'/wp-admin/load-styles.php'  => __DIR__ . '/endpoint-overrides/wp-admin/load-styles.php',
	);

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
		//vars.php
		'pagenow',
		'is_lynx',
		'is_gecko',
		'is_winIE',
		'is_macIE',
		'is_opera', 'is_NS4', 'is_safari', 'is_chrome', 'is_iphone', 'is_IE', 'is_edge',
		'is_apache', 'is_IIS', 'is_iis7', 'is_nginx',
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
		// edit form blocks
		'post_type',
		'post_type_object',
		'post',
		'editor_styles',
		'wp_meta_boxes',
	);

	protected static array $filters_after_bootstrap = array();

	protected static array $actions_after_bootstrap = array();

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
		define( 'WPMU_PLUGIN_DIR', __DIR__ . '/mu-plugins' );
	}

	public function bootstrap() {
		if ( $this->bootstrapped ) {
			return $this->bootstrapped;
		}
		//      $_SERVER['HTTP_HOST'] = 'localhost';
		//      define( 'WP_USE_THEMES', true );
		// Load the WordPress library
		require $this->wordpress_path . '/wp-load.php';
		if ( ! isset( $GLOBALS['wp'] ) ) {
			return false; // Bootstrap failed.
		}

		return $this->bootstrapped     = true;
	}

	public static function after_bootstrap() {
		global $wp_filter, $wp_actions;
		self::$filters_after_bootstrap = $wp_filter;
		self::$actions_after_bootstrap = $wp_actions;

		add_action(
			'wp_exit',
			function( $message ) {
				echo $message;
				throw new PrematureExitException( 'wp_exit' );
			},
			100
		);

		add_action(
			'wp_header',
			function( $header, $replace = true, $response_code = null ) {
				Headers::add_header( $header, $replace, $response_code );
			},
			100,
			3
		);

		add_action(
			'wp_header_remove',
			function( $header ) {
				Headers::remove_header( $header );
			}
		);

		add_action(
			'wp_set_cookie',
			function( $name, $value = '', $expires_or_options = 0, $path = '', $domain = '', $secure = false, $httponly = false ) {
				Headers::set_cookie( $name, $value, $expires_or_options, $path, $domain, $secure, $httponly );
			},
			100,
			7
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle( ServerRequestInterface $request ) : ResponseInterface {
		$this->set_globals( $request );
		ob_start();

		try {
			$this->load_wordpress();
		} catch ( PrematureExitException $e ) {
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

		$this->clean_up();
		return $response;
	}

	protected function clean_up() {
		global $wp, $wp_actions, $wp_filter, $wp_current_filter;

		Headers::reset();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( $wp ) {
			$wp->matched_rule = null;
		}
		$wp_actions        = self::$actions_after_bootstrap;
		$wp_filter         = self::$filters_after_bootstrap;
		$wp_current_filter = array();
		$user_globals      = array( 'user_login', 'userdata', 'user_level', 'user_ID', 'user_email', 'user_url', 'user_identity' );
		foreach ( $user_globals as $user_global ) {
			unset( $GLOBALS[ $user_global ] );
		}

		$page_globals = array(
			'pagenow',
			'is_lynx',
			'is_gecko',
			'is_winIE',
			'is_macIE',
			'is_opera', 'is_NS4', 'is_safari', 'is_chrome', 'is_iphone', 'is_IE', 'is_edge',
			'is_apache', 'is_IIS', 'is_iis7', 'is_nginx',
			'hook_suffix', 'plugin_page', 'typenow', 'taxnow',
			'current_screen',
		);
		foreach ( $page_globals as $page_global ) {
			unset( $GLOBALS[ $page_global ] );
		}

		unset( $GLOBALS['wp_did_header'] );
		unset( $GLOBALS['wp_scripts'] );
		unset( $GLOBALS['wp_styles'] );
		unset( $GLOBALS['concatenate_scripts'] );
		unset( $GLOBALS['wp_meta_boxes'] );
		unset( $GLOBALS['typenow'] );
	}

	protected function set_globals( ServerRequestInterface $request ) {
		foreach ( $request->getServerParams() as $key => $value ) {
			$_SERVER[ strtoupper( $key ) ] = $value;
		}

		foreach ( $request->getHeaders() as $key => $value ) {
			$_SERVER[ 'HTTP_' . str_replace( '-', '_', strtoupper( $key ) ) ] = $value[0];
		}

		$_SERVER['SERVER_NAME'] = gethostname();
		$_SERVER['PHP_SELF']    = preg_replace( '/(\?.*)?$/', '', $_SERVER['REQUEST_URI'] );
		$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];

		$_GET    = $request->getQueryParams() ?: array();
		$_POST   = $request->getParsedBody() ?: array();
		$_COOKIE = $request->getCookieParams() ?: array();
		// Bonus points: parse the request_order ini setting
		$_REQUEST = $_COOKIE + $_POST + $_GET;
	}

	protected function request_bootstrap() {
		if ( ! $this->bootstrapped ) {
			return;
		}
		// Set vars based on request.
		require ABSPATH . WPINC . '/vars.php';
	}

	/**
	 * Loads WordPress.
	 *
	 * @throws PrematureExitException
	 */
	public function load_wordpress() {
		$this->request_bootstrap();
		// set current user.

		foreach ( $this->globals as $globalVariable ) {
			global ${$globalVariable};
		}

		//      do_action( 'plugins_loaded' );

		$is_php_file_request = strpos( $_SERVER['PHP_SELF'], '.php' ) !== false;

		if ( '/' === $_SERVER['PHP_SELF'] || ( ! $is_php_file_request
			&& ! file_exists( $this->wordpress_path . $_SERVER['PHP_SELF'] . 'index.php' ) ) ) {
			if ( ! defined( 'WP_USE_THEMES' ) ) {
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
			if ( isset( self::ENDPOINT_OVERRIDES[ $_SERVER['PHP_SELF'] ] ) ) {
				if ( ! $this->bootstrap() ) {
					return; // WP not setup yet. wp-config.php probably doesn't exist.
				}
				$GLOBALS['wp']->init();
				// Set up the WordPress query.
				\wp();
				require self::ENDPOINT_OVERRIDES[ $_SERVER['PHP_SELF'] ];
			} elseif ( file_exists( $this->wordpress_path . $_SERVER['PHP_SELF'] ) ) {
				require $this->wordpress_path . $_SERVER['PHP_SELF'];
			}
		} else {
			require $this->wordpress_path . $_SERVER['PHP_SELF'] . 'index.php';
		}
		$this->bootstrapped = true;
	}
}
