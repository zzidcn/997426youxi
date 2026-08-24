<?php
/**
 * 游戏 REST API —— 供小游戏 SDK 上报成绩、读取排行榜。
 *
 * 路由：
 *   POST /wp-json/game997426/v1/score        提交成绩 {game_id, score, nonce}
 *   GET  /wp-json/game997426/v1/leaderboard?game_id=&period=&limit=
 *   GET  /wp-json/game997426/v1/me           当前用户积分+徽章
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAME997426_Rest_Api {

	const NS = 'game997426/v1';

	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/score',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit_score' ),
				'permission_callback' => '__return_true',
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
					),
					'nonce'   => array( 'required' => true, 'type' => 'string' ),
					'extra'   => array( 'type' => 'string', 'default' => '' ),
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
					'limit'   => array( 'type' => 'integer', 'default' => 10, 'sanitize_callback' => 'absint' ),
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

	/** 提交成绩。 */
	public static function submit_score( WP_REST_Request $request ) {
		// nonce 校验（SDK 从页面获取）。
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'game997426_submit' ) ) {
			return new WP_Error( 'invalid_nonce', __( '无效的请求凭证', 'game997426' ), array( 'status' => 403 ) );
		}

		$game_id = (int) $request->get_param( 'game_id' );
		if ( 'game' !== get_post_type( $game_id ) ) {
			return new WP_Error( 'invalid_game', __( '游戏不存在', 'game997426' ), array( 'status' => 404 ) );
		}

		// 防刷：同一用户/游客 IP 单局分数需超过其上一次提交的 10% 以上才计积分（防连点刷分），但成绩照记。
		$score    = (int) $request->get_param( 'score' );
		$user_id  = get_current_user_id();
		$result   = GAME997426_Leaderboard::submit( $game_id, $score, $user_id, (string) $request->get_param( 'extra' ) );

		// 积分规则：新纪录部分按 1% 转化为积分（向下取整，至少 1 分时才发）。
		$points_awarded = 0;
		if ( $user_id && $score > 0 ) {
			$best_before = GAME997426_Leaderboard::get_user_best( $game_id, $user_id );
			$is_new_best = ( $score >= $best_before );
			if ( $is_new_best ) {
				$points_awarded = max( 1, (int) floor( $score / 100 ) );
				GAME997426_Points::add( $user_id, $points_awarded, 'new_record', $game_id );
			}
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
		$rows = GAME997426_Leaderboard::top(
			(int) $request->get_param( 'game_id' ),
			min( 100, max( 1, (int) $request->get_param( 'limit' ) ) ),
			$request->get_param( 'period' )
		);
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
