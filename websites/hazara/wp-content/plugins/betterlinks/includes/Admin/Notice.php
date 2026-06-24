<?php

namespace BetterLinks\Admin;

use BetterLinks\Admin\WPDev\PluginUsageTracker;
use Exception;
use PriyoMukul\WPNotice\Notices;
use PriyoMukul\WPNotice\Utils\CacheBank;
use PriyoMukul\WPNotice\Utils\NoticeRemover;

class Notice {
	/**
	 * @var CacheBank
	 */
	private static $cache_bank;

	/**
	 * @var PluginUsageTracker
	 */
	private $opt_in_tracker;

	/**
	 * @var bool Flag to prevent duplicate notice display
	 */
	private static $black_friday_notice_displayed = false;

	const ASSET_URL = BETTERLINKS_ASSETS_URI;

	public function __construct() {
		$this->usage_tracker();

		self::$cache_bank = CacheBank::get_instance();
		try {
			$this->notices();
		} catch ( Exception $e ) {
			unset( $e );
		}

		add_action( 'in_admin_header', [ $this, 'remove_admin_notice' ] );
		add_action( 'btl_compatibity_notices', [ $this, 'btlpro_compatibility_notices' ] );
		// Use multiple hooks for better compatibility across different WordPress setups
		add_action( 'admin_footer', [ $this, 'black_friday_pointer_notice' ], 999 );
	}

	public function btlpro_compatibility_notices() {
		global $wp_version;

		if ( ! defined( 'BETTERLINKS_PRO_VERSION' ) ) {
			return;
		}

		if ( version_compare( $wp_version, '6.6', '>=' ) && version_compare( BETTERLINKS_PRO_VERSION, '2.0.0', '<=' ) ) {
			$message = sprintf( '
			<strong>%1$s</strong>: %2$s <strong>v2.0.1</strong> %3$s <strong>6.6 or later</strong>',
				__( 'Warning', 'betterlinks' ),
				__( 'Please update your BetterLinks Pro plugin to atleast', 'betterlinks' ),
				__( 'to ensure compatibility with WordPress', 'betterlinks' )
			);

			$notice = sprintf( '<div style="padding: 10px;" class="notice notice-warning">%2$s</div>', 'betterlinks', $message );

			echo wp_kses_post( $notice );
		}
	}

	/**
	 * Display Black Friday pointer notice
	 * Shows only once per user with date range validation
	 * Only displays for free users (without BetterLinks Pro)
	 * Only shows on BetterLinks pages and WordPress dashboard
	 *
	 * @return void
	 */
	public function black_friday_pointer_notice() {
		// Prevent duplicate display when hooked to multiple actions
		if ( self::$black_friday_notice_displayed ) {
			return;
		}

		// Check if notice is dismissed
		if ( get_transient( 'betterlinks_black_friday_pointer_dismissed' ) ) {
			return;
		}

		// Check date range: November 16, 2025 to December 4, 2025
		$start_date = strtotime( '11:59:59pm 16th November, 2025' );
		$end_date   = strtotime( '11:59:59pm 4th December, 2025' );
		$current_time = current_time( 'timestamp' );

		// Only show if within date range
		if ( $current_time < $start_date || $current_time > $end_date ) {
			return;
		}

		// Don't show if Pro is already active or installed
		if ( defined( 'BETTERLINKS_PRO_VERSION' ) || is_plugin_active( 'betterlinks-pro/betterlinks-pro.php' ) ) {
			return;
		}

		// Check plugin pointer priority system
		// BetterLinks priority is 7
		$betterlinks_priority = 7;
		$current_priority = get_option( '_wpdeveloper_plugin_pointer_priority' );
		// If priority option doesn't exist, create it with BetterLinks priority
		if ( false === $current_priority || null === $current_priority || '' === $current_priority ) {
			update_option( '_wpdeveloper_plugin_pointer_priority', $betterlinks_priority );
		} elseif ( $current_priority > $betterlinks_priority ) {
			// If current priority is higher than BetterLinks priority, update it
			update_option( '_wpdeveloper_plugin_pointer_priority', $betterlinks_priority );
			$current_priority = $betterlinks_priority;
		}

		if ( $current_priority < $betterlinks_priority  ) {
			return;
		}

		// Only show on BetterLinks pages, WordPress dashboard, and plugins directory
		$current_screen = get_current_screen();
		$is_betterlinks_page = ( 0 === strpos( $current_screen->id, 'toplevel_page_betterlinks' ) || 0 === strpos( $current_screen->id, 'betterlinks_page_' ) );
		$is_dashboard = ( 'dashboard' === $current_screen->id );
		$is_plugins_page = ( 'plugins' === $current_screen->id );

		if ( ! $is_betterlinks_page && ! $is_dashboard && ! $is_plugins_page ) {
			return;
		}

		// Enqueue pointer styles and scripts
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_script( 'jquery' );

		// Create nonce for AJAX
		$nonce = wp_create_nonce( 'betterlinks_dismiss_black_friday_notice' );

		// Mark notice as displayed to prevent duplicates
		self::$black_friday_notice_displayed = true;

		// Output the notice markup
		?>

		<script type="text/javascript">
			(function($) {
				$(document).ready(function() {

					const target = jQuery("#toplevel_page_betterlinks" || 'body');

					if (target.length === 0) {
						return;
					}

					// Prepare content with optional button
					let content = '<h3><?php esc_html_e( 'BetterLinks Black Friday Sale', 'betterlinks' ); ?></h3><p><?php esc_html_e( 'Shorten and redirect links & analyze website performance efficiently.', 'betterlinks' ); ?> </p>' || '';
					content += '<p style="margin-top: 15px;"><a href="https://betterlinks.io/bfcm-wp-admin-pointer" class="button button-primary" target="_blank" rel="noopener"><?php esc_html_e( 'Save 40%', 'betterlinks' ); ?></a></p>';
			
					// Default pointer options
					const options = {
						content: content,
						position: {
							edge: "left",
							align: 'center'
						},
						close: function() {
							// dismissPointer(pointerId);
							var nonce = '<?php echo $nonce; ?>';
								// Send AJAX request to set transient
								$.ajax({
									url: '<?php echo esc_url(admin_url( 'admin-ajax.php' )); ?>',
									type: 'POST',
									data: {
										action: 'betterlinks_dismiss_black_friday_notice',
										nonce: nonce
									},
								});
						}
					};
       
				// Show the pointer
				target.pointer(options).pointer('open');
				});
			})(jQuery);
		</script>
		<?php
	}

	public function remove_admin_notice() {
		$current_screen   = get_current_screen();
		$dashboard_notice = get_option( 'betterlinks_dashboard_notice' );

		if ( ! empty( strpos( $current_screen->id, 'betterlinks-quick-setup' ) ) ) {
			remove_all_actions( 'admin_notices' );

			return;
		}

		if ( 0 === strpos( $current_screen->id, "toplevel_page_betterlinks" ) || 0 === strpos( $current_screen->id, "betterlinks_page_" ) ) {
			remove_all_actions( 'admin_notices' );
			if ( BETTERLINKS_MENU_NOTICE !== $dashboard_notice ) {
				add_action( 'admin_notices', array( $this, 'new_feature_notice' ), - 1 );
			}
			// To showing notice in BetterLinks page
			add_action( 'admin_notices', function () {
				do_action( 'btl_admin_notices' );
				do_action( 'btl_compatibity_notices' );
				Notice\PrettyLinks::init();
				Notice\Simple301::init();
				Notice\ThirstyAffiliates::init();
				// Remove OLD notice from 1.0.0 (if other WPDeveloper plugin has notice)
				NoticeRemover::get_instance( '1.0.0' );
			} );
		}
	}

	public function new_feature_notice() {
		printf(
			"<div class='notice notice-success is-dismissible btl-dashboard-notice' id='btl-dashboard-notice'>
				<p>
				%s
				<a target='_blank' href='https://betterlinks.io/docs/auto-link-keywords-import-export-betterlinks/' style='display: inline-block'>
					%s
				</a>
				%s
				<a target='_blank' href='https://betterlinks.io/changelog/'>%s</a>
				%s
				</p>
		</div>",
			__( 'NEW: BetterLinks Pro 2.6.5 now includes a powerful ', 'betterlinks' ),
			__( 'Auto-Link Keyword Import/Export Feature.', 'betterlinks' ),
			__( ' Check the full ', 'betterlinks' ),
			__( 'Changelog', 'betterlinks' ),
			__( ' for details.', 'betterlinks' ),
		);
	}

	public function usage_tracker() {
		$this->opt_in_tracker = PluginUsageTracker::get_instance( BETTERLINKS_PLUGIN_FILE, [
			'opt_in'       => true,
			'goodbye_form' => true,
			'item_id'      => '720bbe6537bffcb73f37',
		] );
		$this->opt_in_tracker->set_notice_options( array(
			'notice'       => __( 'Want to help make <strong>BetterLinks</strong> even more awesome? Be the first to get access to <strong>BetterLinks PRO</strong> with a huge <strong>50% Early Bird Discount</strong> if you allow us to track the non-sensitive usage data.', 'betterlinks' ),
			'extra_notice' => __( 'We collect non-sensitive diagnostic data and plugin usage information. Your site URL, WordPress & PHP version, plugins & themes and email address to send you the discount coupon. This data lets us make sure this plugin always stays compatible with the most popular plugins and themes. No spam, I promise.', 'betterlinks' ),
		) );
		$this->opt_in_tracker->init();
	}

	/**
	 * @throws Exception
	 */
	public function notices() {
		$notices = new Notices( [
			'id'             => 'betterlinks',
			'storage_key'    => 'notices',
			'lifetime'       => 3,
			'stylesheet_url' => self::ASSET_URL . 'css/betterlinks-admin-notice.css',
			'styles'         => self::ASSET_URL . 'css/betterlinks-admin-notice.css',
			'priority'       => 7
		] );

		global $betterlinks;
		$current_user = wp_get_current_user();
		$total_links  = ( is_array( $betterlinks ) && isset( $betterlinks['links'] ) ? count( $betterlinks['links'] ) : 0 );

		$review_notice = sprintf(
			'%s, %s! %s',
			__( 'Howdy', 'betterlinks' ),
			$current_user->user_login,
			sprintf(
				__( '👋 You have created %d Shortened URLs so far 🎉 If you are enjoying using BetterLinks, feel free to leave a 5* Review on the WordPress Forum.', 'betterlinks' ),
				$total_links
			)
		);

		$_review_notice = [
			'thumbnail' => self::ASSET_URL . 'images/logo-large.svg',
			'html'      => '<p>' . $review_notice . '</p>',
			'links'     => [
				'later'            => array(
					'link'       => 'https://wordpress.org/plugins/betterlinks/#reviews',
					'target'     => '_blank',
					'label'      => __( 'Ok, you deserve it!', 'betterlinks' ),
					'icon_class' => 'dashicons dashicons-external',
				),
				'allready'         => array(
					'label'      => __( 'I already did', 'betterlinks' ),
					'icon_class' => 'dashicons dashicons-smiley',
					'attributes' => [
						'data-dismiss' => true
					],
				),
				'maybe_later'      => array(
					'label'      => __( 'Maybe Later', 'betterlinks' ),
					'icon_class' => 'dashicons dashicons-calendar-alt',
					'attributes' => [
						'data-later' => true
					],
				),
				'support'          => array(
					'link'       => 'https://wpdeveloper.com/support',
					'label'      => __( 'I need help', 'betterlinks' ),
					'icon_class' => 'dashicons dashicons-sos',
				),
				'never_show_again' => array(
					'label'      => __( 'Never show again', 'betterlinks' ),
					'icon_class' => 'dashicons dashicons-dismiss',
					'attributes' => [
						'data-dismiss' => true
					],
				)
			]
		];

		$notices->add(
			'review',
			$_review_notice,
			[
				'start'       => $notices->strtotime( '+20 day' ),
				'recurrence'  => 30,
				'refresh'     => BETTERLINKS_VERSION,
				'dismissible' => true,
			]
		);

		$notices->add(
			'opt_in',
			[ $this->opt_in_tracker, 'notice' ],
			[
				'classes'     => 'updated put-dismiss-notice',
				'start'       => $notices->strtotime( '+25 day' ),
//				'start'       => $notices->time(),
				'refresh'     => BETTERLINKS_VERSION,
				'dismissible' => true,
				'do_action'   => 'wpdeveloper_notice_clicked_for_betterlinks',
				'display_if'  => ! is_plugin_active( 'betterlinks-pro/betterlinks-pro.php' )
			]
		);

		// Holiday Notice 2024
		$crown_icon       = self::ASSET_URL . 'images/crown.svg';
		$b_message        = "<p style='margin-top: 0; margin-bottom: 0;'>🎁 <strong>Holiday Gifts:</strong> Get Flat 25% OFF on every <strong>BetterLinks PRO</strong> plans & upgrade your WordPress links.</p><a style='display: inline-flex;align-items:center;column-gap:5px;' class='button button-primary' href='https://betterlinks.io/holiday24-admin-notice' target='_blank'><img style='width:15px;' src='{$crown_icon}'/>Upgrade To PRO</a>";
		$_holiday_notices = [
			'thumbnail' => self::ASSET_URL . 'images/full-logo.svg',
			'html'      => $b_message,
		];

		$notices->add(
			'betterlinks_holiday_24_25',
			$_holiday_notices,
			[
				'start'       => $notices->time(),
				'recurrence'  => false,
				'dismissible' => true,
				'refresh'     => BETTERLINKS_VERSION,
				"expire"      => strtotime( '11:59:59pm 10th January, 2025' ),
				'display_if'  => ! is_plugin_active( 'betterlinks-pro/betterlinks-pro.php' )
			]
		);

		// Black Friday Mega Sale Notice
        $black_friday_icon = self::ASSET_URL . 'images/full-logo.svg';
		$black_friday_message = "<style>#wpnotice-betterlinks-betterlinks_spring_camp_2026_deal { border-left: 3px solid #5252DC !important; } .notice-betterlinks-betterlinks_spring_camp_2026_deal { border-left: 4px solid #FF6B6B !important; }</style><div> <p style='margin-top: 0; margin-bottom: 10px; font-size: 14px;'><strong>🌸 Spring Savings: </strong>Get AI-Powered Features To Manage, Shorten & Track Every Click – Now <strong> Flat 25% OFF! </strong>⚡️ </p><a style='display: inline-flex;align-items:center;column-gap:5px; background: #5252DC; color: #FFFFFF; font-size: 14px; border-radius: 6px; border-color: unset; font-weight: 500;' class='button button-primary' href='https://betterlinks.io/spring2026-admin-notice' target='_blank'>Upgrade To Pro Now</a><a style='display: inline-flex;align-items:center;column-gap:5px;margin-left:10px; background: unset; box-shadow: unset; border-style: unset; color: #424242; font-size: 14px; text-decoration: underline;' class='button dismiss-btn' href='#' data-dismiss='true'>Maybe Later</a> </div>";

        $_black_friday_notices = [
            'thumbnail' => $black_friday_icon,
            'html'      => $black_friday_message,
        ];
		// 'betterlinks_black_friday_2025',
		// 'betterlinks_feb_camp_2026',
        $notices->add(
            'betterlinks_spring_camp_2026_deal',
            $_black_friday_notices,
            [
                'start'       => strtotime( '12:00:00am 08th April, 2026' ),
                'recurrence'  => false,
                'dismissible' => true,
                'refresh'     => BETTERLINKS_VERSION,
                "expire"      => strtotime( '12:00:00am 10th May, 2026' ),
    			'display_if'  => ! is_plugin_active( 'betterlinks-pro/betterlinks-pro.php' ),
				'priority'    => 7
            ]
        );
		self::$cache_bank->create_account( $notices );
		self::$cache_bank->calculate_deposits( $notices );

		if ( method_exists( self::$cache_bank, 'clear_notices_in_' ) ) {
			self::$cache_bank->clear_notices_in_( [
				'toplevel_page_betterlinks',
				'betterlinks_page_betterlinks-keywords-linking',
				'betterlinks_page_betterlinks-manage-tags',
				'betterlinks_page_betterlinks-custom-domain',
				'betterlinks_page_betterlinks-analytics',
				'betterlinks_page_betterlinks-settings',
			], $notices, true );
		}
	}

}
