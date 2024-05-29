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
*/

require __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$api_key = $_ENV['API_KEY'];


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


function enqueue_sweetalert2_admin() {
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array('jquery'), '11', true);

    wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', array(), '11');
}


function wk_tournament_admin_assets_enqueue() {
	if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'tournament-settings') {
        wk_tournament_form_assets_enqueue();
		enqueue_sweetalert2_admin();
	}
}
add_action('admin_enqueue_scripts', 'wk_tournament_admin_assets_enqueue');


function wk_tournament_frontend_assets_enqueue() {
    wk_tournament_form_assets_enqueue();
}
add_action('wp_enqueue_scripts', 'wk_tournament_frontend_assets_enqueue');





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
	'show_in_menu'       => false,
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
function wk_tournament_posts_custom_columns_content($column_name, $post_ID) {
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
add_action('manage_tournament_posts_custom_column', 'wk_tournament_posts_custom_columns_content', 10, 2);


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

	$seasonID = sanitize_text_field($_POST['tournamentID']);

	$api_url = 'http://api.sportsadmin.dk/api/v1/GetTournaments?seasonID=' . $seasonID;

	$response = wp_remote_get($api_url, array('headers' => array('ApiKey' => $api_key)));

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


/**
 * Registers a custom post type for matches.
 * 
 * This function is responsible for creating a new custom post type called 'match'
 * with the WordPress backend, allowing users to manage 'Match' content separately.
 */
function wk_tournament_register_post_type_matches() {
	$labels = array(
		'name'               => _x('Matches', 'post type general name', 'wk-tournament'),
		'singular_name'      => _x('Match', 'post type singular name', 'wk-tournament'),
		'menu_name'          => _x('Matches', 'admin menu', 'wk-tournament'),
		'name_admin_bar'     => _x('Match', 'add new on admin bar', 'wk-tournament'),
		'add_new'            => _x('Add New', 'match', 'wk-tournament'),
		'add_new_item'       => __('Add New Match', 'wk-tournament'),
		'new_item'           => __('New Match', 'wk-tournament'),
		'edit_item'          => __('Edit Match', 'wk-tournament'),
		'view_item'          => __('View Match', 'wk-tournament'),
		'all_items'          => __('All Matches', 'wk-tournament'),
		'search_items'       => __('Search Matches', 'wk-tournament'),
		'parent_item_colon'  => __('Parent Matches:', 'wk-tournament'),
		'not_found'          => __('No matches found.', 'wk-tournament'),
		'not_found_in_trash' => __('No matches found in Trash.', 'wk-tournament')
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_in_rest'       => true,
		'show_ui'            => true,
		'show_in_menu'       => false,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'match' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => 55,
		'supports'           => array( 'title' )
	);

	register_post_type('match', $args);
}
add_action('init', 'wk_tournament_register_post_type_matches');


/**
 * Shortcode function to generate a button that fetches matches.
 *
 * This function returns an HTML button element with an ID that
 * can be used to trigger an event, such as fetching matches from the database.
 * 
 * @return string HTML markup for the button.
 */
function wk_get_matches_button_shortcode(){
	return '<button id="fetchMatches">'.__('Get Matches', 'wk-tournament').'</button>';
}
add_shortcode('get_matches_button', 'wk_get_matches_button_shortcode' );


add_action('wp_ajax_wk_fetch_tournament_schedule', 'wk_fetch_tournament_schedule_callback' );
add_action('wp_ajax_nopriv_wk_fetch_tournament_schedule', 'wk_fetch_tournament_schedule_callback');


function wk_fetch_tournament_schedule_callback() {

		global $api_key;
	
		if (! wp_verify_nonce($_REQUEST['_wpnonce'], 'get_wk_tournaments') ) {
			wp_send_json_error(
				[
				'message' => __('Nonce verification failed!', 'wk-tournament')
				 ] 
			);
		}

		$tournamentID = sanitize_text_field($_POST['tournamentID']);
	
		$api_url = 'http://api.sportsadmin.dk/api/v1/GetTournamentSchedule?tournamentID=' . $tournamentID;
	
		$response = wp_remote_get($api_url, array('headers' => array('ApiKey' => $api_key)));
	
		if (!is_wp_error($response)) {
			if ($response['response']['code'] === 200) {
				$body = wp_remote_retrieve_body($response);
	
				$matches = json_decode($body);
			
				if (empty($matches)) {
					wp_send_json_success(
						[
						'message' => __('No Matches found.!', 'wk-tournament')
						]
					);
				}
	
				foreach ($matches as $match) {
					$existingMatch = get_posts(array(
						'post_type' => 'match',
						'meta_key' => 'gameID',
						'meta_value' => $match->gameID,
						'posts_per_page' => 1
					));


					$officialsList = implode('<br>', array_map(function ($official) {
						return esc_html($official->role) . ': ' . esc_html($official->name);
					}, $match->gameOfficials));
				
					if ($existingMatch) {
						$post_id = $existingMatch[0]->ID;
						$updated_post_data = array(
							'post_title' => $match->ArenaName,
							'meta_input' => array(
								'gameDate' => date('Y-m-d H:i:s', strtotime($match->gameDate)),
								'ArenaName' => $match->ArenaName,
								'oponents' => esc_html($match->homeTeamDisplayName) . ' vs. ' . esc_html($match->awayTeamDisplayName),
								'score' =>  esc_html($match->homeTeamGoals) . ' - ' . esc_html($match->awayTeamGoals),
								'homeTeamDisplayName' => $match->homeTeamDisplayName,
								'awayTeamDisplayName' => $match->awayTeamDisplayName,
								'homeTeamGoals' => $match->homeTeamGoals,
								'awayTeamGoals' => $match->awayTeamGoals,
								'officials' => $officialsList,
								'gameID' => $match->gameID,
								'leagueID' => $match->leagueID
							)
						);
						wp_update_post(array_merge(['ID' => $post_id], $updated_post_data));
					} else {
						$new_post_data = array(
							'post_title' => $match->ArenaName,
							'post_type' => 'match',
							'meta_input' => array(
								'gameDate' => date('Y-m-d H:i:s', strtotime($match->gameDate)),
								'ArenaName' => $match->ArenaName,
								'oponents' => esc_html($match->homeTeamDisplayName) . ' vs. ' . esc_html($match->awayTeamDisplayName),
								'score' =>  esc_html($match->homeTeamGoals) . ' - ' . esc_html($match->awayTeamGoals),
								'homeTeamDisplayName' => $match->homeTeamDisplayName,
								'awayTeamDisplayName' => $match->awayTeamDisplayName,
								'homeTeamGoals' => $match->homeTeamGoals,
								'awayTeamGoals' => $match->awayTeamGoals,
								'officials' => $officialsList,
								'gameID' => $match->gameID,
								'leagueID' => $match->leagueID
							),
							'post_status' => 'publish'
						);
						wp_insert_post($new_post_data);
					}
				}
				wp_send_json_success(
					[
					'message' => __('Match posts has been updated successfully!', 'wk-tournament')
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


function wk_tournament_schedule_posts_custom_columns_head($defaults) {
	unset($defaults['date']);
    $defaults['gameDate'] = 'Dating';
    $defaults['ArenaName'] = 'Arena Name';
    $defaults['oponents'] = 'Match';
    $defaults['score'] = 'Score';
    $defaults['officials'] = 'Officials';
    $defaults['gameID'] = 'Game ID';
    $defaults['leagueID'] = 'Tournament ID';
    return $defaults;
}
add_filter('manage_match_posts_columns', 'wk_tournament_schedule_posts_custom_columns_head');

	function wk_tournament_schedule_posts_custom_columns_content($column_name, $post_ID) {
		switch ($column_name) {
			case 'gameDate':
				echo get_post_meta($post_ID, 'gameDate', true);
				break;
			case 'ArenaName':
				echo get_post_meta($post_ID, 'ArenaName', true);
				break;
			case 'oponents':
				echo get_post_meta($post_ID, 'oponents', true);
				break;
			case 'score':
				echo get_post_meta($post_ID, 'score', true);
				break;
			case 'officials':
				echo get_post_meta($post_ID, 'officials', true);
				break;
			case 'gameID':
				echo get_post_meta($post_ID, 'gameID', true);
				break;
			case 'leagueID':
				echo get_post_meta($post_ID, 'leagueID', true);
				break;
		}
	}
	add_action('manage_match_posts_custom_column', 'wk_tournament_schedule_posts_custom_columns_content', 10, 2);

	function my_custom_menu() {
		add_menu_page(
			'Tournament Settings',
			'Tournaments',
			'manage_options',
			'tournament-settings',
			'tournament_settings_page',
			'dashicons-admin-generic',
			20
		);
	
		add_submenu_page(
			'tournament-settings',
			'Tournament Settings',
			'Settings',
			'manage_options',
			'tournament-settings',
			'tournament_settings_page'
		);
	
		add_submenu_page(
			'tournament-settings',
			'Tournament List',
			'Tournaments',
			'manage_options',
			'edit.php?post_type=tournament'
		);
	
		add_submenu_page(
			'tournament-settings',
			'Match List',
			'Matches',
			'manage_options',
			'edit.php?post_type=match'
		);
	}
	add_action('admin_menu', 'my_custom_menu');

	function tournament_settings_page() {
		?>
		<div class="wrap">
			<h1>Tournament Settings</h1>
			<form id="tournament-settings-form">
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Tournament/Season ID</th>
						<td><input type="text" id="tournament-id" name="tournament_id" value="" /></td>
					</tr>
				</table>
				<button type="button" id="fetchTournaments" class="button button-primary" style="margin-right: 20px;">Fetch Season</button>
				<button type="button" id="fetchMatches" class="button button-secondary">Fetch Tournament Schedule</button>
			</form>
			<div id="response"></div>
		</div>
    <?php
}


function wpse_get_dynamic_schedule_shortcode($atts) {
	error_log(print_r( $atts, 1 ));
    // Extract shortcode attributes
    $atts = shortcode_atts(
        array(
            'teamname' => '',
            'tournamentid' => 0
        ),
        $atts,
        'get_dynamic_schedule'
    );

    $teamName = sanitize_text_field($atts['teamname']);
    $tournamentID = sanitize_text_field($atts['tournamentid']);

    // If the tournamentID is not valid or teamName is empty, display an error message
    if ($tournamentID <= 0 || empty($teamName)) {
        return 'Invalid team name or tournament ID.';
    }

    // Get the current date in the proper format for comparison
    $current_date = current_time('Y-m-d H:i:s');

    // Query the custom posts with the tournamentID and future game dates
    $args = array(
        'post_type' => 'match',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'leagueID',
                'value' => $tournamentID,
                'compare' => '='
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => 'homeTeamDisplayName',
                    'value' => $teamName,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'awayTeamDisplayName',
                    'value' => $teamName,
                    'compare' => 'LIKE'
                )
            ),
            array(
                'key' => 'gameDate',
                'value' => $current_date,
                'compare' => '>=',
                'type' => 'DATETIME'
            )
        ),
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_key' => 'gameDate',
        'meta_type' => 'DATETIME'
    );
    $query = new WP_Query($args);

    // Check if there are any posts
    if (!$query->have_posts()) {
        return 'No upcoming games found for the provided team and tournament ID.';
    }

    // Initialize HTML output with the table structure
    $output = '<table style="width:100%; border-collapse: collapse; margin-bottom: 20px;">';
    $output .= '<thead>';
    $output .= '<tr style="background-color: #f2f2f2;">';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Date</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Arena</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Match</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Score</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Officials</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Speak</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">PC</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Ur</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Boks</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Cafe</th>';
    $output .= '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Chauffør</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';

    // Loop through each post and add it to the table
    while ($query->have_posts()) {
        $query->the_post();
        $game_date = get_post_meta(get_the_ID(), 'gameDate', true);
        $arena_name = get_post_meta(get_the_ID(), 'ArenaName', true);
        $oponents = get_post_meta(get_the_ID(), 'oponents', true);
        $score = get_post_meta(get_the_ID(), 'score', true);
        $game_officials = get_post_meta(get_the_ID(), 'officials', true);

        $output .= '<tr>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;">' . esc_html(date('j. F, Y, H:i', strtotime($game_date))) . '</td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;">' . esc_html($arena_name) . '</td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;">' . esc_html($oponents) . '</td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;">' . esc_html($score) . '</td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;">' . $game_officials . '</td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;"></td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;"></td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;"></td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;"></td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;"></td>';
        $output .= '<td style="border: 1px solid #dddddd; text-align: left; padding: 8px;"></td>';
        $output .= '</tr>';
    }

    wp_reset_postdata();

    $output .= '</tbody>';
    $output .= '</table>';

    return $output;
}
add_shortcode('get_dynamic_schedule', 'wpse_get_dynamic_schedule_shortcode');
