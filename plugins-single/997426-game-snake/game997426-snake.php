<?php
/**
 * Plugin Name: 997426 游戏 - 贪吃蛇
 * Description: 贪吃蛇小游戏独立插件。用法：在任意文章/页面写短代码 [game_snake]。自带独立排行榜（本插件专属数据表）。
 * Version:     1.0.0
 * Author:      997426
 * License:     GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAME_SNAKE_VER', '1.0.0' );

/* ============================================================
 * 数据表：本游戏专属排行榜（每用户/IP 取最高分）
 * ============================================================ */
final class GameSnake {

	const TABLE_SUFFIX = 'game997426_snake_scores';
	const NS           = 'game-snake/v1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function activate() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$this_table} (
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
	}
}
// dbDelta 需要真实表名变量。
function game_snake_activate() {
	global $wpdb;
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
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'game_snake_activate' );

function game_snake_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'game_snake_deactivate' );

/** 卸载时删除本游戏数据表。 */
function game_snake_uninstall() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . GameSnake::TABLE_SUFFIX ); // phpcs:ignore
}
register_uninstall_hook( __FILE__, 'game_snake_uninstall' );

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
 * REST API（本插件命名空间）
 * ============================================================ */
function game_snake_rest( $r ) {
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
