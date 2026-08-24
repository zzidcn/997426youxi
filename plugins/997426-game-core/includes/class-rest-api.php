<?php
/**
 * 游戏 REST API —— 供小游戏 SDK 上报成绩、读取排行榜。
 *
 * 路由：
 *   POST /wp-json/game997426/v1/score        提交成绩 {game_id, score, nonce}
 *   GET  /wp-json/game997426/v1/leaderboard?game_id=&period=&limit=
 *   GET  /wp-json/game997426/v1/me           当前用户积分+徽章
 *
 * 安全基线（官方插件开发规范）：
 *   - nonce 防 CSRF + 显式参数校验（schema/args）；
 *   - 成绩只接受非负整数且设上限；
 *   - 游客按 IP 限流，登录用户按用户限流，防连发刷分。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAME997426_Rest_Api {

	const NS            = 'game997426/v1';
	const MAX_SCORE     = 100000000; // 分数上限 1 亿。
	const RATE_LIMIT    = 30;        // 每分钟最多提交次数。
	const RATE_WINDOW   = 60;        // 限流窗口秒数。

	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/score',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit_score' ),
				'permission_callback' => array( __CLASS__, 'score_permissions' ),
				'args'                => array(
					'game_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'score'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => array( __CLASS__, 'validate_score' ),
					),
					'nonce'   => array( 'required' => true, 'type' => 'string' ),
					'extra'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'maxLength'         => 191,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/leaderboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_leaderboard' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'game_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'period'  => array( 'type' => 'string', 'default' => 'all', 'enum' => array( 'all', 'day', 'week', 'month' ) ),
					'limit'   => array( 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_me' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** 提交成绩的权限检查：nonce + 限流。 */
	public static function score_permissions( WP_REST_Request $request ) {
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'game997426_submit' ) ) {
			return new WP_Error( 'invalid_nonce', __( '无效的请求凭证', 'game997426' ), array( 'status' => 403 ) );
		}

		if ( ! self::rate_limit_ok() ) {
			return new WP_Error( 'rate_limited', __( '提交过于频繁，请稍后再试', 'game997426' ), array( 'status' => 429 ) );
		}
		return true;
	}

	/** 简易滑动限流：transient 计数器。 */
	private static function rate_limit_ok() {
		$user_id = get_current_user_id();
		$key     = 'g99_rl_' . ( $user_id ? 'u' . $user_id : md5( self::client_ip() ) );

		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	/** 分数校验。 */
	public static function validate_score( $value ) {
		return $value >= 0 && $value <= self::MAX_SCORE;
	}

	/** 提交成绩。 */
	public static function submit_score( WP_REST_Request $request ) {
		$game_id = (int) $request->get_param( 'game_id' );
		if ( 'game' !== get_post_type( $game_id ) ) {
			return new WP_Error( 'invalid_game', __( '游戏不存在', 'game997426' ), array( 'status' => 404 ) );
		}

		$score   = (int) $request->get_param( 'score' );
		$user_id = get_current_user_id();

		$result = GAME997426_Leaderboard::submit( $game_id, $score, $user_id, (string) $request->get_param( 'extra' ) );

		// 游玩次数计数（post meta，后台可排序展示）。
		$plays = (int) get_post_meta( $game_id, '_game997426_plays', true );
		update_post_meta( $game_id, '_game997426_plays', $plays + 1 );

		// 积分规则：新纪录部分按 1% 转化为积分（至少 1 分）。
		$points_awarded = 0;
		if ( $user_id && $score > 0 && $score >= GAME997426_Leaderboard::get_user_best( $game_id, $user_id ) ) {
			$points_awarded = max( 1, (int) floor( $score / 100 ) );
			GAME997426_Points::add( $user_id, $points_awarded, 'new_record', $game_id );
		}

		return rest_ensure_response(
			array(
				'ok'             => true,
				'best'           => $result['best'],
				'rank'           => $result['rank'],
				'points_awarded' => $points_awarded,
				'total_points'   => GAME997426_Points::get( $user_id ),
			)
		);
	}

	/** 排行榜查询。 */
	public static function get_leaderboard( WP_REST_Request $request ) {
		$game_id = (int) $request->get_param( 'game_id' );
		if ( ! $game_id || 'game' !== get_post_type( $game_id ) ) {
			return new WP_Error( 'invalid_game', __( '游戏不存在', 'game997426' ), array( 'status' => 404 ) );
		}

		$cache_key = 'g99_lb_' . $game_id . '_' . $request->get_param( 'period' ) . '_' . (int) $request->get_param( 'limit' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( array( 'rows' => $cached ) );
		}

		$rows = GAME997426_Leaderboard::top(
			$game_id,
			(int) $request->get_param( 'limit' ),
			$request->get_param( 'period' )
		);

		// 排行榜缓存 60 秒：读多写少，显著降低 DB 压力（性能规范）。
		set_transient( $cache_key, $rows, 60 );

		return rest_ensure_response( array( 'rows' => $rows ) );
	}

	/** 当前登录用户信息。 */
	public static function get_me() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return rest_ensure_response( array( 'logged_in' => false ) );
		}
		$user = get_userdata( $user_id );

		$badges = array();
		foreach ( GAME997426_Points::get_badges( $user_id ) as $key => $time ) {
			$defs     = GAME997426_Points::badge_definitions();
			$badges[] = array(
				'key'   => $key,
				'title' => isset( $defs[ $key ] ) ? $defs[ $key ][0] : $key,
				'icon'  => isset( $defs[ $key ] ) ? $defs[ $key ][2] : '🎖️',
				'time'  => $time,
			);
		}

		return rest_ensure_response(
			array(
				'logged_in' => true,
				'name'      => $user->display_name,
				'points'    => GAME997426_Points::get( $user_id ),
				'badges'    => $badges,
			)
		);
	}
}
