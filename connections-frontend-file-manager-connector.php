<?php
/**
 * @package   Connections Business Directory Connector - Frontend File Manager
 * @category  Extension
 * @author    Steven A. Zahm
 * @license   GPL-2.0+
 * @link      https://connections-pro.com
 * @copyright 2020 Steven A. Zahm
 *
 * @wordpress-plugin
 * Plugin Name:       Connections Business Directory Connector - Frontend File Manager
 * Plugin URI:        https://connections-pro.com/
 * Description:       Adds a Content Block showing a list of links of the WordPress User's uploaded files.
 * Version:           1.0
 * Author:            Steven A. Zahm
 * Author URI:        https://connections-pro.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       connections-frontend-file-manager-connector
 * Domain Path:       /languages
 */

namespace Connections_Directory\Connector;

use cnLicense;
use cnOutput;
use cnTemplate;
use Connections_Link;
use WP_Query;
use WP_User;

if ( ! class_exists( 'Frontend_File_Manager' ) ) {

	class Frontend_File_Manager {

		const VERSION = '1.0.0';

		/**
		 * Stores the instance of this class.
		 *
		 * @since 1.0
		 * @var   Frontend_File_Manager
		 */
		private static $instance;

		/**
		 * @var string The absolute path this this file.
		 *
		 * @since 1.0
		 */
		private $file = '';

		/**
		 * @var string The URL to the plugin's folder.
		 *
		 * @since 1.0
		 */
		private $url = '';

		/**
		 * @var string The absolute path to this plugin's folder.
		 *
		 * @since 1.0
		 */
		private $path = '';

		/**
		 * @var string The basename of the plugin.
		 *
		 * @since 1.0
		 */
		private $basename = '';

		/**
		 * A dummy constructor to prevent Frontend_File_Manager from being loaded more than once.
		 *
		 * @access private
		 * @since 1.0
		 */
		private function __construct() { /* Do nothing here */ }

		public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof self ) ) {

				self::$instance = $self = new self;

				$self->file     = __FILE__;
				$self->url      = plugin_dir_url( $self->file );
				$self->path     = plugin_dir_path( $self->file );
				$self->basename = plugin_basename( $self->file );

				self::$instance->hooks();

				// License and Updater.
				if ( class_exists( 'cnLicense' ) ) {

					new cnLicense(
						__FILE__,
						'Frontend File Manager Connector',
						self::VERSION,
						'Steven A. Zahm'
					);
				}
			}

			return self::$instance;
		}

		private function hooks() {

			add_filter( 'cn_content_blocks', array( $this, 'registerContentBlock' ) );
			add_action( 'cn_entry_output_content-wp_frontend_file_manager_files', array( $this, 'renderContentBlock' ), 10, 3 );
		}

		/**
		 * Callback for the `cn_content_blocks` filter.
		 *
		 * This is also required so it'll be rendered by $entry->getContentBlock( 'wp_frontend_file_manager_files' ).
		 *
		 * @param array $blocks
		 *
		 * @return array
		 */
		public function registerContentBlock( $blocks ) {

			$blocks['wp_frontend_file_manager_files'] = 'Files';

			return $blocks;
		}

		/**
		 * Callback for the `cn_entry_output_content-wp_frontend_file_manager_files` action.
		 *
		 * @param cnOutput   $entry
		 * @param array      $atts
		 * @param cnTemplate $template
		 */
		public function renderContentBlock( $entry, $atts, $template ) {

			global $wp_roles;

			$requireLogin = apply_filters( 'Connections_Directory/Frontend_File_Manager/Require_Login', false );

			if ( $requireLogin ) {

				$currentUser = wp_get_current_user();
				$roles       = array_keys( $wp_roles->get_names() );
				$roles       = apply_filters( 'Connections_Directory/Frontend_File_Manager/Roles', $roles );

				if ( is_array( $currentUser->roles ) && empty( array_intersect( $currentUser->roles, $roles ) ) ) {
					return;
				}

				$capability = apply_filters( 'Connections_Directory/Frontend_File_Manager/Capability', 'read' );

				if ( ! current_user_can( $capability ) ) {
					return;
				}

			}

			$linkedUser = Connections_Link::getLinkedUser( $entry );

			if ( ! $linkedUser instanceof WP_User ) {
				return;
			}

			$query = new WP_Query(
				array(
					'author'                 => $linkedUser->ID,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'post_type'              => 'wpfm-files',
					'post_status'            => 'publish',
					'nopaging'               => true,
					'post_parent'            => 0,
					// A new WP_Query object runs five queries by default,
					// including calculating pagination and priming the term and meta caches.
					// Each of the following arguments will remove a query:
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					// Do not use posts_per_page => -1.
					// This is a performance hazard. What if we have 100,000 posts? This could crash the site.
					'posts_per_page'         => 500,
				)
			);

			if ( $query->have_posts() ) {

				/**
				 * @since 1.0
				 *
				 * @param WP_Query $query
				 * @param WP_User  $linkedUser
				 * @param cnOutput $entry
				 */
				do_action('Connections_Directory/Frontend_File_Manager/Files/Before', $query, $linkedUser, $entry );

				echo '<ul>';

				while( $query->have_posts() ) {

					$query->the_post();
					echo '<li><a href="' . get_permalink() . '" download>' . get_the_title() . '</a></li>';
				}

				echo '</ul>';

				do_action('Connections_Directory/Frontend_File_Manager/Files/After', $query, $linkedUser, $entry );
			}

			// Reset the `$post` data to the current post in main query.
			wp_reset_postdata();

		}
	}

	/**
	 * The main function responsible for returning the instance to functions everywhere.
	 *
	 * Use this function like you would a global variable, except without needing to declare the global.
	 *
	 * Example: <?php $connector = Frontend_File_Manager(); ?>
	 *
	 * If the main Connections class exists, fire up the connector. If not, throw an admin error notice.
	 *
	 * @access public
	 * @since  1.0
	 *
	 * @return Frontend_File_Manager|false Frontend_File_Manager Instance or FALSE if Connections is not active.
	 */
	function Connections_Frontend_File_Manager() {

		if ( class_exists( 'connectionsLoad' ) && class_exists( 'Connections_Link' ) ) {

			return Frontend_File_Manager::instance();

		} else {

			//add_action( 'admin_notices', 'Connections_Link_Display_Connections_Admin_Notice' );

			return FALSE;
		}

	}

	/**
	 * We'll load the extension on `plugins_loaded` so we know Connections will be loaded and ready first.
	 * Set priority 12, so we know Link is loaded first.
	 */
	add_action( 'plugins_loaded', __NAMESPACE__ . '\Connections_Frontend_File_Manager', 12 );
}
