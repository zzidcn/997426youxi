<?php
/**
 * Plugin Name: 997426 游戏大厅
 * Description: 自动收集所有已安装的 997426 系列游戏插件，生成游戏大厅页面与短代码。用法：[game_hub] 或访问 /games-hub/。
 * Version:     1.0.0
 * Author:      997426
 * License:     GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAME_HUB_VER', '1.0.0' );
define( 'GAME_HUB_PAGE_SLUG', 'games-hub' );
define( 'GAME_HUB_PAGE_TITLE', '🎮 游戏大厅' );

/* ============================================================
 * 注册表（共享 option）：游戏插件通过函数注册/注销自己
 * ============================================================ */

/** 供各游戏插件激活时调用（snake 插件已内置调用）。 */
function game997426_hub_register( $game ) {
	$defaults = array(
		'slug'   => '',
		'title'  => '',
		'url'    => '',
		'icon'   => '🎮',
		'desc'   => '',
		'plugin' => '',
	);
	$game     = wp_parse_args( $game, $defaults );
	if ( ! $game['slug'] || ! $game['url'] ) {
		return;
	}
	$registry         = get_option( 'game997426_hub_registry', array() );
	$registry[ $game['slug'] ] = $game;
	update_option( 'game997426_hub_registry', $registry, false );
}

/** 供各游戏插件停用/卸载时调用。 */
function game997426_hub_unregister( $slug ) {
	$registry = get_option( 'game997426_hub_registry', array() );
	if ( isset( $registry[ $slug ] ) ) {
		unset( $registry[ $slug ] );
		update_option( 'game997426_hub_registry', $registry, false );
	}
}

/**
 * 兜底校验：只显示对应插件仍处于启用状态的条目
 * （防止手动停用插件后残留死链）。
 */
function game997426_hub_active_games() {
	$registry = get_option( 'game997426_hub_registry', array() );
	$active   = array();
	foreach ( $registry as $slug => $g ) {
		if ( ! empty( $g['plugin'] ) && is_plugin_active( $g['plugin'] ) && 'publish' === get_post_status( get_page_by_path( $slug, OBJECT, array( 'page' ) ) ) ) {
			$active[] = $g;
		}
	}
	return $active;
}

/* ============================================================
 * 激活：自动创建大厅页面
 * ============================================================ */
function game_hub_activate() {
	$existing = get_page_by_path( GAME_HUB_PAGE_SLUG, OBJECT, array( 'page' ) );
	if ( ! $existing ) {
		$page_id = wp_insert_post(
			array(
				'post_title'     => GAME_HUB_PAGE_TITLE,
				'post_name'      => GAME_HUB_PAGE_SLUG,
				'post_content'   => "[game_hub]\n\n全部游戏都在这里，点击即玩。",
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
			)
		);
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'game_hub_page_id', $page_id );
			set_transient( 'game_hub_just_created', $page_id, 60 );
		}
	} else {
		update_option( 'game_hub_page_id', $existing->ID );
		if ( 'publish' !== $existing->post_status ) {
			wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
		}
	}
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'game_hub_activate' );

function game_hub_deactivate() {
	$page_id = get_option( 'game_hub_page_id' );
	if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
		wp_trash_post( $page_id );
	}
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'game_hub_deactivate' );

function game_hub_uninstall() {
	$page_id = get_option( 'game_hub_page_id' );
	if ( $page_id ) {
		wp_delete_post( $page_id, true );
	}
	delete_option( 'game_hub_page_id' );
	delete_option( 'game997426_hub_registry' );
}
register_uninstall_hook( __FILE__, 'game_hub_uninstall' );

function game_hub_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$page_id = get_transient( 'game_hub_just_created' );
	if ( ! $page_id ) {
		return;
	}
	delete_transient( 'game_hub_just_created' );
	echo '<div class="notice notice-success is-dismissible"><p>🎮 <strong>游戏大厅已创建：</strong><a href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html( get_permalink( $page_id ) ) . '</a> —— 之后每安装一个游戏插件都会自动出现在这里。</p></div>';
}
add_action( 'admin_notices', 'game_hub_admin_notice' );

/* ============================================================
 * 短代码 [game_hub]
 * ============================================================ */
function game_hub_shortcode() {
	wp_enqueue_style(
		'game-hub',
		plugins_url( 'assets/hub.css', __FILE__ ),
		array(),
		GAME_HUB_VER
	);

	$games = game997426_hub_active_games();

	ob_start();
	echo '<div class="ghub"><h3 class="ghub-title">🎮 游戏大厅</h3>';
	if ( empty( $games ) ) {
		echo '<p class="ghub-empty">还没有已安装的游戏。上传并启用任意「997426 游戏-xxx」系列插件即可自动出现在这里。</p>';
	} else {
		echo '<div class="ghub-grid">';
		foreach ( $games as $g ) {
			printf(
				'<a class="ghub-card" href="%s"><div class="ghub-icon">%s</div><div class="ghub-body"><div class="ghub-name">%s</div><div class="ghub-desc">%s</div></div></a>',
				esc_url( $g['url'] ),
				esc_html( $g['icon'] ),
				esc_html( preg_replace( '/^[^\w\s\x{4e00}-\x{9fa5}]+/u', '', $g['title'] ) ), // 去掉标题前缀 emoji.
				esc_html( $g['desc'] )
			);
		}
		echo '</div>';
	}
	echo '</div>';
	return ob_get_clean();
}
add_shortcode( 'game_hub', 'game_hub_shortcode' );
