<?php
/**
 * Plugin Name: 997426 游戏核心 (Game Core)
 * Plugin URI:  https://github.com/zzidcn/997426youxi
 * Description: 997426小游戏平台底座 —— 游戏自定义文章类型(CPT)、统一游戏排行榜、全站积分荣誉系统、游戏数据上报 REST API。
 * Version:     1.1.0
 * Author:      997426
 * License:     GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: game997426
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAME997426_VERSION', '1.2.3' );
/**
 * 数据库 schema 版本：结构变更时递增，激活/升级时自动迁移。
 * v2: scores 表新增 ip_hash 列（游客按 IP 聚合）。
 */
define( 'GAME997426_SCHEMA_VERSION', '2' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-game-cpt.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-leaderboard.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-points.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-rest-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-meta-box.php';

/**
 * 激活与升级时执行 schema 迁移（幂等）。
 */
function game997426_migrate_schema() {
	$installed = get_option( 'game997426_schema_version', '0' );
	if ( GAME997426_SCHEMA_VERSION === $installed ) {
		return;
	}

	GAME997426_Leaderboard::create_table();
	GAME997426_Points::create_table();

	update_option( 'game997426_schema_version', GAME997426_SCHEMA_VERSION, false );
}
// 激活钩子必须注册于主文件顶层（官方规范）。
register_activation_hook( __FILE__, 'game997426_migrate_schema' );

/**
 * 版本升级路径：插件更新后自动补跑迁移。
 */
function game997426_maybe_upgrade() {
	if ( GAME997426_SCHEMA_VERSION !== get_option( 'game997426_schema_version', '0' ) ) {
		game997426_migrate_schema();
		flush_rewrite_rules();
	}
}
add_action( 'admin_init', 'game997426_maybe_upgrade' );

function game997426_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'game997426_deactivate' );

// 初始化各模块。
add_action( 'init', array( 'GAME997426_Game_CPT', 'register_post_types' ) );
add_action( 'rest_api_init', array( 'GAME997426_Rest_Api', 'register_routes' ) );
add_action( 'wp_enqueue_scripts', array( 'GAME997426_Shortcodes', 'register_assets' ) );
