<?php 
/*
 * Plugin Name: WK Tournament
 * Description: Get tournament data from external api
 * Plugin URI: https://webkonsulenterne.dk/
 * Author: Md Rashedul Islam, Webkonsulenterne
 * Author URI: https://webkonsulenterne.dk/
 * Version: 1.0
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wk-tournament
 * Domain Path: /languages
 * php version 8.1.2
*/

require __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$api_key = $_ENV['API_KEY'];

add_action('wp_enqueue_scripts', 'wk_tournament_form_assets_enqueue');
function wk_tournament_form_assets_enqueue(){
    $file = plugin_dir_path(__FILE__) . '/assets/js/frontend.js';
    wp_enqueue_script('wk-tournament-frontend-script', plugins_url('/assets/js/frontend.js', __FILE__), array('jquery'), filemtime($file), true);
    
    $get_tournament_nonce = wp_create_nonce('get_wk_tournaments');
    wp_localize_script(
        'wk-tournament-frontend-script', 
        'wkTournament', 
        [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'error'   => __('Something went wrong', 'wk-tournament'),
            'nonce'   => $get_tournament_nonce,
        ] 
    );
}

add_action( 'init', 'wk_tournament_register_post_type' );
function wk_tournament_register_post_type(){
	$labels = array(
	'name'               => _x('Tournaments', 'post type general name', 'wk-tournament'),
	'singular_name'      => _x('Tournament', 'post type singular name', 'wk-tournament'),
	'menu_name'          => _x('Tournaments', 'admin menu', 'wk-tournament'),
	'name_admin_bar'     => _x('Tournament', 'add new on admin bar', 'wk-tournament'),
	'add_new'            => _x('Add New', 'tournament', 'wk-tournament'),
	'add_new_item'       => __('Add New Tournament', 'wk-tournament'),
	'new_item'           => __('New Tournament', 'wk-tournament'),
	'edit_item'          => __('Edit Tournament', 'wk-tournament'),
	'view_item'          => __('View Tournament', 'wk-tournament'),
	'all_items'          => __('All Tournaments', 'wk-tournament'),
	'search_items'       => __('Search Tournaments', 'wk-tournament'),
	'parent_item_colon'  => __('Parent Tournaments:', 'wk-tournament'),
	'not_found'          => __('No tournaments found.', 'wk-tournament'),
	'not_found_in_trash' => __('No tournaments found in Trash.', 'wk-tournament')
	);

	$args = array(
	'labels'             => $labels,
	'public'             => true,
	'publicly_queryable' => true,
	'show_ui'            => true,
	'show_in_menu'       => true,
	'query_var'          => true,
	'rewrite'            => array( 'slug' => 'tournament' ),
	'capability_type'    => 'post',
	'has_archive'        => true,
	'hierarchical'       => false,
	'menu_position'      => null,
	'supports'           => array( 'title' )
	);

	register_post_type('tournament', $args);
}

// Add custom columns to the post list
function custom_columns_head($defaults) {
	unset($defaults['date']);
    $defaults['seasonid'] = 'Season ID';
    $defaults['tournament_id'] = 'Tournament ID';
    $defaults['tournament_type'] = 'Tournament Type';
    $defaults['judges'] = 'Judges';
    $defaults['notes'] = 'Notes';
    $defaults['date'] = 'Date';
    return $defaults;
}
add_filter('manage_tournament_posts_columns', 'custom_columns_head');

// Populate custom columns with custom field values
function custom_columns_content($column_name, $post_ID) {
    switch ($column_name) {
        case 'seasonid':
            echo get_post_meta($post_ID, 'seasonid', true);
            break;
        case 'tournament_id':
            echo get_post_meta($post_ID, 'tournament_id', true);
            break;
        case 'tournament_type':
            echo get_post_meta($post_ID, 'tournament_type', true);
            break;
        case 'judges':
            echo get_post_meta($post_ID, 'judges', true);
            break;
        case 'notes':
            echo get_post_meta($post_ID, 'notes', true);
            break;
    }
}
add_action('manage_tournament_posts_custom_column', 'custom_columns_content', 10, 2);


add_shortcode('get_tournaments_button', 'wk_get_tournaments_button_shortcode' );
function wk_get_tournaments_button_shortcode(){
	return '<button id="fetchTournaments">Fetch Tournaments</button>';
}


add_action('wp_ajax_wk_fetch_tournaments', 'wk_fetch_tournaments_callback' );
add_action('wp_ajax_nopriv_wk_fetch_tournaments', 'wk_fetch_tournaments_callback');

function wk_fetch_tournaments_callback(){

	global $api_key;

	if (! wp_verify_nonce($_REQUEST['_wpnonce'], 'get_wk_tournaments') ) {
		wp_send_json_error(
			[
			'message' => __('Nonce verification failed!', 'wk-tournament')
			 ] 
		);
	}

	$seasonID = 42;

	$api_url = 'http://api.sportsadmin.dk/api/v1/GetTournaments?seasonID=' . $seasonID;

	$response = wp_remote_get($api_url, array('headers' => array('ApiKey' => $api_key)));
	error_log(print_r( $response, 1 ));

	if (!is_wp_error($response)) {
		if ($response['response']['code'] === 200) {
			$body = wp_remote_retrieve_body($response);

			$tournaments = json_decode($body);
		
			if (empty($tournaments)) {
				wp_send_json_success(
					[
					'message' => __('No tournaments found.!', 'wk-tournament')
					]
				);
			}

			foreach ($tournaments as $post) {
				$existing_post = get_posts(array(
					'post_type' => 'tournament',
					'meta_key' => 'tournament_id',
					'meta_value' => $post->TournamentID,
					'posts_per_page' => 1
				));
			
				if ($existing_post) {
					$post_id = $existing_post[0]->ID;
					$updated_post_data = array(
						'post_title' => $post->Name,
						'meta_input' => array(
							'seasonid' => $post->SeasonID,
							'tournament_id' => $post->TournamentID,
							'tournament_type' => $post->TournamentType
						)
					);
					wp_update_post(array_merge(['ID' => $post_id], $updated_post_data));
				} else {
					$new_post_data = array(
						'post_title' => $post->Name,
						'post_type' => 'tournament',
						'meta_input' => array(
							'seasonid' => $post->SeasonID,
							'tournament_id' => $post->TournamentID,
							'tournament_type' => $post->TournamentType
						),
						'post_status' => 'publish'
					);
					wp_insert_post($new_post_data);
				}
			}
			wp_send_json_success(
				[
				'message' => __('Tournament posts has been updated successfully!', 'wk-tournament')
				]
			);
		} else {
			$response_message = wp_remote_retrieve_response_message($response);
			wp_send_json_error([
				'message' => $response_message
			]);
		}
		
	} else {
		$error_message = $response->get_error_message();
		error_log(print_r( $error_message, 1 ));
		wp_send_json_error([
			'message' => $error_message
		]);
	}

}