<?php
/**
 * 997426 游戏核心 —— 卸载清理。
 *
 * 仅在 WordPress 卸载插件时运行；删除本插件自建数据，不触碰文章内容。
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 删除自建数据表。
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}game997426_scores" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}game997426_points_log" ); // phpcs:ignore

// 删除用户 meta（积分余额与徽章）。
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('game997426_points', 'game997426_badges')" // phpcs:ignore
);

// 删除 schema 版本记录。
delete_option( 'game997426_schema_version' );
