<?php
namespace BetterLinks;

use BetterLinks\Link\Utils;
use DeviceDetector\DeviceDetector;

class Link extends Utils {
	public function __construct() {
		if ( ! is_admin() && isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
			add_action( 'init', array( $this, 'run_redirect' ), 0 );
			add_action( 'betterlinks_quick_link_creation', array( $this, 'quick_link_creation' ) );
			add_action( 'betterlinks_prevent_unwanted_cle', array( $this, 'prevent_unwanted_cle' ) );
			// $this->run_redirect();
		}
	}

	/**
	 * Redirects short links to the destination url
	 */
	public function run_redirect() {
		// Quick Link Creation Functionality
		do_action( 'betterlinks_quick_link_creation' );

		// Note: Using sanitize_text_field for $_SERVER['REQUEST_URI'] may not handle redirects properly when short URLs contain non-ASCII characters (e.g., Chinese).
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
		$request_uri = stripslashes( rawurldecode( $request_uri ) );
		$request_uri = substr( $request_uri, strlen( wp_parse_url( site_url( '/' ), PHP_URL_PATH ) ) );
		$param       = explode( '?', $request_uri, 2 );
		$data        = $this->get_slug_raw( rtrim( current( $param ), '/' ) );

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore
		$dd         = new DeviceDetector( $user_agent );
		$dd->parse();

		$data['is_bot'] = $dd->isBot();
		if ( empty( $data['target_url'] ) || ! apply_filters( 'betterlinks/pre_before_redirect', $data ) ) {
			// password protection logics
			do_action( 'betterlinkspro/admin/check_password_protection', $request_uri, $data );

			if ( empty( $data['target_url'] ) || ! apply_filters( 'betterlinks/pre_before_redirect', $data ) ) { // phpcs:ignore
				return false;
			}
		}
		$data = apply_filters( 'betterlinks/link/before_dispatch_redirect', $data ); // phpcs:ignore.
		if ( empty( $data ) ) {
			return false;
		}

		do_action( 'betterlinks/before_redirect', $data ); // phpcs:ignore.
		$this->dispatch_redirect( $data, next( $param ) );
	}

	public function quick_link_creation() {
		global $betterlinks_settings;
		if ( isset( $_GET['action'], $_GET['api_key'] ) && sanitize_text_field( $_GET['action'] ) === 'btl_cle' && sanitize_text_field( $_GET['api_key'] ) === md5( AUTH_KEY ) ) {
			$target_url = isset( $_GET['target_url'] ) ? sanitize_url( $_GET['target_url'] ) : '';

			do_action( 'betterlinks_prevent_unwanted_cle' );
			$title = isset( $_GET['title'] ) ?  sanitize_text_field($_GET['title']) : ''; // geting title from document obj, instead of fetching
			if ( empty( $betterlinks_settings['cle']['enable_cle'] ) ) {
				return;
			}

			if ( empty( $title ) ) {
				$title = ( new Helper() )->fetch_target_url( $target_url );
			}
			
			if ( ! empty( $title ) ) {
				$this->create_new_link( $title, $target_url, $betterlinks_settings );
			}

			return;
		}
	}
}
