<?php
/*
 * Plugin Name:		BetterLinks
 * Plugin URI:		https://betterlinks.io/
 * Description:		Ultimate plugin to create, shorten, track and manage any URL. Gather analytics reports and run successfully marketing campaigns easily.
 * Version:			2.4.9
 * Author:			WPDeveloper
 * Author URI:		https://wpdeveloper.com
 * License:			GPL-3.0+
 * License URI:		http://www.gnu.org/licenses/gpl-3.0.txt
 * Author URI:		https://wpdeveloper.com
 * Text Domain:		betterlinks
 * Domain Path:		/languages
 */

use BetterLinks\Admin\Cache;

if (!defined('ABSPATH')) {
    exit();
}

if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

if (!class_exists('BetterLinks')) {
    final class BetterLinks
    {
        private $Installer;
        private $upload_dir;
        private function __construct()
        {
            $this->upload_dir_path();
            $this->define_constants();
            $this->set_global_settings();
            $this->Installer = new BetterLinks\Installer();
            register_activation_hook(__FILE__, [$this, 'activate']);
            register_deactivation_hook(__FILE__, [$this, 'deactivate']);
            add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
            add_action('betterlinks_loaded', [$this, 'init_plugin']);
            add_action('admin_init', [$this, 'run_migrator']);
            add_action('admin_init', [$this, 'do_the_works_if_failed_during_activation'], 100);
            add_action('admin_init', [$this, 'quick_setup']);
            $this->dispatch_hook();
            add_action( 'wp_enqueue_scripts', [$this, 'frontend_scripts'] );
        }

        public function do_the_works_if_failed_during_activation()
        {
            $betterlinks_activation_flag = BetterLinks\Helper::btl_get_option("betterlinks_activation_flag");
            if(isset($betterlinks_activation_flag["last_activation_background_processes_firing_timestamp"]) && isset($betterlinks_activation_flag["last_activation_timestamp"])) {
                if($betterlinks_activation_flag["last_activation_background_processes_firing_timestamp"]){
                    return false;
                }
                $waiting_time_in_seconds = 5;
                if((absInt($betterlinks_activation_flag["last_activation_timestamp"]) + $waiting_time_in_seconds) > time()){
                    // don't go any further and return false here if,
                    // $waiting_time_in_seconds (in this case 5 seconds) haven't passed yet since the activation flag was setted
                    return false;
                }
                $all_tasks = array_merge(
                    $this->Installer->activation,
                    $this->Installer->migration
                );
                foreach ($all_tasks as $task) {
                    $this->Installer->$task();
                }
            }
        }

        public static function init()
        {
            static $instance = false;

            if (!$instance) {
                $instance = new self();
            }

            return $instance;
        }
        public function define_constants()
        {
            /**
             * Defines CONSTANTS for Whole plugins.
             */
            define('BETTERLINKS_VERSION', '2.4.9');
            define('BETTERLINKS_DB_VERSION', '1.6.11');
            define('BETTERLINKS_MENU_NOTICE', '9');
            define('BETTERLINKS_SETTINGS_NAME', 'betterlinks_settings');
            define('BETTERLINKS_PLUGIN_FILE', __FILE__);
            define('BETTERLINKS_PLUGIN_BASENAME', plugin_basename(__FILE__));
            define('BETTERLINKS_PLUGIN_SLUG', 'betterlinks');
            define('BETTERLINKS_PLUGIN_ROOT_URI', plugins_url('/', __FILE__));
            define('BETTERLINKS_ROOT_DIR_PATH', plugin_dir_path(__FILE__));
            define('BETTERLINKS_ASSETS_DIR_PATH', BETTERLINKS_ROOT_DIR_PATH . 'assets/');
            define('BETTERLINKS_ASSETS_URI', BETTERLINKS_PLUGIN_ROOT_URI . 'assets/');
            define('BETTERLINKS_UPLOAD_DIR_PATH', $this->upload_dir['basedir'] . '/betterlinks_uploads');
            define('BETTERLINKS_EXISTS_LINKS_JSON', defined('BETTERLINKS_ALLOW_JSON_REDIRECT') ? file_exists(BETTERLINKS_UPLOAD_DIR_PATH . '/links.json') && BETTERLINKS_ALLOW_JSON_REDIRECT : file_exists(BETTERLINKS_UPLOAD_DIR_PATH . '/links.json'));
            define('BETTERLINKS_EXISTS_CLICKS_JSON', file_exists(BETTERLINKS_UPLOAD_DIR_PATH . '/clicks.json'));
            define('BETTERLINKS_EXISTS_SETTINGS_JSON', defined('BETTERLINKS_ALLOW_JSON_REDIRECT') ? file_exists(BETTERLINKS_UPLOAD_DIR_PATH . '/settings.json') && BETTERLINKS_ALLOW_JSON_REDIRECT : file_exists(BETTERLINKS_UPLOAD_DIR_PATH . '/settings.json'));
            define('BETTERLINKS_LINKS_OPTION_NAME', 'betterlinks_links');
            define('BETTERLINKS_CACHE_LINKS_NAME', 'betterlinks_cache_links_data');
            define('BETTERLINKS_DB_ALTER_OPTIONS', 'betterlinks_db_alter_options');
            define('BETTERLINKS_CUSTOM_DOMAIN_MENU', 'betterlinks_custom_domain_menu');
            define('BETTERLINKS_AI_API_KEYS_OPTION_NAME', 'betterlinks_ai_api_keys');
        }

        public function upload_dir_path()
        {
            $this->upload_dir = wp_get_upload_dir();
        }


        public function on_plugins_loaded()
        {
            do_action('betterlinks_loaded');
        }

        /**
         * Initialize the plugin
         *
         * @return void
         */
        public function init_plugin()
        {
            $this->load_textdomain();
            BetterLinks\API::init();
            if (is_admin()) {
                new BetterLinks\Admin();
            }
            BetterLinks\Integration::init();
            new BetterLinks\Link();
            new BetterLinks\Tools();
            new BetterLinks\Frontend;
            new BetterLinks\Elementor();
        }

        public function dispatch_hook()
        {
            BetterLinks\API::dispatch_hook();
            BetterLinks\Cron::init();
        }

        public function load_textdomain()
        {
            load_plugin_textdomain('betterlinks', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        public function set_global_settings()
        {
            $GLOBALS['betterlinks'] = BetterLinks\Helper::get_links();
            $settings = Cache::get_json_settings();
            $auto_create_link_settings = defined('BETTERLINKS_PRO_AUTO_LINK_CREATE_OPTION_NAME') ? get_option( BETTERLINKS_PRO_AUTO_LINK_CREATE_OPTION_NAME, array() ) : array();
            if ( is_string( $auto_create_link_settings ) ) {
                $auto_create_link_settings = json_decode( $auto_create_link_settings, true );
            }
            $settings = is_array($settings) ? $settings : array();
            $auto_create_link_settings = is_array( $auto_create_link_settings ) ? $auto_create_link_settings : array();
            $GLOBALS['betterlinks_settings'] = array_merge( $settings, $auto_create_link_settings );
        }

        public function run_migrator()
        {
            $btl_version = BetterLinks\Helper::btl_get_option('betterlinks_version');
            $should_insert = $btl_version===false;
            if ($btl_version != BETTERLINKS_VERSION && BetterLinks\Helper::btl_update_option('betterlinks_version', BETTERLINKS_VERSION, $should_insert, !$should_insert)) {
                $this->Installer->data($this->Installer->migration)->save()->dispatch();
                BetterLinks\Helper::btl_update_option('betterlinks_activation_flag', [
                    "last_activation_timestamp" => time(),
                    "last_activation_background_processes_firing_timestamp" => false,
                ]);
            }
        }

        public function activate()
        {
            $this->Installer->data($this->Installer->activation)->save()->dispatch();
            BetterLinks\Helper::btl_update_option('betterlinks_activation_flag', [
                "last_activation_timestamp" => time(),
                "last_activation_background_processes_firing_timestamp" => false,
            ]);
            add_option('betterlinks_quick_setup', true);
        }

        public function quick_setup() {
            if( 'complete' === get_option('betterlinks_quick_setup_step') ) return;
            if( get_option( 'betterlinks_quick_setup' ) && is_admin() ) {
                delete_option( 'betterlinks_quick_setup' );

                $redirect_url = admin_url('admin.php?page=betterlinks-quick-setup');
                wp_safe_redirect($redirect_url);
                exit;
            }
        }

        public function deactivate()
        {
            new BetterLinks\Uninstall();
        }

        public function frontend_scripts() {
			$dependencies = include_once BETTERLINKS_ASSETS_DIR_PATH . 'js/betterlinks.app.core.min.asset.php';

			// Enqueue main app script (geolocation logic is bundled inside)
			wp_enqueue_script( 'betterlinks-app', BETTERLINKS_ASSETS_URI . 'js/betterlinks.app.core.min.js', [ 'jquery' ], $dependencies['version'], true );

            wp_localize_script('betterlinks-app', 'betterLinksApp', [
                'betterlinks_nonce' => wp_create_nonce('betterlinks_admin_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'site_url' => apply_filters('betterlinks/site_url', site_url()),
                'rest_url' => rest_url(),
                'nonce' => wp_create_nonce('wp_rest'),
                'betterlinkspro_version' => defined('BETTERLINKS_PRO_VERSION') ? BETTERLINKS_PRO_VERSION : null,
            ]);
        }
    }
}

/**
 * Initializes the main plugin
 *
 * @return \BetterLinks
 */
if (!function_exists('BetterLinks_Start')) {
    function BetterLinks_Start()
    {
        return BetterLinks::init();

    }
}

// Plugin Start
BetterLinks_Start();
