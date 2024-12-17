<?php
/**
 * All admin facing functions
 */
namespace Codexpert\WeatherWithApi\App;
use Codexpert\Plugin\Base;
use Codexpert\Plugin\Metabox;

/**
 * if accessed directly, exit.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @package Plugin
 * @subpackage Admin
 * @author Codexpert <hi@codexpert.io>
 */
class Admin extends Base {

	public $plugin;

	/**
	 * Constructor function
	 */
	public function __construct( $plugin ) {
		$this->plugin	= $plugin;
		$this->slug		= $this->plugin['TextDomain'];
		$this->name		= $this->plugin['Name'];
		$this->server	= $this->plugin['server'];
		$this->version	= $this->plugin['Version'];
	}

	/**
	 * Internationalization
	 */
	public function i18n() {
		load_plugin_textdomain( 'weather-with-api', false, Weather_With_Api_DIR . '/languages/' );
	}

	/**
	 * Installer. Runs once when the plugin in activated.
	 *
	 * @since 1.0
	 */
	public function install() {

		if( ! get_option( 'weather-with-api_version' ) ){
			update_option( 'weather-with-api_version', $this->version );
		}
		
		if( ! get_option( 'weather-with-api_install_time' ) ){
			update_option( 'weather-with-api_install_time', time() );
		}
	}

	/**
	 * Enqueue JavaScripts and stylesheets
	 */
	public function enqueue_scripts() {
		$min = defined( 'Weather_With_Api_DEBUG' ) && Weather_With_Api_DEBUG ? '' : '.min';
		
		wp_enqueue_style( $this->slug, plugins_url( "/assets/css/admin{$min}.css", Weather_With_Api ), '', $this->version, 'all' );

		wp_enqueue_script( $this->slug, plugins_url( "/assets/js/admin{$min}.js", Weather_With_Api ), [ 'jquery' ], $this->version, true );
	}

	public function footer_text( $text ) {
		if( get_current_screen()->parent_base != $this->slug ) return $text;

		return sprintf( __( 'Built with %1$s by the folks at <a href="%2$s" target="_blank">Codexpert, Inc</a>.' ), '&hearts;', 'https://codexpert.io' );
	}

	public function register_custom_post_type() {
        $labels = [
            'name' 				=> 'API Data',
            'singular_name' 	=> 'API Data',
            'add_new' 			=> 'Add New',
            'add_new_item' 		=> 'Add New API Data',
            'edit_item' 		=> 'Edit API Data',
            'new_item' 			=> 'New API Data',
            'view_item' 		=> 'View API Data',
            'search_items' 		=> 'Search API Data',
            'not_found' 		=> 'No API Data found',
            'not_found_in_trash' => 'No API Data found in Trash',
            'all_items' 		=> 'All API Data',
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'supports' => [ 'title', 'editor' ],
            'show_in_rest' => true,
        ];

        register_post_type( 'api_data', $args );
    }

	public function modal() {
		echo '
		<div id="weather-with-api-modal" style="display: none">
			<img id="weather-with-api-modal-loader" src="' . esc_attr( Weather_With_Api_ASSET . '/img/loader.gif' ) . '" />
		</div>';
	}
}