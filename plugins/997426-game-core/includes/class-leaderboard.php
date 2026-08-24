<?php
/**
 * 统一游戏排行榜（自定义数据表，跨游戏通用）。
 *
 * 表结构：每条记录 = 某用户(或游客)在某游戏的一次成绩。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAME997426_Leaderboard {

	const TABLE_SUFFIX = 'game997426_scores';

	/** 获取带前缀的完整表名。 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/** 激活建表。 */
	public static function create_table() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			game_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_name VARCHAR(60) NOT NULL DEFAULT '',
			ip_hash VARCHAR(32) NOT NULL DEFAULT '',
			score BIGINT NOT NULL DEFAULT 0,
			extra VARCHAR(191) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY game_score (game_id, score DESC),
			KEY user_game (user_id, game_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * 提交一次成绩。
	 *
	 * @param int    $game_id  游戏 post ID.
	 * @param int    $score    分数.
	 * @param int    $user_id  用户 ID，0 表示游客.
	 * @param string $extra    附加信息 JSON 字符串.
	 * @return array {best:int, rank:int}
	 */
	public static function submit( $game_id, $score, $user_id = 0, $extra = '' ) {
		global $wpdb;

		$user_name = '';
		$ip_hash   = '';
		if ( $user_id ) {
			$user      = get_userdata( $user_id );
			$user_name = $user ? $user->display_name : '';
		} else {
			$user_name = __( '游客', 'game997426' );
			$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
			$ip_hash   = md5( $ip . wp_salt( 'auth' ) );
		}

		$wpdb->insert(
			self::table(),
			array(
				'game_id'    => (int) $game_id,
				'user_id'    => (int) $user_id,
				'user_name'  => sanitize_text_field( $user_name ),
				'ip_hash'    => $ip_hash,
				'score'      => (int) $score,
				'extra'      => sanitize_text_field( $extra ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return array(
			'best' => self::get_user_best( $game_id, $user_id ),
			'rank' => self::get_score_rank( $game_id, (int) $score ),
		);
	}

	/** 指定游戏的排行榜（每用户/游客取最高分）。 */
	public static function top( $game_id, $limit = 10, $period = 'all' ) {
		global $wpdb;
		$table  = self::table();
		$where  = 'WHERE game_id = %d';
		$params = array( (int) $game_id );

		if ( 'day' === $period ) {
			$where   .= ' AND created_at >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );
		} elseif ( 'week' === $period ) {
			$where   .= ' AND created_at >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( '-1 week' ) );
		} elseif ( 'month' === $period ) {
			$where   .= ' AND created_at >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) );
		}

		// 每个用户/游客取最高分：登录用户按 user_id 聚合；
		// 游客（user_id=0）按 IP 哈希聚合，同一游客只留最高一条。
		$sql = "SELECT * FROM (
				SELECT CASE WHEN user_id > 0 THEN CONCAT('u', user_id) ELSE CONCAT('g', SUBSTRING(ip_hash, 1, 8)) END AS uid_key,
				       MAX(user_id) AS user_id,
				       MAX(CASE WHEN user_id > 0 THEN user_name ELSE '游客' END) AS user_name,
				       MAX(score) AS score
				FROM {$table}
				{$where}
				GROUP BY uid_key
			) agg
			ORDER BY score DESC
			LIMIT %d";
		array_unshift( $params, (int) $game_id );
		$params[] = (int) $limit;

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ); // phpcs:ignore
	}

	/** 用户在某游戏的最高分。 */
	public static function get_user_best( $game_id, $user_id ) {
		global $wpdb;
		if ( ! $user_id ) {
			return 0;
		}
		$table = self::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(score) FROM {$table} WHERE game_id = %d AND user_id = %d", // phpcs:ignore
				$game_id,
				$user_id
			)
		);
	}

	/** 某分数在游戏内的名次。 */
	public static function get_score_rank( $game_id, $score ) {
		global $wpdb;
		$table = self::table();
		$higher = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT COALESCE(NULLIF(user_id,0), -id)) FROM {$table} WHERE game_id = %d AND score > %d", // phpcs:ignore
				$game_id,
				$score
			)
		);
		return $higher + 1;
	}

	/** 全站总榜（所有游戏分数合计），用于积分荣誉联动展示。 */
	public static function site_top_players( $limit = 10 ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, user_name, SUM(score) AS total_score, COUNT(DISTINCT game_id) AS games_played
				FROM {$table} WHERE user_id > 0
				GROUP BY user_id, user_name
				ORDER BY total_score DESC LIMIT %d", // phpcs:ignore
				$limit
			)
		);
	}
}
