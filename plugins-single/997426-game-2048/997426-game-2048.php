<?php
/**
 * Plugin Name: 997426 游戏 - 2048
 * Description: 2048 小游戏独立插件。启用后自动创建并发布游戏页面，自动接入游戏大厅与全站积分排行。
 * Version:     1.0.0
 * Author:      997426
 * License:     GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAME_2048_VER', '1.0.0' );
define( 'GAME_2048_PAGE_SLUG', 'game-2048' );
define( 'GAME_2048_PAGE_TITLE', '🔢 2048' );

final class Game2048 {

	const TABLE_SUFFIX = 'game997426_2048_scores';
	const NS           = 'game-2048/v1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}
}

/* ============================================================
 * 激活：建表 + 自动建页 + 大厅注册
 * ============================================================ */
function game_2048_activate() {
	global $wpdb;

	$table   = $wpdb->prefix . Game2048::TABLE_SUFFIX;
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		user_name VARCHAR(60) NOT NULL DEFAULT '',
		ip_hash VARCHAR(32) NOT NULL DEFAULT '',
		score BIGINT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY score (score DESC),
		KEY created (created_at)
	) {$charset};" );

	$existing = get_page_by_path( GAME_2048_PAGE_SLUG, OBJECT, array( 'page' ) );
	if ( ! $existing ) {
		$page_id = wp_insert_post(
			array(
				'post_title'     => GAME_2048_PAGE_TITLE,
				'post_name'      => GAME_2048_PAGE_SLUG,
				'post_content'   => "[game_2048]\n\n🔢 经典 2048：滑动合并相同数字，挑战合成 2048！支持电脑键盘与手机触屏，成绩实时计入本游戏排行榜。",
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
			)
		);
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'game_2048_page_id', $page_id );
			set_transient( 'game_2048_just_created', $page_id, 60 );
		}
	} else {
		update_option( 'game_2048_page_id', $existing->ID );
		$page_id = $existing->ID;
		if ( 'publish' !== $existing->post_status ) {
			wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
		}
	}

	if ( function_exists( 'game997426_hub_register' ) ) {
		game997426_hub_register(
			array(
				'slug'   => GAME_2048_PAGE_SLUG,
				'title'  => GAME_2048_PAGE_TITLE,
				'url'    => get_permalink( $page_id ),
				'icon'   => '🔢',
				'desc'   => '滑动合并数字，挑战 2048！',
				'plugin' => plugin_basename( __FILE__ ),
			)
		);
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'game_2048_activate' );

function game_2048_deactivate() {
	$page_id = get_option( 'game_2048_page_id' );
	if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
		wp_trash_post( $page_id );
	}
	if ( function_exists( 'game997426_hub_unregister' ) ) {
		game997426_hub_unregister( GAME_2048_PAGE_SLUG );
	}
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'game_2048_deactivate' );

function game_2048_uninstall() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . Game2048::TABLE_SUFFIX ); // phpcs:ignore
	$page_id = get_option( 'game_2048_page_id' );
	if ( $page_id ) {
		wp_delete_post( $page_id, true );
	}
	delete_option( 'game_2048_page_id' );
}
register_uninstall_hook( __FILE__, 'game_2048_uninstall' );

function game_2048_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$page_id = get_transient( 'game_2048_just_created' );
	if ( ! $page_id ) {
		return;
	}
	delete_transient( 'game_2048_just_created' );
	echo '<div class="notice notice-success is-dismissible"><p>🔢 <strong>2048 已安装！</strong> 游戏页面已自动创建并发布：<a href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html( get_permalink( $page_id ) ) . '</a></p></div>';
}
add_action( 'admin_notices', 'game_2048_admin_notice' );

/* ============================================================
 * 前端资源 + 短代码
 * ============================================================ */
function game_2048_assets() {
	wp_register_style( 'game-2048', plugins_url( 'assets/game.css', __FILE__ ), array(), GAME_2048_VER );
	wp_register_script( 'game-2048', plugins_url( 'assets/game.js', __FILE__ ), array(), GAME_2048_VER, true );

	wp_localize_script(
		'game-2048',
		'Game2048Cfg',
		array(
			'restUrl' => esc_url_raw( rest_url( Game2048::NS ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'userId'  => get_current_user_id(),
			'name'    => is_user_logged_in() ? wp_get_current_user()->display_name : '',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'game_2048_assets' );

function game_2048_shortcode( $atts ) {
	wp_enqueue_style( 'game-2048' );
	wp_enqueue_script( 'game-2048' );

	ob_start();
	?>
	<div class="g2048">
		<h3 class="g2048-title">🔢 2048</h3>

		<div class="g2048-hud">
			<div class="g2048-scores">
				<span>得分 <b id="g2048-score">0</b></span>
				<span>最高 <b id="g2048-best">0</b></span>
				<span class="g2048-me" id="g2048-me"></span>
			</div>
			<div class="g2048-btns">
				<button type="button" class="g2048-iconbtn" id="g2048-info" title="游戏介绍">❓</button>
				<button type="button" class="g2048-iconbtn" id="g2048-fs" title="全屏">⛶</button>
				<button type="button" class="g2048-iconbtn" id="g2048-quit" title="结束游戏" style="display:none;">⏹</button>
			</div>
		</div>

		<div class="g2048-stage">
			<div class="g2048-grid" id="g2048-grid"></div>

			<div class="g2048-overlay" id="g2048-start">
				<div>
					<p class="g2048-intro">
						滑动合并相同数字，挑战 2048！<br>
						💻 方向键 / WASD　📱 滑动屏幕
					</p>
					<button type="button" class="g2048-bigbtn" id="g2048-go">▶ 开始游戏</button>
					<p class="g2048-login" id="g2048-login"></p>
				</div>
			</div>

			<div class="g2048-overlay g2048-hidden" id="g2048-over">
				<div>
					<h4>游戏结束</h4>
					<div class="g2048-final" id="g2048-final">0</div>
					<p class="g2048-result" id="g2048-result"></p>
					<button type="button" class="g2048-bigbtn" id="g2048-retry">🔄 再来一局</button>
				</div>
			</div>

			<div class="g2048-overlay g2048-hidden" id="g2048-modal">
				<div class="g2048-card">
					<h4>🎮 游戏介绍</h4>
					<ul>
						<li><b>目标：</b>相同数字碰撞合并翻倍，合成 2048。</li>
						<li><b>加分：</b>每次合并获得对应数字的分。</li>
						<li><b>失败：</b>棋盘填满且无法合并。</li>
						<li><b>电脑：</b>方向键 / WASD，空格开始。</li>
						<li><b>手机：</b>滑动屏幕移动方块。</li>
						<li><b>排行：</b>登录后以昵称上榜；游客按设备记最高一条。</li>
					</ul>
					<div style="text-align:center;margin-top:12px;">
						<button type="button" class="g2048-bigbtn" id="g2048-close" style="padding:10px 30px;">知道了</button>
					</div>
				</div>
			</div>
		</div>

		<div class="g2048-lb">
			<h4>🏆 本游戏排行榜</h4>
			<ol class="g2048-lb-list" id="g2048-lb"><li class="g2048-lb-empty">加载中…</li></ol>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'game_2048', 'game_2048_shortcode' );

/* ============================================================
 * REST API
 * ============================================================ */
function game_2048_rest() {
	register_rest_route(
		Game2048::NS,
		'/score',
		array(
			'methods'             => 'POST',
			'permission_callback' => function ( $req ) {
				return wp_verify_nonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );
			},
			'callback'            => function ( WP_REST_Request $req ) {
				global $wpdb;
				$score   = absint( $req['score'] );
				$user_id = get_current_user_id();

				if ( ! $score ) {
					return new WP_Error( 'bad_score', '无效分数', array( 'status' => 400 ) );
				}

				$user_name = '游客';
				$ip_hash   = '';
				if ( $user_id ) {
					$u         = get_userdata( $user_id );
					$user_name = $u ? $u->display_name : '玩家';
				} else {
					$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '0'; // phpcs:ignore
					$ip_hash = md5( $ip . wp_salt( 'auth' ) );
				}

				$wpdb->insert(
					Game2048::table(),
					array(
						'user_id'    => $user_id,
						'user_name'  => $user_name,
						'ip_hash'    => $ip_hash,
						'score'      => $score,
						'created_at' => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%d', '%s' )
				);

				return rest_ensure_response( array( 'ok' => true ) );
			},
		)
	);

	register_rest_route(
		Game2048::NS,
		'/leaderboard',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				global $wpdb;
				$key  = 'g2048_lb';
				$rows = get_transient( $key );
				if ( false === $rows ) {
					$t    = Game2048::table();
					$rows = $wpdb->get_results(
						"SELECT uid_key, user_id, user_name, score FROM (
							SELECT CASE WHEN user_id > 0 THEN CONCAT('u', user_id) ELSE CONCAT('g', SUBSTRING(ip_hash,1,8)) END AS uid_key,
							       MAX(user_id) AS user_id,
							       MAX(user_name) AS user_name,
							       MAX(score) AS score
							FROM {$t}
							GROUP BY uid_key
						) agg
						ORDER BY score DESC LIMIT 10"
					); // phpcs:ignore
					set_transient( $key, $rows, 60 );
				}
				return rest_ensure_response( array( 'rows' => $rows ) );
			},
		)
	);
}
add_action( 'rest_api_init', 'game_2048_rest' );
