<?php
/*
 * Yahoo BOSS API
 * Author: Denis de Bernardy <http://www.mesoconcepts.com>
 * Version: 1.0
*/

/**
 * ysearch
 *
 * @package Yahoo BOSS API
 **/

global $wpdb;
if ( defined('YSEARCH') ) {
	$wpdb->ysearch = YSEARCH;
} else {
	$wpdb->ysearch = 'ysearch'; // share this across blogs by default
}

class ysearch {
	/**
	 * activate()
	 *
	 * @return void
	 **/

	function activate() {
		if ( !function_exists('dbDelta') ) {
			include ABSPATH . '/wp-admin/includes/upgrade.php';
		}
		
		global $wpdb;
		$charset_collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			if ( ! empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty($wpdb->collate) )
				$charset_collate .= " COLLATE $wpdb->collate";
		}
		
		dbDelta("
		CREATE TABLE ysearch (
			id			char(32) PRIMARY KEY,
			expires		datetime NOT NULL,
			response	text NOT NULL DEFAULT '',
			INDEX ysearch_expires ( expires )
		) $charset_collate;");
	} # activate()
	
	
	/**
	 * query()
	 *
	 * @param string $appid BOSS API Key
	 * @param string $s search query
	 * @param int $start start result
	 * @return array of results
	 **/

	function query($s, $start = 0) {
		$app_id = get_option('ysearch');
		
		if ( !$app_id ) return false;
		
		$url = 'http://boss.yahooapis.com/ysearch/web/v1/';
		$url .= rawurlencode($s);
		
		$url .= '?count=10';
		$url .= '&appid=' . rawurlencode($appid);
		$url .= '&start=' . intval($start);
		$url .= '&abstract=long';
		$url .= '&format=xml';
		
		$cache_id = md5($url);
		
		if ( $xml = ysearch::get_cache($cache_id) ) {
			$res = new SimpleXMLElement($xml);
		} else {
			$xml = wp_remote_fopen($url);
			
			if ( !$xml ) {
				return false;
			}
			
			try {
				$res = @ new SimpleXMLElement($xml);
			} catch ( Exception $e ) {
				return false;
			}

			if ( $res->attributes()->responsecode != 200 ) {
				return false;
			}
		}
		
		$res = $res->resultset_web;
		
		if ( !intval($res->attributes()->totalhits) ) {
			ysearch::set_cache($cache_id, time() + 86400, $xml); # 1 day
			return false;
		} else {
			ysearch::set_cache($cache_id, time() + 256200, $xml); # 3 days
			return $res;
		}
	} # query()
	
	
	/**
	 * get_cache()
	 *
	 * @param string $cache_id
	 * @return mixed $result string on cache hit, else string
	 **/

	function get_cache($cache_id) {
		global $wpdb;
		
		$res = $wpdb->get_row("
			SELECT	*
			FROM	$wpdb->ysearch
			WHERE	id = '" . $wpdb->escape($cache_id) . "'
			");
		
		if ( !$res )
			return false;
		
		if ( strtotime($res->expires) < time() ) {
			$wpdb->query("
				DELETE FROM $wpdb->ysearch
				WHERE	expires < '" . $wpdb->escape(date('Y-m-d H:i:s')) . "'
				");
			return false;
		} else {
			return $res->response;
		}
	} # get_cache()
	
	
	/**
	 * set_cache()
	 *
	 * @param string $cache_id
	 * @param timestamp $expires
	 * @param string $xml
	 * @return void
	 **/

	function set_cache($cache_id, $expires, $xml) {
		global $wpdb;
		
		$wpdb->query("
			INSERT INTO $wpdb->ysearch (
				id,
				expires,
				response
				)
			VALUES (
				'" . $wpdb->escape($cache_id) . "',
				'" . $wpdb->escape(date('Y-m-d H:i:s', $expires)) . "',
				'" . $wpdb->escape($xml) . "'
				);
			");
	} # set_cache()
} # ysearch
?>