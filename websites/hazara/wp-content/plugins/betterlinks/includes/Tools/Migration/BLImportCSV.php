<?php
namespace BetterLinks\Tools\Migration;

use BetterLinks\Helper;
use BetterLinks\Interfaces\ImportCsvInterface;

class BLImportCSV extends BaseCSV implements ImportCsvInterface {

	private $link_header = array();

	public function start_importing( $csv, $optional_param_1 = '' ) {
		$link_message  = array();
		$click_message = array();
		$count         = 0;
		while ( ( $item = fgetcsv( $csv, 0, ',', '"', '"' ) ) !== false ) {
			if ( $count === 0 ) {
				$this->link_header = $item;
				++$count;
				continue;
			}
			// Skip rows with mismatched column count
            if ( count( $item ) !== count( $this->link_header ) ) {
                ++$count;
                continue;
            }
			$item = array_combine( $this->link_header, $item );
			if ( isset( $item['short_url'] ) ) {
				$item['short_url'] = rtrim( $item['short_url'], '/' );
			}
			$item = \BetterLinks\Helper::sanitize_text_or_array_field( $item );

			if ( is_array( $item ) && ( ( isset( $item['click_count'] ) && count( $item ) === 12 ) || ( ! isset( $item['click_count'] ) && count( $item ) === 11 ) ) ) {
				$is_insert = $this->insert_click_data( $item );
				if ( $is_insert ) {
					$click_message[] = 'Imported Successfully "' . $item['short_url'] . '"';
				} else {
					$click_message[] = 'import failed "' . $item['short_url'] . '" already exists';
				}
			} elseif ( is_array( $item ) && in_array( count( $item ), array( 24, 25, 26, 27 ) ) ) {
				$is_insert = $this->insert_link_data( $item );
				if ( $is_insert ) {
					$link_message[] = 'Imported Successfully "' . $item['short_url'] . '"';
				} else {
					$link_message[] = 'import failed "' . $item['short_url'] . '" already exists';
				}
			}
		}
		return array(
			'links'  => $link_message,
			'clicks' => $click_message,
		);
	}

	public function insert_link_data( $item ) {
		if ( ! empty( $item['link_title'] ) && ! empty( $item['short_url'] ) ) {
			$link_id = $this->insert_link( $item );
			if ( ! ( empty( $link_id ) || empty( $item['auto_link_keywords'] ) ) ) {
				$auto_link_keywords = unserialize( $item['auto_link_keywords'] );

				foreach ( $auto_link_keywords as $keyword ) {
					['meta_key' => $meta_key, 'meta_value' => $meta_value] = $keyword;
					if ( Helper::isJson( $meta_value ) ) {
						$meta_value = json_decode( $meta_value, true );
					}
					if ( 'keywords' === $meta_key ) {
						$this->insert_keywords( $link_id, null, $meta_value, $meta_key, true );
					}
				}
			}
			return $link_id;
		}
		return;
	}

	public function insert_click_data( $item ) {
		if ( ! empty( $item['short_url'] ) ) {
			$link_id = \BetterLinks\Helper::insert_click( $item );
			return $link_id;
		}
		return;
	}
}
