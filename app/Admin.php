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

    public function register_fetch_data_page() {
        add_submenu_page(
            'tools.php',
            'Fetch API Data',
            'Fetch API Data',
            'manage_options',
            'fetch-api-data',
            [ $this, 'fetch_data_page_html' ]
        );
    }

     public function fetch_data_page_html() {
        ?>
        <div class="wrap">
            <h1>Fetch API Data</h1>
            <form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
                <input type="hidden" name="action" value="fetch_api_data">
                <button type="submit" class="button button-primary">Fetch Data</button>
            </form>
        </div>
        <?php
    }

     public function fetch_api_data() {
        $this->fetch_data_from_api();
        wp_redirect( admin_url( 'edit.php?post_type=' . 'api_data' ) );
        exit;
    }

    private function fetch_data_from_api() {
        $api_url = 'https://api.coindesk.com/v1/bpi/currentprice.json';

        $response = wp_remote_get( $api_url );

        if ( is_wp_error( $response ) ) {
            error_log( 'Failed to fetch API data: ' . $response->get_error_message() );
            return;
        }

        $data = wp_remote_retrieve_body( $response );
        $decoded_data = json_decode( $data, true );

        if ( empty( $decoded_data ) ) {
            error_log( 'Invalid API response.' );
            return;
        }

        $title = $decoded_data['chartName'] ?? 'Unknown Data';
        $content = wp_json_encode( $decoded_data, JSON_PRETTY_PRINT );
        $existing_post = get_posts([
            'post_type' => 'api_data',
            'title' => $title,
            'posts_per_page' => 1,
        ]);

        $post_data = [
            'post_title' 	=> $title,
            'post_content' 	=> $content,
            'post_type' 	=> 'api_data',
            'post_status' 	=> 'publish',
            'meta_input' 	=> [
                'date_retrieved' => current_time( 'mysql' ),
            ],
        ];

        if ( $existing_post ) {
            $post_data['ID'] = $existing_post[0]->ID;
            wp_update_post( $post_data );
        } else {
            wp_insert_post( $post_data );
        }
    }

	public function modal() {
		echo '
		<div id="weather-with-api-modal" style="display: none">
			<img id="weather-with-api-modal-loader" src="' . esc_attr( Weather_With_Api_ASSET . '/img/loader.gif' ) . '" />
		</div>';
	}
}