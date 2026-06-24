<?php

namespace BetterLinks\API;

use BetterLinks\Services\CountryDetectionService;

/**
 * Geolocation REST API
 *
 * Provides backend fallback for frontend geolocation detection
 */
class Geolocation {

	private $namespace = BETTERLINKS_PLUGIN_SLUG . '/v1';

	/**
	 * Initialize hooks
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes for geolocation detection
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/geolocation/detect',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'detect_country' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Endpoint to fetch country for a specific IP (for backward compatibility)
		register_rest_route(
			$this->namespace,
			'/geolocation/fetch-by-ip',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'fetch_country_by_ip' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'ip' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Detect country for current user's IP
	 *
	 * This is a fallback endpoint when frontend geolocation fails
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_REST_Response
	 */
	public function detect_country( $request ) {
		// Check if BetterLinks Pro v2.5.0 or newer is installed
		if ( ! defined( 'BETTERLINKS_PRO_VERSION' ) || version_compare( BETTERLINKS_PRO_VERSION, '2.5.0', '<' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Country detection requires BetterLinks Pro v2.5.0 or newer',
					'code'    => 'pro_version_required',
					'data'    => null,
				),
				403
			);
		}

		// Additional check: Verify Pro plugin has the country tracking function (prevents bypass with old Pro files)
		if ( ! class_exists( 'BetterLinksPro\\Helper' ) || 
			 ! method_exists( 'BetterLinksPro\\Helper', 'is_country_tracking_enabled' ) ||
			 ! \BetterLinksPro\Helper::is_country_tracking_enabled() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Please update BetterLinks Pro to v2.5.0 or newer to use this feature',
					'code'    => 'pro_update_required',
					'data'    => null,
				),
				403
			);
		}

		$ip = CountryDetectionService::get_current_client_ip();

		if ( ! $ip ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Could not determine client IP',
					'data'    => null,
				),
				400
			);
		}

		$country_data = CountryDetectionService::get_country_by_ip( $ip );

		if ( $country_data ) {
			// Get or create country record and include country_id in response
			$country_id = CountryDetectionService::get_or_create_country_id(
				$country_data['country_code'],
				$country_data['country_name']
			);

			$response_data = $country_data;
			if ( $country_id ) {
				$response_data['country_id'] = $country_id;
			}

			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Country detected successfully',
					'data'    => $response_data,
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Could not detect country for this IP',
				'data'    => null,
			),
			404
		);
	}

	/**
	 * Fetch country data for a specific IP address
	 *
	 * Used for backward compatibility to fetch country for existing clicks
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_REST_Response
	 */
	public function fetch_country_by_ip( $request ) {
		// Check if BetterLinks Pro v2.5.0 or newer is installed
		if ( ! defined( 'BETTERLINKS_PRO_VERSION' ) || version_compare( BETTERLINKS_PRO_VERSION, '2.5.0', '<' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Country detection requires BetterLinks Pro v2.5.0 or newer',
					'code'    => 'pro_version_required',
					'data'    => null,
				),
				403
			);
		}

		// Additional check: Verify Pro plugin has the country tracking function (prevents bypass with old Pro files)
		if ( ! class_exists( 'BetterLinksPro\\Helper' ) || 
			 ! method_exists( 'BetterLinksPro\\Helper', 'is_country_tracking_enabled' ) ||
			 ! \BetterLinksPro\Helper::is_country_tracking_enabled() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Please update BetterLinks Pro to v2.5.0 or newer to use this feature',
					'code'    => 'pro_update_required',
					'data'    => null,
				),
				403
			);
		}

		$ip = $request->get_param( 'ip' );

		if ( ! $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid IP address',
					'data'    => null,
				),
				400
			);
		}

		$country_data = CountryDetectionService::get_country_by_ip( $ip );

		if ( $country_data ) {
			// Get or create country record and include country_id in response
			$country_id = CountryDetectionService::get_or_create_country_id(
				$country_data['country_code'],
				$country_data['country_name']
			);

			$response_data = $country_data;
			if ( $country_id ) {
				$response_data['country_id'] = $country_id;
			}

			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Country detected successfully',
					'data'    => $response_data,
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Could not detect country for this IP',
				'data'    => null,
			),
			404
		);
	}
}

