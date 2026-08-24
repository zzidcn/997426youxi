<?php
/**
 * 游戏编辑 metabox：游戏地址与尺寸可视化配置。
 * 替代手工填写自定义字段。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 注册 metabox。 */
function game997426_add_meta_box() {
	add_meta_box(
		'game997426_settings',
		__( '🎮 游戏设置（必填）', 'game997426' ),
		'game997426_render_meta_box',
		'game',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'game997426_add_meta_box' );

/** 渲染表单。 */
function game997426_render_meta_box( $post ) {
	wp_nonce_field( 'game997426_meta', 'game997426_meta_nonce' );

	$url   = get_post_meta( $post->ID, 'game997426_url', true );
	$width = get_post_meta( $post->ID, 'game997426_width', true );
	$height= get_post_meta( $post->ID, 'game997426_height', true );
	?>
	<style>
		.g99-mb-row { margin-bottom: 14px; }
		.g99-mb-row label { display: block; font-weight: 600; margin-bottom: 4px; }
		.g99-mb-row input[type="url"], .g99-mb-row input[type="number"] {
			width: 100%; max-width: 560px;
		}
		.g99-mb-desc { color: #666; margin: 4px 0 0; font-size: 12px; }
	</style>
	<div class="g99-mb-row">
		<label for="game997426_url"><?php esc_html_e( '游戏地址 (index.html 完整 URL)', 'game997426' ); ?></label>
		<input type="url" id="game997426_url" name="game997426_url"
			value="<?php echo esc_attr( $url ); ?>"
			placeholder="https://example.com/games/2048/index.html" class="widefat">
		<p class="g99-mb-desc">例如：https://你的域名/games/2048/index.html —— 留空则前台显示"尚未配置"。</p>
	</div>
	<div style="display:flex; gap:24px;">
		<div class="g99-mb-row">
			<label for="game997426_width"><?php esc_html_e( '设计宽度（可选，默认 960）', 'game997426' ); ?></label>
			<input type="number" id="game997426_width" name="game997426_width"
				value="<?php echo esc_attr( $width ); ?>" min="200" max="4096">
		</div>
		<div class="g99-mb-row">
			<label for="game997426_height"><?php esc_html_e( '设计高度（可选，默认 640）', 'game997426' ); ?></label>
			<input type="number" id="game997426_height" name="game997426_height"
				value="<?php echo esc_attr( $height ); ?>" min="150" max="4096">
		</div>
	</div>
	<?php
}

/** 保存。 */
function game997426_save_meta( $post_id ) {
	if ( ! isset( $_POST['game997426_meta_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_POST['game997426_meta_nonce'] ), 'game997426_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'game997426_url'    => 'esc_url_raw',
		'game997426_width'  => 'absint',
		'game997426_height' => 'absint',
	);
	foreach ( $fields as $key => $sanitize ) {
		if ( isset( $_POST[ $key ] ) ) {
			$value = call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) );
			if ( '' === $value || 0 === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}
}
add_action( 'save_post_game', 'game997426_save_meta' );
