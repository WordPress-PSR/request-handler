<?php

namespace WordPressPsr;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use WordPressPsr\Psr7\SimpleStream;

class RequestHandler implements RequestHandlerInterface {

	const ENDPOINT_OVERRIDES = array(
		'/wp-admin/load-scripts.php' => __DIR__ . '/endpoint-overrides/wp-admin/load-scripts.php', // These rely on noop.php which is hard to avoid.
		'/wp-admin/load-styles.php'  => __DIR__ . '/endpoint-overrides/wp-admin/load-styles.php',
	);

	protected string $wordpress_path;

	/** @var array|string[] globals used by WP that must be loaded with global keyword to ensure it's always in current context. */
	protected array $globals = array(
		// major
		'wp',
		'wp_the_query',
		'wpdb',
		'wp_query',
		'allowedentitynames',
		'wp_db_version',
		//other
		'error',
		'concatenate_scripts',
		'wp_scripts',
		'wp_xmlrpc_server',
		'wp_importers',
		'wp_locale',
		'update_title',
		'total_update_count',
		'parent_file',
		'self',
		'submenu_file',
		'_wp_menu_nopriv',
		'_wp_submenu_nopriv',
		'wp_customize',
		'editor_styles',
		// rewrite
		'wp_rewrite',
		// version.php
		'wp_version',
		'wp_db_version',
		'tinymce_version',
		'required_php_version',
		'required_mysql_version',
	);

	/**
	 * @var array|string[] Global vars related to the logged in users.
	 */
	protected array $user_globals = array( 'user_login', 'userdata', 'user_level', 'user_ID', 'user_email', 'user_url', 'user_identity' );

	/**
	 * @var array|string[] Global vars related to the current page or browser.
	 */
	protected array $page_globals = array(
		//vars.php
		'pagenow',
		'is_lynx',
		'is_gecko',
		'is_winIE',
		'is_macIE',
		'is_opera',
		'is_NS4',
		'is_safari',
		'is_chrome',
		'is_iphone',
		'is_IE',
		'is_edge',
		'is_apache',
		'is_IIS',
		'is_iis7',
		'is_nginx',
		//admin
		'hook_suffix',
		'plugin_page',
		'typenow',
		'taxnow',
		'current_screen',
		'post',
		'post_type',
		'post_type_object',
		'title',
		'wp_did_header',
		'wp_scripts',
		'wp_styles',
		'concatenate_scripts',
		'wp_meta_boxes',
		'typenow',
		'menu',
		'submenu',
	);

	/**
	 * List of admin endpoints and the correct file to perform the request init.
	 * We are using a map instead of regex for performance reasons and an added security so only php file on this lisk will be included.
	 * @var array|string[]
	 */
	protected array $wp_admin_endpoints_to_bootstrap = array(
		'/wp-admin/index.php'                  => '/wp-admin/admin.php',
		'/wp-admin/about.php'                  => '/wp-admin/admin.php',
		'/wp-admin/authorize-application.php'  => '/wp-admin/admin.php',
		'/wp-admin/comment.php'                => '/wp-admin/admin.php',
		'/wp-admin/credits.php'                => '/wp-admin/admin.php',
		'/wp-admin/customize.php'              => '/wp-admin/admin.php',
		'/wp-admin/edit-comments.php'          => '/wp-admin/admin.php',
		'/wp-admin/edit-tags.php'              => '/wp-admin/admin.php',
		'/wp-admin/edit.php'                   => '/wp-admin/admin.php',
		'/wp-admin/erase-personal-data.php'    => '/wp-admin/admin.php',
		'/wp-admin/export-personal-data.php'   => '/wp-admin/admin.php',
		'/wp-admin/export.php'                 => '/wp-admin/admin.php',
		'/wp-admin/import.php'                 => '/wp-admin/admin.php',
		'/wp-admin/freedoms.php'               => '/wp-admin/admin.php',
		'/wp-admin/link-add.php'               => '/wp-admin/admin.php',
		'/wp-admin/link-manager.php'           => '/wp-admin/admin.php',
		'/wp-admin/link.php'                   => '/wp-admin/admin.php',
		'/wp-admin/media-new.php'              => '/wp-admin/admin.php',
		'/wp-admin/media-upload.php'           => '/wp-admin/admin.php',
		'/wp-admin/media.php'                  => '/wp-admin/admin.php',
		'/wp-admin/ms-admin.php'               => '/wp-admin/admin.php',
		'/wp-admin/ms-delete-site.php'         => '/wp-admin/admin.php',
		'/wp-admin/ms-edit.php'                => '/wp-admin/admin.php',
		'/wp-admin/ms-options.php'             => '/wp-admin/admin.php',
		'/wp-admin/ms-themes.php'              => '/wp-admin/admin.php',
		'/wp-admin/ms-upgrade-network.php'     => '/wp-admin/admin.php',
		'/wp-admin/ms-users.php'               => '/wp-admin/admin.php',
		'/wp-admin/ms-sites.php'               => '/wp-admin/admin.php',
		'/wp-admin/nav-menus.php'              => '/wp-admin/admin.php',
		'/wp-admin/network.php'                => '/wp-admin/admin.php',
		'/wp-admin/options-discussion.php'     => '/wp-admin/admin.php',
		'/wp-admin/options-general.php'        => '/wp-admin/admin.php',
		'/wp-admin/options-media.php'          => '/wp-admin/admin.php',
		'/wp-admin/options-permalink.php'      => '/wp-admin/admin.php',
		'/wp-admin/options-privacy.php'        => '/wp-admin/admin.php',
		'/wp-admin/options-reading.php'        => '/wp-admin/admin.php',
		'/wp-admin/options-writing.php'        => '/wp-admin/admin.php',
		'/wp-admin/options.php'                => '/wp-admin/admin.php',
		'/wp-admin/plugin-editor.php'          => '/wp-admin/admin.php',
		'/wp-admin/plugin-install.php'         => '/wp-admin/admin.php',
		'/wp-admin/plugins.php'                => '/wp-admin/admin.php',
		'/wp-admin/post-new.php'               => '/wp-admin/admin.php',
		'/wp-admin/post.php'                   => '/wp-admin/admin.php',
		'/wp-admin/press-this.php'             => '/wp-admin/admin.php',
		'/wp-admin/privacy-policy-guide.php'   => '/wp-admin/admin.php',
		'/wp-admin/privacy.php'                => '/wp-admin/admin.php',
		'/wp-admin/profile.php'                => '/wp-admin/admin.php',
		'/wp-admin/revision.php'               => '/wp-admin/admin.php',
		'/wp-admin/site-health-info.php'       => '/wp-admin/admin.php',
		'/wp-admin/site-health.php'            => '/wp-admin/admin.php',
		'/wp-admin/term.php'                   => '/wp-admin/admin.php',
		'/wp-admin/theme-editor.php'           => '/wp-admin/admin.php',
		'/wp-admin/theme-install.php'          => '/wp-admin/admin.php',
		'/wp-admin/themes.php'                 => '/wp-admin/admin.php',
		'/wp-admin/tools.php'                  => '/wp-admin/admin.php',
		'/wp-admin/update-core.php'            => '/wp-admin/admin.php',
		'/wp-admin/update.php'                 => '/wp-admin/admin.php',
		'/wp-admin/upload.php'                 => '/wp-admin/admin.php',
		'/wp-admin/user-edit.php'              => '/wp-admin/admin.php',
		'/wp-admin/user-new.php'               => '/wp-admin/admin.php',
		'/wp-admin/users.php'                  => '/wp-admin/admin.php',
		'/wp-admin/widgets.php'                => '/wp-admin/admin.php',
		'/wp-admin/user/index.php'             => '/wp-admin/user/admin.php',
		'/wp-admin/user/privacy.php'           => '/wp-admin/user/admin.php',
		'/wp-admin/user/credits.php'           => '/wp-admin/user/admin.php',
		'/wp-admin/user/about.php'             => '/wp-admin/user/admin.php',
		'/wp-admin/user/profile.php'           => '/wp-admin/user/admin.php',
		'/wp-admin/user/freedoms.php'          => '/wp-admin/user/admin.php',
		'/wp-admin/user/user-edit.php'         => '/wp-admin/user/admin.php',
		'/wp-admin/network/site-new.php'       => '/wp-admin/network/admin.php',
		'/wp-admin/network/settings.php'       => '/wp-admin/network/admin.php',
		'/wp-admin/network/theme-install.php'  => '/wp-admin/network/admin.php',
		'/wp-admin/network/site-users.php'     => '/wp-admin/network/admin.php',
		'/wp-admin/network/site-settings.php'  => '/wp-admin/network/admin.php',
		'/wp-admin/network/themes.php'         => '/wp-admin/network/admin.php',
		'/wp-admin/network/site-themes.php'    => '/wp-admin/network/admin.php',
		'/wp-admin/network/index.php'          => '/wp-admin/network/admin.php',
		'/wp-admin/network/privacy.php'        => '/wp-admin/network/admin.php',
		'/wp-admin/network/update.php'         => '/wp-admin/network/admin.php',
		'/wp-admin/network/theme-editor.php'   => '/wp-admin/network/admin.php',
		'/wp-admin/network/plugin-install.php' => '/wp-admin/network/admin.php',
		'/wp-admin/network/credits.php'        => '/wp-admin/network/admin.php',
		'/wp-admin/network/site-info.php'      => '/wp-admin/network/admin.php',
		'/wp-admin/network/edit.php'           => '/wp-admin/network/admin.php',
		'/wp-admin/network/about.php'          => '/wp-admin/network/admin.php',
		'/wp-admin/network/upgrade.php'        => '/wp-admin/network/admin.php',
		'/wp-admin/network/profile.php'        => '/wp-admin/network/admin.php',
		'/wp-admin/network/freedoms.php'       => '/wp-admin/network/admin.php',
		'/wp-admin/network/users.php'          => '/wp-admin/network/admin.php',
		'/wp-admin/network/user-edit.php'      => '/wp-admin/network/admin.php',
		'/wp-admin/network/user-new.php'       => '/wp-admin/network/admin.php',
		'/wp-admin/network/sites.php'          => '/wp-admin/network/admin.php',
		'/wp-admin/network/update-core.php'    => '/wp-admin/network/admin.php',
		'/wp-admin/network/setup.php'          => '/wp-admin/network/admin.php',
		'/wp-admin/network/plugin-editor.php'  => '/wp-admin/network/admin.php',
		'/wp-admin/network/plugins.php'        => '/wp-admin/network/admin.php',
	);

	/**
	 * @var array|string[]
	 * Valid url that can be loaded directly. Any php file requests not on this list will 404.
	 */
	protected array $wp_special_endpoints = array(
		'/wp-admin/'                 => '/wp-admin/index.php',
		'/wp-admin/setup-config.php' => '/wp-admin/setup-config.php',
		'/wp-admin/install.php'      => '/wp-admin/install.php',
		'/wp-admin/admin-ajax.php'   => '/wp-admin/admin-ajax.php',
		'/wp-login.php'              => '/wp-login.php',
		'/wp-activate.php'           => '/wp-activate.php',
		'/wp-comments-post.php'      => '/wp-comments-post.php',
		'/wp-cron.php'               => '/wp-cron.php',
		'/wp-links-opml.php'         => '/wp-links-opml.php',
		'/wp-mail.php'               => '/wp-mail.php',
		'/wp-signup.php'             => '/wp-signup.php',
		'/wp-trackback.php'          => '/wp-trackback.php',
		'/xmlrpc.php'                => '/xmlrpc.php',
	);

	protected static array $filters_after_bootstrap = array();

	protected static array $actions_after_bootstrap = array();

	protected ResponseFactoryInterface $response_factory;

	protected StreamFactoryInterface $stream_factory;

	protected bool $bootstrapped = false;

	public function __construct(
		$wordpress_path,
		ResponseFactoryInterface $response_factory,
		StreamFactoryInterface $stream_factory
	) {
		$this->response_factory = $response_factory;
		$this->stream_factory   = $stream_factory;
		$this->wordpress_path   = $wordpress_path;
	}

	public function bootstrap() {
		if ( $this->bootstrapped ) {
			return $this->bootstrapped;
		}
		// Load the WordPress library
		require $this->wordpress_path . '/wp-load.php';
		if ( ! isset( $GLOBALS['wp'] ) ) {
			return false; // Bootstrap failed.
		}

		$this->bootstrapped = true;
		return $this->bootstrapped;
	}

	public static function after_bootstrap() {
		global $wp_filter, $wp_actions;
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
		self::$filters_after_bootstrap = $wp_filter;
		self::$actions_after_bootstrap = $wp_actions;
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
		} catch ( \Exception $e ) {
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
		foreach ( $this->user_globals as $user_global ) {
			unset( $GLOBALS[ $user_global ] );
		}

		foreach ( $this->page_globals as $page_global ) {
			unset( $GLOBALS[ $page_global ] );
		}
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
		// This is the default order for $_REQUEST.
		// To maintain max compatibility we could parse the request_order ini setting, but who doesn't use the default?
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

		// Declare each global var so it's availabled in the current context when a file in required.
		foreach ( $this->globals as $global_variable ) {
			global ${$global_variable};
		}
		foreach ( $this->user_globals as $global_variable ) {
			global ${$global_variable};
		}
		foreach ( $this->page_globals as $global_variable ) {
			global ${$global_variable};
		}

		if ( isset( self::ENDPOINT_OVERRIDES[ $_SERVER['PHP_SELF'] ] ) ) {
			if ( ! $this->bootstrap() ) {
				return; // WP not setup yet. wp-config.php probably doesn't exist.
			}
				$GLOBALS['wp']->init();
				// Set up the WordPress query.
				\wp();
				require self::ENDPOINT_OVERRIDES[ $_SERVER['PHP_SELF'] ];
		} elseif ( isset( $this->wp_admin_endpoints_to_bootstrap[ $_SERVER['PHP_SELF'] ] ) ) {
			// This file must be required everytime because they are included with `require_once`.
			require $this->wordpress_path . $this->wp_admin_endpoints_to_bootstrap[ $_SERVER['PHP_SELF'] ];
			require $this->wordpress_path . $_SERVER['PHP_SELF'];
		} elseif ( isset( $this->wp_special_endpoints[ $_SERVER['PHP_SELF'] ] ) ) {
			if ( isset( $this->wp_admin_endpoints_to_bootstrap[ $this->wp_special_endpoints[ $_SERVER['PHP_SELF'] ] ] ) ) {
				// Right now it is only for /wp-admin/ without the index.php.
				require $this->wordpress_path . $this->wp_admin_endpoints_to_bootstrap[ $this->wp_special_endpoints[ $_SERVER['PHP_SELF'] ] ];
			}
			require $this->wordpress_path . $this->wp_special_endpoints[ $_SERVER['PHP_SELF'] ];
		} else {
			if ( ! defined( 'WP_USE_THEMES' ) ) {
				define( 'WP_USE_THEMES', true );
			}
			if ( ! $this->bootstrap() ) {
				return; // WP not setup yet. wp-config.php probably doesn't exist.
			}
			$GLOBALS['wp']->init();
			// Set up the WordPress query.
			\wp();

			// Load the theme template.
			require ABSPATH . WPINC . '/template-loader.php';
		}
		$this->bootstrapped = true;
	}
}
