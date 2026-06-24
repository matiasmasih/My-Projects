<?php
namespace BetterLinks\Services;

use BetterLinks\Helper;

/**
 * Country Detection Service
 * 
 * Handles IP-to-country detection with caching and efficient database storage
 */
class CountryDetectionService {

    /**
     * Cache duration for IP-to-country mapping (24 hours)
     */
    const CACHE_DURATION = DAY_IN_SECONDS;

    /**
     * Multiple API endpoints for fallback support
     * Tries APIs in order until one succeeds
     */
    const API_ENDPOINTS = array(
        array(
            'url' => 'http://ip-api.com/json/{IP}',
            'limit' => '45 requests per minute',
            'country_field' => 'country',
            'country_code_field' => 'countryCode'
        ),
        array(
            'url' => 'https://api.db-ip.com/v2/free/{IP}',
            'limit' => '500 requests per day',
            'country_field' => 'countryName',
            'country_code_field' => 'countryCode'
        ),
        array(
            'url' => 'https://free.freeipapi.com/api/json/{IP}',
            'limit' => '60 requests per minute',
            'country_field' => 'countryName',
            'country_code_field' => 'countryCode'
        ),
        array(
            'url' => 'https://api.ipinfo.io/lite/{IP}?token=42ae8aabca02ac',
            'limit' => 'depends on token plan',
            'country_field' => 'country',
            'country_code_field' => 'country_code'
        )
    );

    /**
     * Get country information for an IP address
     *
     * Note: Country data is primarily detected on the frontend via JavaScript.
     * This method is used as a fallback when frontend detection fails.
     *
     * @param string $ip The IP address to lookup
     * @return array|null Array with country_code and country_name, or null if not found
     */
    public static function get_country_by_ip( $ip ) {
        if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return null;
        }

        // Check cache first
        $cached_country = self::get_cached_country( $ip );
        if ( $cached_country !== null ) {
            return $cached_country;
        }

        // Try to fetch from APIs if not cached
        $country_data = self::fetch_country_from_api( $ip );
        if ( $country_data ) {
            // Cache the result
            self::cache_country( $ip, $country_data );
            return $country_data;
        }

        return null;
    }

    /**
     * Get cached country data for an IP
     * 
     * @param string $ip The IP address
     * @return array|null Cached country data or null
     */
    private static function get_cached_country( $ip ) {
        $cache_key = 'btl_country_' . md5( $ip );
        $cached = get_transient( $cache_key );
        
        if ( $cached && is_array( $cached ) ) {
            return $cached;
        }
        
        return null;
    }

    /**
     * Cache country data for an IP
     *
     * @param string $ip The IP address
     * @param array $country_data Country information
     */
    public static function cache_country( $ip, $country_data ) {
        $cache_key = 'btl_country_' . md5( $ip );
        set_transient( $cache_key, $country_data, self::CACHE_DURATION );
    }

    /**
     * Fetch country data from multiple APIs with fallback support
     *
     * @param string $ip The IP address
     * @return array|null Country data from API or null
     */
    private static function fetch_country_from_api( $ip ) {
        foreach ( self::API_ENDPOINTS as $api_config ) {
            $country_data = self::try_single_api( $ip, $api_config );
            if ( $country_data ) {
                return $country_data;
            }
        }
        return null;
    }

    /**
     * Try a single API endpoint
     *
     * @param string $ip The IP address
     * @param array $api_config API configuration
     * @return array|null Country data or null if failed
     */
    private static function try_single_api( $ip, $api_config ) {
        $rate_limit_key = 'btl_api_rate_limit_' . md5( $api_config['url'] );
        if ( get_transient( $rate_limit_key ) ) {
            return null;
        }

        $api_url = str_replace( '{IP}', $ip, $api_config['url'] );

        $response = wp_remote_get( $api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'BetterLinks/' . BETTERLINKS_VERSION
            )
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 429 ) {
            set_transient( $rate_limit_key, true, 5 * MINUTE_IN_SECONDS );
            return null;
        }

        if ( $response_code !== 200 ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return null;
        }

        if ( isset( $data['status'] ) && $data['status'] === 'fail' ) {
            return null;
        }

        $country_field = $api_config['country_field'];
        $country_code_field = $api_config['country_code_field'];

        if ( ! isset( $data[$country_field] ) || ! isset( $data[$country_code_field] ) ) {
            return null;
        }

        return array(
            'country_code' => sanitize_text_field( $data[$country_code_field] ),
            'country_name' => sanitize_text_field( $data[$country_field] ),
        );
    }

    /**
     * Get or create country record and return country_id
     *
     * @param string $country_code The country code
     * @param string $country_name The country name
     * @return int|null Country ID or null if failed
     */
    public static function get_or_create_country_id( $country_code, $country_name ) {
        global $wpdb;

        if ( empty( $country_code ) || empty( $country_name ) ) {
            return null;
        }

        $table_name = $wpdb->prefix . 'betterlinks_countries';

        // Try to get existing country
        $country = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE country_code = %s",
            $country_code
        ), ARRAY_A );

        if ( $country ) {
            return (int) $country['id'];
        }

        // Create new country record
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'country_code' => $country_code,
                'country_name' => $country_name,
            ),
            array( '%s', '%s' )
        );

        if ( $inserted ) {
            return (int) $wpdb->insert_id;
        }

        return null;
    }

    /**
     * Get country data from lookup table by country code
     *
     * @param string $country_code The country code
     * @return array|null Country data or null
     */
    public static function get_country_from_lookup_table( $country_code ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'betterlinks_countries';

        $country = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE country_code = %s",
            $country_code
        ), ARRAY_A );

        return $country ? $country : null;
    }

    /**
     * Get country data by country_id
     *
     * @param int $country_id The country ID
     * @return array|null Country data or null
     */
    public static function get_country_by_id( $country_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'betterlinks_countries';

        $country = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $country_id
        ), ARRAY_A );

        return $country ? $country : null;
    }

    /**
     * Get all countries from lookup table
     * 
     * @return array Array of all countries
     */
    public static function get_all_countries() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'betterlinks_countries';
        
        $countries = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY country_name ASC",
            ARRAY_A
        );
        
        return $countries ? $countries : array();
    }

    /**
     * Clear country cache for an IP
     * 
     * @param string $ip The IP address
     */
    public static function clear_country_cache( $ip ) {
        $cache_key = 'btl_country_' . md5( $ip );
        delete_transient( $cache_key );
    }



    /**
     * Get country statistics for analytics
     *
     * @param string $from Start date
     * @param string $to End date
     * @param int|null $link_id Optional link ID to filter by
     * @return array Country statistics
     */
    public static function get_country_statistics( $from, $to, $link_id = null ) {
        global $wpdb;

        $cache_key = 'btl_country_stats_' . md5( $from . $to . $link_id );
        $cached = get_transient( $cache_key );

        if ( $cached && is_array( $cached ) ) {
            return $cached;
        }

        $clicks_table = $wpdb->prefix . 'betterlinks_clicks';
        $countries_table = $wpdb->prefix . 'betterlinks_countries';

        $where_clause = "WHERE c.created_at BETWEEN %s AND %s AND c.country_id IS NOT NULL";
        $params = array( $from . ' 00:00:00', $to . ' 23:59:59' );

        if ( $link_id ) {
            $where_clause .= " AND c.link_id = %d";
            $params[] = $link_id;
        }

        $query = $wpdb->prepare(
            "SELECT co.country_code, co.country_name, COUNT(*) as clicks, COUNT(DISTINCT c.ip) as unique_clicks
             FROM {$clicks_table} c
             LEFT JOIN {$countries_table} co ON c.country_id = co.id
             {$where_clause}
             GROUP BY c.country_id, co.country_code, co.country_name
             ORDER BY clicks DESC
             LIMIT 10",
            $params
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        set_transient( $cache_key, $results, self::CACHE_DURATION );
        return $results ? $results : array();
    }

    /**
     * Get current client IP address
     *
     * @return string|null The client IP address or null
     */
    public static function get_current_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                $ip = sanitize_text_field( $_SERVER[ $key ] );

                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = explode( ',', $ip )[0];
                }

                $ip = trim( $ip );

                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Backfill country data for existing clicks without country information
     *
     * @param int $limit Number of records to process per batch
     * @return array Processing results
     */
    public static function backfill_country_data( $limit = 100 ) {
        global $wpdb;

        // Get clicks without country_id
        $clicks = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, ip FROM {$wpdb->prefix}betterlinks_clicks
             WHERE ip IS NOT NULL AND ip != ''
             AND country_id IS NULL
             LIMIT %d",
            $limit
        ), ARRAY_A );

        $processed = 0;
        $updated = 0;
        $errors = 0;

        foreach ( $clicks as $click ) {
            $processed++;

            $country_data = self::get_country_by_ip( $click['ip'] );

            if ( $country_data ) {
                // Get or create country record and get its ID
                $country_id = self::get_or_create_country_id(
                    $country_data['country_code'],
                    $country_data['country_name']
                );

                if ( $country_id ) {
                    $result = $wpdb->update(
                        $wpdb->prefix . 'betterlinks_clicks',
                        array( 'country_id' => $country_id ),
                        array( 'ID' => $click['ID'] ),
                        array( '%d' ),
                        array( '%d' )
                    );

                    if ( $result !== false ) {
                        $updated++;
                    } else {
                        $errors++;
                    }
                } else {
                    $errors++;
                }
            } else {
                $errors++;
            }

            // Add a small delay to avoid overwhelming the API
            usleep( 100000 ); // 0.1 second delay
        }

        return array(
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors,
            'remaining' => self::get_clicks_without_country_count()
        );
    }

    /**
     * Get count of clicks without country data
     *
     * @return int Number of clicks without country data
     */
    public static function get_clicks_without_country_count() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}betterlinks_clicks
             WHERE ip IS NOT NULL AND ip != ''
             AND country_id IS NULL"
        );
    }


}
