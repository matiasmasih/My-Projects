<?php

namespace BetterLinks\Admin;

use BetterLinks\Admin\WPDev\PluginUsageTracker;
use BetterLinks\Cron;
use BetterLinks\Helper;
use BetterLinks\Link\Utils;
use BetterLinks\Admin\Cache;

class Ajax {

	use \BetterLinks\Traits\Links;
	use \BetterLinks\Traits\Terms;
	use \BetterLinks\Traits\Clicks;
	use \BetterLinks\Traits\ArgumentSchema;

	public function __construct() {
		// link & clicks.
		add_action( 'wp_ajax_betterlinks/admin/search_clicks_data', array( $this, 'search_clicks_data' ) );
		add_action( 'wp_ajax_betterlinks/admin/links_reorder', array( $this, 'links_reorder' ) );
		add_action( 'wp_ajax_betterlinks/admin/links_move_reorder', array( $this, 'links_move_reorder' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_links_by_short_url', array( $this, 'get_links_by_short_url' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_links_by_permalink', array( $this, 'get_links_by_permalink' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_cat_by_link_id', array( $this, 'get_category_by_link_id' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_betterlink_categories', array( $this, 'get_betterlink_categories' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_betterlink_tags', array( $this, 'get_betterlink_tags' ) );
		add_action( 'wp_ajax_betterlinks/admin/create_betterlink_category', array( $this, 'create_betterlink_category' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_autolink_create_settings', array( $this, 'get_auto_link_create_settings' ) );
		add_action( 'wp_ajax_betterlinks/admin/write_json_links', array( $this, 'write_json_links' ) );
		add_action( 'wp_ajax_betterlinks/admin/write_json_clicks', array( $this, 'write_json_clicks' ) );
		add_action( 'wp_ajax_betterlinks/admin/analytics', array( $this, 'analytics' ) );
		add_action( 'wp_ajax_betterlinks/admin/short_url_unique_checker', array( $this, 'short_url_unique_checker' ) );
		add_action( 'wp_ajax_betterlinks/admin/cat_slug_unique_checker', array( $this, 'cat_slug_unique_checker' ) );
		add_action( 'wp_ajax_betterlinks/admin/reset_analytics', array( $this, 'reset_analytics' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_clicks_count', array( $this, 'get_clicks_count' ) );
		add_action( 'wp_ajax_betterlinks/admin/backfill_country_data', array( $this, 'backfill_country_data' ) );
		add_action( 'wp_ajax_betterlinks/admin/clear_analytics_cache', array( $this, 'clear_analytics_cache' ) );
		// prettylinks.
		add_action( 'wp_ajax_betterlinks/admin/get_prettylinks_data', array( $this, 'get_prettylinks_data' ) );
		add_action( 'wp_ajax_betterlinks/admin/run_prettylinks_migration', array( $this, 'run_prettylinks_migration' ) );
		add_action( 'wp_ajax_betterlinks/admin/migration_prettylinks_notice_hide', array( $this, 'migration_prettylinks_notice_hide' ) );
		add_action( 'wp_ajax_betterlinks/admin/deactive_prettylinks', array( $this, 'deactive_prettylinks' ) );
		// simple 301.
		add_action( 'wp_ajax_betterlinks/admin/get_simple301redirects_data', array( $this, 'get_simple301redirects_data' ) );
		add_action( 'wp_ajax_betterlinks/admin/run_simple301redirects_migration', array( $this, 'run_simple301redirects_migration' ) );
		add_action( 'wp_ajax_betterlinks/admin/migration_simple301redirects_notice_hide', array( $this, 'migration_simple301redirects_notice_hide' ) );
		add_action( 'wp_ajax_betterlinks/admin/deactive_simple301redirects', array( $this, 'deactive_simple301redirects' ) );
		// Thirsty affiliates.
		add_action( 'wp_ajax_betterlinks/admin/get_thirstyaffiliates_data', array( $this, 'get_thirstyaffiliates_data' ) );
		add_action( 'wp_ajax_betterlinks/admin/run_thirstyaffiliates_migration', array( $this, 'run_thirstyaffiliates_migration' ) );
		add_action( 'wp_ajax_betterlinks/admin/deactive_thirstyaffiliates', array( $this, 'deactive_thirstyaffiliates' ) );
		// API Fallbck Ajax.
		add_action( 'wp_ajax_betterlinks/admin/get_all_links', array( $this, 'get_all_links' ) );
		add_action( 'wp_ajax_betterlinks/admin/create_link', array( $this, 'create_new_link' ) );
		add_action( 'wp_ajax_betterlinks/admin/update_link', array( $this, 'update_existing_link' ) );
		add_action( 'wp_ajax_betterlinks/admin/handle_favorite', array( $this, 'handle_links_favorite_option' ) );
		add_action( 'wp_ajax_betterlinks/admin/delete_link', array( $this, 'delete_existing_link' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_settings', array( $this, 'get_settings' ) );
		add_action( 'wp_ajax_betterlinks/admin/update_settings', array( $this, 'update_settings' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_terms', array( $this, 'get_terms' ) );
		add_action( 'wp_ajax_betterlinks/admin/create_new_term', array( $this, 'create_new_term' ) );
		add_action( 'wp_ajax_betterlinks/admin/update_term', array( $this, 'update_existing_term' ) );
		add_action( 'wp_ajax_betterlinks/admin/delete_term', array( $this, 'delete_existing_term' ) );
		add_action( 'wp_ajax_betterlinks/admin/fetch_analytics', array( $this, 'fetch_analytics' ) );

		// post type, tags, categories.
		add_action( 'wp_ajax_betterlinks/admin/get_post_types', array( $this, 'get_post_types' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_post_tags', array( $this, 'get_post_tags' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_post_categories', array( $this, 'get_post_categories' ) );

		// Affiliate Disclosure Text.
		add_action( 'wp_ajax_betterlinks/admin/set_affiliate_link_disclosure_post', array( $this, 'set_affiliate_link_disclosure_post' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_affiliate_link_disclosure_post', array( $this, 'get_affiliate_link_disclosure_post' ) );
		add_action( 'wp_ajax_betterlinks/admin/set_affiliate_link_disclosure_text', array( $this, 'set_affiliate_link_disclosure_text' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_affiliate_link_disclosure_text', array( $this, 'get_affiliate_link_disclosure_text' ) );

		// Auto create links settings.
		add_action( 'wp_ajax_betterlinks/admin/get_auto_create_links_settings', array( $this, 'get_auto_create_links_settings' ) );
		// External Analytics settings.
		add_action( 'wp_ajax_betterlinks/admin/get_external_analytics', array( $this, 'get_external_analytics' ) );

		// Analytics
		add_action( 'wp_ajax_betterlinks__admin_fetch_analytics_graph', array( $this, 'fetch_analytics_graph' ) );

		// Notices
		add_action( 'wp_ajax_betterlinks__admin_menu_notice', array( $this, 'admin_menu_notice' ) );
		add_action( 'wp_ajax_betterlinks__admin_dashboard_notice', array( $this, 'admin_dashboard_notice' ) );
		add_action( 'wp_ajax_betterlinks_dismiss_black_friday_notice', array( $this, 'dismiss_black_friday_notice' ) );

		add_action( 'wp_ajax_betterlinks__fetch_target_url', array( $this, 'fetch_target_url' ) );

		// Fluent Board Integration
		add_action( 'wp_ajax_betterlinks__check_fbs_link', array( $this, 'check_fbs_link' ) );
		add_action( 'wp_ajax_betterlinks__create_fbs_link', array( $this, 'create_fbs_link' ) );
		add_action( 'wp_ajax_betterlinks__update_fbs_link', array( $this, 'update_fbs_link' ) );

		// Quick Setu
		add_action( 'wp_ajax_betterlinks__client_consent', array( $this, 'client_consent' ) );
		add_action( 'wp_ajax_betterlinks__complete_setup', array( $this, 'complete_setup' ) );
		// js analytics tracking
		add_action( 'wp_ajax_nopriv_betterlinks__js_analytics_tracking', array( $this, 'js_analytics_tracking' ) );
		add_action( 'wp_ajax_betterlinks__js_analytics_tracking', array( $this, 'js_analytics_tracking' ) );

		// Update click country data (for backward compatibility)
		add_action( 'wp_ajax_betterlinks/admin/update_click_country', array( $this, 'update_click_country' ) );
		add_action( 'wp_ajax_betterlinks/admin/update_clicks_country_by_ip', array( $this, 'update_clicks_country_by_ip' ) );

		// UTM Template Application
		add_action( 'wp_ajax_betterlinks/admin/apply_utm_template_to_links', array( $this, 'apply_utm_template_to_links' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_links_by_categories', array( $this, 'get_links_by_categories' ) );
		add_action( 'wp_ajax_betterlinks/admin/get_utm_status_counts', array( $this, 'get_utm_status_counts' ) );
	}

	public function update_fbs_link() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! defined( 'FLUENT_BOARDS' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$helper        = new Helper();
		$id            = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : null;
		$short_url     = isset( $_POST['short_url'] ) ? sanitize_text_field( $_POST['short_url'] ) : null;
		$old_short_url = isset( $_POST['old_short_url'] ) ? sanitize_text_field( $_POST['old_short_url'] ) : null;

		if ( $helper::is_exists_short_url( $short_url ) ) {
			wp_send_json_error(
				array(
					'result'  => false,
					'message' => __( 'Link already exists', 'betterlinks' ),
				)
			);
		}

		global $wpdb;
		$data  = array(
			'short_url' => $short_url,
		);
		$where = array(
			'id' => $id,
		);
		if ( empty( $wpdb->update( $wpdb->prefix . 'betterlinks', $data, $where ) ) ) {
			wp_send_json_error(
				array(
					'result'  => false,
					'message' => __( 'Something went wrong, please try again', 'betterlinks' ),
				)
			);
		}
		$helper::clear_query_cache();
		if ( BETTERLINKS_EXISTS_LINKS_JSON ) {
			// Fetch the complete link data from database to update JSON file
			$link_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}betterlinks WHERE ID = %d",
					$id
				),
				ARRAY_A
			);
			if ( $link_data ) {
				// Update short_url with the new value
				$link_data['short_url'] = $short_url;
				$helper::update_json_into_file( trailingslashit( BETTERLINKS_UPLOAD_DIR_PATH ) . 'links.json', $link_data, $old_short_url );
			}
		}

		wp_send_json_error(
			array(
				'result'  => array(
					'short_url' => $short_url,
				),
				'message' => __( 'Short Link updated successfully', 'betterlinks' ),
			)
		);
	}
	public function create_fbs_link() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! defined( 'FLUENT_BOARDS' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$helper = new Helper();

		$settings = Cache::get_json_settings();
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$taskId   = isset( $_POST['taskId'] ) ? sanitize_text_field( $_POST['taskId'] ) : null;
		if ( empty( $taskId ) ) {
			wp_send_json_error(
				array(
					'result' => false,
				)
			);
		}
		$slug             = "fbs-{$taskId}";
		$target_url       = isset( $_POST['target_url'] ) ? sanitize_url( $_POST['target_url'] ) : null;
		$short_url        = isset( $_POST['short_url'] ) ? sanitize_text_field( $_POST['short_url'] ) : null;
		$prefix           = isset( $settings['prefix'] ) ? $settings['prefix'] . '/' : '';
		$short_url        = ! empty( $short_url ) ? $short_url : $prefix . $slug;
		$nofollow         = ! empty( $settings['nofollow'] ) ? $settings['nofollow'] : null;
		$sponsored        = ! empty( $settings['sponsored'] ) ? $settings['sponsored'] : null;
		$track_me         = ! empty( $settings['track_me'] ) ? $settings['track_me'] : null;
		$param_forwarding = ! empty( $settings['param_forwarding'] ) ? $settings['param_forwarding'] : null;
		$date             = wp_date( 'Y-m-d H:i:s' );
		$redirect_type    = ! empty( $settings['redirect_type'] ) ? $settings['redirect_type'] : '307';
		$fbs_cat          = ! empty( $settings['fbs']['cat_id'] ) ? $settings['fbs']['cat_id'] : 1;

		if ( empty( $settings['fbs']['cat_id'] ) ) {
			delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
			$args                      = array(
				'ID'        => 0,
				'term_name' => 'Fluent Boards',
				'term_slug' => 'btl-fluent-boards',
				'term_type' => 'category',
			);
			$results                   = $this->create_term( $args );
			$fbs_cat                   = ! empty( $results['ID'] ) ? $results['ID'] : $fbs_cat;
			$settings['fbs']['cat_id'] = $fbs_cat;

			$response = json_encode( $settings );

			if ( $response ) {
				update_option( BETTERLINKS_LINKS_OPTION_NAME, $response );
				Cache::write_json_settings();
			}
			// regenerate links for wildcards option update
			Helper::write_links_inside_json();
		}

		$initial_values = array(
			'link_title'        => $title,
			'link_slug'         => $slug,
			'target_url'        => $target_url,
			'short_url'         => $short_url,
			'redirect_type'     => $redirect_type,
			'nofollow'          => $nofollow,
			'sponsored'         => $sponsored,
			'track_me'          => $track_me,
			'param_forwarding'  => $param_forwarding,
			'link_date'         => $date,
			'link_date_gmt'     => $date,
			'link_modified'     => $date,
			'link_modified_gmt' => $date,
			'cat_id'            => $fbs_cat,
		);

		$helper->clear_query_cache();
		$args    = $this->sanitize_links_data( $initial_values );
		$results = $this->insert_link( $args );

		if ( empty( $results ) ) {
			wp_send_json_error(
				array(
					'result' => array(
						'short_url' => $short_url,
					),
					'status' => false,
				)
			);
		}

		wp_send_json_success(
			array(
				'result' => $results,
				'status' => true,
			)
		);
	}

	public function check_fbs_link() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! defined( 'FLUENT_BOARDS' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$boardUrl = isset( $_POST['boardUrl'] ) ? sanitize_text_field( $_POST['boardUrl'] ) : null;
		$taskId   = isset( $_POST['taskId'] ) ? (int) sanitize_text_field( $_POST['taskId'] ) : null;

		$target_url = null;

		if ( ! empty( $boardUrl ) || ! empty( $taskId ) ) {
			global $wpdb;

			$target_url = $boardUrl . 'tasks/' . $taskId;
			$link       = Helper::get_link_by_permalink( $target_url, '`id`, `short_url`' );
			$task       = $wpdb->get_row( $wpdb->prepare( "SELECT `title`,`slug` FROM {$wpdb->prefix}fbs_tasks WHERE id=%d", $taskId ) );

			if ( ! empty( $link ) ) {
				wp_send_json_success(
					array(
						'result'    => array(
							'id'        => $link['id'],
							'short_url' => $link['short_url'],
							'task_slug' => $task->slug,
						),
						'is_exists' => true,
					)
				);
			}

			// if not exists any short url
			$task = $wpdb->get_row( $wpdb->prepare( "SELECT `title`,`slug` FROM {$wpdb->prefix}fbs_tasks WHERE id=%d", $taskId ) );

			if ( ! empty( $task ) ) {
				wp_send_json_success(
					array(
						'result'    => array(
							'title'      => $task->title,
							'slug'       => $task->slug,
							'target_url' => $target_url,
						),
						'is_exists' => false,
					)
				);
			}
		}

		wp_send_json_error(
			array(
				'result' => false,
			)
		);
	}

	public function fetch_target_url() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		
		// Check if user has permission - either manage_options or role-based permissions
		$can_fetch_target_url = current_user_can( 'manage_options' );
		
		// Allow role-based permissions from BetterLinks Pro
		if ( ! $can_fetch_target_url ) {
			$can_fetch_target_url = apply_filters( 'betterlinks_can_fetch_target_url', false );
		}
		
		if ( ! $can_fetch_target_url ) {
			wp_send_json_error(
				array(
					'result'  => false,
					'message' => __( 'You don\'t have permission to fetch target URL.', 'betterlinks' ),
				)
			);
		}

		$target_url = isset( $_POST['target_url'] ) ? sanitize_url( $_POST['target_url'] ) : '';
		$title      = ( new Helper() )->fetch_target_url( $target_url );

		if ( empty( $title ) ) {
			wp_send_json_error(
				array(
					'result'  => false,
					'message' => 'Something wrong with target url or title',
				)
			);
		}

		wp_send_json(
			array(
				'result' => array(
					'title' => $title,
				),
			)
		);
	}

	public function admin_dashboard_notice() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$dashboard_notice = get_option( 'betterlinks_dashboard_notice', 0 );
		if ( BETTERLINKS_MENU_NOTICE !== $dashboard_notice ) {
			update_option( 'betterlinks_dashboard_notice', BETTERLINKS_MENU_NOTICE );
		}
		wp_send_json(
			array(
				'result' => BETTERLINKS_MENU_NOTICE,
			)
		);
	}
	public function admin_menu_notice() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		wp_send_json(
			array(
				'result' => get_option( 'betterlinks_menu_notice', 0 ),
			)
		);
	}
	public function fetch_analytics_graph() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';

		if( ! strtotime( $from ) || ! strtotime( $to ) ){
			wp_send_json_error( [
				'message' => __( "Invalid date range provided.", 'betterlinks' ),
			], 400 );
		}

		global $wpdb;
		$query   = $wpdb->prepare(
			"SELECT id,link_id,ip,created_at FROM {$wpdb->prefix}betterlinks_clicks WHERE created_at BETWEEN %s AND %s",
			 $from .  ' 00:00:00', $to . ' 23:59:59');

		$results = $wpdb->get_results( $query );
		wp_send_json(
			array(
				'results' => $results,
			)
		);
	}

	public function get_prettylinks_data() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		
		$pretty_links_data = Helper::get_prettylinks_data();

		wp_send_json_success($pretty_links_data);
	}

	public function run_prettylinks_migration() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		// give betterlinks a lot of time to properly set the migration work for background.
		set_time_limit( 300 );
		$re_run = isset( $_POST['re_run'] ) ? $_POST['re_run'] : false;

		if ( empty($re_run) && Helper::btl_get_option( 'btl_prettylink_migration_should_not_start_in_background' ) ) {
			// preventing multiple migration call to prevent duplicate datas from migrating.
			wp_send_json_error( array( 'duplicate_migration_detected__so_prevented_it_here' => true ) );
		}
		$pretty_links_data = null;
		if( !empty( $re_run ) ){
			$pretty_links_data = Helper::get_prettylinks_data();
			delete_option('btl_prettylink_migration_should_not_start_in_background');
		}
		
		Helper::btl_update_option( 'btl_prettylink_migration_should_not_start_in_background', true, true );
		global $wpdb;
		$query = "DELETE FROM {$wpdb->prefix}options WHERE option_name IN(
                'betterlinks_notice_ptl_migration_running_in_background',
                'btl_failed_migration_prettylinks_links',
                'btl_failed_migration_prettylinks_clicks',
                'btl_migration_prettylinks_current_successful_links_count',
                'btl_migration_prettylinks_current_successful_clicks_count'
        )";
		$wpdb->query( $query ); // phpcs:ignore.
		Helper::btl_update_option( 'btl_failed_migration_prettylinks_links', array(), true );
		Helper::btl_update_option( 'btl_failed_migration_prettylinks_clicks', array(), true );
		Helper::btl_update_option( 'btl_migration_prettylinks_current_successful_links_count', 0, true );
		Helper::btl_update_option( 'btl_migration_prettylinks_current_successful_clicks_count', 0, true );

		$type                  = isset( $_POST['type'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['type'] ) ) ) : '';
		$total_links_clicks    = !empty( $pretty_links_data ) ? $pretty_links_data : get_transient( 'betterlinks_migration_data_prettylinks' );
		$should_migrate_links  = ! ( strpos( $type, 'links' ) === false );
		$should_migrate_clicks = ! ( strpos( $type, 'clicks' ) === false );
		$installer = new \BetterLinks\Installer();
		if ( $should_migrate_links && ! empty( $total_links_clicks['links_count'] ) ) {
			$links_count = absint( $total_links_clicks['links_count'] );
			$installer   = Helper::run_migration_for_ptrl_links_in_background( $installer, $links_count );
		}

		if ( $should_migrate_clicks && ! empty( $total_links_clicks['clicks_count'] ) ) {
			$clicks_count = absint( $total_links_clicks['clicks_count'] );
			$installer    = Helper::run_migration_for_ptrl_clicks_in_background( $installer, $clicks_count );
		}

		$installer->data( array( 'betterlinks_notice_ptl_migrate' ) )->save();
		$installer->dispatch();
		Helper::btl_update_option( 'betterlinks_notice_ptl_migration_running_in_background', true, true );
		wp_send_json_success( array( 'btl_prettylinks_migration_running_in_background' => true ) );
	}

	public function migration_prettylinks_notice_hide() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		if ( 'deactive' === $type ) {
			update_option( 'betterlinks_hide_notice_ptl_deactive', true );
		} elseif ( 'migrate' === $type ) {
			update_option( 'betterlinks_hide_notice_ptl_migrate', true );
		}
		wp_die( "You don't have permission to do this." );
	}
	public function deactive_prettylinks() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$deactivate = deactivate_plugins( 'pretty-link/pretty-link.php' );
		wp_send_json_success( $deactivate );
	}
	public function write_json_links() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( apply_filters( 'betterlinks/admin/current_user_can_edit_settings', current_user_can( 'manage_options' ) ) ) { // phpcs:ignore.
			$Cron    = new Cron();
			$resutls = $Cron->write_json_links();
			wp_send_json_success( $resutls );
		}
		wp_die( "You don't have permission to do this." );
	}
	public function write_json_clicks() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( apply_filters( 'betterlinks/admin/current_user_can_edit_settings', current_user_can( 'manage_options' ) ) && ! BETTERLINKS_EXISTS_CLICKS_JSON ) {
			$emptyContent = '{}';
			$file_handle  = @fopen( trailingslashit( BETTERLINKS_UPLOAD_DIR_PATH ) . 'clicks.json', 'wb' );
			if ( $file_handle ) {
				fwrite( $file_handle, $emptyContent );
				fclose( $file_handle );
			}
			wp_send_json_success( true );
		}
		wp_send_json_error( false );
	}
	public function analytics() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( apply_filters( 'betterlinks/admin/current_user_can_edit_settings', current_user_can( 'manage_options' ) ) ) {
			$Cron    = new Cron();
			$resutls = $Cron->analytics();
			wp_send_json_success( $resutls );
		}
		wp_die( "You don't have permission to do this." );
	}
	public function short_url_unique_checker() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( apply_filters( 'betterlinks/admin/current_user_can_edit_settings', current_user_can( 'manage_options' ) ) ) {
			$ID            = isset( $_POST['ID'] ) ? sanitize_text_field( $_POST['ID'] ) : '';
			$slug          = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
			$alreadyExists = false;
			$resutls       = array();
			if ( ! empty( $slug ) ) {
				$resutls = Helper::get_link_by_short_url( $slug );
				if ( count( $resutls ) > 0 ) {
					$alreadyExists = true;
					$resutls       = current( $resutls );
					if ( $resutls['ID'] == $ID ) {
						$alreadyExists = false;
					}
				}
			}
			wp_send_json_success( $alreadyExists );
		}
		wp_die( "You don't have permission to do this." );
	}
	public function cat_slug_unique_checker() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$ID            = isset( $_POST['ID'] ) ? sanitize_text_field( $_POST['ID'] ) : '';
		$slug          = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
		$alreadyExists = false;
		$resutls       = array();
		if ( ! empty( $slug ) ) {
			$resutls = Helper::get_term_by_slug( $slug );
			if ( count( $resutls ) > 0 ) {
				$alreadyExists = true;
				$resutls       = current( $resutls );
				if ( $resutls['ID'] == $ID ) {
					$alreadyExists = false;
				}
			}
		}
		wp_send_json_success( $alreadyExists );
	}
	public function get_simple301redirects_data() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$links = get_option( '301_redirects' );
		wp_send_json_success( $links );
	}
	public function run_simple301redirects_migration() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		try {
			$simple_301_redirects = get_option( '301_redirects', [] );
			$migrator             = new \BetterLinks\Tools\Migration\S301ROneClick();
			$resutls              = $migrator->run_importer( array_reverse( $simple_301_redirects ) );
			do_action( 'betterlinks/admin/after_import_data' );
			update_option( 'betterlinks_notice_s301r_migrate', true );
			wp_send_json_success( $resutls );
		} catch ( \Throwable $th ) {
			wp_send_json_error( $th->getMessage() );
		}
	}
	public function migration_simple301redirects_notice_hide() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		if ( $type == 'deactive' ) {
			update_option( 'betterlinks_hide_notice_s301r_deactive', true );
		} elseif ( $type == 'migrate' ) {
			update_option( 'betterlinks_notice_s301r_migrate', true );
		}
		wp_die( "You don't have permission to do this." );
	}
	public function deactive_simple301redirects() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$deactivate = deactivate_plugins( 'simple-301-redirects/wp-simple-301-redirects.php' );
		wp_send_json_success( $deactivate );
	}
	public function search_clicks_data() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$title   = isset( $_GET['title'] ) ? sanitize_text_field( $_GET['title'] ) : '';
		$results = Helper::search_clicks_data( $title );

		wp_send_json_success(
			array(
				'clicks' => $results,
			)
		);
	}
	public function links_reorder() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$links = ( isset( $_POST['links'] ) ? explode( ',', sanitize_text_field( $_POST['links'] ) ) : array() );
		if ( count( $links ) > 0 ) {
			foreach ( $links as $key => $value ) {
				Helper::insert_link(
					array(
						'ID'         => $value,
						'link_order' => $key,
					),
					true
				);
			}
		}
		wp_send_json_success( array() );
	}
	public function links_move_reorder() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$source      = ( isset( $_POST['source'] ) ? explode( ',', sanitize_text_field( $_POST['source'] ) ) : array() );
		$destination = ( isset( $_POST['destination'] ) ? explode( ',', sanitize_text_field( $_POST['destination'] ) ) : array() );
		if ( count( $source ) > 0 ) {
			foreach ( $source as $key => $value ) {
				Helper::insert_link(
					array(
						'ID'         => $value,
						'link_order' => $key,
					),
					true
				);
			}
		}
		if ( count( $destination ) > 0 ) {
			foreach ( $destination as $key => $value ) {
				Helper::insert_link(
					array(
						'ID'         => $value,
						'link_order' => $key,
					),
					true
				);
			}
		}
		wp_send_json_success( array() );
	}

	public function get_thirstyaffiliates_data() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$response = Helper::get_thirstyaffiliates_links();
		wp_send_json_success( $response );
	}

	public function run_thirstyaffiliates_migration() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		try {
			$links    = Helper::get_thirstyaffiliates_links();
			$migrator = new \BetterLinks\Tools\Migration\TAOneClick();
			$resutls  = $migrator->run_importer( $links );
			do_action( 'betterlinks/admin/after_import_data' );
			update_option( 'betterlinks_notice_ta_migrate', true );
			wp_send_json_success( $resutls );
		} catch ( \Throwable $th ) {
			wp_send_json_error( $th->getMessage() );
		}
	}

	public function deactive_thirstyaffiliates() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$deactivate = deactivate_plugins( 'thirstyaffiliates/thirstyaffiliates.php' );
		wp_send_json_success( $deactivate );
	}

	public function get_links_by_short_url() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$short_url = ( isset( $_POST['short_url'] ) ? sanitize_text_field( $_POST['short_url'] ) : '' );
		$results   = Helper::get_link_by_short_url( $short_url );
		wp_send_json_success( is_array( $results ) ? current( $results ) : false );
	}
	public function get_links_by_permalink() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$short_url = ( isset( $_POST['target_url'] ) ? sanitize_text_field( $_POST['target_url'] ) : '' );
		$results   = Helper::get_link_by_permalink( $short_url );
		wp_send_json_success( is_array( $results ) ? $results : false );
	}

	public function get_category_by_link_id() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$ID      = ( isset( $_POST['ID'] ) ? sanitize_text_field( $_POST['ID'] ) : '' );
		$results = Helper::get_terms_by_link_ID_and_term_type( $ID, 'category' );
		return wp_send_json( $results );
	}

	public function get_betterlink_categories() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$categories = $this->get_all_categories();
		$formatted_categories = array();

		foreach ($categories as $category) {
			$formatted_categories[] = array(
				'value' => $category['ID'],
				'label' => $category['term_name'],
				'slug' => $category['term_slug'],
				'link_count' => isset($category['link_count']) ? $category['link_count'] : 0
			);
		}

		wp_send_json_success($formatted_categories);
	}

	public function create_betterlink_category() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
		
		if (empty($category_name)) {
			wp_send_json_error(array('message' => __('Category name is required', 'betterlinks')));
			return;
		}

		// Create the category using the existing Helper method
		$term_data = array(
			'term_name' => $category_name,
			'term_slug' => sanitize_title($category_name),
			'term_type' => 'category'
		);

		$term_id = Helper::insert_term($term_data);
		
		if ($term_id) {
			// Return the created category data
			$created_category = array(
				'id' => $term_id,
				'term_name' => $category_name,
				'term_slug' => sanitize_title($category_name),
				'link_count' => 0
			);
			
			wp_send_json_success($created_category);
		} else {
			wp_send_json_error(array('message' => __('Failed to create category', 'betterlinks')));
		}
	}

	public function get_betterlink_tags() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$tags = $this->get_all_tags();
		$formatted_tags = array();

		foreach ($tags as $tag) {
			$formatted_tags[] = array(
				'value' => $tag['ID'],
				'label' => $tag['term_name'],
				'slug' => $tag['term_slug'],
				'link_count' => isset($tag['link_count']) ? $tag['link_count'] : 0
			);
		}

		wp_send_json_success($formatted_tags);
	}

	public function get_auto_link_create_settings() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$data = get_option( 'betterlinkspro_auto_link_create', array() );
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}
		wp_send_json_success( $data );
	}

	public function get_all_links() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/links_get_items_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$cache_data = get_transient( BETTERLINKS_CACHE_LINKS_NAME );
		if ( empty( $cache_data ) || ! json_decode( $cache_data, true ) ) {
			$results = Helper::get_prepare_all_links();
			set_transient( BETTERLINKS_CACHE_LINKS_NAME, json_encode( $results ) );
			wp_send_json_success(
				array(
					'success' => true,
					'cache'   => false,
					'data'    => $results,
				),
				200
			);
		}
		wp_send_json_success(
			array(
				'success' => true,
				'cache'   => true,
				'data'    => json_decode( $cache_data ),
			),
			200
		);
	}
	public function create_new_link() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/links_create_item_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
		$args    = $this->sanitize_links_data( $_POST );
		$results = $this->insert_link( $args );
		if ( $results ) {
			wp_send_json_success(
				$results,
				200
			);
		}
		wp_send_json_error(
			$results,
			200
		);
	}
	public function update_existing_link() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/links_update_item_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
		$args    = $this->sanitize_links_data( $_POST );
		$results = $this->update_link( $args );
		if ( $results ) {
			wp_send_json_success(
				$results,
				200
			);
		}
		wp_send_json_error(
			$args,
			200
		);
	}
	public function handle_links_favorite_option() {
		if ( isset( $_POST['favForAll'] ) && isset( $_POST['ID'] ) ) {
			check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
			if ( ! apply_filters( 'betterlinks/api/links_update_favorite_permissions_check', current_user_can( 'manage_options' ) ) ) {
				wp_die( "You don't have permission to do this." );
			}
			delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
			$params   = array(
				'ID'   => absint( $_POST['ID'] ),
				'data' => array(
					'favForAll' => $_POST['favForAll'] === 'true' ? true : false,
				),
			);
			$result   = $this->update_link_favorite( $params );
			$response = array(
				'ID'        => $params['ID'],
				'favForAll' => $params['data']['favForAll'],
			);
			if ( $result ) {
				wp_send_json_success(
					$response,
					200
				);
			}
			wp_send_json_error(
				$response,
				200
			);
		}
	}
	public function delete_existing_link() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
		$args = array(
			'ID'        => ( isset( $_REQUEST['ID'] ) ? sanitize_text_field( $_REQUEST['ID'] ) : '' ),
			'short_url' => ( isset( $_REQUEST['short_url'] ) ? sanitize_text_field( $_REQUEST['short_url'] ) : '' ),
			'term_id'   => ( isset( $_REQUEST['term_id'] ) ? sanitize_text_field( $_REQUEST['term_id'] ) : '' ),
		);
		$this->delete_link( $args );

		wp_send_json_success(
			$args,
			200
		);
	}
	public function get_settings() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/settings_get_items_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		// $results = get_option( BETTERLINKS_LINKS_OPTION_NAME, '[]' );
		$results = Cache::get_json_settings();
		if ( $results ) {
			wp_send_json_success(
				json_encode( $results ),
				200
			);
		}
		wp_send_json_success(
			array(
				'success' => false,
				'data'    => '{}',
			),
			200
		);
	}
	public function update_settings() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/settings_update_items_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$helper                           = new Helper();
		$response                         = $helper::fresh_ajax_request_data( $_POST );
		$response                         = $helper::sanitize_text_or_array_field( $response );
		$response['uncloaked_categories'] = isset( $response['uncloaked_categories'] ) && is_string( $response['uncloaked_categories'] ) ? json_decode( $response['uncloaked_categories'] ) : array();
		
		// Validate and sanitize excluded IPs
		if ( isset( $response['excluded_ips'] ) ) {
			if ( is_array( $response['excluded_ips'] ) ) {
				$response['excluded_ips'] = array_values( array_filter( array_map( function( $ip ) {
					$ip = sanitize_text_field( trim( $ip ) );
					// Validate IP address (IPv4 or IPv6)
					return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : null;
				}, $response['excluded_ips'] ) ) );
			} else {
				$response['excluded_ips'] = array();
			}
		}

		// Pro Logics
		$response = apply_filters( 'betterlinkspro/admin/update_settings', $response );

		// Handle custom SVG icon sanitization
		if ( ! empty( $response['autolink_custom_icon'] ) ) {
			// Use the sanitize_custom_svg method if it exists, otherwise use custom wp_kses for SVG
			if ( class_exists( '\BetterLinksPro\Frontend\AutoLinks' ) && method_exists( '\BetterLinksPro\Frontend\AutoLinks', 'sanitize_custom_svg' ) ) {
				$response['autolink_custom_icon'] = \BetterLinksPro\Frontend\AutoLinks::sanitize_custom_svg( $response['autolink_custom_icon'] );
			} else {
				// Custom wp_kses with SVG allowed elements as fallback
				$allowed_svg = array(
					'svg' => array( 'class' => array(), 'width' => array(), 'height' => array(), 'viewbox' => array(), 'viewBox' => array(), 'fill' => array(), 'xmlns' => array() ),
					'path' => array( 'd' => array(), 'stroke' => array(), 'stroke-width' => array(), 'stroke-linecap' => array(), 'stroke-linejoin' => array(), 'fill' => array() ),
					'g' => array( 'fill' => array(), 'stroke' => array() ),
					'circle' => array( 'cx' => array(), 'cy' => array(), 'r' => array(), 'fill' => array(), 'stroke' => array() ),
					'rect' => array( 'x' => array(), 'y' => array(), 'width' => array(), 'height' => array(), 'fill' => array(), 'stroke' => array() ),
				);
				$response['autolink_custom_icon'] = wp_kses( $response['autolink_custom_icon'], $allowed_svg );
			}
		}

		if ( ! empty( $response['fbs']['enable_fbs'] ) ) {
			$category                  = ! empty( $response['fbs']['cat_id'] ) ? sanitize_text_field( $response['fbs']['cat_id'] ) : 1;
			$category                  = $helper::insert_new_category( $category );
			$response['fbs']['cat_id'] = $category;
		}

		update_option( BETTERLINKS_CUSTOM_DOMAIN_MENU, !empty( $response['enable_custom_domain_menu'] ) ? $response['enable_custom_domain_menu'] : false );
		$response = json_encode( $response );
		if ( $response ) {
			update_option( BETTERLINKS_LINKS_OPTION_NAME, $response );
		}
		// regenerate links for wildcards option update
		$helper::write_links_inside_json(); // it's better to write the links instantly here than scheduling/corning it
		wp_send_json_success(
			$response,
			200
		);
	}
	public function get_terms() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/settings_get_items_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$args = array();
		if ( isset( $_REQUEST['ID'] ) ) {
			$args['ID'] = sanitize_text_field( $_REQUEST['ID'] );
		}
		if ( isset( $_REQUEST['term_type'] ) ) {
			$args['term_type'] = sanitize_text_field( $_REQUEST['term_type'] );
		}

		$results = $this->get_all_terms_data( $args );
		if ( $results ) {
			wp_send_json_success(
				$results,
				200
			);
		}
		wp_send_json_error(
			array(),
			200
		);
	}
	public function create_new_term() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
		$args    = array(
			'ID'        => ( isset( $_REQUEST['ID'] ) ? absint( sanitize_text_field( $_REQUEST['ID'] ) ) : 0 ),
			'term_name' => ( isset( $_REQUEST['term_name'] ) ? sanitize_text_field( $_REQUEST['term_name'] ) : '' ),
			'term_slug' => ( isset( $_REQUEST['term_slug'] ) ? sanitize_text_field( $_REQUEST['term_slug'] ) : '' ),
			'term_type' => ( isset( $_REQUEST['term_type'] ) ? sanitize_text_field( $_REQUEST['term_type'] ) : '' ),
		);
		$results = $this->create_term( $args );
		wp_send_json_success(
			$results,
			200
		);
	}
	public function update_existing_term() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
		$args = array(
			'cat_id'   => ( isset( $_REQUEST['ID'] ) ? absint( sanitize_text_field( $_REQUEST['ID'] ) ) : 0 ),
			'cat_name' => ( isset( $_REQUEST['term_name'] ) ? sanitize_text_field( $_REQUEST['term_name'] ) : '' ),
			'cat_slug' => ( isset( $_REQUEST['term_slug'] ) ? sanitize_text_field( $_REQUEST['term_slug'] ) : '' ),
		);
		$this->update_term( $args );
		wp_send_json_success(
			array(
				'ID'        => $args['cat_id'],
				'term_name' => $args['cat_name'],
				'term_slug' => $args['cat_slug'],
			),
			200
		);
	}
	public function delete_existing_term() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
		$args = array(
			'cat_id' => ( isset( $_REQUEST['cat_id'] ) ? absint( sanitize_text_field( $_REQUEST['cat_id'] ) ) : 0 ),
			'tag_id' => ( isset( $_REQUEST['tag_id'] ) ? absint( sanitize_text_field( $_REQUEST['tag_id'] ) ) : 0 ),
		);

		// Check if trying to delete the default 'Uncategorized' category (ID: 1)
		// This can come as either cat_id or tag_id parameter
		$term_id_to_delete = null;
		if ( isset( $args['cat_id'] ) && $args['cat_id'] > 0 ) {
			$term_id_to_delete = $args['cat_id'];
		} elseif ( isset( $args['tag_id'] ) && $args['tag_id'] > 0 ) {
			$term_id_to_delete = $args['tag_id'];
		}

		if ( $term_id_to_delete && ( $term_id_to_delete == 1 || $term_id_to_delete === '1' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cannot delete the default "Uncategorized" category.', 'betterlinks' ),
					'term_id' => $term_id_to_delete,
				),
				403
			);
		}

		$this->delete_term( $args );
		wp_send_json_success(
			$args,
			200
		);
	}
	public function fetch_analytics() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/analytics_items_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$from = isset( $_REQUEST['from'] ) ? sanitize_text_field( $_REQUEST['from'] ) : date( 'Y-m-d', strtotime( ' - 30 days' ) );
		$to   = isset( $_REQUEST['to'] ) ? sanitize_text_field( $_REQUEST['to'] ) : date( 'Y-m-d' );
		$ID   = ( isset( $_REQUEST['ID'] ) ? sanitize_text_field( $_REQUEST['ID'] ) : '' );
		if ( ! empty( $ID ) && class_exists( 'BetterLinksPro' ) ) {
			$results = \BetterLinksPro\Helper::get_individual_link_analytics(
				array(
					'id'   => $ID,
					'from' => $from,
					'to'   => $to,
				)
			);
		} else {
			$results = $this->get_clicks_data( $from, $to );
		}
		wp_send_json_success(
			$results,
			200
		);
	}
	public function reset_analytics() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/analytics_items_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		global $wpdb;
		$prefix          = $wpdb->prefix;
		$days_older_than = isset( $_REQUEST['days_older_than'] ) ? sanitize_text_field( $_REQUEST['days_older_than'] ) : false;
		$from            = isset( $_REQUEST['from'] ) ? sanitize_text_field( $_REQUEST['from'] ) : date( 'Y-m-d', strtotime( ' - 30 days' ) );
		$to              = isset( $_REQUEST['to'] ) ? sanitize_text_field( $_REQUEST['to'] ) : date( 'Y-m-d' );
		$link_id         = isset( $_REQUEST['link_id'] ) ? intval( $_REQUEST['link_id'] ) : null;
		$query           = '';
		
		if ( $days_older_than !== false ) {
			// Legacy support for days_older_than parameter
			$range_days_in_seconds           = intval( $days_older_than ) * 24 * 60 * 60;
			$gmt_timestamp_of_the_range_time = time() - $range_days_in_seconds;
			if ( $link_id !== null ) {
				$query = "DELETE FROM {$prefix}betterlinks_clicks WHERE UNIX_TIMESTAMP(created_at_gmt) < %d AND link_id = %d";
				$query = $wpdb->prepare( $query, $gmt_timestamp_of_the_range_time, $link_id );
			} else {
				$query = "DELETE FROM {$prefix}betterlinks_clicks WHERE UNIX_TIMESTAMP(created_at_gmt) < %d";
				$query = $wpdb->prepare( $query, $gmt_timestamp_of_the_range_time );
			}
		} elseif ( !empty( $from ) && !empty( $to ) ) {
			// Use date range for deletion
			if ( $link_id !== null ) {
				$query = "DELETE FROM {$prefix}betterlinks_clicks WHERE DATE(created_at_gmt) >= %s AND DATE(created_at_gmt) <= %s AND link_id = %d";
				$query = $wpdb->prepare( $query, $from, $to, $link_id );
			} else {
				$query = "DELETE FROM {$prefix}betterlinks_clicks WHERE DATE(created_at_gmt) >= %s AND DATE(created_at_gmt) <= %s";
				$query = $wpdb->prepare( $query, $from, $to );
			}
		} else {
			// Delete all records as fallback
			if ( $link_id !== null ) {
				$query = "DELETE FROM {$prefix}betterlinks_clicks WHERE link_id = %d";
				$query = $wpdb->prepare( $query, $link_id );
			} else {
				$query = "DELETE FROM {$prefix}betterlinks_clicks";
			}
		}
		$count = $wpdb->query( $query );
		if ( $count === false ) {
			wp_send_json_error( $count );
		}
		Helper::clear_query_cache();
		Helper::clear_analytics_cache();
		Helper::update_links_analytics();
		$new_clicks_data = Helper::get_clicks_by_date( $from, $to );
		$new_links_data  = Helper::get_prepare_all_links();
		set_transient( BETTERLINKS_CACHE_LINKS_NAME, json_encode( $new_links_data ) );
		wp_send_json_success(
			array(
				'count'           => $count,
				'new_clicks_data' => $new_clicks_data,
				'new_links_data'  => $new_links_data,
			),
			200
		);
	}

	public function get_clicks_count() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! apply_filters( 'betterlinks/api/analytics_items_permissions_check', current_user_can( 'manage_options' ) ) ) {
			wp_die( "You don't have permission to do this." );
		}
		global $wpdb;
		$prefix  = $wpdb->prefix;
		$from    = isset( $_REQUEST['from'] ) ? sanitize_text_field( $_REQUEST['from'] ) : date( 'Y-m-d', strtotime( ' - 30 days' ) );
		$to      = isset( $_REQUEST['to'] ) ? sanitize_text_field( $_REQUEST['to'] ) : date( 'Y-m-d' );
		$link_id = isset( $_REQUEST['link_id'] ) ? intval( $_REQUEST['link_id'] ) : null;

		// Build count query similar to delete query
		if ( !empty( $from ) && !empty( $to ) ) {
			if ( $link_id !== null ) {
				$query = "SELECT COUNT(*) FROM {$prefix}betterlinks_clicks WHERE DATE(created_at_gmt) >= %s AND DATE(created_at_gmt) <= %s AND link_id = %d";
				$query = $wpdb->prepare( $query, $from, $to, $link_id );
			} else {
				$query = "SELECT COUNT(*) FROM {$prefix}betterlinks_clicks WHERE DATE(created_at_gmt) >= %s AND DATE(created_at_gmt) <= %s";
				$query = $wpdb->prepare( $query, $from, $to );
			}
		} else {
			// Count all records as fallback
			if ( $link_id !== null ) {
				$query = "SELECT COUNT(*) FROM {$prefix}betterlinks_clicks WHERE link_id = %d";
				$query = $wpdb->prepare( $query, $link_id );
			} else {
				$query = "SELECT COUNT(*) FROM {$prefix}betterlinks_clicks";
			}
		}

		$count = $wpdb->get_var( $query );
		if ( $count === false ) {
			wp_send_json_error( array( 'message' => 'Failed to get click count' ) );
		}

		wp_send_json_success(
			array(
				'count' => intval( $count ),
			),
			200
		);
	}

	public function get_post_types() {
		$post_types = get_post_types(['public' => true]);
		wp_send_json_success(
			$post_types,
			200
		);
	}
	public function get_post_tags() {
		$tags = get_tags( array( 'get' => 'all' ) );
		$tags = wp_list_pluck( $tags, 'name', 'slug' );
		wp_send_json_success(
			$tags,
			200
		);
	}
	public function get_post_categories() {
		$categories = get_categories(
			array(
				'orderby' => 'name',
			)
		);
		$categories = wp_list_pluck( $categories, 'name', 'slug' );
		wp_send_json_success(
			$categories,
			200
		);
	}

	public function set_affiliate_link_disclosure_post() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$ID    = ( isset( $_POST['ID'] ) ? intval( $_POST['ID'] ) : '' );
		$value = ( isset( $_POST['value'] ) ? sanitize_text_field( $_POST['value'] ) : '' );

		update_post_meta( $ID, 'betterlinks_enable_affiliate_link_disclosure', $value );

		wp_send_json(
			array(
				'ID'    => $ID,
				'value' => $value,
			)
		);
	}

	public function get_affiliate_link_disclosure_post() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$ID        = ( isset( $_POST['ID'] ) ? intval( sanitize_text_field( $_POST['ID'] ) ) : '' );
		$post_meta = get_post_meta( $ID, 'betterlinks_enable_affiliate_link_disclosure' );
		wp_send_json( $post_meta );
	}
	public function set_affiliate_link_disclosure_text() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$ID    = isset( $_POST['ID'] ) ? sanitize_text_field( $_POST['ID'] ) : '';
		$value = isset( $_POST['value'] ) ? $_POST['value'] : '';

		$meta_key = 'betterlinks_enable_affiliate_link_disclosure_text';

		if ( ! empty( get_post_meta( $ID, $meta_key ) ) ) {
			update_post_meta( $ID, $meta_key, $value );
		} else {
			add_post_meta( $ID, $meta_key, $value );
		}

		wp_send_json( $value );
	}

	public function get_affiliate_link_disclosure_text() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}

		$ID       = isset( $_POST['ID'] ) ? sanitize_text_field( $_POST['ID'] ) : '';
		$meta_key = 'betterlinks_enable_affiliate_link_disclosure_text';

		$data           = array();
		$affiliate_text = get_post_meta( $ID, $meta_key );
		if ( count( $affiliate_text ) > 0 ) {
			$data = json_decode( html_entity_decode( $affiliate_text[0] ), true );
		}

		$settings                  = json_decode( get_option( BETTERLINKS_LINKS_OPTION_NAME ), true );
		$affiliate_disclosure_text = ! empty( $settings['affiliate_disclosure_text'] ) ? $settings['affiliate_disclosure_text'] : '';
		$affiliate_link_position   = ! empty( $settings['affiliate_link_position'] ) ? sanitize_text_field( $settings['affiliate_link_position'] ) : '';

		wp_send_json(
			array(
				'affiliate_disclosure_text' => empty( $data['affiliate_disclosure_text'] ) ? $affiliate_disclosure_text : str_replace( ' rn ', '', $data['affiliate_disclosure_text'] ),
				'affiliate_link_position'   => empty( $data['affiliate_link_position'] ) ? $affiliate_link_position : $data['affiliate_link_position'],
			)
		);
	}

	public function get_auto_create_links_settings() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( apply_filters( 'betterlinkspro/admin/current_user_can_edit_settings', current_user_can( 'manage_options' ) ) ) {
			$data = get_option( BETTERLINKS_PRO_AUTO_LINK_CREATE_OPTION_NAME, array() );
			if ( is_string( $data ) ) {
				$data = json_decode( $data, true );
			}
			wp_send_json_success( $data );
		}
		wp_die( "You don't have permission to do this." );
	}
	public function get_external_analytics() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( apply_filters( 'betterlinkspro/admin/current_user_can_edit_settings', current_user_can( 'manage_options' ) ) ) {
			$data = defined( 'BETTERLINKS_PRO_EXTERNAL_ANALYTICS_OPTION_NAME' ) ? get_option( BETTERLINKS_PRO_EXTERNAL_ANALYTICS_OPTION_NAME, array() ) : array();
			if ( is_string( $data ) ) {
				$data = json_decode( $data, true );
			}
			wp_send_json_success( $data );
		}
		wp_die( "You don't have permission to do this." );
	}

	public function client_consent() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$opt_in_value = isset( $_POST['opt_in_value'] ) ? sanitize_text_field( $_POST['opt_in_value'] ) : 'no';
		$opt_in = PluginUsageTracker::get_instance( BETTERLINKS_PLUGIN_FILE, [
			'opt_in'       => true,
			'goodbye_form' => true,
			'item_id'      => '720bbe6537bffcb73f37',
		] );

		$opt_in->opt_in($opt_in_value, 'betterlinks');
		
		update_option('betterlinks_quick_setup_step', 1);
		wp_send_json_success([
			'result' => $opt_in_value 
		]);
	}

	public function complete_setup() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "You don't have permission to do this." );
		}
		$is_update = update_option('betterlinks_quick_setup_step', 'complete');
		wp_send_json_success([
			'result' => (bool) $is_update ? 'complete' : 'error' 
		]);
	}
	
	public function js_analytics_tracking() {
		global $wpdb;

		$searchKey = !empty( $_POST['target_url'] ) ? 'target_url' : 'ID';
		$searchValue = (isset( $_POST['target_url'] ) ? sanitize_url($_POST['target_url']) : '');
		$searchValue = (empty( $searchValue ) && isset( $_POST['linkId'] ) ? sanitize_text_field( $_POST['linkId'] ) : '');
		$location = isset( $_POST['location'] ) ? esc_url_raw( $_POST['location'] ) : '';
		$query = $wpdb->prepare( "select short_url from {$wpdb->prefix}betterlinks where {$searchKey}=%s", $searchValue );
		$short_url = $wpdb->get_row( $query, ARRAY_A );
		$short_url = current( $short_url );
		$utils = new Utils();
		$data = $utils->get_slug_raw($short_url);
		$data['skip_password_protection'] = true;
		$data['location'] = $location;

		// Accept country data from frontend geolocation
		if ( isset( $_POST['country_code'] ) ) {
			$data['country_code'] = sanitize_text_field( $_POST['country_code'] );
		}
		if ( isset( $_POST['country_name'] ) ) {
			$data['country_name'] = sanitize_text_field( $_POST['country_name'] );
		}

		Helper::init_tracking($data, $utils);

		wp_send_json([
			'data' => true
		]);
	}

	/**
	 * Update click record with country data
	 *
	 * Used for backward compatibility to update existing clicks with country information
	 */
	public function update_click_country() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );

		// Check if BetterLinks Pro v2.5.0 or newer is installed
		if ( ! defined( 'BETTERLINKS_PRO_VERSION' ) || version_compare( BETTERLINKS_PRO_VERSION, '2.5.0', '<' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Country detection requires BetterLinks Pro v2.5.0 or newer', 'betterlinks' )
			) );
		}

		global $wpdb;

		$click_id = isset( $_POST['click_id'] ) ? intval( $_POST['click_id'] ) : 0;
		$country_code = isset( $_POST['country_code'] ) ? sanitize_text_field( $_POST['country_code'] ) : '';
		$country_name = isset( $_POST['country_name'] ) ? sanitize_text_field( $_POST['country_name'] ) : '';

		if ( ! $click_id || ! $country_code || ! $country_name ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid parameters', 'betterlinks' )
			) );
		}

		$table_name = $wpdb->prefix . 'betterlinks_clicks';

		// Get or create country record and get its ID
		$country_id = \BetterLinks\Services\CountryDetectionService::get_or_create_country_id(
			$country_code,
			$country_name
		);

		if ( ! $country_id ) {
			wp_send_json_error( array(
				'message' => __( 'Failed to create country record', 'betterlinks' )
			) );
		}

		$updated = $wpdb->update(
			$table_name,
			array( 'country_id' => $country_id ),
			array( 'ID' => $click_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $updated !== false ) {
			wp_send_json_success( array(
				'message' => __( 'Country data updated successfully', 'betterlinks' ),
				'country_code' => $country_code,
				'country_name' => $country_name,
				'country_id' => $country_id,
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Failed to update country data', 'betterlinks' )
			) );
		}
	}

	/**
	 * Update all clicks with the same IP within a specific link with country data
	 *
	 * This bulk update ensures that when country is fetched for one IP,
	 * all clicks from the same IP within the same short URL are updated automatically
	 */
	public function update_clicks_country_by_ip() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );

		// Check if BetterLinks Pro v2.5.0 or newer is installed and has country tracking feature
		if ( ! defined( 'BETTERLINKS_PRO_VERSION' ) || version_compare( BETTERLINKS_PRO_VERSION, '2.5.0', '<' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Country detection requires BetterLinks Pro v2.5.0 or newer', 'betterlinks' ),
				'code' => 'pro_version_required'
			) );
		}

		// Additional check: Verify Pro plugin has the country tracking function (prevents bypass with old Pro files)
		if ( ! class_exists( 'BetterLinksPro\\Helper' ) || 
			 ! method_exists( 'BetterLinksPro\\Helper', 'is_country_tracking_enabled' ) ||
			 ! \BetterLinksPro\Helper::is_country_tracking_enabled() ) {
			wp_send_json_error( array(
				'message' => __( 'Please update BetterLinks Pro to v2.5.0 or newer to use this feature', 'betterlinks' ),
				'code' => 'pro_update_required'
			) );
		}

		global $wpdb;

		$link_id = isset( $_POST['link_id'] ) ? intval( $_POST['link_id'] ) : 0;
		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( $_POST['ip'] ) : '';
		$country_code = isset( $_POST['country_code'] ) ? sanitize_text_field( $_POST['country_code'] ) : '';
		$country_name = isset( $_POST['country_name'] ) ? sanitize_text_field( $_POST['country_name'] ) : '';

		if ( ! $link_id || ! $ip || ! $country_code || ! $country_name ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid parameters', 'betterlinks' )
			) );
		}

		$table_name = $wpdb->prefix . 'betterlinks_clicks';

		// Get or create country record and get its ID
		$country_id = \BetterLinks\Services\CountryDetectionService::get_or_create_country_id(
			$country_code,
			$country_name
		);

		if ( ! $country_id ) {
			wp_send_json_error( array(
				'message' => __( 'Failed to create country record', 'betterlinks' )
			) );
		}

		// Update all clicks with the same IP within this link_id
		// This will update ALL clicks with this IP, regardless of whether they already have country data
		$updated = $wpdb->update(
			$table_name,
			array( 'country_id' => $country_id ),
			array(
				'link_id' => $link_id,
				'ip' => $ip,
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		if ( $updated !== false ) {
			// Clear the transient cache for this link's analytics data
			// This ensures the API returns fresh data with the updated country information
			$this->clear_individual_clicks_transient( $link_id );

			wp_send_json_success( array(
				'message' => sprintf( __( 'Country data updated for %d clicks', 'betterlinks' ), $updated ),
				'country_code' => $country_code,
				'country_name' => $country_name,
				'country_id' => $country_id,
				'updated_count' => $updated,
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Failed to update country data', 'betterlinks' )
			) );
		}
	}

	/**
	 * Clear transient cache for individual clicks analytics
	 * This ensures fresh data is fetched from the database
	 *
	 * @param int $link_id The link ID
	 */
	private function clear_individual_clicks_transient( $link_id ) {
		global $wpdb;

		// Get all transient keys for this link and delete them
		// The transient key format is: btl_individual_analytics_clicks_{from}_{to}_{link_id}
		$transient_prefix = 'btl_individual_analytics_clicks_';

		// Query the options table to find all matching transients
		$transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'%' . $wpdb->esc_like( $transient_prefix ) . '%' . $wpdb->esc_like( (string) $link_id ) . '%'
			)
		);

		// Delete each transient
		if ( $transients ) {
			foreach ( $transients as $transient ) {
				// Remove the '_transient_' prefix to get the transient name
				$transient_name = str_replace( '_transient_', '', $transient->option_name );
				delete_transient( $transient_name );
			}
		}
	}

	/**
	 * Get links by categories
	 */
	public function get_links_by_categories() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'betterlinks' ) );
		}

		$category_ids = isset( $_POST['category_ids'] ) ? array_map( 'intval', $_POST['category_ids'] ) : array();

		if ( empty( $category_ids ) ) {
			wp_send_json_error( __( 'No categories provided.', 'betterlinks' ) );
		}

		global $wpdb;

		// Get links for the specified categories
		$placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
		$query = $wpdb->prepare(
			"SELECT DISTINCT l.ID, l.short_url, l.target_url, l.link_title 
			FROM {$wpdb->prefix}betterlinks l
			INNER JOIN {$wpdb->prefix}betterlinks_terms_relationships tr ON l.ID = tr.link_id
			WHERE tr.term_id IN ($placeholders)",
			...$category_ids
		);

		$links = $wpdb->get_results( $query, ARRAY_A );

		wp_send_json_success( array(
			'links' => $links,
			'total' => count( $links )
		) );
	}

	/**
	 * Apply UTM template to links
	 */
	public function apply_utm_template_to_links() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'betterlinks' ) );
		}

		// Clear cache to ensure fresh data after updates
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );

		// Parse JSON data that comes from makeRequest
		$template_data = isset( $_POST['template_data'] ) ? json_decode( stripslashes( $_POST['template_data'] ), true ) : array();
		$category_ids = isset( $_POST['category_ids'] ) ? json_decode( stripslashes( $_POST['category_ids'] ), true ) : array();
		$rewrite_existing = isset( $_POST['rewrite_existing'] ) ? filter_var( $_POST['rewrite_existing'], FILTER_VALIDATE_BOOLEAN ) : false;
		$reset_existing = isset( $_POST['reset_existing'] ) ? filter_var( $_POST['reset_existing'], FILTER_VALIDATE_BOOLEAN ) : false;

		// Convert to integers if needed
		if ( is_array( $category_ids ) ) {
			$category_ids = array_map( 'intval', $category_ids );
		}

		if ( empty( $template_data ) || empty( $category_ids ) ) {
			wp_send_json_error( __( 'Invalid template data or categories.', 'betterlinks' ) );
		}

		// Sanitize template data
		$utm_source = sanitize_text_field( $template_data['utm_source'] ?? '' );
		$utm_medium = sanitize_text_field( $template_data['utm_medium'] ?? '' );
		$utm_campaign = sanitize_text_field( $template_data['utm_campaign'] ?? '' );
		$utm_term = sanitize_text_field( $template_data['utm_term'] ?? '' );
		$utm_content = sanitize_text_field( $template_data['utm_content'] ?? '' );

		global $wpdb;

		// Get links for the specified categories
		$placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
		$query = $wpdb->prepare(
			"SELECT DISTINCT l.ID, l.target_url 
			FROM {$wpdb->prefix}betterlinks l
			INNER JOIN {$wpdb->prefix}betterlinks_terms_relationships tr ON l.ID = tr.link_id
			WHERE tr.term_id IN ($placeholders)",
			...$category_ids
		);

		$links = $wpdb->get_results( $query, ARRAY_A );
		$updated_count = 0;
		$skipped_count = 0;

		foreach ( $links as $link ) {
			$target_url = $link['target_url'];
			$parsed_url = parse_url( $target_url );
			
			// Check if URL already has UTM parameters
			$existing_query = isset( $parsed_url['query'] ) ? $parsed_url['query'] : '';
			parse_str( $existing_query, $existing_params );
			
			$has_utm = false;
			$utm_params = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
			foreach ( $utm_params as $param ) {
				if ( isset( $existing_params[$param] ) && !empty( $existing_params[$param] ) ) {
					$has_utm = true;
					break;
				}
			}

			// Handle reset existing UTM functionality
			if ( $reset_existing ) {
				// Only process links that have UTM parameters to reset
				if ( ! $has_utm ) {
					$skipped_count++;
					continue;
				}
				// Remove all UTM parameters when resetting
				foreach ( $utm_params as $param ) {
					unset( $existing_params[$param] );
				}
			} else {
				// Skip if has UTM and rewrite is disabled
				if ( $has_utm && ! $rewrite_existing ) {
					$skipped_count++;
					continue;
				}

				// Remove existing UTM parameters if rewriting
				if ( $rewrite_existing ) {
					foreach ( $utm_params as $param ) {
						unset( $existing_params[$param] );
					}
				}

				// Add new UTM parameters
				if ( ! empty( $utm_source ) ) {
					$existing_params['utm_source'] = $utm_source;
				}
				if ( ! empty( $utm_medium ) ) {
					$existing_params['utm_medium'] = $utm_medium;
				}
				if ( ! empty( $utm_campaign ) ) {
					$existing_params['utm_campaign'] = $utm_campaign;
				}
				if ( ! empty( $utm_term ) ) {
					$existing_params['utm_term'] = $utm_term;
				}
				if ( ! empty( $utm_content ) ) {
					$existing_params['utm_content'] = $utm_content;
				}
			}

			// Rebuild the URL
			$new_query = http_build_query( $existing_params );
			$new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
			
			if ( isset( $parsed_url['port'] ) ) {
				$new_url .= ':' . $parsed_url['port'];
			}
			
			if ( isset( $parsed_url['path'] ) ) {
				$new_url .= $parsed_url['path'];
			}
			
			if ( ! empty( $new_query ) ) {
				$new_url .= '?' . $new_query;
			}
			
			if ( isset( $parsed_url['fragment'] ) ) {
				$new_url .= '#' . $parsed_url['fragment'];
			}

			// Update the link in database
			$result = $wpdb->update(
				$wpdb->prefix . 'betterlinks',
				array( 'target_url' => $new_url ),
				array( 'ID' => $link['ID'] ),
				array( '%s' ),
				array( '%d' )
			);

			if ( $result !== false ) {
				$updated_count++;
			}
		}

		$message = $reset_existing
			? sprintf(
				__( 'UTM parameters reset successfully. Updated: %d, Skipped: %d, Total: %d', 'betterlinks' ),
				$updated_count,
				$skipped_count,
				count( $links )
			)
			: sprintf(
				__( 'UTM template applied successfully. Updated: %d, Skipped: %d, Total: %d', 'betterlinks' ),
				$updated_count,
				$skipped_count,
				count( $links )
			);

		// Clear cache again after all updates to ensure fresh data
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );

		// Track UTM Builder usage
		if ( $updated_count > 0 ) {
			update_option( 'betterlinks_utm_builder_used', true );
		}

		// Regenerate JSON file cache if it exists
		if ( defined( 'BETTERLINKS_EXISTS_LINKS_JSON' ) && BETTERLINKS_EXISTS_LINKS_JSON ) {
			$cron = new \BetterLinks\Cron();
			$cron->write_json_links();
		}

		wp_send_json_success( array(
			'updated_count' => $updated_count,
			'skipped_count' => $skipped_count,
			'total_links' => count( $links ),
			'message' => $message
		) );
	}

	/**
	 * Get UTM status counts for specified categories
	 */
	public function get_utm_status_counts() {
		check_ajax_referer( 'betterlinks_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'betterlinks' ) );
		}

		$category_ids = isset( $_POST['category_ids'] ) ? json_decode( stripslashes( $_POST['category_ids'] ), true ) : array();

		// Convert to integers if needed
		if ( is_array( $category_ids ) ) {
			$category_ids = array_map( 'intval', $category_ids );
		}

		if ( empty( $category_ids ) ) {
			wp_send_json_error( __( 'No categories specified.', 'betterlinks' ) );
		}

		global $wpdb;

		// Get links for the specified categories
		$placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
		$query = $wpdb->prepare(
			"SELECT DISTINCT l.ID, l.target_url 
			FROM {$wpdb->prefix}betterlinks l
			INNER JOIN {$wpdb->prefix}betterlinks_terms_relationships tr ON l.ID = tr.link_id
			WHERE tr.term_id IN ($placeholders)",
			...$category_ids
		);

		$links = $wpdb->get_results( $query, ARRAY_A );
		
		$total_links = count( $links );
		$links_with_utm = 0;
		$links_without_utm = 0;

		foreach ( $links as $link ) {
			$target_url = $link['target_url'];
			$parsed_url = parse_url( $target_url );
			
			// Check if URL already has UTM parameters
			$existing_query = isset( $parsed_url['query'] ) ? $parsed_url['query'] : '';
			parse_str( $existing_query, $existing_params );
			
			$has_utm = false;
			$utm_params = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
			foreach ( $utm_params as $param ) {
				if ( isset( $existing_params[$param] ) && !empty( $existing_params[$param] ) ) {
					$has_utm = true;
					break;
				}
			}

			if ( $has_utm ) {
				$links_with_utm++;
			} else {
				$links_without_utm++;
			}
		}

		wp_send_json_success( array(
			'total_links' => $total_links,
			'links_with_utm' => $links_with_utm,
			'links_without_utm' => $links_without_utm
		) );
	}

	/**
	 * Dismiss Black Friday notice via AJAX
	 * Sets a transient so the notice doesn't show again for 30 days
	 *
	 * @return void
	 */
	public function dismiss_black_friday_notice() {
		// Verify nonce for security
		check_ajax_referer( 'betterlinks_dismiss_black_friday_notice', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( "You don't have permission to do this.", 'betterlinks' ) ),
				403
			);
		}

		// Set transient for 30 days (2592000 seconds)
		$transient_set = set_transient( 'betterlinks_black_friday_pointer_dismissed', true, 2592000 );

		if ( ! $transient_set ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to dismiss notice. Please try again.', 'betterlinks' ) ),
				500
			);
		}

		// Update plugin pointer priority to null when notice is dismissed
		update_option( '_wpdeveloper_plugin_pointer_priority', null );

		// Dismiss the notice in the Notices library system as well
		// This ensures the notice doesn't show again even after page refresh
		// The key format is: {app}_{notice_id}_notice_dismissed
		update_site_option( 'betterlinks_betterlinks_spring_camp_2026_deal_notice_dismissed', true );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed successfully.', 'betterlinks' ) ) );
	}
	
	/**
	 * Backfill country data for existing clicks
	 */
	public function backfill_country_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! wp_verify_nonce( $_POST['security'], 'betterlinks_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		$limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 100;
		$limit = max( 1, min( 500, $limit ) ); // Limit between 1 and 500

		if ( ! class_exists( '\BetterLinks\Services\CountryDetectionService' ) ) {
			wp_send_json_error( array( 'message' => 'Country detection service not available' ) );
		}

		$results = \BetterLinks\Services\CountryDetectionService::backfill_country_data( $limit );

		wp_send_json_success( $results );
	}

	/**
	 * Clear analytics cache
	 */
	public function clear_analytics_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! wp_verify_nonce( $_POST['security'], 'betterlinks_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		global $wpdb;

		// Clear all BetterLinks transients
		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_btl_%' OR option_name LIKE '_transient_timeout_btl_%'"
		);

		wp_send_json_success( array(
			'message' => 'Analytics cache cleared successfully',
			'deleted_transients' => $deleted
		) );
	}


}
