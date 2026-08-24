<?php
/**
 * 全站积分荣誉系统。
 *
 * - 积分 (points)：可累计的通用货币，玩游戏、上榜均可获得。
 * - 荣誉 (badges)：达成条件后授予的徽章，存为 user_meta。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAME997426_Points {

	const META_POINTS = 'game997426_points';
	const META_BADGES = 'game997426_badges';

	/** 积分日志表。 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'game997426_points_log';
	}

	public static function create_table() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$wpdb->prefix}game997426_points_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			points BIGINT NOT NULL DEFAULT 0,
			reason VARCHAR(64) NOT NULL DEFAULT '',
			ref_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_time (user_id, created_at),
			KEY reason (reason)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * 增减积分（正数加、负数减）。
	 *
	 * @return int 用户当前积分总额。
	 */
	public static function add( $user_id, $points, $reason = '', $ref_id = 0 ) {
		global $wpdb;
		if ( ! $user_id || ! $points ) {
			return self::get( $user_id );
		}

		$wpdb->insert(
			self::table(),
			array(
				'user_id'    => (int) $user_id,
				'points'     => (int) $points,
				'reason'     => sanitize_key( $reason ),
				'ref_id'     => (int) $ref_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		// user_meta 存当前余额（快速读取）。
		$current = (int) get_user_meta( $user_id, self::META_POINTS, true );
		$current += (int) $points;
		update_user_meta( $user_id, self::META_POINTS, max( 0, $current ) );

		// 积分变动后检查荣誉。
		self::check_badges( $user_id );

		return max( 0, $current );
	}

	/** 查询用户积分。 */
	public static function get( $user_id ) {
		if ( ! $user_id ) {
			return 0;
		}
		return (int) get_user_meta( $user_id, self::META_POINTS, true );
	}

	/** 授予徽章。返回是否为新获得。 */
	public static function award_badge( $user_id, $badge ) {
		$badges = get_user_meta( $user_id, self::META_BADGES, true );
		$badges = is_array( $badges ) ? $badges : array();
		if ( isset( $badges[ $badge ] ) ) {
			return false;
		}
		$badges[ $badge ] = current_time( 'mysql' );
		update_user_meta( $user_id, self::META_BADGES, $badges );
		/**
		 * 徽章获得时触发。
		 */
		do_action( 'game997426_badge_awarded', $user_id, $badge );
		return true;
	}

	/** 用户全部徽章。 */
	public static function get_badges( $user_id ) {
		$badges = get_user_meta( $user_id, self::META_BADGES, true );
		return is_array( $badges ) ? $badges : array();
	}

	/** 徽章定义表（名称 => [标题, 描述, 图标 emoji]）。 */
	public static function badge_definitions() {
		return apply_filters(
			'game997426_badge_definitions',
			array(
				'first_game'    => array( __( '初出茅庐', 'game997426' ), __( '第一次提交游戏成绩', 'game997426' ), '🌱' ),
				'score_1000'    => array( __( '千分俱乐部', 'game997426' ), __( '单局分数达到 1000', 'game997426' ), '🎯' ),
				'score_10000'   => array( __( '万分传奇', 'game997426' ), __( '单局分数达到 10000', 'game997426' ), '👑' ),
				'top1_any'      => array( __( '登顶王者', 'game997426' ), __( '在任意游戏排行榜排名第一', 'game997426' ), '🏆' ),
				'play_10_games' => array( __( '十项全能', 'game997426' ), __( '玩过 10 款不同游戏', 'game997426' ), '🎮' ),
				'points_5000'   => array( __( '积分大亨', 'game997426' ), __( '累计积分达到 5000', 'game997426' ), '💎' ),
			)
		);
	}

	/** 根据积分与成绩自动检查并授予荣誉。 */
	public static function check_badges( $user_id ) {
		global $wpdb;

		if ( ! $user_id ) {
			return;
		}
		$score_table = GAME997426_Leaderboard::table();

		// 有成绩记录 → 初出茅庐.
		$has_score = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$score_table} WHERE user_id = %d", $user_id ) // phpcs:ignore
		);
		if ( $has_score > 0 ) {
			self::award_badge( $user_id, 'first_game' );
		}

		// 最高分成就.
		$best = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT MAX(score) FROM {$score_table} WHERE user_id = %d", $user_id ) // phpcs:ignore
		);
		if ( $best >= 1000 ) {
			self::award_badge( $user_id, 'score_1000' );
		}
		if ( $best >= 10000 ) {
			self::award_badge( $user_id, 'score_10000' );
		}

		// 是否拿过第一.
		$top1 = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT game_id, user_id, MAX(score) ms FROM {$score_table} WHERE user_id > 0 GROUP BY game_id
				) x WHERE x.user_id = %d AND x.ms = (
					SELECT MAX(score) FROM {$score_table} t2 WHERE t2.game_id = x.game_id
				)", // phpcs:ignore
				$user_id
			)
		);
		if ( $top1 > 0 ) {
			self::award_badge( $user_id, 'top1_any' );
		}

		// 玩过的游戏数.
		$games = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(DISTINCT game_id) FROM {$score_table} WHERE user_id = %d", $user_id ) // phpcs:ignore
		);
		if ( $games >= 10 ) {
			self::award_badge( $user_id, 'play_10_games' );
		}

		// 积分里程碑.
		if ( self::get( $user_id ) >= 5000 ) {
			self::award_badge( $user_id, 'points_5000' );
		}
	}
}
