<?php
/**
 * Custom functions that act independently of the theme templates
 *
 * @package Hades_Theme
 */

if ( ! class_exists( 'Theme_Extra' ) ) {
	/**
	 * Custom theme extra class
	 */
	class Theme_Extra {
		/**
		 * Init everything here
		 */
		public function init() {
			$this->add_filters();

			$this->add_actions();

			// Register options page for ACF field
			if ( function_exists( 'acf_add_options_page' ) ) {
				acf_add_options_page(
					array(
						'page_title' => 'Theme General Settings',
						'menu_title' => 'Theme Settings',
						'menu_slug'  => 'theme-general-settings',
						'capability' => 'edit_posts',
						'redirect'   => false,
					)
				);
			}

			// Disable for post types
			// add_filter('use_block_editor_for_post_type', '__return_false', 10);
			// add_action('init', 'my_remove_editor_from_post_type');
			// function my_remove_editor_from_post_type() {
			// remove_post_type_support( 'page', 'editor' );
			// }

			// Disable WordPress Admin Bar for all users
			// add_filter( 'show_admin_bar', '__return_false' );

			add_post_type_support( 'page', 'excerpt' );
		}

		/**
		 * Add Filters
		 */
		public function add_filters() {
			add_filter( 'body_class', array( $this, 'body_class' ) );
		}

		/**
		 * Add actions
		 */
		public function add_actions() {
			add_action( 'wp_head', array( $this, 'add_ajax_url' ) );
			add_action( 'init', array( $this, 'add_categories_to_pages' ) );
			add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue_scripts' ) );
			// If ACF is installed load acf fields from local json
			if ( class_exists( 'ACF' ) ) {
				add_action( 'acf/init', array( $this, 'acf_init' ) );
			}
		}

		/**
		 * Adds custom classes to the array of body classes.
		 *
		 * @param array $classes Classes for the body element.
		 * @return array
		 */
		public function body_class( $classes ) {
			// Adds a class of group-blog to blogs with more than 1 published author.
			if ( is_multi_author() ) {
				$classes[] = 'group-blog';
			}

			// Adds a class of hfeed to non-singular pages.
			if ( ! is_singular() ) {
				$classes[] = 'hfeed';
			}

			// Add acf custom body class
			if ( class_exists( 'ACF' ) ) {
				$body_class = get_field( 'body_class', get_queried_object_id() );
				if ( $body_class ) {
					$body_class = esc_attr( trim( $body_class ) );
					$classes[]  = $body_class;
				}
			}
			return $classes;
		}

		/**
		 * Styling login form
		 */
		public function login_enqueue_scripts() {
			wp_enqueue_style( 'custom-login', get_stylesheet_directory_uri() . '/assets/css/style-login.css', array(), '1.0' );
			// wp_enqueue_script( 'custom-login', get_stylesheet_directory_uri() . '/style-login.js' );
		}

		/**
		 * Add categories and tages for pages
		 */
		public function add_categories_to_pages() {
			register_taxonomy_for_object_type( 'category', 'page' );
		}

		/**
		 * Init ACF plugin settings
		 */
		public function acf_init() {
			acf_update_setting( 'show_updates', true );
			acf_update_setting( 'google_api_key', '' );
		}
		/**
		 * Add AJAX URL in <head></head>
		 */
		public function add_ajax_url() {
			$url = wp_parse_url( home_url() );
			if ( 'https' === $url['scheme'] ) {
				$protocol = 'https';
			} else {
				$protocol = 'http';
			}
			?>
			<script type="text/javascript">
				var ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php', $protocol ) ); ?>';
			</script>
			<?php
		}
	}

	$extra = new Theme_Extra();
	$extra->init();
}


/**
 * Like get_template_part() put lets you pass args to the template file
 * Args are available in the tempalte as $template_args array
 *
 * @param string $file template file url
 * @param mixed  $template_args style argument list
 * @param mixed  $cache_args cache args
 *  https://wordpress.stackexchange.com/questions/176804/passing-a-variable-to-get-template-part
 */
function get_template_part_args( $file, $template_args = array(), $cache_args = array() ) {
	$template_args = wp_parse_args( $template_args );
	$cache_args    = wp_parse_args( $cache_args );
	if ( $cache_args ) {
		foreach ( $template_args as $key => $value ) {
			if ( is_scalar( $value ) || is_array( $value ) ) {
				$cache_args[ $key ] = $value;
			} elseif ( is_object( $value ) && method_exists( $value, 'get_id' ) ) {
				$cache_args[ $key ] = call_user_func( 'get_id', $value );
			}
		}
		// phpcs:disabled WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache = wp_cache_get( $file, serialize( $cache_args ) );
		if ( false !== $cache ) {
			if ( ! empty( $template_args['return'] ) ) {
				return $cache;
			}
			// phpcs:disabled WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $cache;
			return;
		}
	}
	$file_handle = $file;
	do_action( 'start_operation', 'hm_template_part::' . $file_handle );
	if ( file_exists( get_stylesheet_directory() . '/' . $file . '.php' ) ) {
		$file = get_stylesheet_directory() . '/' . $file . '.php';
	} elseif ( file_exists( get_template_directory() . '/' . $file . '.php' ) ) {
		$file = get_template_directory() . '/' . $file . '.php';
	}
	ob_start();
	$return = require $file;
	$data   = ob_get_clean();
	do_action( 'end_operation', 'hm_template_part::' . $file_handle );
	if ( $cache_args ) {
		wp_cache_set( $file, $data, serialize( $cache_args ), 3600 );
	}
	if ( ! empty( $template_args['return'] ) ) {
		if ( false === $return ) {
			return false;
		} else {
			return $data;
		}
	}
	echo $data;
}
