<?php
namespace BetterLinks;

use BetterLinks\Admin\Cache;
use WP_Http;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\OperatingSystem;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use DeviceDetector\Parser\Client\Browser;

class Helper {

	use Traits\Query;
	use Traits\Clicks;

	public static function btl_menu_notice() {
		return BETTERLINKS_MENU_NOTICE !== get_option( 'betterlinks_menu_notice', 0 );
	}
	public static function get_links() {
		if ( BETTERLINKS_EXISTS_LINKS_JSON ) {
			$data = json_decode( file_get_contents( BETTERLINKS_UPLOAD_DIR_PATH . '/links.json' ), true );
			if ( empty( $data ) ) {
				$cron = new Cron();
				$cron->write_json_links();
				return json_decode( file_get_contents( BETTERLINKS_UPLOAD_DIR_PATH . '/links.json' ), true );
			}
			return $data;
		}
		$options = json_decode( get_option( BETTERLINKS_LINKS_OPTION_NAME ), true );
		return is_array( $options )
				? array(
					'wildcards_is_active'         => isset( $options['wildcards'] ) ? $options['wildcards'] : false,
					'disablebotclicks'            => isset( $options['disablebotclicks'] ) ? $options['disablebotclicks'] : false,
					'force_https'                 => isset( $options['force_https'] ) ? $options['force_https'] : false,
					'autolink_disable_post_types' => isset( $options['autolink_disable_post_types'] ) ? $options['autolink_disable_post_types'] : array(),
					'is_autolink_icon'            => isset( $options['is_autolink_icon'] ) ? $options['is_autolink_icon'] : false,
					'is_autolink_headings'        => isset( $options['is_autolink_headings'] ) ? $options['is_autolink_headings'] : false,
					'uncloaked_categories'        => isset( $options['uncloaked_categories'] ) ? $options['uncloaked_categories'] : array(),
					'is_disable_analytics_ip'     => isset( $options['is_disable_analytics_ip'] ) ? $options['is_disable_analytics_ip'] : false,
					'excluded_ips'                => isset( $options['excluded_ips'] ) ? $options['excluded_ips'] : array(),
				)
				: array(
					'wildcards_is_active' => false,
					'disablebotclicks'    => false,
					'force_https'         => false,
					'excluded_ips'        => array(),
				);
	}

	public static function get_link_from_json_file( $short_url ) {
		if ( empty( $short_url ) ) {
			return;
		}
		global $betterlinks;
		if ( ! ( isset( $betterlinks['is_case_sensitive'] ) && $betterlinks['is_case_sensitive'] ) ) {
			$short_url = strtolower( $short_url );
		}
		if ( isset( $betterlinks['links'][ $short_url ] ) ) {
			return $betterlinks['links'][ $short_url ];
		}
		if ( isset( $betterlinks['wildcards_is_active'] ) && $betterlinks['wildcards_is_active'] ) {
			if ( isset( $betterlinks['wildcards'] ) && count( $betterlinks['wildcards'] ) > 0 ) {
				foreach ( $betterlinks['wildcards'] as $key => $item ) {
					$postion = strpos( $key, '/*' );
					if ( false !== $postion ) {
						if ( substr( $key, 0, $postion ) === substr( $short_url, 0, $postion ) ) {
							$target_postion = strpos( $item['target_url'], '/*' );
							if ( false !== $target_postion ) {
								$target_url         = str_replace( '/*', substr( $short_url, $postion ), $item['target_url'] );
								$item['target_url'] = $target_url;
								return $item;
							}
							return $item;
						}
					}
				}
			}
		}
	}

	public static function get_menu_items() {
		// $enable_custom_domain_menu = get_option(BETTERLINKS_CUSTOM_DOMAIN_MENU, 0);
		$enable_custom_domain_menu = Cache::get_json_settings();
		$enable_custom_domain_menu = !empty( $enable_custom_domain_menu['enable_custom_domain_menu'] ) ?  $enable_custom_domain_menu['enable_custom_domain_menu'] : false;
		
		$menu_items = array(
			BETTERLINKS_PLUGIN_SLUG                  => array(
				'title'      => __( 'Manage Links', 'betterlinks' ),
				'capability' => 'manage_options',
			),
			BETTERLINKS_PLUGIN_SLUG . '-manage-tags-and-categories' => array(
				'title'      => __( 'Tags & Categories', 'betterlinks' ),
				'capability' => 'manage_options',
			),
			BETTERLINKS_PLUGIN_SLUG . '-analytics'   => array(
				'title'      => __( 'Analytics', 'betterlinks' ),
				'capability' => 'manage_options',
			),
			BETTERLINKS_PLUGIN_SLUG . '-link-scanner'   => array(
				'title'      => __( 'Link Scanner', 'betterlinks' ),
				'capability' => 'manage_options',
			),
			BETTERLINKS_PLUGIN_SLUG . '-settings'    => array(
				'title'      => __( 'Settings', 'betterlinks' ),
				'capability' => 'manage_options',
			),
		);
		

		if( get_option( 'betterlinks_quick_setup_step' ) !== 'complete' ){
			$menu_items[BETTERLINKS_PLUGIN_SLUG . '-quick-setup'] = array(
				'title'      => __( 'Quick Setup', 'betterlinks' ),
				'capability' => 'manage_options',
			);
		}

		if( !empty( $enable_custom_domain_menu ) ){
			$before = array_splice( $menu_items, 0, 2 );
			$inserted = array(
				BETTERLINKS_PLUGIN_SLUG . '-custom-domain' => array(
					'title'      => __( 'Custom Domain', 'betterlinks' ),
					'capability' => 'manage_options',
				),
			);
			$menu_items = $before + $inserted + $menu_items;
		}

		return apply_filters( 'betterlinks/helper/menu_items', $menu_items );
	}

	/**
	 * Check Supported Post type for admin page and plugin main settings page
	 *
	 * @return bool
	 */
	public static function plugin_page_hook_suffix( $hook ) {
		if ( 'toplevel_page_' . BETTERLINKS_PLUGIN_SLUG === $hook ) {
			return true;
		} else {
			foreach ( self::get_menu_items() as $key => $value ) {
				if ( BETTERLINKS_PLUGIN_SLUG . '_page_' . $key === $hook || strpos( $hook, BETTERLINKS_PLUGIN_SLUG . '_page_' . $key ) || strpos( '_' . $hook, 'betterlinks' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function make_slug( $str ) {
		if ( empty( $str ) ) {
			return;
		}
		if ( $str !== mb_convert_encoding( mb_convert_encoding( $str, 'UTF-32', 'UTF-8' ), 'UTF-8', 'UTF-32' ) ) {
			$str = mb_convert_encoding( $str, 'UTF-8', mb_detect_encoding( $str ) );
		}
		$str = htmlentities( $str, ENT_NOQUOTES, 'UTF-8' );
		$str = preg_replace( '`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i', '\\1', $str );
		$str = html_entity_decode( $str, ENT_NOQUOTES, 'UTF-8' );
		$str = preg_replace( array( '`[^a-z0-9]`i', '`[-]+`' ), '-', $str );
		$str = strtolower( trim( $str, '-' ) );
		$str = substr( $str, 0, 100 );
		return $str;
	}

	public static function link_exists( $title, $slug = '' ) {
		global $wpdb;

		$link_title  = wp_unslash( sanitize_post_field( 'link_title', $title, 0, 'db' ) );
		$short_url   = wp_unslash( sanitize_post_field( 'short_url', $slug, 0, 'db' ) );
		$betterlinks = $wpdb->prefix . 'betterlinks';
		$query       = "SELECT link_title, short_url FROM  $betterlinks WHERE ";
		$args        = array();

		if ( ! empty( $title ) ) {
			$query .= ' link_title = %s';
			$args[] = $link_title;
		}

		if ( ! empty( $slug ) ) {
			$query .= ' AND short_url = %s';
			$args[] = $short_url;
		}

		if ( ! empty( $args ) ) {
			$results = $wpdb->get_var( $wpdb->prepare( $query, $args ) );
			if ( ! empty( $results ) ) {
				return true;
			}
			return;
		}
	}
	public static function term_exists( $slug ) {
		global $wpdb;

		$term_slug   = wp_unslash( sanitize_post_field( 'term_slug', $slug, 0, 'db' ) );
		$betterlinks = $wpdb->prefix . 'betterlinks_terms';
		$query       = "SELECT term_slug FROM  $betterlinks WHERE ";
		$args        = array();

		if ( ! empty( $slug ) ) {
			$query .= ' term_slug = %s';
			$args[] = $term_slug;
		}

		if ( ! empty( $args ) ) {
			$results = $wpdb->get_var( $wpdb->prepare( $query, $args ) );
			if ( ! empty( $results ) ) {
				return true;
			}
			return;
		}
	}
	public static function click_exists( $ID ) {
		global $wpdb;
		$click_ID    = wp_unslash( sanitize_post_field( 'ID', $ID, 0, 'db' ) );
		$betterlinks = $wpdb->prefix . 'betterlinks_clicks';
		$query       = "SELECT ID FROM  $betterlinks WHERE ";
		$args        = array();

		if ( ! empty( $click_ID ) ) {
			$query .= ' ID = %d';
			$args[] = $click_ID;
		}

		if ( ! empty( $args ) ) {
			$results = $wpdb->get_var( $wpdb->prepare( $query, $args ) );
			if ( ! empty( $results ) ) {
				return true;
			}
			return;
		}
	}

	public static function create_cron_jobs_for_json_links() {
		wp_clear_scheduled_hook( 'betterlinks/write_json_links' );
		wp_schedule_single_event( time() + 5, 'betterlinks/write_json_links' );
	}

	public static function write_links_inside_json() {
		$cron = new Cron();
		$cron->write_json_links();
	}

	public static function create_cron_jobs_for_analytics() {
		wp_clear_scheduled_hook( 'betterlinks/analytics' );
		wp_schedule_single_event( time() + 5, 'betterlinks/analytics' );
	}

	public static function clear_query_cache() {
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
	}

	public static function create_links_cache() {
		$results = self::get_prepare_all_links();
		set_transient( BETTERLINKS_CACHE_LINKS_NAME, json_encode( $results ) );
	}

	public static function parse_link_response( $items, $analytic, $broken_links ) {
		$results                  = array();
		$broken_link_status_codes = array( 401, 403, 404 );

		$tags_list = array();

		foreach ( $items as $item ) {
			if ( null !== $item->ID && 'tags' === $item->term_type ) {
				array_push( $tags_list, $item );
			}
		}

		foreach ( $items as $item ) {
			if ( 'category' === $item->term_type ) {
				// insert analytic data.
				if ( null !== $item->ID && isset( $analytic[ $item->ID ] ) ) {
					$item->analytic = $analytic[ $item->ID ];
				}
				if ( ! empty( $item->param_struct ) ) {
					$item->param_struct = unserialize( $item->param_struct );
				}
				if ( class_exists( '\BetterLinksPro' ) ) {
					$custom_tracking_scripts = self::get_link_meta( $item->ID, 'btl_custom_tracking_scripts' );
					if ( ! empty( $custom_tracking_scripts ) ) {
						$custom_tracking_scripts       = unserialize( $custom_tracking_scripts );
						$item->enable_custom_scripts   = isset( $custom_tracking_scripts['enable'] ) ? $custom_tracking_scripts['enable'] : false;
						$item->custom_tracking_scripts = isset( $custom_tracking_scripts['script'] ) ? $custom_tracking_scripts['script'] : '';
					}
				}

				if ( null !== $item->ID && isset( $broken_links[ $item->ID ] ) && is_array( $broken_links[ $item->ID ] ) && isset( $broken_links[ $item->ID ]['status']['status_code'] ) && in_array( $broken_links[ $item->ID ]['status']['status_code'], $broken_link_status_codes ) && empty( $broken_links[ $item->ID ]['is_log_removed'] ) ) {
					$item->link_status = 'broken';
				} elseif ( 'broken' === $item->link_status && null !== $item->ID && isset( $broken_links[ $item->ID ] ) && is_array( $broken_links[ $item->ID ] ) && isset( $broken_links[ $item->ID ]['old_link_status'] ) && 'broken' !== $broken_links[ $item->ID ]['old_link_status'] ) {
					// if the link is fixed, but if db is not updated it to fixed link immediately then it will be marked as old status code.
					$item->link_status = $broken_links[ $item->ID ]['old_link_status'];
				}
				$item->tags_data = array();
				$item->tags_id = array(); // Initialize tags_id array for form submission

				foreach ( $tags_list as $tag ) {
					if ( $tag->ID === $item->ID ) {
						array_push(
							$item->tags_data,
							array(
								'term_id'   => $tag->cat_id,
								'term_name' => $tag->term_name,
								'term_slug' => $tag->term_slug,
							)
						);
						// Also add to tags_id array for form submission
						array_push( $item->tags_id, $tag->cat_id );
					}
				}

				// formatting response.
				if ( ! isset( $results[ $item->cat_id ] ) ) {
					$results[ $item->cat_id ] = array(
						'term_name' => $item->term_name,
						'term_slug' => $item->term_slug,
						'term_type' => $item->term_type,
					);
					if ( null !== $item->ID ) {
						$results[ $item->cat_id ]['lists'][] = $item;
					} else {
						$results[ $item->cat_id ]['lists'] = array();
					}
				} else {
					$results[ $item->cat_id ]['lists'][] = $item;
				}
			}
		}
		return $results;
	}
	public static function json_link_formatter( $data ) {
		$res = array(
			'ID'               => $data['ID'],
			'link_slug'        => $data['link_slug'],
			'link_status'      => ( isset( $data['link_status'] ) ? $data['link_status'] : 'publish' ),
			'short_url'        => $data['short_url'],
			'redirect_type'    => ( isset( $data['redirect_type'] ) ? $data['redirect_type'] : '307' ),
			'target_url'       => $data['target_url'],
			'nofollow'         => ( isset( $data['nofollow'] ) ? $data['nofollow'] : false ),
			'sponsored'        => ( isset( $data['sponsored'] ) ? $data['sponsored'] : false ),
			'param_forwarding' => ( isset( $data['param_forwarding'] ) ? $data['param_forwarding'] : false ),
			'track_me'         => ( isset( $data['track_me'] ) ? $data['track_me'] : false ),
			'wildcards'        => ( isset( $data['wildcards'] ) ? $data['wildcards'] : false ),
			'expire'           => ( isset( $data['expire'] ) ? $data['expire'] : null ),
			'dynamic_redirect' => ( isset( $data['dynamic_redirect'] ) ? $data['dynamic_redirect'] : null ),
			'cat_id'           => isset( $data['cat_id'] ) ? $data['cat_id'] : null,
		);
		if ( isset( $data['uncloaked'] ) && $data['uncloaked'] ) {
			$res['uncloaked'] = $data['uncloaked'];
		}
		return $res;
	}
	public static function insert_json_into_file( $file, $data ) {
		$existingData              = file_get_contents( $file );
		$existingData              = json_decode( $existingData, true );
		$case_sensitive_is_enabled = isset( $existingData['is_case_sensitive'] ) ? $existingData['is_case_sensitive'] : false;
		$short_url                 = $case_sensitive_is_enabled ? $data['short_url'] : strtolower( $data['short_url'] );
		if ( isset( $data['wildcards'] ) && $data['wildcards'] ) {
			$tempArray = $existingData['wildcards'];
			// Remove any existing entry with the same ID to prevent duplicates
			if ( isset( $data['ID'] ) ) {
				foreach ( $tempArray as $key => $entry ) {
					if ( isset( $entry['ID'] ) && $entry['ID'] == $data['ID'] ) {
						unset( $tempArray[ $key ] );
						break;
					}
				}
			}
			$tempArray[ $short_url ]   = self::json_link_formatter( $data );
			$existingData['wildcards'] = $tempArray;
		} else {
			$tempArray = ( isset( $existingData['links'] ) ? $existingData['links'] : array() );
			// Remove any existing entry with the same ID to prevent duplicates
			if ( isset( $data['ID'] ) ) {
				foreach ( $tempArray as $key => $entry ) {
					if ( isset( $entry['ID'] ) && $entry['ID'] == $data['ID'] ) {
						unset( $tempArray[ $key ] );
						break;
					}
				}
			}
			$tempArray[ $short_url ] = self::json_link_formatter( $data );
			$existingData['links']   = $tempArray;
		}
		return file_put_contents( $file, wp_json_encode( $existingData ) );
	}
	public static function update_json_into_file( $file, $data, $old_short_url = '' ) {
		if ( ! isset( $data['short_url'] ) ) {
			return false;
		}
		$existingData              = file_get_contents( $file );
		$existingData              = json_decode( $existingData, true );
		// make sure we always have an array to work with
		if ( ! is_array( $existingData ) ) {
			$existingData = array();
		}
        $case_sensitive_is_enabled = isset( $existingData['is_case_sensitive'] ) ? $existingData['is_case_sensitive'] : false;
        $short_url                 = $case_sensitive_is_enabled ? $data['short_url'] : strtolower( $data['short_url'] );

        if ( isset( $data['wildcards'] ) && ! empty( $data['wildcards'] ) ) {
            $tempArray = isset( $existingData['wildcards'] ) && is_array( $existingData['wildcards'] ) ? $existingData['wildcards'] : array();
             if ( is_array( $tempArray ) ) {
                 $found_old_entry = false;

                // First, try to find and remove by old_short_url if provided
                if ( ! empty( $old_short_url ) ) {
                    $old_short_url_lower = strtolower( $old_short_url );
                    if ( isset( $tempArray[ $old_short_url ] ) ) {
                        unset( $tempArray[ $old_short_url ] );
                        $found_old_entry = true;
                    } elseif ( isset( $tempArray[ $old_short_url_lower ] ) ) {
                        unset( $tempArray[ $old_short_url_lower ] );
                        $found_old_entry = true;
                    }
                }

                // If old entry not found by short_url, search by ID to remove all duplicates
                if ( ! $found_old_entry && isset( $data['ID'] ) ) {
                    foreach ( $tempArray as $key => $entry ) {
                        if ( isset( $entry['ID'] ) && $entry['ID'] == $data['ID'] ) {
                            unset( $tempArray[ $key ] );
                        }
                    }
                }

                $tempArray[ $short_url ]   = self::json_link_formatter( $data );
                $existingData['wildcards'] = $tempArray;
                return file_put_contents( $file, wp_json_encode( $existingData ) );
            }
        } else {
            $tempArray     = isset( $existingData['links'] ) && is_array( $existingData['links'] ) ? $existingData['links'] : array();
             $previous_data = array();
             if ( is_array( $tempArray ) ) {
                 $found_old_entry = false;

                // First, try to find and remove by old_short_url if provided
                if ( ! empty( $old_short_url ) ) {
                    $old_short_url_lower = strtolower( $old_short_url );
                    if ( isset( $tempArray[ $old_short_url ] ) ) {
                        $previous_data = $tempArray[ $old_short_url ];
                        unset( $tempArray[ $old_short_url ] );
                        $found_old_entry = true;
                    } elseif ( isset( $tempArray[ $old_short_url_lower ] ) ) {
                        $previous_data = $tempArray[ $old_short_url_lower ];
                        unset( $tempArray[ $old_short_url_lower ] );
                        $found_old_entry = true;
                    }
                }

                // If old entry not found by short_url, search by ID to remove all duplicates
                if ( ! $found_old_entry && isset( $data['ID'] ) ) {
                    foreach ( $tempArray as $key => $entry ) {
                        if ( isset( $entry['ID'] ) && $entry['ID'] == $data['ID'] ) {
                            $previous_data = $entry;
                            unset( $tempArray[ $key ] );
                        }
                    }
                }

                $data                    = wp_parse_args( $data, $previous_data );
                $tempArray[ $short_url ] = self::json_link_formatter( $data );
                $existingData['links']   = $tempArray;
                return file_put_contents( $file, wp_json_encode( $existingData ) );
            }
        }
	}
	public static function delete_json_into_file( $file, $short_url ) {
		$existingData = file_get_contents( $file );
		$existingData = json_decode( $existingData, true );
		if ( ! is_array( $existingData ) ) {
			$existingData = array();
		}
		if ( isset( $existingData['wildcards'][ $short_url ] ) || isset( $existingData['wildcards'][ strToLower( $short_url ) ] ) ) {
			$tempArray = $existingData['wildcards'];
			if ( is_array( $tempArray ) ) {
				unset( $tempArray[ $short_url ] );
				unset( $tempArray[ strToLower( $short_url ) ] );
				$existingData['wildcards'] = $tempArray;
				return file_put_contents( $file, wp_json_encode( $existingData ) );
			}
		} elseif ( isset( $existingData['links'][ $short_url ] ) || isset( $existingData['links'][ strtolower( $short_url ) ] ) ) {
			$tempArray = $existingData['links'];
			if ( is_array( $tempArray ) ) {
				unset( $tempArray[ $short_url ] );
				unset( $tempArray[ strtolower( $short_url ) ] );
				$existingData['links'] = $tempArray;
				return file_put_contents( $file, wp_json_encode( $existingData ) );
			}
		}
		return;
	}

	public static function is_exists_short_url( $short_url ) {
		$resutls = self::get_link_by_short_url( $short_url );
		if ( count( $resutls ) > 0 ) {
			return true;
		}
		return false;
	}

	public static function sanitize_text_or_array_field( $array_or_string, $key = '' ) {

		$boolean = array( 'true', 'false', '1', '0' );
		$skip    = array( 'affiliate_disclosure_text', 'allow_contact_text', 'form_title', 'customFields', 'autolink_custom_icon' );
		if ( is_string( $array_or_string ) ) {
			if( 'link' === $key ) {
				return esc_url_raw($array_or_string);
			}
			$array_or_string = in_array( $array_or_string, $boolean ) || is_bool( $array_or_string ) ? rest_sanitize_boolean( $array_or_string ) : sanitize_text_field( $array_or_string );
		} elseif ( is_array( $array_or_string ) ) {
			foreach ( $array_or_string as $key => &$value ) {
				if ( in_array( $key, $skip ) ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$value = self::sanitize_text_or_array_field( $value );
				} else {
					$value = in_array( $value, $boolean ) || is_bool( $value ) ? rest_sanitize_boolean( $value ) : sanitize_text_field( $value );
				}
			}
		}
		return $array_or_string;
	}
	public static function fresh_ajax_request_data( $data ) {
		$remove = array( 'action', 'security' );
		return array_diff_key( $data, array_flip( $remove ) );
	}

	public static function force_relative_url( $url ) {
		return preg_replace( '/^(http)?s?:?\/\/[^\/]*(\/?.*)$/i', '$2', '' . $url );
	}

	/**
	 * Normalizing Clicks Data
	 *
	 * This function is responsible for manualy filter the duplicates IPs and link_id's from the data.
	 *
	 * @internal this is used in update_links_analytics for clicks on cron hook called 'betterlinks/analytics'
	 *
	 * @since 1.3.1
	 *
	 * @param array $data This should be the clicks data for IP's and links.
	 * @return array
	 */
	public static function normalize_ips_data( &$data ) {
		$_results = array();
		if ( ! empty( $data ) ) {
			foreach ( $data as &$analytic ) {
				$_link_id    = $analytic['link_id'];
				$_link_count = $analytic['lidc'];
				$_ip         = isset( $analytic['ip'] ) ? trim( $analytic['ip'] ) : '';
				$_ip_count   = $analytic['ipc'];

				if ( ! isset( $_results[ $_link_id ] ) ) {
					$_results[ $_link_id ] = array(
						'link_count' => $_link_count,
						'ip'         => array(),
					);
				}

				if ( $_ip && ! isset( $_results[ $_link_id ]['ip'][ $_ip ] ) ) {
					$_results[ $_link_id ]['ip'][ $_ip ] = $_ip_count;
				}
			}
		}

		return $_results;
	}

	public static function update_links_analytics() {
		$results      = array();
		$clicks_count = self::get_clicks_count();

		$total_clicks  = $clicks_count['total_clicks'];
		$unique_clicks = $clicks_count['unique_clicks'];

		for ( $i = 0; $i < count( $total_clicks ); $i++ ) {
			$results[ $total_clicks[ $i ]['link_id'] ] = array(
				'link_count' => $total_clicks[ $i ]['total_clicks'],
				'ip'         => isset( $unique_clicks[ $i ]['unique_clicks'] ) ? $unique_clicks[ $i ]['unique_clicks'] : 1,
			);
		}

		return update_option( 'betterlinks_analytics_data', wp_json_encode( $results ), false );
	}

	public static function maybe_json( $data, $sanitize_text = true ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			return wp_json_encode( $data );
		}

		if ( is_string( $data ) && $sanitize_text ) {
			return sanitize_text_field( $data );
		}

		return $data;
	}
	public static function generate_short_url( $short_url ) {
		return site_url( '/' ) . trim( $short_url, '/' );
	}

	public static function btl_get_option( $option_name ) {
		global $wpdb;
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}options WHERE option_name=%s", $option_name ),
			ARRAY_A
		);
		$value  = false;
		if ( ! empty( $result['option_id'] ) ) {
			$value = maybe_unserialize( $result['option_value'] );
		}
		return $value;
	}
	public static function btl_update_option( $option_name, $option_value, $careless_insert = false, $careless_update = false ) {
		global $wpdb;
		$option_value = maybe_serialize( $option_value );
		$result       = false;
		if ( ! $careless_insert && ! $careless_update ) {
			$result = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}options WHERE option_name=%s", $option_name ),
				ARRAY_A
			);
		}
		if ( $careless_insert || ( ! $careless_update && empty( $result['option_id'] ) ) ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}options ( option_name, option_value, autoload ) VALUES ( %s, %s, %s )",
					array(
						$option_name,
						$option_value,
						'no',
					)
				)
			);
			return $result;
		}
		if ( $careless_update || ! empty( $result['option_id'] ) ) {
			$result = $wpdb->update(
				"{$wpdb->prefix}options",
				array(
					'option_value' => $option_value,
					'autoload'     => 'no',
				),
				array( 'option_name' => $option_name )
			);
			return $result !== false;
		}
	}
	public static function btl_update_autoload_option( $option_name, $autoload = false ) {
		global $wpdb;
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}options WHERE option_name=%s", $option_name ),
			ARRAY_A
		);

		if ( ! empty( $result['option_id'] ) && ! empty( $result['option_value'] ) ) {
			if ( $autoload === false ) {
				$result = $wpdb->update(
					"{$wpdb->prefix}options",
					array(
						'option_value' => $result['option_value'],
						'autoload'     => 'no',
					),
					array( 'option_name' => $option_name )
				);
			} elseif ( $autoload === true ) {
				$result = $wpdb->update(
					"{$wpdb->prefix}options",
					array(
						'option_value' => $result['option_value'],
						'autoload'     => 'yes',
					),
					array( 'option_name' => $option_name )
				);
			}
			return $result !== false;
		}
	}
	public static function run_migration_for_ptrl_links_in_background( $installer, $links_count ) {
		global $wpdb;
		$per_page   = 10000;
		$total_page = ceil( $links_count / $per_page );
		for ( $page = 1; $page <= $total_page; $page++ ) {
			$offset = ( $page - 1 ) * $per_page;
			$links  = $wpdb->get_col(
				"SELECT concat('prli_links-', ID) AS ID FROM {$wpdb->prefix}prli_links LIMIT $per_page OFFSET {$offset}",
				0
			);
			$installer->data( $links )->save();
		}
		$installer->data( array( 'betterlinks_ptl_links_migrated' ) )->save();
		return $installer;
	}
	public static function run_migration_for_ptrl_clicks_in_background( $installer, $clicks_count ) {
		global $wpdb;
		$per_page   = 10000;
		$total_page = ceil( $clicks_count / $per_page );
		for ( $page = 1; $page <= $total_page; $page++ ) {
			$offset = ( $page - 1 ) * $per_page;
			$clicks = $wpdb->get_col(
				"SELECT concat('prli_clicks-', ID) AS ID FROM {$wpdb->prefix}prli_clicks LIMIT $per_page OFFSET {$offset}",
				0
			);
			$installer->data( $clicks )->save();
		}
		$installer->data( array( 'betterlinks_ptl_clicks_migrated' ) )->save();
		return $installer;
	}

	public function generate_random_slug( $length = 3 ) {
		$characters        = '0123456789abcdefghijklmnopqrstuvwxyz';
		$random_string     = '';
		$characters_length = strlen( $characters );

		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $characters[ wp_rand( 0, $characters_length - 1 ) ];
		}
		$random_num = wp_rand( 0, 10 ) . wp_rand( 0, 10 ) . wp_rand( 0, 10 );
		return $random_string . $random_num;
	}
	public function get_betterlinks_prefix() {
		if ( BETTERLINKS_EXISTS_SETTINGS_JSON ) {
			$data = Cache::get_json_settings();

			if ( empty( $data ) ) {
				$data = Cache::write_json_settings();
			}
			$prefix = ! empty( $data['prefix'] ) ? $data['prefix'] . '/' : '';
			return $prefix;
		}

		$betterlinks_links = get_option( 'betterlinks_links', array() );
		if ( is_string( $betterlinks_links ) ) {
			$betterlinks_links = json_decode( $betterlinks_links, true );
		}
		$prefix = ! empty( $betterlinks_links['prefix'] ) ? $betterlinks_links['prefix'] . '/' : '';
		return $prefix;
	}

	public function fetch_target_url( $target_url ) {
		if ( empty( $target_url ) ) {
			return false;
		}

		$http   = new WP_Http();
		$result = $http->get( $target_url, array( 'sslverify' => false ) );
		$title  = '';
		if ( ! is_wp_error( $result ) && ! empty( $result['body'] ) && preg_match( '/<title>(.*)<\/title>/siU', $result['body'], $title_matches ) ) {
			$title = html_entity_decode( $title_matches[1] );
		}
		return $title;
	}
	public static function insert_new_category( $slug ) {
		if ( ! ! intval( $slug ) ) {
			return $slug;
		}

		// Check if category exists and get its ID for AI
		$existing_term = self::get_term_by_slug( self::make_slug( $slug ), 'category' );
		if ( ! empty( $existing_term ) && is_array( $existing_term ) && count( $existing_term ) > 0 ) {
			// Category exists, return its ID
			return $existing_term[0]['ID'];
		}

		// Category doesn't exist, create it
		$insert_id = self::insert_term(
			array(
				'term_name' => $slug,
				'term_slug' => self::make_slug( $slug ),
				'term_type' => 'category',
			)
		);
		if ( $insert_id ) {
			self::clear_query_cache();
			return $insert_id;
		}

		return $slug;
	}

	public static function isJson( $string ) {
		json_decode( $string );
		return json_last_error() === JSON_ERROR_NONE;
	}

	public static function get_migratable_plugins() {
		return [
			'simple301redirects' => defined('SIMPLE301REDIRECTS_VERSION'),
			'thirstyaffiliates' => class_exists('ThirstyAffiliates'),
			'prettylinks' => defined('PRLI_VERSION'),
		];
	}
	
	public static function init_tracking( $data, $utils ) {
		global $betterlinks;
		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore
		$dd         = new DeviceDetector( $user_agent );
		$dd->parse();

		$data['is_bot'] = $dd->isBot();
		if ( empty( $data['target_url'] ) || ! apply_filters( 'betterlinks/pre_before_redirect', $data ) ) {
			// password protection logics
			if( empty( $data['skip_password_protection'] ) ){
				do_action( 'betterlinkspro/admin/check_password_protection', $data['short_url'], $data );
			}

			if ( empty( $data['target_url'] ) || ! apply_filters( 'betterlinks/pre_before_redirect', $data ) ) { // phpcs:ignore
				return false;
			}
		}
		$data = apply_filters( 'betterlinks/link/before_dispatch_redirect', $data ); // phpcs:ignore.
		if ( empty( $data ) ) {
			return false;
		}
		do_action( 'betterlinks/before_redirect', $data ); // phpcs:ignore.

		$comparable_url  = rtrim( preg_replace( '/https?\:\/\//', '', site_url( '/' ) ), '/' ) . '/' . $data['short_url'];
		$destination_url = rtrim( preg_replace( '/https?\:\/\//', '', $data['target_url'] ), '/' );
		$comparable_url  = rtrim( preg_replace( '/^www\.?/', '', $comparable_url ), '/' );
		$destination_url = rtrim( preg_replace( '/^www\.?/', '', $destination_url ), '/' );
		if ( ! $data || $comparable_url === $destination_url ) {
			return;
		}

		if ( filter_var( $data['track_me'], FILTER_VALIDATE_BOOLEAN ) ) {
			$data      = apply_filters( 'betterlinks/extra_tracking_data', $data, $dd );
	
			$data['os']      = OperatingSystem::getOsFamily( $dd->getOs( 'name' ) );
			$data['browser'] = Browser::getBrowserFamily( $dd->getClient( 'name' ) );
			$data['device']  = $dd->getDeviceName();
	
			if ( isset( $betterlinks['disablebotclicks'] ) && $betterlinks['disablebotclicks'] ) {
				if ( ! $dd->isBot() ) {
					$utils->start_trakcing( $data );
				}
			} else {
				$utils->start_trakcing( $data );
			}
		}
	}

	/**
	 * Rebuild links JSON from database
	 * Useful when JSON file is corrupted or completely out of sync
	 * 
	 * @since 2.6.0
	 * @return bool True if rebuild successful
	 */
	public static function rebuild_links_json() {
		if ( ! BETTERLINKS_EXISTS_LINKS_JSON ) {
			return false;
		}

		$formattedArray = self::get_links_for_json();
		$json_file = trailingslashit( BETTERLINKS_UPLOAD_DIR_PATH ) . 'links.json';
		
		return (bool) file_put_contents( $json_file, wp_json_encode( $formattedArray ) );
	}

	/**
	 * Sync all missing links from database to JSON file
	 * Checks if all database links exist in JSON and adds missing ones
	 * Called when admin page loads to ensure complete synchronization
	 * 
	 * @since 2.6.2
	 * @return array Results: ['total' => count, 'synced' => count]
	 */
	public static function sync_all_missing_links_to_json() {
		if ( ! BETTERLINKS_EXISTS_LINKS_JSON ) {
			return array( 'total' => 0, 'synced' => 0 );
		}

		if ( ! function_exists( 'file_get_contents' ) || ! function_exists( 'file_put_contents' ) ) {
			return array( 'total' => 0, 'synced' => 0 );
		}

		$json_file = trailingslashit( BETTERLINKS_UPLOAD_DIR_PATH ) . 'links.json';

		// Read JSON file
		if ( ! file_exists( $json_file ) ) {
			// JSON doesn't exist, rebuild from scratch
			self::rebuild_links_json();
			return array( 'total' => 0, 'synced' => 0 );
		}

		$json_content = file_get_contents( $json_file );
		$json_data = json_decode( $json_content, true );

		// If JSON is corrupted, rebuild
		if ( ! is_array( $json_data ) ) {
			self::rebuild_links_json();
			return array( 'total' => 0, 'synced' => 0 );
		}

		// Get all published links from database
		global $wpdb;
		$all_db_links = $wpdb->get_results(
			"SELECT ID, short_url, wildcards FROM {$wpdb->prefix}betterlinks WHERE link_status = 'publish'",
			ARRAY_A
		);

		$synced_count = 0;
		$total_links = count( $all_db_links );

		// Check each database link and add if missing from JSON
		foreach ( $all_db_links as $db_link ) {
			$short_url = $db_link['short_url'];
			$link_id = $db_link['ID'];
			$is_wildcard = isset( $db_link['wildcards'] ) && $db_link['wildcards'];

			// Determine where to look
			$target_section = $is_wildcard ? 'wildcards' : 'links';

			// Check if link exists in JSON
			$link_exists = false;
			if ( isset( $json_data[ $target_section ] ) && is_array( $json_data[ $target_section ] ) ) {
				if ( isset( $json_data[ $target_section ][ $short_url ] ) ) {
					$link_exists = true;
				}
			}

			// If missing, add it
			if ( ! $link_exists ) {
				$full_link_data = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}betterlinks WHERE ID = %d",
						$link_id
					),
					ARRAY_A
				);

				if ( $full_link_data ) {
					$formatted_link = self::json_link_formatter( $full_link_data );

					if ( $formatted_link ) {
						// Ensure target section exists
						if ( ! isset( $json_data[ $target_section ] ) ) {
							$json_data[ $target_section ] = array();
						}

						// Add missing link to JSON
						$json_data[ $target_section ][ $short_url ] = $formatted_link;
						$synced_count++;
					}
				}
			}
		}

		// Write back to file if any links were synced
		if ( $synced_count > 0 ) {
			file_put_contents( $json_file, wp_json_encode( $json_data ) );
		}

		return array(
			'total' => $total_links,
			'synced' => $synced_count,
		);
	}
}
