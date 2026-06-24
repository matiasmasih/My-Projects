<?php
namespace BetterLinks\Traits;

use BetterLinks\Helper;

trait Clicks {
	private static $transient_timeout = MINUTE_IN_SECONDS * 30; // 30 MINUTES

	/**
	 * Get Clicks data within a certain time limit
	 *
	 * @param string $from The start time.
	 * @param string $to The end time.
	 *
	 * @return array
	 */
	public function get_clicks_data( $from, $to ) {
		$results = \BetterLinks\Helper::get_clicks_by_date( $from, $to );
		return $results;
	}

	/**
	 * Get transient key for cached analytics data
	 *
	 * @param string     $key The unique identifier for the transient key
	 * @param string     $from The start time.
	 * @param string     $to The end time.
	 * @param string|int $id Clicks id.
	 *
	 * @return string
	 */
	private static function get_transient_key( $key, $from, $to, $id = null ) {
		$transient_key = str_replace( '-', '_', $from ) . '_' . str_replace( '-', '_', $to );
		if ( $id ) {
			$transient_key .= '_' . $id;
		}
		return $key . $transient_key;
	}

	/**
	 * Get Analytics Graph Data
	 *
	 * @param $from The start time.
	 * @param $to The end time.
	 *
	 * @return array Array of total unique clicks and total clicks.
	 */
	public function get_analytics_graph_data( $from, $to ) {
		$transient_key = self::get_transient_key( 'btl_analytics_graph_', $from, $to );
		if ( $results = get_transient( $transient_key ) ) {
			return $results;
		}

		global $wpdb;
		
		// Get excluded IPs
		$options      = json_decode( get_option( BETTERLINKS_LINKS_OPTION_NAME ), true );
		$excluded_ips = isset( $options['excluded_ips'] ) && is_array( $options['excluded_ips'] ) ? $options['excluded_ips'] : array();
		$excluded_ips_condition = '';
		if ( ! empty( $excluded_ips ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $excluded_ips ), '%s' ) );
			$excluded_ips_condition = $wpdb->prepare( " AND ip NOT IN ({$placeholders})", $excluded_ips );
		}
		
		$query        = $wpdb->prepare( 
			"SELECT count(id) as click_count, DATE(created_at) as c_date FROM {$wpdb->prefix}betterlinks_clicks 
            WHERE created_at  BETWEEN %s AND %s {$excluded_ips_condition} GROUP BY c_date ORDER BY c_date DESC",
			$from . ' 00:00:00',
			$to . ' 23:59:59',
		 );
		$total_counts = $wpdb->get_results( $query, ARRAY_A );

		$query         = $wpdb->prepare( 
			"SELECT count(ip) as uniq_count, T1.c_date from ( SELECT ip, DATE( created_at ) as c_date FROM {$wpdb->prefix}betterlinks_clicks 
            WHERE created_at  BETWEEN %s AND %s {$excluded_ips_condition} GROUP BY `ip`, `c_date` ) as T1 GROUP BY T1.c_date ORDER BY T1.c_date DESC",
			$from . ' 00:00:00',
			$to . ' 23:59:59',
		 );
		$unique_counts = $wpdb->get_results(  $query, ARRAY_A );

		$results = array(
			'total_count'  => $total_counts,
			'unique_count' => $unique_counts,
		);
		set_transient( $transient_key, $results, self::$transient_timeout );
		return $results;
	}

	/**
	 * Get Analytics Graph Data by Tag ID
	 *
	 * @param $from The start time.
	 * @param $to The end time.
	 * @param $tag_id The Tag ID.
	 *
	 * @return array Array of total unique clicks and total clicks.
	 */
	public function get_analytics_graph_data_by_tag( $from, $to, $tag_id ) {
		$transient_key = self::get_transient_key( 'btl_analytics_graph_by_tag_', $from, $to, $tag_id );
		if ( $results = get_transient( $transient_key ) ) {
			return $results;
		}

		global $wpdb;
		
		// Get excluded IPs
		$options      = json_decode( get_option( BETTERLINKS_LINKS_OPTION_NAME ), true );
		$excluded_ips = isset( $options['excluded_ips'] ) && is_array( $options['excluded_ips'] ) ? $options['excluded_ips'] : array();
		$excluded_ips_condition = '';
		if ( ! empty( $excluded_ips ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $excluded_ips ), '%s' ) );
			$excluded_ips_condition = $wpdb->prepare( " AND c.ip NOT IN ({$placeholders})", $excluded_ips );
		}
		
		$query        = "SELECT COUNT(c.id) as click_count, DATE(c.created_at) AS c_date FROM {$wpdb->prefix}betterlinks_clicks c 
							LEFT JOIN {$wpdb->prefix}betterlinks_terms_relationships tr ON tr.link_id=c.link_id 
							LEFT JOIN {$wpdb->prefix}betterlinks_terms t ON tr.term_id=t.id 
						WHERE t.term_type='tags' AND t.id='%d' AND c.created_at BETWEEN %s AND %s {$excluded_ips_condition}
						GROUP BY c_date ORDER BY c_date DESC";
		$total_counts = $wpdb->get_results( $wpdb->prepare( $query, $tag_id, "{$from} 00:00:00", "{$to} 23:59:59" ), ARRAY_A );

		$query         = "SELECT COUNT(ip) as uniq_count, T1.c_date FROM 
							( SELECT ip, DATE( created_at ) AS c_date FROM {$wpdb->prefix}betterlinks_clicks c
								LEFT JOIN {$wpdb->prefix}betterlinks_terms_relationships tr ON c.link_id=tr.link_id 
								LEFT JOIN {$wpdb->prefix}betterlinks_terms t ON tr.term_id=t.id 
									WHERE t.term_type='tags' AND t.id='%d' AND created_at BETWEEN %s AND %s {$excluded_ips_condition}
								GROUP BY `ip`, `c_date` ) AS T1  
							GROUP BY T1.c_date ORDER BY T1.c_date DESC";
		$unique_counts = $wpdb->get_results( $wpdb->prepare( $query, $tag_id, "{$from} 00:00:00", "{$to} 23:59:59" ), ARRAY_A );

		$results = array(
			'total_count'  => $total_counts,
			'unique_count' => $unique_counts,
		);
		set_transient( $transient_key, $results, self::$transient_timeout );
		return $results;
	}

	/**
	 * Returns the unique analytics data by tag
	 *
	 * @return array Array of unique analytics by tag
	 */
	public function get_analytics_unique_list_by_tag( $from, $to, $id ) {
		$transient_key = self::get_transient_key( 'btl_analytics_unique_list_by_tag_', $from, $to, $id );
		if ( $results = get_transient( $transient_key ) ) {
			return $results;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		$query = $wpdb->prepare(
			"SELECT id as link_id, link_title, short_url, target_url from {$prefix}betterlinks as links right join (select distinct link_id from {$prefix}betterlinks_clicks where created_at between %s and %s) as clicks on clicks.link_id=links.id right join (select tr.link_id from {$prefix}betterlinks_terms t left join {$prefix}betterlinks_terms_relationships tr on t.ID=tr.term_id where t.term_type='tags' and t.ID=%s) tl on links.id=tl.link_id where id!=''",
			$from . ' 00:00:00',
			$to . ' 23:59:59',
			$id
		);
		$results = $wpdb->get_results( $query, ARRAY_A );

		set_transient( $transient_key, $results, self::$transient_timeout );
		return $results;
	}

	/**
	 * Returns the unique analytics clicks
	 *
	 * @return array Array of unique analytics
	 */
	public function get_analytics_unique_list( $from, $to ) {
		$transient_key = self::get_transient_key( 'btl_analytics_unique_list_', $from, $to );
		if ( $results = get_transient( $transient_key ) ) {
			return $results;
		}
		global $wpdb;
		$prefix = $wpdb->prefix;

		$query = $wpdb->prepare( 
			"SELECT id as link_id, link_title, short_url, target_url from {$prefix}betterlinks as links right join (select distinct link_id from {$prefix}betterlinks_clicks where created_at between %s and %s) as clicks on clicks.link_id=links.id order by links.id desc",
			$from . ' 00:00:00',
			$to . ' 23:59:59',
		 );

		$results = $wpdb->get_results( $query, ARRAY_A );

		set_transient( $transient_key, $results, self::$transient_timeout );
		return $results;
	}

	/**
	 * Returns individual analytics clicks within a time limit
	 *
	 * @param int|string $id Clicks id.
	 * @param string     $from The start time.
	 * @param string     $to The end time.
	 *
	 * @return array Array of individual analytics clicks.
	 */
	public function get_individual_analytics_clicks( $id, $from, $to ) {
		$transient_key = self::get_transient_key( 'btl_individual_analytics_clicks_', $from, $to, $id );
		if ( $results = get_transient( $transient_key ) ) {
			return $results;
		}
		global $wpdb;

		$clicks_table = $wpdb->prefix . 'betterlinks_clicks';
		$countries_table = $wpdb->prefix . 'betterlinks_countries';
		
		// Get excluded IPs
		$options      = json_decode( get_option( BETTERLINKS_LINKS_OPTION_NAME ), true );
		$excluded_ips = isset( $options['excluded_ips'] ) && is_array( $options['excluded_ips'] ) ? $options['excluded_ips'] : array();
		$excluded_ips_condition = '';
		if ( ! empty( $excluded_ips ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $excluded_ips ), '%s' ) );
			$excluded_ips_condition = $wpdb->prepare( " AND c.ip NOT IN ({$placeholders})", $excluded_ips );
		}
		
		// Check if extra data tracking (including country data) is enabled
		$is_extra_data_tracking_compatible = apply_filters( 'betterlinks/is_extra_data_tracking_compatible', false );
		
		// Check if user_agents table exists
		$user_agents_table = $wpdb->prefix . 'betterlinks_user_agents';
		$user_agents_table_exists = $wpdb->get_var( 
			$wpdb->prepare( 
				"SHOW TABLES LIKE %s", 
				$user_agents_table 
			)
		);
		
		if ( $is_extra_data_tracking_compatible ) {
			// Use normalized schema with JOIN to countries table (Pro version)
			if ( $user_agents_table_exists ) {
				$query = $wpdb->prepare(
					"SELECT c.ID, c.link_id, c.ip, c.browser, c.referer, c.os, c.device, c.query_params, c.created_at,
					 co.country_code, co.country_name, ua.user_agent
					 FROM {$clicks_table} c
					 LEFT JOIN {$countries_table} co ON c.country_id = co.id
					 LEFT JOIN {$user_agents_table} ua ON c.user_agent_id = ua.id
					 WHERE c.link_id=%d AND c.created_at BETWEEN %s AND %s {$excluded_ips_condition}
					 ORDER BY c.created_at DESC",
					$id,
					$from . ' 00:00:00',
					$to . ' 23:59:59'
				);
			} else {
				$query = $wpdb->prepare(
					"SELECT c.ID, c.link_id, c.ip, c.browser, c.referer, c.os, c.device, c.query_params, c.created_at,
					 co.country_code, co.country_name, NULL as user_agent
					 FROM {$clicks_table} c
					 LEFT JOIN {$countries_table} co ON c.country_id = co.id
					 WHERE c.link_id=%d AND c.created_at BETWEEN %s AND %s {$excluded_ips_condition}
					 ORDER BY c.created_at DESC",
					$id,
					$from . ' 00:00:00',
					$to . ' 23:59:59'
				);
			}
		} else {
			// Basic query without country data (Free version)
			if ( $user_agents_table_exists ) {
				$query = $wpdb->prepare(
					"SELECT c.ID, c.link_id, c.ip, c.browser, c.referer, c.created_at, ua.user_agent
					 FROM {$clicks_table} c
					 LEFT JOIN {$user_agents_table} ua ON c.user_agent_id = ua.id
					 WHERE c.link_id=%d AND c.created_at BETWEEN %s AND %s {$excluded_ips_condition}
					 ORDER BY c.created_at DESC",
					$id,
					$from . ' 00:00:00',
					$to . ' 23:59:59'
				);
			} else {
				$query = $wpdb->prepare(
					"SELECT c.ID, c.link_id, c.ip, c.browser, c.referer, c.created_at, NULL as user_agent
					 FROM {$clicks_table} c
					 WHERE c.link_id=%d AND c.created_at BETWEEN %s AND %s {$excluded_ips_condition}
					 ORDER BY c.created_at DESC",
					$id,
					$from . ' 00:00:00',
					$to . ' 23:59:59'
				);
			}
		}
		$results = $wpdb->get_results( $query, ARRAY_A );

		// Ensure we always return an array, even if empty
		if ( ! is_array( $results ) ) {
			$results = array();
		}

		set_transient( $transient_key, $results, self::$transient_timeout );
		return $results;
	}	/**
	 * Returns individual link details
	 *
	 * @param int|string $id link id.
	 *
	 * @return Object Object of individual link details.
	 */
	public function get_individual_link_details( $id ) {
		global $wpdb;
		$query = $wpdb->prepare( 
			"SELECT link_title, short_url, target_url FROM {$wpdb->prefix}betterlinks where id=%s",
			$id
		 );
		return $wpdb->get_row( $query );
	}

	private function sanitize_date( $date ){
		if( empty( $date ) ){
			return false;
		}
		$date = sanitize_text_field( $date );
		return strtotime( $date );
	}

	/**
	 * Returns individual link details
	 *
	 * @param int|string $id link id.
	 *
	 * @return Object Object of individual link details.
	 */
	public function get_unique_clicks_count($from, $to) {
		$transient_key = self::get_transient_key( 'btl_unique_clicks_count_', $from, $to );
		if ( $results = get_transient( $transient_key ) ) {
			return $results;
		}
		global $wpdb;
		
		// Get excluded IPs
		$options      = json_decode( get_option( BETTERLINKS_LINKS_OPTION_NAME ), true );
		$excluded_ips = isset( $options['excluded_ips'] ) && is_array( $options['excluded_ips'] ) ? $options['excluded_ips'] : array();
		$excluded_ips_condition = '';
		if ( ! empty( $excluded_ips ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $excluded_ips ), '%s' ) );
			$excluded_ips_condition = $wpdb->prepare( " AND ip NOT IN ({$placeholders})", $excluded_ips );
		}
		
		$query = "SELECT COUNT( DISTINCT ip ) AS count FROM {$wpdb->prefix}betterlinks_clicks WHERE created_at BETWEEN '{$from} 00:00:00' AND '{$to} 23:59:59'" . $excluded_ips_condition;
		$results = $wpdb->get_row( $query, ARRAY_A );
		$results = current( $results );
		set_transient( $transient_key, $results, self::$transient_timeout );
		return $results;
	}

	public function get_analytics_data($from, $to) {
		$transient_key = self::get_transient_key( 'btl_analytics_data_', $from, $to );
		if ( $results = get_transient( $transient_key ) ) {
			return $results;
		}
		
		$results      = array();
		$clicks_count = Helper::get_clicks_count($from, $to);

		$total_clicks  = $clicks_count['total_clicks'];
		$unique_clicks = $clicks_count['unique_clicks'];

		for ( $i = 0; $i < count( $total_clicks ); $i++ ) {
			$results[ $total_clicks[ $i ]['link_id'] ] = array(
				'link_count' => $total_clicks[ $i ]['total_clicks'],
				'ip'         => isset( $unique_clicks[ $i ]['unique_clicks'] ) ? $unique_clicks[ $i ]['unique_clicks'] : 1,
			);
		}
		$results = wp_json_encode( $results );
		set_transient( $transient_key, $results, self::$transient_timeout );
		return $results;
	}
}
