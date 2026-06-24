<?php
namespace BetterLinks\Link;

use BetterLinks\Helper;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use BetterLinks\Traits\Links;
use BetterLinks\Traits\ArgumentSchema;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\OperatingSystem;
use DeviceDetector\Parser\Client\Browser;

class Utils {
	use Links;
	use ArgumentSchema;

	public function __construct() {
		AbstractDeviceParser::setVersionTruncation( AbstractDeviceParser::VERSION_TRUNCATION_NONE );
	}
	public function get_slug_raw( $slug ) {
		if ( BETTERLINKS_EXISTS_LINKS_JSON ) {
			return apply_filters( 'betterlinks/link/get_link_by_slug', Helper::get_link_from_json_file( $slug ) );
		}
		$link_options      = json_decode( get_option( BETTERLINKS_LINKS_OPTION_NAME, '{}' ), true );
		$is_case_sensitive = isset( $link_options['is_case_sensitive'] ) ? $link_options['is_case_sensitive'] : false;
		$results           = current( Helper::get_link_by_short_url( $slug, $is_case_sensitive ) );
		if ( ! empty( $results ) ) {
			return apply_filters( 'betterlinks/link/get_link_by_slug', json_decode( wp_json_encode( $results ), true ) );
		}
		// wildcards.
		$links_option = json_decode( get_option( BETTERLINKS_LINKS_OPTION_NAME ), true );
		if ( isset( $links_option['wildcards'] ) && $links_option['wildcards'] ) {
			$results = Helper::get_link_by_wildcards( 1 );
			if ( is_array( $results ) && count( $results ) > 0 ) {
				foreach ( $results as $key => $item ) {
					$postion = strpos( $item['short_url'], '/*' );
					if ( false !== $postion ) {
						$item_short_url_substr = substr( $item['short_url'], 0, $postion );
						$slug_substr           = substr( $slug, 0, $postion );
						if ( ! $is_case_sensitive ) {
							$item_short_url_substr = strtolower( $item_short_url_substr );
							$slug_substr           = strtolower( $slug_substr );
						}
						if ( $item_short_url_substr === $slug_substr ) {
							$target_postion = strpos( $item['target_url'], '/*' );
							if ( false !== $target_postion ) {
								$target_url         = str_replace( '/*', substr( $slug, $postion ), $item['target_url'] );
								$item['target_url'] = $target_url;
								return apply_filters( 'betterlinks/link/get_link_by_slug', json_decode( wp_json_encode( $item ), true ) );
							}
							return apply_filters( 'betterlinks/link/get_link_by_slug', json_decode( wp_json_encode( $item ), true ) );
						}
					}
				}
			}
		}
	}
	public function dispatch_redirect( $data, $param ) {
		global $betterlinks;

		$comparable_url  = rtrim( preg_replace( '/https?\:\/\//', '', site_url( '/' ) ), '/' ) . '/' . $data['short_url'];
		$destination_url = rtrim( preg_replace( '/https?\:\/\//', '', $data['target_url'] ), '/' );
		$comparable_url  = rtrim( preg_replace( '/^www\.?/', '', $comparable_url ), '/' );
		$destination_url = rtrim( preg_replace( '/^www\.?/', '', $destination_url ), '/' );
		if ( ! $data || $comparable_url === $destination_url ) {
			return;
		}

		$target_url    = $this->addScheme( $data['target_url'] );
		$_query_params = array();
		wp_parse_str( $param, $_query_params );
		$data['pf'] = build_query( $_query_params );
		if ( filter_var( $data['param_forwarding'], FILTER_VALIDATE_BOOLEAN ) && ! empty( $param ) && $param !== $data['link_slug'] ) {
			$_target_url = wp_parse_url( $target_url );
			$target_url .= ( isset( $_target_url['query'] ) ? '&' : '?' ) . $data['pf'];
		}

		if ( filter_var( $data['track_me'], FILTER_VALIDATE_BOOLEAN ) ) {
			$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore
			$dd         = new DeviceDetector( $user_agent );
			$dd->parse();
	
			$data      = apply_filters( 'betterlinks/extra_tracking_data', $data, $dd );
	
			$data['os']      = OperatingSystem::getOsFamily( $dd->getOs( 'name' ) );
			$data['browser'] = Browser::getBrowserFamily( $dd->getClient( 'name' ) );
			$data['device']  = $dd->getDeviceName();
	
			if ( isset( $betterlinks['disablebotclicks'] ) && $betterlinks['disablebotclicks'] ) {
				if ( ! $dd->isBot() ) {
					$this->start_trakcing( $data );
				}
			} else {
				$this->start_trakcing( $data );
			}
		}
		

		$robots_tags = array();
		if ( filter_var( $data['sponsored'], FILTER_VALIDATE_BOOLEAN ) ) {
			$robots_tags[] = 'sponsored';
		}
		if ( filter_var( $data['nofollow'], FILTER_VALIDATE_BOOLEAN ) ) {
			$robots_tags[] = 'noindex';
			$robots_tags[] = 'nofollow';
		}
		if ( ! empty( $robots_tags ) ) {
			header( 'X-Robots-Tag: ' . implode( ', ', $robots_tags ), true );
		}

		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
		header( 'Cache-Control: no-cache' );
		header( 'Pragma: no-cache' );
		header( 'X-Redirect-Powered-By:  https://www.betterlinks.io/' );

		switch ( $data['redirect_type'] ) {
			case '301':
				wp_redirect( esc_url_raw( $target_url ), 301 );
				exit;
			case '302':
				wp_redirect( esc_url_raw( $target_url ), 302 );
				exit;
			case '307':
				wp_redirect( esc_url_raw( $target_url ), 307 );
				exit;
			case 'cloak':
				do_action( 'betterlinks/make_cloaked_redirect', $target_url, $data );
				exit;
			default:
				wp_redirect( esc_url_raw( $target_url ) );
				exit;
		}
	}

	public function start_trakcing( $data ) {
		global $betterlinks;
		$is_disable_analytics_ip = isset( $betterlinks['is_disable_analytics_ip'] ) ? $betterlinks['is_disable_analytics_ip'] : false;
		do_action( 'betterlinks/link/before_start_tracking', $data );
		$now            = current_time( 'mysql' );
		$now_gmt        = current_time( 'mysql', 1 );
		$visitor_cookie = 'betterlinks_visitor';
		if ( ! isset( $_COOKIE[ $visitor_cookie ] ) ) {
			$visitor_cookie_expire_time = time() + 60 * 60 * 24 * 365; // 1 year
			$visitor_uid                = uniqid( 'bl' );
			setcookie( $visitor_cookie, $visitor_uid, $visitor_cookie_expire_time, '/' );
		}
		// checking if split tes enabled.
		$is_split_enabled = apply_filters( 'betterlinkspro/admin/split_test_tracking', false, $data );

		$click_data = array(
			'link_id'             => $data['ID'],
			'browser'             => isset( $data['browser'] ) ? $data['browser'] : '',
			'os'                  => isset( $data['os'] ) ? $data['os'] : '',
			'device'              => isset( $data['device'] ) ? $data['device'] : '',
			'referer'             => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '', // phpcs:ignore
			'uri'                 => $data['link_slug'],
			'click_count'         => 0,
			'visitor_id'          => isset( $_COOKIE[ $visitor_cookie ] ) ? sanitize_text_field( $_COOKIE[ $visitor_cookie ] ) : '',
			'click_order'         => 0,
			'created_at'          => $now,
			'created_at_gmt'      => $now_gmt,
			'rotation_target_url' => $data['target_url'],
			'target_url'          => $data['target_url'],
			'is_split_enabled'    => $is_split_enabled,
		);
		if ( ! $is_disable_analytics_ip ) {
			$IP                 = $this->get_current_client_IP();
			$click_data['ip']   = $IP;
			$click_data['host'] = $IP;

			// Only process country data if BetterLinks Pro v2.5.0 or newer is installed
			$is_pro_version_valid = defined( 'BETTERLINKS_PRO_VERSION' ) && version_compare( BETTERLINKS_PRO_VERSION, '2.5.0', '>=' );

			if ( $is_pro_version_valid ) {
				// Check if country data was provided from frontend geolocation
				$has_frontend_country = isset( $data['country_code'] ) && isset( $data['country_name'] );

				if ( $has_frontend_country ) {
					// Use country data from frontend geolocation
					$click_data['country_code'] = $data['country_code'];
					$click_data['country_name'] = $data['country_name'];
				} else {
					// Fallback to server-side detection if frontend didn't provide country data
					if ( class_exists( '\BetterLinks\Services\CountryDetectionService' ) ) {
						$country_data = \BetterLinks\Services\CountryDetectionService::get_country_by_ip( $IP );
						if ( $country_data ) {
							$click_data['country_code'] = $country_data['country_code'];
							$click_data['country_name'] = $country_data['country_name'];
						}
					}
				}
			}
		}

		if ( apply_filters( 'betterlinks/is_extra_data_tracking_compatible', false ) ) {
			$query_params = apply_filters( 'betterlinkspro/admin/parameter_tracking_values', array(), $data );

			$click_data['brand_name']      = isset( $data['brand_name'] ) ? $data['brand_name'] : '';
			$click_data['model']           = isset( $data['model'] ) ? $data['model'] : '';
			$click_data['bot_name']        = isset( $data['bot_name'] ) ? $data['bot_name'] : '';
			$click_data['browser_type']    = isset( $data['browser_type'] ) ? $data['browser_type'] : '';
			$click_data['browser_version'] = isset( $data['browser_version'] ) ? $data['browser_version'] : '';
			$click_data['os_version']      = isset( $data['os_version'] ) ? $data['os_version'] : '';
			$click_data['language']        = isset( $data['language'] ) ? $data['language'] : '';
			$click_data['query_params']    = wp_json_encode( $query_params );
		}

		// Add user agent if tracking is enabled
		$settings = get_option( BETTERLINKS_LINKS_OPTION_NAME, '[]' );
		if ( is_string( $settings ) ) {
			$settings = json_decode( $settings, true );
		}
		if ( ! empty( $settings['enable_user_agent_tracking'] ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$click_data['user_agent'] = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
		}
		$arg = apply_filters( 'betterlinks/link/insert_click_arg', $click_data );

		if ( BETTERLINKS_EXISTS_CLICKS_JSON ) {
			$this->insert_json_into_file( BETTERLINKS_UPLOAD_DIR_PATH . '/clicks.json', $arg );
		} else {
			try {
				$click_id = Helper::insert_click( $arg );
				if ( ! empty( $click_id ) && $is_split_enabled ) {
					do_action( 'betterlinks/link/after_insert_click', $arg['link_id'], $click_id, $arg['target_url'] );
				}
			} catch ( \Throwable $th ) {
				echo $th->getMessage();
			}
		}
	}

	public function get_current_client_IP() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) && $_SERVER['HTTP_CLIENT_IP'] != '127.0.0.1' ) {
			$address = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) && $_SERVER['HTTP_X_FORWARDED'] != '127.0.0.1' ) {
			$address = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED'] );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '127.0.0.1' ) {
			$address = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		} elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) && $_SERVER['HTTP_FORWARDED'] != '127.0.0.1' ) {
			$address = sanitize_text_field( $_SERVER['HTTP_FORWARDED'] );
		} elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) && $_SERVER['HTTP_FORWARDED_FOR'] !== '127.0.0.1' ) {
			$address = sanitize_text_field( $_SERVER['HTTP_FORWARDED_FOR'] );
		}
		$IPS = explode( ',', $address );
		if ( isset( $IPS[1] ) ) {
			$address = $IPS[0];
		}
		return $address;
	}
	public function addScheme( $url, $scheme = 'http://' ) {
		if ( strpos( $url, '/' ) === 0 ) {
			return $url = site_url( '/' ) . $url;
		}
		return apply_filters( 'betterlinks/link/target_url', wp_parse_url( $url, PHP_URL_SCHEME ) === null ? $scheme . $url : $url );
	}

	protected function insert_json_into_file( $file, $data ) {
		$existing_data = file_get_contents( $file );
		$temp_array    = (array) json_decode( $existing_data, true );
		array_push( $temp_array, $data );
		return file_put_contents( $file, wp_json_encode( $temp_array ) );
	}


	public function create_new_link( $title, $target_url, $settings ) {
		$date             = wp_date( 'Y-m-d H:i:s' );
		$helper           = new Helper();
		$slug             = $helper->generate_random_slug();
		$prefix           = ! empty( $settings['prefix'] ) ? $settings['prefix'] . '/' : '';
		$nofollow         = ! empty( $settings['nofollow'] ) ? $settings['nofollow'] : null;
		$sponsored        = ! empty( $settings['sponsored'] ) ? $settings['sponsored'] : null;
		$track_me         = ! empty( $settings['track_me'] ) ? $settings['track_me'] : null;
		$param_forwarding = ! empty( $settings['param_forwarding'] ) ? $settings['param_forwarding'] : null;
		$powered_by       = ! empty( $settings['cle']['powered_by'] ) ? sanitize_text_field( $settings['cle']['powered_by'] ) : '';
		$short_url        = $prefix . $slug;

		$initial_values = array(
			'link_title'        => $title,
			'link_slug'         => $slug,
			'target_url'        => $target_url,
			'short_url'         => $short_url,
			'redirect_type'     => '307',
			'nofollow'          => $nofollow,
			'sponsored'         => $sponsored,
			'track_me'          => $track_me,
			'param_forwarding'  => $param_forwarding,
			'link_date'         => $date,
			'link_date_gmt'     => $date,
			'link_modified'     => $date,
			'link_modified_gmt' => $date,
			'cat_id'            => 1,
			'powered_by'        => $powered_by,
		);
		$initial_values = apply_filters( 'betterlinks_before_cle', $initial_values, $settings );

		$helper->clear_query_cache();
		$args    = $this->sanitize_links_data( $initial_values );
		$results = $this->insert_link( $args );

		if ( ! empty( $results ) ) {
			require_once BETTERLINKS_ROOT_DIR_PATH . '/includes/Views/create-link-externally.php';
			exit;
		}
		wp_safe_redirect( home_url() );
		exit;
	}

	public static function prevent_unwanted_cle() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( sanitize_url( $_SERVER['REQUEST_URI'] ) ) : '';

		$params = strpos($request_uri, 'action%3Dbtl_cle%26api_key');
		if ( !empty( $params ) ) { // to prevent short link creation of the 'Here is your BetterLinks' page
			$prevent_unwanted_click = true; // phpcs:ignore
			require_once BETTERLINKS_ROOT_DIR_PATH . '/includes/Views/create-link-externally.php';
			exit;
		}
	}
}
