<?php
/**
 * Plugin Name: API Data Fetcher
 * Description: Fetch data from a public API and store it in a custom post type.
 * Version: 1.0.2
 * Author: Mahbub
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class API_Data_Fetcher {

    const POST_TYPE = 'api_data';

    public function __construct() {
        add_action( 'init', [ $this, 'register_custom_post_type' ] );
        add_action( 'admin_menu', [ $this, 'register_fetch_data_page' ] );
        add_action( 'admin_post_fetch_api_data', [ $this, 'fetch_api_data' ] );
        add_action( 'wp', [ $this, 'schedule_cron_event' ] );
        add_action( 'api_data_fetch_cron', [ $this, 'fetch_api_data_cron' ] );
        add_shortcode( 'display_latest_api_data', [ $this, 'display_latest_api_data' ] );
    }

    public function register_custom_post_type() {
        $labels = [
            'name' => 'API Data',
            'singular_name' => 'API Data',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New API Data',
            'edit_item' => 'Edit API Data',
            'new_item' => 'New API Data',
            'view_item' => 'View API Data',
            'search_items' => 'Search API Data',
            'not_found' => 'No API Data found',
            'not_found_in_trash' => 'No API Data found in Trash',
            'all_items' => 'All API Data',
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'supports' => [ 'title', 'editor' ],
            'show_in_rest' => true,
        ];

        register_post_type( self::POST_TYPE, $args );
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
        wp_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) );
        exit;
    }

    public function fetch_api_data_cron() {
        $this->fetch_data_from_api();
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
            'post_type' => self::POST_TYPE,
            'title' => $title,
            'posts_per_page' => 1,
        ]);

        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => [
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

    public function schedule_cron_event() {
        if ( ! wp_next_scheduled( 'api_data_fetch_cron' ) ) {
            wp_schedule_event( time(), 'every_30_minutes', 'api_data_fetch_cron' );
        }
    }

    public function display_latest_api_data() {
        $query = new WP_Query([
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 1,
            'orderby' => 'meta_value',
            'meta_key' => 'date_retrieved',
            'order' => 'DESC',
        ]);

        if ( $query->have_posts() ) {
            ob_start();
            while ( $query->have_posts() ) {
                $query->the_post();
                $date_retrieved = get_post_meta( get_the_ID(), 'date_retrieved', true );
                ?>
                <div class="api-data">
                    <h2><?php the_title(); ?></h2>
                    <div class="content">
                        <pre><?php echo esc_html( get_the_content() ); ?></pre>
                    </div>
                    <p><strong>Date Retrieved:</strong> <?php echo esc_html( $date_retrieved ); ?></p>
                </div>
                <?php
            }
            wp_reset_postdata();
            return ob_get_clean();
        } else {
            return '<p>No data available.</p>';
        }
    }
}

new API_Data_Fetcher();

// Add meta box for "Date Retrieved"
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'date_retrieved',
        'Date Retrieved',
        function( $post ) {
            $date_retrieved = get_post_meta( $post->ID, 'date_retrieved', true );
            echo '<p>' . esc_html( $date_retrieved ) . '</p>';
        },
        'api_data',
        'side'
    );
});

// Register custom interval for wp_cron
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['every_30_minutes'] = [
        'interval' => 1800,
        'display' => 'Every 30 Minutes'
    ];
    return $schedules;
} );

// Unschedule the event on plugin deactivation
register_deactivation_hook( __FILE__, function() {
    $timestamp = wp_next_scheduled( 'api_data_fetch_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'api_data_fetch_cron' );
    }
});
