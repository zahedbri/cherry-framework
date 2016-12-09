<?php
/**
 * Module Name: Database Updater
 * Description: Module loads templater.
 * Version: 1.0.0
 * Author: Cherry Team
 * Author URI: http://www.cherryframework.com/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package    Db_Updater
 * @subpackage Modules
 * @version    1.0.0
 * @author     Cherry Team <cherryframework@gmail.com>
 * @copyright  Copyright (c) 2012 - 2016, Cherry Team
 * @link       http://www.cherryframework.com/
 * @license    http://www.gnu.org/licenses/gpl-3.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_Db_Updater' ) ) {

	/**
	 * Class Cherry Db Updater.
	 *
	 * @since 1.0.0
	 */
	class Cherry_Db_Updater {

		/**
		 * Module arguments.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var    array
		 */
		private $args = array(
			'callbacks' => array(),
			'slug'      => null,
			'version'   => null,
		);

		/**
		 * Option key for DB version.
		 *
		 * @var string
		 */
		protected $version_key = '%s-db-version';

		/**
		 * Nonce format.
		 *
		 * @var string
		 */
		protected $nonce = '_%s-db-update-nonce';

		/**
		 * Messages array
		 *
		 * @var array
		 */
		protected $messages = array();

		/**
		 * Update done trigger.
		 *
		 * @var boolean
		 */
		protected $updated = false;

		/**
		 * Core instance
		 *
		 * @var object
		 */
		public $core = null;

		/**
		 * Cherry_Db_Updater constructor.
		 *
		 * @since 1.0.0
		 * @access public
		 * @return void
		 */
		public function __construct( $core = null, $args = array() ) {

			$this->core = $core;
			$this->args = wp_parse_args( $args, $this->args );

			if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
				return;
			}

			add_action( 'admin_notices', array( $this, 'init_notices' ) );
			add_action( 'admin_init',    array( $this, 'do_update' ) );

			/**
			 * @todo  Prepare strings for translate.
			 */
			$this->messages = array(
				'error'   => 'Module DB Updater init error in <b>%s</b> - version and slug is required arguments',
				'update'  => 'We need to update your database to the latest version.',
				'updated' => 'Update complete, thank you for updating to the latest version!',
			);

		}

		/**
		 * Process DB update.
		 */
		public function do_update() {

			if ( ! $this->is_current_update() ) {
				return;
			}

			$callbacks = $this->prepare_callbacks();

			if ( ! empty( $callbacks ) ) {
				foreach ( $callbacks as $callback ) {
					if ( is_callable( $callback ) ) {
						call_user_func( $callback );
					}
				}
			}

			$this->set_updated();
		}

		/**
		 * Finalize update
		 */
		public function set_updated() {
			$this->updated = true;
			$option        = sprintf( $this->version_key, esc_attr( $this->args['slug'] ) );
			update_option( $option, esc_attr( $this->args['version'] ) );
		}

		/**
		 * Prepare callbacks array.
		 *
		 * @return array
		 */
		private function prepare_callbacks() {

			$callbacks = array();

			if ( empty( $this->args['callbacks'] ) ) {
				return $callbacks;
			}

			foreach ( $this->args['callbacks'] as $ver => $ver_cb ) {

				if ( version_compare( $this->get_current_version(), $ver, '<' ) ) {

					$callbacks = array_merge( $callbacks, $ver_cb );

				}

			}

			return $callbacks;

		}

		/**
		 * Check if we processed update for plugin passed in arguments.
		 *
		 * @return boolean
		 */
		private function is_current_update() {

			if ( empty( $_GET['cherry_db_update'] ) || empty( $_GET['slug'] ) || empty( $_GET['_nonce'] ) ) {
				return false;
			}

			if ( $_GET['slug'] !== $this->args['slug'] ) {
				return false;
			}

			$nonce_action = sprintf( $this->nonce, esc_attr( $this->args['slug'] ) );

			if ( ! wp_verify_nonce( $_GET['_nonce'], $nonce_action ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Init admin notices.
		 *
		 * @return void
		 */
		public function init_notices() {

			$enabled = $this->validate_module_args();

			if ( ! $enabled ) {
				return;
			}

			$slug = esc_attr( $this->args['slug'] );

			if ( $this->is_update_required() ) {
				$this->show_notice( $slug );
			}

			if ( $this->is_updated() ) {
				$this->show_updated_notice( $slug );
			}

		}

		/**
		 * Returns current DB version.
		 *
		 * @return string
		 */
		private function get_current_version() {
			$option = sprintf( $this->version_key, esc_attr( $this->args['slug'] ) );
			return get_option( $option, '1.0.0' );
		}

		/**
		 * Check if database requires update
		 *
		 * @return bool
		 */
		private function is_update_required() {
			$current = $this->get_current_version();
			return version_compare( $current, esc_attr( $this->args['version'] ), '<' );
		}

		/**
		 * Check if update was succesfully done.
		 *
		 * @return bool
		 */
		private function is_updated() {

			if ( ! $this->is_current_update() ) {
				return false;
			}

			return (bool) $this->updated;

		}

		/**
		 * Validate module arguments.
		 *
		 * @return bool
		 */
		private function validate_module_args() {

			if ( empty( $this->args['slug'] ) || empty( $this->args['version'] ) ) {
				echo '<div class="error"><p>';
				printf(
					$this->messages['error'],
					str_replace( untrailingslashit( ABSPATH ), '', $this->core->settings['base_dir'] )
				);
				echo '</p></div>';

				return false;
			}

			return true;

		}

		/**
		 * Show notice.
		 *
		 * @param  string $slug Plugin slug.
		 * @return void
		 */
		private function show_notice( $slug ) {

			echo '<div class="notice notice-info">';
				echo '<p>';
					$this->notice_title( $slug );
					echo $this->messages['update'];
				echo '</p>';
				echo '<p>';
					$this->notice_submit( $slug );
				echo '</p>';
			echo '</div>';

		}

		/**
		 * Show update notice.
		 *
		 * @return void
		 */
		private function show_updated_notice() {

			$slug = esc_attr( $this->args['slug'] );

			echo '<div class="notice notice-success is-dismissible">';
				echo '<p>';
					$this->notice_title( $slug );
					echo $this->messages['updated'];
				echo '</p>';
			echo '</div>';

		}

		/**
		 * Show plugin notice submit button.
		 *
		 * @param  string $slug Plugin slug.
		 * @return void
		 */
		private function notice_submit( $slug = '' ) {

			/**
			 * @todo  Prepare strings for translate.
			 */
			$format = '<a href="%1s" class="button button-primary">%2$s</a>';
			$label  = 'Start Update';
			$url    = add_query_arg(
				array(
					'cherry_db_update' => true,
					'slug'             => $slug,
					'_nonce'           => $this->create_nonce( $slug ),
				),
				esc_url( admin_url( 'index.php' ) )
			);

			printf( $format, $url, $label );

		}

		/**
		 * Create DB update nonce
		 *
		 * @param  string $slug Plugin slug.
		 * @return string
		 */
		private function create_nonce( $slug ) {
			return wp_create_nonce( sprintf( $this->nonce, $slug ) );
		}

		/**
		 * Show plugin notice title.
		 *
		 * @param  string $slug Plugin slug.
		 * @return void
		 */
		private function notice_title( $slug ) {

			$name = str_replace( '-', ' ', $slug );
			$name = ucwords( $name );

			/**
			 * @todo  Prepare strings for translate.
			 */
			printf( '<strong>%s Data Update</strong> &#8211; ', $name );

		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return object
		 */
		public static function get_instance( $core = null, $args = array() ) {
			return new self( $core, $args );
		}
	}
}
