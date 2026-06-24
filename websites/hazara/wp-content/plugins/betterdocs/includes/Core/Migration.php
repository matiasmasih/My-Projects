<?php

namespace WPDeveloper\BetterDocs\Core;

use WP_Query;
use WPDeveloper\BetterDocs\Utils\Base;
use WPDeveloper\BetterDocs\Utils\Database;
use WPDeveloper\BetterDocs\Dependencies\DI\Container;

class Migration extends Base {
	/**
	 * Database
	 * @var Database
	 */
	private $database;
	private $settings;
	private $container;

	public function __construct( Container $container ) {
		$this->container = $container;
		$this->database  = $container->get( Database::class );
		$this->settings  = $container->get( Settings::class );
	}

	public function init( $version ) {
		if ( $version > 250 ) {
			for ( $_version = 250; $_version <= $version; $_version++ ) {
				if ( method_exists( $this, "v$_version" ) ) {
					call_user_func( [ $this, "v$_version" ] );
				}
			}
		}

		$this->search_migration();
		$this->fix_search_table_collation();

		/**
		 * Settings Migration
		 */
		$this->settings->migration( $version );
	}

	public function search_migration() {
		global $wpdb;
		if ( ! $this->database->get( 'betterdocs_search_data_migration', false ) ) {
			$search_data = $this->database->get( 'betterdocs_search_data' );
			if ( ! empty( $search_data ) ) {
				$search_data_arr = unserialize( $search_data );
				foreach ( $search_data_arr as $key => $value ) {
					$args = [
						'post_type'        => 'docs',
						'post_status'      => 'publish',
						'posts_per_page'   => -1,
						'suppress_filters' => true,
						's'                => $key
					];

					$loop = new WP_Query( $args );
					if ( $loop->have_posts() ) {
						$count           = $value;
						$not_found_count = 0;
					} else {
						$count           = 0;
						$not_found_count = $value;
					}

					// Use BINARY comparison to avoid collation mismatch errors
				$keyword = $wpdb->get_var(
						$wpdb->prepare(
							"
                            SELECT keyword
                            FROM {$wpdb->prefix}betterdocs_search_keyword
                            WHERE BINARY keyword = %s",
							$key
						)
					);

					if ( $keyword == null ) {
						$insert = $wpdb->query(
							$wpdb->prepare(
								"INSERT INTO {$wpdb->prefix}betterdocs_search_keyword
                                ( keyword )
                                VALUES ( %s )",
								[
									$key
								]
							)
						);

						if ( $insert ) {
							$wpdb->query(
								$wpdb->prepare(
									"INSERT INTO {$wpdb->prefix}betterdocs_search_log
                                    (keyword_id, count, not_found_count, created_at)
                                    VALUES (%d, %d, %d, %s)",
									[
										$wpdb->insert_id,
										$count,
										$not_found_count,
										date( 'Y-m-d' )
									]
								)
							);
						}
					}
				}

				$this->database->save( 'betterdocs_search_data_migration', '1.0' );
			}
		}
	}

	/**
	 * Fix collation issues in search tables
	 * Converts tables to proper UTF-8 charset and collation to prevent
	 * "Illegal mix of collations" errors when searching with non-Latin characters
	 *
	 * @since 1.0.2
	 * @return void
	 */
	public function fix_search_table_collation() {
		global $wpdb;

		// Check if migration already ran
		if ( $this->database->get( 'betterdocs_search_collation_fixed', false ) ) {
			return;
		}

		// Get the WordPress default charset and collation
		$charset = $wpdb->charset ? $wpdb->charset : 'utf8mb4';
		$collate = $wpdb->collate ? $wpdb->collate : 'utf8mb4_unicode_520_ci';

		// Fix search_keyword table
		$search_keyword_table = $wpdb->prefix . 'betterdocs_search_keyword';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$search_keyword_table'" ) == $search_keyword_table ) {
			// Convert table charset and collation
			$wpdb->query( "ALTER TABLE {$search_keyword_table} CONVERT TO CHARACTER SET {$charset} COLLATE {$collate}" );

			// Explicitly set keyword column collation
			$wpdb->query( "ALTER TABLE {$search_keyword_table} MODIFY keyword TEXT CHARACTER SET {$charset} COLLATE {$collate} NOT NULL" );
		}

		// Fix search_log table
		$search_log_table = $wpdb->prefix . 'betterdocs_search_log';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$search_log_table'" ) == $search_log_table ) {
			// Convert table charset and collation
			$wpdb->query( "ALTER TABLE {$search_log_table} CONVERT TO CHARACTER SET {$charset} COLLATE {$collate}" );
		}

		// Mark migration as complete
		$this->database->save( 'betterdocs_search_collation_fixed', '1.0' );
	}
}
