<?php
/**
 * Plugin Name: 997426 游戏 - 贪吃蛇
 * Description: 贪吃蛇小游戏独立插件。启用后自动创建并发布游戏页面，无需手写短代码。每款游戏独立数据表与排行榜。
 * Version:     1.1.0
 * Author:      997426
 * License:     GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAME_SNAKE_VER', '1.1.0' );

/* 页面 slug 与标题（自动建页用） */
define( 'GAME_SNAKE_PAGE_SLUG', 'game-snake' );
define( 'GAME_SNAKE_PAGE_TITLE', '🐍 贪吃蛇' );

final class GameSnake {

	const TABLE_SUFFIX = 'game997426_snake_scores';
	const NS           = 'game-snake/v1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}
}

/* ============================================================
 * 激活：建表 + 自动创建游戏页面（幂等，重复激活不会重复建页）
 * ============================================================ */
function game_snake_activate() {
	global $wpdb;

	// 1) 数据表。
	$table   = $wpdb->prefix . GameSnake::TABLE_SUFFIX;
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

	// 2) 自动创建并发布游戏页面（已存在则跳过）。
	$existing = get_page_by_path( GAME_SNAKE_PAGE_SLUG, OBJECT, array( 'page' ) );
	if ( ! $existing ) {
		$page_id = wp_insert_post(
			array(
				'post_title'     => GAME_SNAKE_PAGE_TITLE,
				'post_name'      => GAME_SNAKE_PAGE_SLUG,
				'post_content'   => "[game_snake]\n\n🎮 经典贪吃蛇：吃果实变长变快，撞墙或撞到自己即结束。支持电脑键盘与手机触屏，成绩实时计入本游戏排行榜。",
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
			)
		);
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			// 记录页面 ID，停用时据此回收站。
			update_option( 'game_snake_page_id', $page_id );
			set_transient( 'game_snake_just_created', $page_id, 60 );
		}
	} else {
		update_option( 'game_snake_page_id', $existing->ID );
		// 页面若在回收站/草稿则恢复发布。
		if ( 'publish' !== $existing->post_status ) {
			wp_update_post(
				array(
					'ID'          => $existing->ID,
					'post_status' => 'publish',
				)
			);
		}
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'game_snake_activate' );

/** 停用：把自动创建的页面移入回收站（不硬删，用户数据安全）。 */
function game_snake_deactivate() {
	$page_id = get_option( 'game_snake_page_id' );
	if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
		wp_trash_post( $page_id );
	}
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'game_snake_deactivate' );

/** 卸载：删除数据表 + 硬删页面 + 清理 option。 */
function game_snake_uninstall() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . GameSnake::TABLE_SUFFIX ); // phpcs:ignore

	$page_id = get_option( 'game_snake_page_id' );
	if ( $page_id ) {
		wp_delete_post( $page_id, true ); // 强制硬删（含回收站）。
	}
	delete_option( 'game_snake_page_id' );
}
register_uninstall_hook( __FILE__, 'game_snake_uninstall' );

/** 激活后给管理员一个带链接的提示。 */
function game_snake_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$page_id = get_transient( 'game_snake_just_created' );
	if ( ! $page_id ) {
		return;
	}
	delete_transient( 'game_snake_just_created' );
	echo '<div class="notice notice-success is-dismissible"><p>🐍 <strong>贪吃蛇已安装！</strong> 游戏页面已自动创建并发布：<a href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html( get_permalink( $page_id ) ) . '</a></p></div>';
}
add_action( 'admin_notices', 'game_snake_admin_notice' );

/* ============================================================
 * 前端资源 + 短代码
 * ============================================================ */
function game_snake_assets() {
	wp_register_style( 'game-snake', plugins_url( 'assets/game.css', __FILE__ ), array(), GAME_SNAKE_VER );
	wp_register_script( 'game-snake', plugins_url( 'assets/game.js', __FILE__ ), array(), GAME_SNAKE_VER, true );

	wp_localize_script(
		'game-snake',
		'GameSnakeCfg',
		array(
			'restUrl' => esc_url_raw( rest_url( GameSnake::NS ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'userId'  => get_current_user_id(),
			'name'    => is_user_logged_in() ? wp_get_current_user()->display_name : '',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'game_snake_assets' );

function game_snake_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'title' => '🐍 贪吃蛇', 'grid' => 20 ),
		$atts,
		'game_snake'
	);

	wp_enqueue_style( 'game-snake' );
	wp_enqueue_script( 'game-snake' );

	ob_start();
	?>
	<div class="gsnake" data-grid="<?php echo esc_attr( (int) $atts['grid'] ); ?>">
		<h3 class="gsnake-title"><?php echo esc_html( $atts['title'] ); ?></h3>

		<div class="gsnake-hud">
			<div class="gsnake-scores">
				<span>得分 <b id="gsnake-score">0</b></span>
				<span>最高 <b id="gsnake-best">0</b></span>
				<span class="gsnake-me" id="gsnake-me"></span>
			</div>
			<div class="gsnake-btns">
				<button type="button" class="gsnake-iconbtn" id="gsnake-info" title="游戏介绍">❓</button>
				<button type="button" class="gsnake-iconbtn" id="gsnake-fs" title="全屏">⛶</button>
				<button type="button" class="gsnake-iconbtn" id="gsnake-quit" title="结束游戏" style="display:none;">⏹</button>
			</div>
		</div>

		<div class="gsnake-stage">
			<canvas id="gsnake-cv"></canvas>

			<div class="gsnake-overlay" id="gsnake-start">
				<div>
					<p class="gsnake-intro">
						吃果实变长变快，撞墙或撞到自己即结束。<br>
						💻 方向键 / WASD　📱 滑动屏幕
					</p>
					<button type="button" class="gsnake-bigbtn" id="gsnake-go">▶ 开始游戏</button>
					<p class="gsnake-login" id="gsnake-login"></p>
				</div>
			</div>

			<div class="gsnake-overlay gsnake-hidden" id="gsnake-over">
				<div>
					<h4>游戏结束</h4>
					<div class="gsnake-final" id="gsnake-final">0</div>
					<p class="gsnake-result" id="gsnake-result"></p>
					<button type="button" class="gsnake-bigbtn" id="gsnake-retry">🔄 再来一局</button>
				</div>
			</div>

			<div class="gsnake-overlay gsnake-hidden" id="gsnake-modal">
				<div class="gsnake-card">
					<h4>🎮 游戏介绍</h4>
					<ul>
						<li><b>目标：</b>吃红色果实，每颗 +10 分。</li>
						<li><b>加速：</b>每吃一颗速度提升。</li>
						<li><b>失败：</b>撞墙或撞到自己。</li>
						<li><b>电脑：</b>方向键 / WASD，空格开始。</li>
						<li><b>手机：</b>滑动转向。</li>
						<li><b>排行：</b>登录后成绩以昵称上榜；游客按设备记录最高一条。</li>
					</ul>
					<div style="text-align:center;margin-top:12px;">
						<button type="button" class="gsnake-bigbtn" id="gsnake-close" style="padding:10px 30px;">知道了</button>
					</div>
				</div>
			</div>
		</div>

		<div class="gsnake-lb">
			<h4>🏆 本游戏排行榜</h4>
			<ol class="gsnake-lb-list" id="gsnake-lb"><li class="gsnake-lb-empty">加载中…</li></ol>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'game_snake', 'game_snake_shortcode' );

/* ============================================================
 * REST API
 * ============================================================ */
function game_snake_rest() {
	register_rest_route(
		GameSnake::NS,
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
					GameSnake::table(),
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
		GameSnake::NS,
		'/leaderboard',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				global $wpdb;
				$key  = 'gsnake_lb';
				$rows = get_transient( $key );
				if ( false === $rows ) {
					$t    = GameSnake::table();
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
add_action( 'rest_api_init', 'game_snake_rest' );
