<?php
/**
 * Plugin Name: 997426 游戏核心 (Game Core)
 * Plugin URI:  https://github.com/zzidcn/997426小游戏
 * Description: 997426小游戏平台底座 —— 游戏自定义文章类型(CPT)、统一游戏排行榜、全站积分荣誉系统、游戏数据上报 REST API。
 * Version:     1.0.0
 * Author:      997426
 * License:     GPL-2.0+
 * Text Domain: game997426
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAME997426_VERSION', '1.0.0' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-game-cpt.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-leaderboard.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-points.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-rest-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-shortcodes.php';

/**
 * 激活时建表。
 */
function game997426_activate() {
	GAME997426_Leaderboard::create_table();
	GAME997426_Points::create_table();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'game997426_activate' );

function game997426_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'game997426_deactivate' );

// 初始化各模块.
add_action( 'init', array( 'GAME997426_Game_CPT', 'register_post_types' ) );
add_action( 'rest_api_init', array( 'GAME997426_Rest_Api', 'register_routes' ) );
add_action( 'wp_enqueue_scripts', array( 'GAME997426_Shortcodes', 'register_assets' ) );
