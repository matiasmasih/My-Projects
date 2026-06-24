<?php

namespace WPDeveloper\BetterDocs\Core;

use Exception;
use PriyoMukul\WPNotice\Notices;
use WPDeveloper\BetterDocs\Admin\NoticePointers;
use WPDeveloper\BetterDocs\Utils\Base;
use PriyoMukul\WPNotice\Utils\CacheBank;
use WPDeveloper\BetterDocs\Utils\Helper;
use WPDeveloper\BetterDocs\Utils\Enqueue;
use WPDeveloper\BetterDocs\Utils\Insights;
use PriyoMukul\WPNotice\Utils\NoticeRemover;
use WPDeveloper\BetterDocs\Core\PluginInstaller;
use WPDeveloper\BetterDocs\Dependencies\DI\Container;

class Admin extends Base {
	/**
	 * @var CacheBank
	 */
	private static $cache_bank;
	/**
	 * Admin Root Menu Slug
	 *
	 * @var string
	 */
	private $slug = 'betterdocs-dashboard';
	/**
	 * Insights
	 *
	 * @var Insights
	 */
	private $insights = null;

	/**
	 * DI\Container
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Database Wrapper
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * KBMigration
	 *
	 * @var KBMigration
	 */
	private $kbmigration;

	/**
	 * Enqueue
	 *
	 * @var Enqueue
	 */
	private $assets;

	// modules
	protected $installer;

	/**
	 * FAQBuilder
	 *
	 * @var FAQBuilder
	 */
	private $faq_builder;
	private $glossaries;

	public function __construct( Container $container, PostType $type, Enqueue $assets, Settings $settings, KBMigration $kbmigration ) {
		$this->container   = $container;
		$this->assets      = $assets;
		$this->settings    = $settings;
		$this->kbmigration = $kbmigration;
		$this->slug        = 'betterdocs-dashboard';

		add_action( 'init', array( $type, 'register' ), 9 );
		add_action( 'rest_api_init', array( $this, 'order_terms_in_wp_terms_admin_table' ) );

		$type->init();
		$type->admin_init();

		$this->faq_builder = $this->container->get( FAQBuilder::class );
		$this->glossaries  = $this->container->get( Glossaries::class );

		if ( ! is_admin() ) {
			return;
		}

		$this->installer = new PluginInstaller();

		$this->plugin_insights();
		add_action( 'admin_notices', array( $this, 'compatibility_notices' ) );
		// add_action( 'admin_init', [$this, 'notices'], 9 );
		add_filter( 'admin_init', array( $this, 'save_admin_page' ), 99 );

		add_action( 'admin_menu', array( $this, 'menus' ) );
		add_action( 'admin_menu', array( $this, 'reset_submenu' ) );
		add_action( 'admin_head', array( $this, 'add_custom_classes_to_menu_items' ) );
		add_filter( 'plugin_action_links_' . BETTERDOCS_PLUGIN_BASENAME, array( $this, 'insert_plugin_links' ) );

		// $this->container->get( SetupWizard::class )->init();

		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		// add_action( 'betterdocs_listing_header', [ $this, 'header' ], 10, 1 );
		add_action( 'admin_bar_menu', array( $this, 'toolbar_menu' ), 32 );

		add_filter( 'admin_body_class', array( $this, 'body_classes' ) );
		add_filter( 'parent_file', array( $type, 'highlight_admin_menu' ) );
		add_filter( 'submenu_file', array( $type, 'highlight_admin_submenu' ), 10, 2 );
		add_filter( 'betterdocs_admin_menu', array( $this, 'quick_setup_menu' ), 10, 1 );

		/**
		 * Remove Comments Column from List Table.
		 */
		add_filter( 'manage_docs_posts_columns', array( $this, 'set_custom_edit_action_columns' ) );
		add_filter( 'manage_docs_posts_custom_column', array( $this, 'manage_custom_columns' ), 10, 2 );

		/**
		 * Add New Column
		 */
		add_filter( 'manage_users_columns', array( $this, 'add_users_total_docs_column' ), 10, 1 );
		add_filter( 'manage_users_custom_column', array( $this, 'popular_users_docs_data' ), 10, 3 );
		if ( is_plugin_active( 'betterdocs-pro/betterdocs-pro.php' ) ) {
			add_action( 'admin_footer-plugins.php', array( $this, 'disable_deactivation' ) );
		}

		if ( $this->settings->get( 'enable_estimated_reading_time' ) ) {
			// Hook into unified metabox instead of creating separate metabox
			add_action( 'betterdocs_reading_time_tab_content', array( $this, 'render_estimated_time_markup' ) );
		}

		self::$cache_bank = CacheBank::get_instance();

		// Remove OLD notice from 1.0.0 (if other WPDeveloper plugin has notice)
		NoticeRemover::get_instance( '1.0.0' );

		try {
			$this->notices();
		} catch ( Exception $e ) {
			unset( $e );
		}

		// Initialize Black Friday Pointer
		$this->init_black_friday_pointer();

		// Register AJAX handler for pointer dismissal
		add_action( 'wp_ajax_betterdocs_dismiss_black_friday_pointer', array( $this, 'ajax_dismiss_black_friday_pointer' ) );
	}

	public function order_terms_in_wp_terms_admin_table() {
		// order the terms correctly to be shown on the admin panel categories menu with betterdocs order
		add_action(
			'rest_insert_doc_category',
			function ( $term, $request, $bool ) {
				$max_order  = Helper::get_max_doc_category_order_from_term_meta() ?? 0;
				$next_order = $max_order + 1;
				update_term_meta( $term->term_id, 'doc_category_order', $next_order );
			},
			10,
			3
		);
	}

	public function disable_deactivation() {
		$tooltip_text = esc_html__( 'Deactivate BetterDocs Pro First', 'betterdocs' );
		?>
		<style type="text/css">
			#deactivate-betterdocs {
				color: #cccccc;
				position: relative;
			}

			/* Tooltip styling */
			#deactivate-betterdocs[title]:hover::after {
				content: attr(title);
				position: absolute;
				bottom: 100%;
				left: 50%;
				transform: translateX(-50%);
				background-color: #333;
				color: #fff;
				padding: 5px 10px;
				border-radius: 4px;
				font-size: 12px;
				white-space: nowrap;
				box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.2);
				z-index: 10;
			}
			#deactivate-betterdocs:focus {
				box-shadow: none;
				outline: none;
			}
		</style>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Disable the default action and add class with tooltip by default
				const tooltipText = "<?php echo esc_attr( $tooltip_text ); ?>";
				$("#deactivate-betterdocs")
					.addClass("disabled-tooltip")
					.attr("title", tooltipText)
					.on("click", function(e) {
						e.preventDefault(); // Prevent any action on click
					});
			});
		</script>
		<?php
	}

	public function add_users_total_docs_column( $columns ) {
		$new_column = array(
			'docs' => __( 'Docs', 'betterdocs' ),
		);
		$columns    = array_merge( $columns, $new_column );
		return $columns;
	}

	public function popular_users_docs_data( $output, $column_name, $user_id ) {
		if ( 'docs' == $column_name ) {
			$total_count = count_user_posts( $user_id, 'docs', true );
			return '<a href="edit.php?post_type=docs&author=' . $user_id . '" class="edit"><span aria-hidden="true">' . $total_count . '</span></a>';
		}
		return $output;
	}

	public function render_estimated_time_markup() {
		betterdocs()->views->get( 'admin/metabox/estimated-reading-box' );
	}

	public function compatibility_notices() {
		if ( betterdocs()->is_pro_active() ) {
			$plugins     = Helper::get_plugins();
			$plugin_data = $plugins['betterdocs-pro/betterdocs-pro.php'];

			if ( isset( $plugin_data['Version'] ) && version_compare( $plugin_data['Version'], '2.5.0', '>=' ) ) {
				return;
			}

			betterdocs()->views->get( 'admin/notices/compatibility', array( 'version' => $plugin_data['Version'] ) );
		}
	}

	public function plugin_insights( $prevent_init = false ) {
		$this->insights = Insights::get_instance(
			BETTERDOCS_PLUGIN_FILE,
			array(
				'opt_in'       => true,
				'goodbye_form' => true,
				'item_id'      => 'c7b16777b4f1b83f6083',
			)
		);

		$this->insights->set_notice_options(
			array(
				'notice'       => __( 'Want to help make <strong>BetterDocs</strong> even more awesome? You can get a <strong>10% discount coupon</strong> for Premium extensions if you allow us to track the usage.', 'betterdocs' ),
				'extra_notice' => __( 'We collect non-sensitive diagnostic data and plugin usage information. Your site URL, WordPress & PHP version, plugins & themes and email address to send you the discount coupon. This data lets us make sure this plugin always stays compatible with the most popular plugins and themes. No spam, I promise.', 'betterdocs' ),
			)
		);

		if ( ! $prevent_init ) {
			$this->insights->init();
		}

		return $this->insights;
	}

	/**
	 * Admin notices for Review and others.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function notices() {
		$notices = new Notices(
			array(
				'id'             => 'betterdocs',
				'storage_key'    => 'notices',
				'lifetime'       => 3,
				'stylesheet_url' => $this->assets->asset_url( 'admin/css/notices.css' ),
				'styles'         => $this->assets->asset_url( 'admin/css/notices.css' ),
				'priority'       => 4,
			)
		);

		/**
         * Review Notice
		 *
         * @var mixed $message
         */

		$message = __( 'We hope you\'re enjoying BetterDocs! Could you please do us a BIG favor and give it a 5-star rating on WordPress to help us spread the word and boost our motivation?', 'betterdocs' );

		$_review_notice = array(
			'thumbnail' => $this->assets->icon( 'betterdocs-logo.svg', true ),
			'html'      => '<p>' . $message . '</p>',
			'links'     => array(
				'later'            => array(
					'link'       => 'https://wordpress.org/plugins/betterdocs/#reviews',
					'target'     => '_blank',
					'label'      => __( 'Sure, you deserve it!', 'betterdocs' ),
					'icon_class' => 'dashicons dashicons-external',
				),
				'allready'         => array(
					'label'      => __( 'I already did', 'betterdocs' ),
					'icon_class' => 'dashicons dashicons-smiley',
					'attributes' => array(
						'data-dismiss' => true,
					),
				),
				'maybe_later'      => array(
					'label'      => __( 'Maybe Later', 'betterdocs' ),
					'icon_class' => 'dashicons dashicons-calendar-alt',
					'attributes' => array(
						'data-later' => true,
						'class'      => 'dismiss-btn',
					),
				),
				'support'          => array(
					'link'       => 'https://wpdeveloper.com/support',
					'attributes' => array(
						'target' => '_blank',
					),
					'label'      => __( 'I need help', 'betterdocs' ),
					'icon_class' => 'dashicons dashicons-sos',
				),
				'never_show_again' => array(
					'label'      => __( 'Never show again', 'betterdocs' ),
					'icon_class' => 'dashicons dashicons-dismiss',
					'attributes' => array(
						'data-dismiss' => true,
					),
				),
			),
		);

		$notices->add(
			'review',
			$_review_notice,
			array(
				'start'       => $notices->strtotime( '+10 days' ),
				'recurrence'  => 30,
				'dismissible' => true,
			)
		);

		if ( $this->kbmigration->existing_plugins && ! in_array( $this->kbmigration->existing_plugins[0][0], $this->kbmigration->migrated_plugins ) ) {
			$plugin_name = '<strong>' . esc_html( $this->kbmigration->existing_plugins[0][1] ) . '</strong>';

			$message = sprintf(
			/* translators: %s is the name of the existing knowledge base plugin. */
				__( 'Already using %s? Power up your Knowledge Base by migrating all your docs and settings to BetterDocs with just 1 click.', 'betterdocs' ),
				esc_html( $plugin_name )
			);

			$migration_message = sprintf(
				'<p class="migration-message">%s</p><a class="button button-primary betterdocs-migration-notice" href="%s">%s</a>',
				$message,
				esc_url( admin_url( 'admin.php?page=betterdocs-settings&tab=tab-migration' ) ),
				esc_html__( 'Start Migration', 'betterdocs' )
			);

			$_migration_notice = array(
				'thumbnail' => '',
				'html'      => $migration_message,
				'links'     => array(
					'maybe_later'      => array(
						'label'      => __( 'Maybe Later', 'betterdocs' ),
						'icon_class' => 'dashicons dashicons-calendar-alt',
						'attributes' => array(
							'data-later' => true,
							'class'      => 'dismiss-btn',
						),
					),
					'never_show_again' => array(
						'label'      => __( 'Never show again', 'betterdocs' ),
						'icon_class' => 'dashicons dashicons-dismiss',
						'attributes' => array(
							'data-dismiss' => true,
						),
					),
				),
			);

			$notices->add(
				'migration',
				$_migration_notice,
				array(
					'start'       => $notices->time(),
					'recurrence'  => false,
					'dismissible' => true,
				)
			);
		}

		/**
         * 
		 * Opt-In Notice
		 */
		$allow_tracking = get_option( 'wpins_allow_tracking' );
		if ( null != $this->insights && ! isset( $allow_tracking['betterdocs'] ) ) {
			$notices->add(
				'opt_in',
				array( $this->insights, 'notice' ),
				array(
					'classes'     => 'updated put-dismiss-notice',
					'start'       => $notices->time(),
					'refresh'     => BETTERDOCS_VERSION,
					'dismissible' => true,
					'do_action'   => 'wpdeveloper_notice_clicked_for_betterdocs',
					'display_if'  => ! function_exists( 'betterdocs_pro' ),
					'screens'     => array( 'dashboard' ),
				)
			);
		}

		$spring_campaign_message = '<div class="betterdocs-spring-notice-body"><p style="margin-top: 0; margin-bottom: 0;">🌸 <strong>Spring Savings:</strong> Build AI-powered Knowledge Bases & FAQs to empower support and improve user experience – now <strong>Flat 25% OFF!</strong> ⚡️</p></div>';
		$_spring_campaign_notice = array(
			'thumbnail' => $this->assets->icon( 'betterdocs-logo.svg', true ),
			'html'      => $spring_campaign_message,
			'links'     => array(
				'support'     => array(
					'link'       => 'https://betterdocs.co/spring2026-admin-notice',
					'attributes' => array(
						'target' => '_blank',
						'class'  => 'offer-button',
					),
					'label'      => __( 'Upgrade To PRO Now', 'betterdocs' ),
				),
				'maybe_later' => array(
					'label'      => __( 'I’ll Grab It Later', 'betterdocs' ),
					'attributes' => array(
						'target'     => '_blank',
						'data-later' => true,
						'class'      => 'dismiss-btn',
					),
				),
			),
		);

		$notices->add(
			'spring-campaign-26',
			$_spring_campaign_notice,
			array(
				'start'       => $notices->time(),
				'recurrence'  => false,
				'dismissible' => true,
				'refresh'     => BETTERDOCS_VERSION,
				'expire'      => strtotime( '11:59:59pm May 31, 2026' ),
				'display_if'  => ! is_plugin_active( 'betterdocs-pro/betterdocs-pro.php' ),
			)
		);

		self::$cache_bank->create_account( $notices );
		self::$cache_bank->calculate_deposits( $notices );
		if ( method_exists( self::$cache_bank, 'clear_notices_in_' ) ) {
			self::$cache_bank->clear_notices_in_(
				array(
					'toplevel_page_betterdocs-dashboard',
					'admin_page_betterdocs-admin',
					'betterdocs_page_betterdocs-admin',
					'betterdocs_page_betterdocs-settings',
					'betterdocs_page_betterdocs-faq',
					'betterdocs_page_betterdocs-analytics',
					'betterdocs_page_betterdocs-glossaries',
					'betterdocs_page_betterdocs-ai-chatbot',
					'edit-doc_category',
					'edit-doc_tag',
				),
				$notices,
				true
			);
		}
	}

	public function body_classes( $classes ) {
		$saved_settings     = get_option( 'betterdocs_settings', false );
		$dark_mode          = isset( $saved_settings['dark_mode'] ) ? $saved_settings['dark_mode'] : false;
		$dark_mode          = ! empty( $dark_mode ) ? boolval( $dark_mode ) : false;
		$current_screen_id  = get_current_screen() != null ? str_replace( 'betterdocs_page_', '', str_replace( 'toplevel_page_', '', str_replace( 'admin_page_', '', get_current_screen()->id ) ) ) : '';
		$registered_screens = array(
			'betterdocs-settings',
			'betterdocs-admin',
			'betterdocs-dashboard',
			'betterdocs-analytics',
			'betterdocs-glossaries',
			'betterdocs-faq',
			'edit-doc_category',
			'edit-doc_tag',
			'edit-knowledge_base',
			'betterdocs-ai-chatbot',
		);

		if ( in_array( $current_screen_id, $registered_screens ) ) {
			$classes .= ' betterdocs-admin ';
		}

		if ( true === $dark_mode && in_array( $current_screen_id, $registered_screens ) ) {
			$classes .= ' betterdocs-dark-mode ';
		}

		return $classes;
	}

	/**
	 * Remove Comments Column From List Table
	 *
	 * @param array $columns
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function set_custom_edit_action_columns( $columns ) {
		unset( $columns['comments'] );
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			if ( 'date' == $key ) {
				$new_columns['betterdocs_word_count'] = __( 'Word Count', 'betterdocs' ); // put the tags column before it
				$new_columns['betterdocs_reaction']   = __( 'Reactions', 'betterdocs' );
			}
			$new_columns[ $key ] = $value;
		}

		return $new_columns;
	}

	public function manage_custom_columns( $column, $post_id ) {
		global $wpdb;
		switch ( $column ) {
			case 'betterdocs_word_count':
				$content_without_html_tags = trim( strip_tags( get_post_field( 'post_content', $post_id ) ) );
				preg_match_all( '/<[^>]*>|[\p{L}\p{M}]+/u', $content_without_html_tags, $matches );
				$total_words = ! empty( $matches[0] ) ? count( $matches[0] ) : count( array() );
				$word_count  = $total_words;
				echo '<span>' . esc_html( intval( $word_count ) ) . '</span>';
				break;
			case 'betterdocs_reaction':
				$where     = "WHERE post_id='" . esc_sql( $post_id ) . "'";
				$analytics = $wpdb->get_results(
					"SELECT
                        sum(impressions) as totalViews,
                        sum(unique_visit) as totalUniqueViews,
                        sum(happy + sad + normal) as totalReactions,
                        sum(happy) as totalHappy,
                        sum(normal) as totalNormal,
                        sum(sad) as totalSad
                    FROM {$wpdb->prefix}betterdocs_analytics
                    $where"
				);

				echo '<ul class="reactions-count">
                    <li>
                        <a title="happy" class="betterdocs-feelings happy" data-feelings="happy" href="#">
                            <svg width="15" height="15" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 20 20" style="enable-background:new 0 0 20 20;" xml:space="preserve">
                                <path class="st0" d="M10,0.1c-5.4,0-9.9,4.4-9.9,9.8c0,5.4,4.4,9.9,9.8,9.9c5.4,0,9.9-4.4,9.9-9.8C19.9,4.5,15.4,0.1,10,0.1z
                            M13.3,6.4c0.8,0,1.5,0.7,1.5,1.5c0,0.8-0.7,1.5-1.5,1.5c-0.8,0-1.5-0.7-1.5-1.5C11.8,7.1,12.5,6.4,13.3,6.4z M6.7,6.4
                            c0.8,0,1.5,0.7,1.5,1.5c0,0.8-0.7,1.5-1.5,1.5c-0.8,0-1.5-0.7-1.5-1.5C5.2,7.1,5.9,6.4,6.7,6.4z M10,16.1c-2.6,0-4.9-1.6-5.8-4
                            l1.2-0.4c0.7,1.9,2.5,3.2,4.6,3.2s3.9-1.3,4.6-3.2l1.2,0.4C14.9,14.5,12.6,16.1,10,16.1z" />
                                <path class="st1" d="M-6.6-119.7c-7.1,0-12.9,5.8-12.9,12.9s5.8,12.9,12.9,12.9s12.9-5.8,12.9-12.9S0.6-119.7-6.6-119.7z
                            M-2.3-111.4c1.1,0,2,0.9,2,2c0,1.1-0.9,2-2,2c-1.1,0-2-0.9-2-2C-4.3-110.5-3.4-111.4-2.3-111.4z M-10.9-111.4c1.1,0,2,0.9,2,2
                            c0,1.1-0.9,2-2,2c-1.1,0-2-0.9-2-2C-12.9-110.5-12-111.4-10.9-111.4z M-6.6-98.7c-3.4,0-6.4-2.1-7.6-5.3l1.6-0.6
                            c0.9,2.5,3.3,4.2,6,4.2s5.1-1.7,6-4.2L1-104C-0.1-100.8-3.2-98.7-6.6-98.7z" />
                            </svg>
                            <span>' . esc_html( ( intval( $analytics[0]->totalHappy ) !== null ? intval( $analytics[0]->totalHappy ) : 0 ) ) . '</span>
                        </a>
                    </li>
                    <li>
                        <a title="normal" class="betterdocs-feelings normal" data-feelings="normal" href="#">
                            <svg width="15" height="15" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 20 20" style="enable-background:new 0 0 20 20;" xml:space="preserve">
                                <path class="st0" d="M10,0.2c-5.4,0-9.8,4.4-9.8,9.8s4.4,9.8,9.8,9.8s9.8-4.4,9.8-9.8S15.4,0.2,10,0.2z M6.7,6.5
                        c0.8,0,1.5,0.7,1.5,1.5c0,0.8-0.7,1.5-1.5,1.5C5.9,9.5,5.2,8.9,5.2,8C5.2,7.2,5.9,6.5,6.7,6.5z M14.2,14.3H5.9
                        c-0.3,0-0.6-0.3-0.6-0.6c0-0.3,0.3-0.6,0.6-0.6h8.3c0.3,0,0.6,0.3,0.6,0.6C14.8,14,14.5,14.3,14.2,14.3z M13.3,9.5
                        c-0.8,0-1.5-0.7-1.5-1.5c0-0.8,0.7-1.5,1.5-1.5c0.8,0,1.5,0.7,1.5,1.5C14.8,8.9,14.1,9.5,13.3,9.5z" />
                            </svg>
                            <span>' . esc_html( ( intval( $analytics[0]->totalNormal ) !== null ? intval( $analytics[0]->totalNormal ) : 0 ) ) . '</span>
                        </a>
                    </li>
                    <li>
                        <a title="sad" class="betterdocs-feelings sad" data-feelings="sad" href="#">
                            <svg width="15" height="15" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 20 20" style="enable-background:new 0 0 20 20;" xml:space="preserve">
                                <circle class="st0" cx="27.5" cy="0.6" r="1.9" />
                                <circle class="st0" cx="36" cy="0.6" r="1.9" />
                                <path class="st1" d="M10,0.3c-5.4,0-9.8,4.4-9.8,9.8s4.4,9.8,9.8,9.8s9.8-4.4,9.8-9.8S15.4,0.3,10,0.3z M13.3,6.6
                            c0.8,0,1.5,0.7,1.5,1.5c0,0.8-0.7,1.5-1.5,1.5c-0.8,0-1.5-0.7-1.5-1.5C11.8,7.3,12.4,6.6,13.3,6.6z M6.7,6.6c0.8,0,1.5,0.7,1.5,1.5
                            c0,0.8-0.7,1.5-1.5,1.5C5.9,9.6,5.2,9,5.2,8.1C5.2,7.3,5.9,6.6,6.7,6.6z M14.1,15L14.1,15c-0.2,0-0.4-0.1-0.5-0.2
                            c-0.9-1-2.2-1.7-3.7-1.7s-2.8,0.6-3.7,1.7C6.2,14.9,6,15,5.9,15h0c-0.6,0-0.8-0.6-0.5-1.1c1.1-1.3,2.8-2.1,4.6-2.1
                            c1.8,0,3.5,0.8,4.6,2.1C15,14.3,14.7,15,14.1,15z" />
                            </svg>
                            <span>' . esc_html( ( intval( $analytics[0]->totalNormal ) !== null ? intval( $analytics[0]->totalNormal ) : 0 ) ) . '</span>
                        </a>
                    </li>
                </ul>';
				break;
		}
	}

	/**
	 * Enqueue Assets for Admin ( Styles )
	 *
	 * @param string $hook
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function styles( $hook ) {
		$this->assets->enqueue( 'betterdocs-global', 'admin/css/global.css', array(), 'all' );

		if ( ! betterdocs()->is_betterdocs_screen( $hook ) ) {
			return;
		}

		$this->assets->enqueue( 'betterdocs-select2', 'vendor/css/select2.min.css', array(), 'all' );
		$this->assets->enqueue( 'betterdocs-daterangepicker', 'vendor/css/daterangepicker.css', array(), 'all' );
		$this->assets->enqueue( 'betterdocs-old', 'admin/css/betterdocs.css', array(), 'all' );

		/**
		* This scripts enqueued for Dashboard App.
		*/
		$this->assets->enqueue( 'betterdocs', 'admin/css/dashboard.css', array( 'betterdocs-old' ), '', BETTERDOCS_VERSION );
		$this->assets->enqueue( 'betterdocs-icons', 'admin/btd-icon/style.css' );
	}

	/**
	 * Enqueue Assets for Admin ( Scripts )
	 *
	 * @param string $hook
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function scripts( $hook ) {
		if ( ( 'edit.php' === $hook ) && get_post_type() == 'docs' ) {
			$this->assets->enqueue(
				'betterdocs-switcher',
				'admin/js/switcher.js',
				array(
					'jquery',
				)
			);

			$this->assets->localize(
				'betterdocs-switcher',
				'betterdocsSwitcher',
				array(
					'menu_title'             => __( 'Switch to BetterDocs UI', 'betterdocs' ),
					'site_address'           => get_bloginfo( 'url' ),
					'betterdocs_pro_plugin'  => betterdocs()->is_pro_active(),
					'betterdocs_pro_version' => betterdocs()->pro_version(),
				)
			);

			return;
		}

		wp_enqueue_script( 'wp-editor' ); // enqueue this for yoast related issue

		if ( ! betterdocs()->is_betterdocs_screen( $hook ) ) {
			return;
		}

		wp_enqueue_media(); // load early to fix problems with media upload issues on settings for WordPress 6.0.9
		$this->assets->register( 'betterdocs-admin', 'admin/js/dashboard.js' );

		$saved_settings = get_option( 'betterdocs_settings', false );
		$dark_mode      = $saved_settings['dark_mode'] ?? false;
		$dark_mode      = ! empty( $dark_mode ) && boolval( $dark_mode );
		$this->assets->localize(
			'betterdocs-admin',
			'betterdocs_admin',
			array(
				'ajaxurl'                    => admin_url( 'admin-ajax.php' ),
				'doc_cat_order_nonce'        => wp_create_nonce( 'doc_cat_order_nonce' ),
				'knowledge_base_order_nonce' => wp_create_nonce( 'knowledge_base_order_nonce' ),
				'paged'                      => isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
                'per_page_id'                    => 'edit_doc_category_per_page',
                'menu_title'                     => __( 'Switch to BetterDocs UI', 'betterdocs' ),
                'dark_mode'                      => $dark_mode,
                'text'                           => __( 'Copied!', 'betterdocs' ),
                'test_report'                    => __( 'Test Report!', 'betterdocs' ),
                'sending'                        => __( 'Sending...', 'betterdocs' ),
                'dir_url'                        => BETTERDOCS_ABSURL,
                'rest_url'                       => esc_url_raw( rest_url() ),
                'free_version'                   => betterdocs()->version,
                'generate_data_url'              => get_rest_url( null, '/betterdocs/v1/create-sample-docs' ),
                'nonce'                          => wp_create_nonce( 'wp_rest' ),
                'sync_nonce'                     => wp_create_nonce( 'ai_chatbot_embed' ),
                'count_all_docs'                 => array_sum( (array) wp_count_posts( 'docs' ) ),
                'count_all_faq'                  => array_sum( (array) wp_count_posts( 'betterdocs_faq' ) ),
                'count_new_docs'                 => $this->get_not_synced_docs_count(),
                'admin_url'                      => admin_url(),
                'ia_preview'                     => betterdocs()->settings->get( 'ia_enable_preview', false ),
                'multiple_kb'                    => betterdocs()->settings->get( 'multiple_kb' ),
                'previewMode'                    => betterdocs()->settings->get( 'ia_enable_preview', false ),
                'dashboard_mode'                 => get_option( 'dashboard_mode' ),
                'betterdocs_pro_plugin'          => betterdocs()->is_pro_active(),
                'betterdocs_pro_version'         => betterdocs()->pro_version(),
                'analytics_older'                => version_compare( betterdocs()->pro_version(), '3.3.4', '<=' ),
                'disabled_embed_model_option'    => get_option( 'disabled_embed_model_option' ),
                'betterdocs_ChatBot_plugin'      => is_plugin_active( 'betterdocs-ai-chatbot/betterdocs-ai-chatbot.php' ),
                'total_doc_category_terms'       => wp_count_terms( 'doc_category' ),
			)
		);

		// If wp-date (which includes moment.js) is not registered, enqueue your custom moment.js
		if ( ! wp_script_is( 'wp-date', 'registered' ) ) {
			$this->assets->enqueue( 'moment', 'vendor/js/moment.min.js', array() );
		}
		wp_enqueue_script( 'betterdocs-admin' );

		/**
		* Duplicate Codes Need to Be Removed From Here Onwards
		*/

		// FAQ Builder Related Localization
		betterdocs()->assets->enqueue( 'betterdocs-admin-faq', 'admin/css/faq.css' );
		betterdocs()->assets->enqueue( 'betterdocs-admin-faq', 'admin/js/faq.js' );

		// removing emoji support
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );

		// Get settings and remove unnecessary keys
		$betterdocs_settings = get_option( 'betterdocs_settings', false );
		if ( is_array( $betterdocs_settings ) && ! current_user_can( 'edit_docs_settings' ) ) {
			unset( $betterdocs_settings['ai_autowrite_api_key'] );
			unset( $betterdocs_settings['ai_chatbot_api_key'] );
		}

		betterdocs()->assets->localize(
			'betterdocs-admin-faq',
			'betterdocsFaq',
			array(
				'dir_url'             => BETTERDOCS_ABSURL,
				'rest_url'            => esc_url_raw( rest_url() ),
				'free_version'        => betterdocs()->version,
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'betterdocs_settings' => $betterdocs_settings,
			)
		);

		// Glossaries Related Localization
		betterdocs()->assets->enqueue( 'betterdocs-admin-glossaries', 'admin/css/faq.css' );

		betterdocs()->assets->enqueue( 'betterdocs-admin-glossaries', 'admin/js/glossaries.js' );

		betterdocs()->assets->localize(
			'betterdocs-admin-glossaries',
			'betterdocsGlossary',
			array(
				'dir_url'             => BETTERDOCS_ABSURL,
				'rest_url'            => esc_url_raw( rest_url() ),
				'free_version'        => betterdocs()->version,
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'betterdocs_settings' => $betterdocs_settings,
			)
		);
	}

	/**
	 * All admin pages header
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function header( $admin_tab_name ) {
		$quick_links = array(
			'switch_view' => sprintf(
				'<a href="%s" class="betterdocs-button betterdocs-button-secondary">%s</a>',
				add_query_arg(
					array(
						'post_type'  => 'docs',
						'bdocs_view' => 'classic',
					),
					'edit.php'
				),
				__( 'Switch to Classic UI', 'betterdocs' )
			),
			'add_new_doc' => sprintf( '<a href="%s" class="betterdocs-button betterdocs-button-primary">%s</a>', add_query_arg( array( 'post_type' => 'docs' ), 'post-new.php' ), __( 'Add New Doc', 'betterdocs' ) ),
		);

		$quick_links = apply_filters( 'betterdocs_quick_links', $quick_links );

		betterdocs()->views->get(
			'admin/header',
			array(
				'quick_links' => $quick_links,
				'active_tab'  => $admin_tab_name,
			)
		);
	}

	/**
	 * Register all the menus for BetterDocs
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function menus() {
		$default_args = array(
			'page_title' => 'BetterDocs',
			'menu_title' => 'BetterDocs',
			'capability' => 'edit_docs', // Unified capability
			'menu_slug'  => $this->slug,
			'callback'   => array( $this, 'output' ),
			'icon_url'   => betterdocs()->assets->icon( 'betterdocs-icon-white.svg', true ),
			'position'   => 5,
		);

		$_menu_position = 5;
		global $submenu;

		// Always register both UI endpoints
		$this->register_modern_ui_fallback();

		foreach ( $this->menu_list() as $key => $value ) {
			if ( 'betterdocs' === $key ) {
				$callable = 'add_menu_page';
				$value    = wp_parse_args( $value, $default_args );
				call_user_func_array( $callable, $value );
			} else {
				$is_core_page = strpos( $value['menu_slug'], '?' ) !== false;

				if ( $is_core_page ) {
					// Add classic UI directly
					$submenu[ $this->slug ][] = array(
						$value['menu_title'],
						$value['capability'],
						$value['menu_slug'],
						$value['page_title'],
					);
				} else {
					// Add modern UI through WordPress API
					add_submenu_page(
						$this->slug,
						$value['page_title'],
						$value['menu_title'],
						$value['capability'],
						$value['menu_slug'],
						$value['callback']
					);
				}
				++$_menu_position;
			}
		}
	}

	private function register_modern_ui_fallback() {
		// Add the submenu with valid parent slug
		add_submenu_page(
			'betterdocs', // Valid parent slug
			__( 'All Docs', 'betterdocs' ),
			'', // Empty menu title hides it
			'edit_docs',
			'betterdocs-admin',
			array( $this, 'output' )
		);

		// Hide the menu item from appearing in the admin sidebar
		global $submenu;
		if ( isset( $submenu['betterdocs'] ) ) {
			foreach ( $submenu['betterdocs'] as $key => $item ) {
				if ( 'betterdocs-admin' === $item[2] ) {
					unset( $submenu['betterdocs'][ $key ] );
					break;
				}
			}
		}
	}

	/**
	 * BetterDocs Admin Page Output
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function output() {
		if ( betterdocs()->is_pro_active()
		&& version_compare( betterdocs()->pro_version(), '3.3.4', '<=' )
		&& get_current_screen()->id == 'betterdocs_page_betterdocs-analytics' ) {
			betterdocs_pro()->views->get( 'admin/analytics-pro' );
		} else {
			betterdocs()->views->get(
				'admin/main',
				array(
					'admin_ui' => 'dnd',
				)
			);
		}
	}

	/**
	 * Menu creator helper
	 *
	 * @param string $title
	 * @param string $slug
	 * @param string $cap
	 * @param array  $callback
	 *
	 * @return array
	 * @since 2.5.0
	 */
	private function normalize_menu( $title, $slug, $cap = 'edit_docs', $callback = null, $optional = array() ) {
		return Helper::normalize_menu( $title, $slug, $cap, $callback, $optional );
	}

	/**
	 * BetterDocs Menu List
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private function menu_list() {
		$parent_slug = array();

		$betterdocs_admin_pages = array(
			'betterdocs' => array(
				'menu_slug'  => $this->slug,
				'page_title' => 'BetterDocs',
				'menu_title' => 'BetterDocs',
				'capability' => 'edit_docs',
				'callback'   => array( $this, 'output' ),
				'icon_url'   => betterdocs()->assets->icon( 'betterdocs-icon-white.svg', true ),
				'position'   => 5,
			),
			'dashboard'  => $this->normalize_menu(
				__( 'Dashboard', 'betterdocs' ),
				'betterdocs-dashboard',
				'edit_docs',
				array(
					$this,
					'output',
				)
			),
			'all_docs'   => $this->normalize_menu(
				__( 'All Docs', 'betterdocs' ),
				$this->ui_slug(),
				'edit_docs',
				array( $this, 'output' ),
				$parent_slug
			),
			'add_new'    => $this->normalize_menu(
				__( 'Add New', 'betterdocs' ),
				'post-new.php?post_type=docs'
			),
			'categories' => $this->normalize_menu(
				__( 'Categories', 'betterdocs' ),
				'edit-tags.php?taxonomy=doc_category&post_type=docs',
				'manage_doc_terms'
			),
			'tags'       => $this->normalize_menu(
				__( 'Tags', 'betterdocs' ),
				'edit-tags.php?taxonomy=doc_tag&post_type=docs',
				'manage_doc_terms'
			),
			'settings'   => $this->normalize_menu(
				__( 'Settings', 'betterdocs' ),
				'betterdocs-settings',
				'edit_docs_settings',
				array(
					$this,
					'output',
				),
				$parent_slug
			),
			'analytics'  => $this->normalize_menu(
				__( 'Analytics', 'betterdocs' ),
				'betterdocs-analytics',
				'read_docs_analytics',
				array(
					$this,
					'output',
				),
				$parent_slug
			),
			'faq'        => $this->normalize_menu(
				__( 'FAQ Builder', 'betterdocs' ),
				'betterdocs-faq',
				'read_faq_builder',
				array(
					$this,
					'output',
				),
				$parent_slug
			),
		);

		if ( betterdocs()->is_pro_active() && betterdocs()->settings->get( 'enable_glossaries' ) == true ) {
			$betterdocs_admin_pages['glossaries'] = $this->normalize_menu(
				__( 'Glossaries', 'betterdocs' ),
				'betterdocs-glossaries',
				'read_docs_analytics',
				array(
					$this,
					'output',
				),
				$parent_slug
			);
		}

		if ( ! betterdocs()->is_chatbot_active() ) {
			$betterdocs_admin_pages['ai_chatbot'] = $this->normalize_menu(
				__( 'AI Chatbot', 'betterdocs' ),
				'betterdocs-ai-chatbot',
				'edit_docs_settings',
				array(
					$this,
					'output',
				),
				$parent_slug
			);
		}

		return apply_filters( 'betterdocs_admin_menu', $betterdocs_admin_pages, array( $this, 'output' ), $parent_slug );
	}

	public function add_custom_classes_to_menu_items() {
		global $menu, $submenu;

		$menu_items = array(
			'betterdocs'               => 'betterdocs',
			'betterdocs_page_all_docs' => 'betterdocs-all-docs',
			'betterdocs_page_add_new'  => 'betterdocs-add-new',
			'edit-tags.php?taxonomy=doc_category&post_type=docs' => 'betterdocs-categories',
			'edit-tags.php?taxonomy=doc_tag&post_type=docs' => 'betterdocs-tags',
			'betterdocs-settings'      => 'betterdocs-settings',
			'betterdocs-analytics'     => 'betterdocs-analytics',
			'betterdocs-faq'           => 'betterdocs-faq',
			'betterdocs-glossaries'    => 'betterdocs-glossaries',
			'betterdocs-ai-chatbot'    => 'betterdocs-ai-chatbot',
			'edit-tags.php?taxonomy=knowledge_base&post_type=docs' => 'betterdocs-multiplekb',
		);

		foreach ( $menu as &$item ) {
			if ( isset( $menu_items[ $item[2] ] ) ) {
				if ( ! isset( $item[4] ) ) {
					$item[4] = '';
				}
				$item[4] .= '' . $menu_items[ $item[2] ];
			}
		}

		foreach ( $submenu as &$submenu_items ) {
			foreach ( $submenu_items as &$sub_item ) {
				if ( isset( $menu_items[ $sub_item[2] ] ) ) {
					if ( ! isset( $sub_item[4] ) ) {
						$sub_item[4] = '';
					}
					$sub_item[4] .= '' . $menu_items[ $sub_item[2] ];
				}
			}
		}
	}

	public function quick_setup_menu( $menus ) {
		$betterdocs_settings = get_option( 'betterdocs_settings' );
		if ( $betterdocs_settings ) {
			return $menus;
		} else {
			$menus['quick_setup'] = $this->normalize_menu(
				__( 'Quick Setup', 'betterdocs' ),
				'betterdocs-setup',
				'delete_users',
				array(
					$this->container->get( SetupWizard::class ),
					'views',
				)
			);
		}

		return $menus;
	}

	public function insert_plugin_links( $links ) {
		$links[] = '<a href="admin.php?page=betterdocs-settings">' . __( 'Settings', 'betterdocs' ) . '</a>';

		if ( ! is_plugin_active( 'betterdocs-pro/betterdocs-pro.php' ) ) {
			$links[] = '<a href="https://betterdocs.co/upgrade-to-pro-plugins-wp" target="_blank" style="color: #000; font-weight: bold;">' . __( 'Upgrade to Pro', 'betterdocs' ) . '</a>';
		}

		return $links;
	}

	public function toolbar_menu( $admin_bar ) {
		if ( ! is_admin() || ! is_admin_bar_showing() ) {
			return;
		}

		// Show only when the user is a member of this site, or they're a super admin.
		if ( ! is_user_member_of_blog() && ! is_super_admin() ) {
			return;
		}

		$docs_url         = '';
		$encyclopedia_url = '';

		if ( $this->settings->get( 'builtin_doc_page' ) ) {
			$docs_url = get_post_type_archive_link( 'docs' );
		} elseif ( intval( $docs_page = $this->settings->get( 'docs_page' ) ) ) {
			$docs_url = ! empty( $docs_page ) ? get_page_link( $docs_page ) : false;
		}

		if ( ! $docs_url ) {
			return;
		}

		$slug = $this->settings->get( 'encyclopedia_root_slug' );

		global $wp_rewrite;
		if ( $wp_rewrite->using_index_permalinks() ) {
			$slug = $wp_rewrite->index . '/' . $slug;
		}

		$encyclopedia_url = home_url( $slug );

		$admin_bar->add_node(
			array(
				'parent' => 'site-name',
				'id'     => 'view-docs',
				'title'  => __( 'Visit Documentation', 'betterdocs' ),
				'href'   => $docs_url,
			)
		);

		$is_enable_encyclopedia = betterdocs()->settings->get( 'enable_encyclopedia' );

		if ( $is_enable_encyclopedia && betterdocs()->is_pro_active() ) {
			$admin_bar->add_node(
				array(
					'parent' => 'site-name',
					'id'     => 'view-encyclopedia',
					'title'  => __( 'Visit Encyclopedia', 'betterdocs' ),
					'href'   => $encyclopedia_url,
				)
			);
		}
	}

	/**
	 * Save last visited admin ui
	 *
	 * @since 3.0.1
	 */
	public function save_admin_page() {
		if ( isset( $_GET['post_type'] ) && 'docs' === $_GET['post_type'] && isset( $_GET['bdocs_view'] ) && 'classic' === $_GET['bdocs_view'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_user_meta( get_current_user_id(), 'last_visited_docs_admin_page', 'classic_ui' );
		} elseif ( isset( $_GET['page'] ) && 'betterdocs-admin' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_user_meta( get_current_user_id(), 'last_visited_docs_admin_page', 'modern_ui' );
		}
	}

	/**
	 * Return last visited admin ui slug
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function ui_slug() {
		$last_visited = get_user_meta( get_current_user_id(), 'last_visited_docs_admin_page', true );
		$docs_exist   = get_posts(
			array(
				'post_type'   => 'docs',
				'post_status' => 'any',
				'numberposts' => 1,
			)
		);

		return ( 'modern_ui' === $last_visited || empty( $docs_exist ) )
		? 'betterdocs-admin'
		: 'edit.php?post_type=docs&bdocs_view=classic';
	}

	/**
	 * Resets a duplicate submenu in WordPress if the parent main menu and the first submenu permalink are not the same.
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function reset_submenu() {
		global $submenu;

		$docs = get_posts( array( 'post_type' => 'docs' ) );
		if ( count( $docs ) == 0 ) {
			return;
		}

		$last_visited = get_user_meta( get_current_user_id(), 'last_visited_docs_admin_page', true );

		if ( 'classic_ui' === $last_visited && isset( $submenu['betterdocs-admin'] ) && in_array( 'betterdocs-admin', $submenu['betterdocs-admin'][0] ) ) {
			unset( $submenu['betterdocs-admin'][0] );
			$submenu['betterdocs-admin'] = array_values( $submenu['betterdocs-admin'] );
		}
	}

	/**
	 * Initialize Black Friday Pointer
	 *
	 * @return void
	 * @since 3.7.0
	 */
	private function init_black_friday_pointer() {
		// Only initialize if conditions are met
		if ( NoticePointers::should_display_notice() ) {
			new NoticePointers();
		}
	}

	/**
	 * AJAX handler for dismissing Black Friday pointer
	 *
	 * @return void
	 * @since 3.7.0
	 */
	public function ajax_dismiss_black_friday_pointer() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'betterdocs_dismiss_pointer' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'betterdocs' ) ) );
		}

		// Check if user has permission
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_docs' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'betterdocs' ) ) );
		}

		// Get the introduction key
		$introduction_key = isset( $_POST['introduction_key'] ) ? sanitize_text_field( $_POST['introduction_key'] ) : '';

		if ( empty( $introduction_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid introduction key', 'betterdocs' ) ) );
		}

		// Set the introduction as viewed
		NoticePointers::set_introduction_viewed( $introduction_key );

		// Clear the priority option so other plugins can set their priority
		delete_option( '_wpdeveloper_plugin_pointer_priority' );

		wp_send_json_success( array( 'message' => __( 'Pointer dismissed successfully', 'betterdocs' ) ) );
	}

	/**
	 * Get count of docs that are not yet synced
	 * Similar to Helper::get_not_synced_post_ids() in betterdocs-ai-chatbot plugin
	 *
	 * @return int
	 * @since 3.7.0
	 */
	private function get_not_synced_docs_count() {
		$new_post_ids     = get_option( 'saved_docs_post_ids', array() );
		$error_posts_data = get_option( 'betterdocs_ai_chatbot_error_posts', array() );

		// Extract post IDs from error posts (handle both old and new formats)
		$error_post_ids = array();
		if ( is_array( $error_posts_data ) && ! empty( $error_posts_data ) ) {
			foreach ( $error_posts_data as $key => $value ) {
				if ( is_numeric( $key ) && is_numeric( $value ) ) {
					// Old format: numeric key with post ID as value
					$error_post_ids[] = $value;
				} elseif ( is_numeric( $key ) && is_array( $value ) && isset( $value['post_id'] ) ) {
					// New format: post ID as key with structured data
					$error_post_ids[] = $key;
				} elseif ( is_numeric( $key ) ) {
					// New format: post ID as key
					$error_post_ids[] = $key;
				}
			}
		}

		// Merge both arrays and remove duplicates, then count
		return count( array_unique( array_merge( $new_post_ids, $error_post_ids ) ) );
	}
}
