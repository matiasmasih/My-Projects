<?php
namespace BetterLinks\Integration;

use BetterLinks\Admin\Cache;
use BetterLinks\Helper;
use BetterLinks\Traits\Links;

class FluentBoards {
	use Links;

	public static function init() {
		$self = new self();
		add_action( 'fluent_boards/task_deleted', array( $self, 'fbs_task_deleted' ), 10, 1 );
		add_action( 'fluent_boards/board_task_archived', array( $self, 'fbs_task_archive' ), 10, 1 );
		add_filter( 'betterlinks__intlfbs_filter_category_from_dashboard', array( $self, 'filter_category_from_dashboard' ), 10, 2 );
	}

	/**
	 * Fluent Boards Task Delete
	 */
	public function fbs_task_deleted( $task ) {
		$settings = Cache::get_json_settings();
		if ( ! isset( $settings['fbs']['delete_on'] ) || 'task_delete' !== $settings['fbs']['delete_on'] ) {
			return;
		}

		$this->fbs_shorten_link_delete( $task );
	}

	/**
	 * Fluent Boards Task Archive
	 */
	public function fbs_task_archive( $task ) {
		$settings = Cache::get_json_settings();
		if ( ! isset( $settings['fbs']['delete_on'] ) || 'task_archive' !== $settings['fbs']['delete_on'] ) {
			return;
		}

		$this->fbs_shorten_link_delete( $task );
	}

	public function fbs_shorten_link_delete( $task ) {
		$page_url = fluent_boards_page_url();
		$task_url = $page_url . 'boards/' . $task->board_id . '/tasks/' . $task->id;

		$link = Helper::get_link_by_permalink( $task_url, 'id, short_url' );
		if ( ! empty( $link ) ) {
			$args = array(
				'ID'        => ( isset( $link['id'] ) ? sanitize_text_field( $link['id'] ) : '' ),
				'short_url' => ( isset( $link['short_url'] ) ? sanitize_text_field( $link['short_url'] ) : '' ),
			);
			$this->delete_link( $args );
		}
	}

	/**
	 * Fluent Boards Category Filter from Dashboard
	 */
	public function filter_category_from_dashboard( $value, $settings ) {
		if ( ! empty( $settings['fbs']['enable_fbs'] ) && ! empty( $settings['fbs']['show_fbs_category'] ) ) {
			return $value;
		}
		$fbs_cat_arr = array();
		$fbs_cat     = Helper::get_term_by_slug( 'fluent-boards' );
		if ( ! empty( $fbs_cat[0]['ID'] ) ) {
			array_push( $fbs_cat_arr, $fbs_cat[0]['ID'] );
		}

		if ( ! empty( $settings['fbs']['cat_id'] ) ) {
			array_push( $fbs_cat_arr, $settings['fbs']['cat_id'] );
		}

		$fbs_cat_arr = '(' . implode( ',', $fbs_cat_arr ) . ')';

		$fbs_category_query = sprintf( 'WHERE bt.ID not in %1$s', $fbs_cat_arr );

		return $fbs_category_query;
	}
}
