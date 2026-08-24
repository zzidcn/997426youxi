<?php
/**
 * 游戏自定义文章类型与分类法。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAME997426_Game_CPT {

	/**
	 * 注册 game CPT 及其分类法。
	 */
	public static function register_post_types() {
		register_post_type(
			'game',
			array(
				'labels'        => array(
					'name'          => __( '游戏', 'game997426' ),
					'singular_name' => __( '游戏', 'game997426' ),
					'add_new_item'  => __( '上架新游戏', 'game997426' ),
					'edit_item'     => __( '编辑游戏', 'game997426' ),
				),
				'public'        => true,
				'has_archive'   => true,
				'menu_icon'     => 'dashicons-games',
				'rewrite'       => array( 'slug' => 'game' ),
				'supports'      => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'excerpt' ),
				'show_in_rest'  => true,
			)
		);

		register_taxonomy(
			'game_category',
			'game',
			array(
				'labels'       => array(
					'name'          => __( '游戏分类', 'game997426' ),
					'singular_name' => __( '游戏分类', 'game997426' ),
				),
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'game-category' ),
			)
		);

		register_taxonomy(
			'game_tag',
			'game',
			array(
				'labels'       => array(
					'name'          => __( '游戏标签', 'game997426' ),
					'singular_name' => __( '游戏标签', 'game997426' ),
				),
				'hierarchical' => false,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'game-tag' ),
			)
		);
	}
}
