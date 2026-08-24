<?php
/**
 * 997426 Game Platform
 *
 * @package theme997426
 */

/*
Theme Name: 997426 Game Platform
Theme URI: https://github.com/zzidcn/997426youxi
Author: 997426
Author URI: https://github.com/zzidcn
Description: 997426小游戏平台主题 —— 响应式游戏门户，含游戏列表、分类、单页游戏播放器（自适应全屏）、排行榜与积分荣誉展示。配合 997426 游戏核心插件使用。
Version: 2.2.1
Requires at least: 6.0
Requires PHP: 7.4
License: GPL-2.0-or-later
Text Domain: game997426
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function theme997426_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	register_nav_menus(
		array(
			'primary' => __( '主菜单', 'game997426' ),
			'footer'  => __( '页脚菜单', 'game997426' ),
		)
	);
}
add_action( 'after_setup_theme', 'theme997426_setup' );

function theme997426_assets() {
	// REST API 预连接：游戏页 SDK 首次请求更快（性能规范 resource hints）。
	$host = parse_url( home_url(), PHP_URL_HOST );
	if ( $host ) {
		printf(
			'<link rel="preconnect" href="%s" crossorigin>' . "\n",
			esc_url( 'https://' . $host )
		);
	}

	wp_enqueue_style(
		'theme997426',
		get_stylesheet_directory_uri() . '/assets/css/main.css',
		array(),
		'2.2.1'
	);
	wp_enqueue_script(
		'theme997426',
		get_stylesheet_directory_uri() . '/assets/js/main.js',
		array(),
		'2.2.1',
		true
	);

	// 给游戏 iframe 注入配置（供 SDK 代理使用）。
	// 注意：登录用户的 nonce 因人而异，页面必须禁止 CDN/浏览器缓存，
	// 否则 A 用户的 nonce 被缓存后 B 用户使用会 403。
	if ( is_singular( 'game' ) ) {
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		wp_localize_script(
			'theme997426',
			'Game997426Config',
			array(
				'restUrl' => esc_url_raw( rest_url( 'game997426/v1' ) ),
				'nonce'   => wp_create_nonce( 'game997426_submit' ),
				'userId'  => get_current_user_id(),
			)
		);
		wp_add_inline_script( 'theme997426', 'window.Game997426GameId=' . get_queried_object_id() . ';', 'before' );
	}
}
add_action( 'wp_enqueue_scripts', 'theme997426_assets' );

/** 游戏单页模板包含。 */
function theme997426_game_template( $template ) {
	if ( is_singular( 'game' ) ) {
		$custom = locate_template( array( 'single-game.php' ) );
		if ( $custom ) {
			return $custom;
		}
	}
	return $template;
}
add_filter( 'single_template', 'theme997426_game_template' );
