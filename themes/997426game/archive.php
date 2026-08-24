<?php
/**
 * 归档页（游戏列表 / 分类 / 搜索结果）。
 */
get_header();
?>
<div class="g99-container">
	<header class="g99-archive-head">
		<h1><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<p class="g99-muted"><?php echo esc_html( get_the_archive_description() ?: '全部游戏' ); ?></p>
	</header>
	<?php
	if ( shortcode_exists( 'games_grid' ) ) {
		echo do_shortcode( '[games_grid limit="24"]' );
	} else {
		echo '<p class="g99-muted">请先安装并启用 997426 游戏核心插件。</p>';
	}
	?>
</div>
<?php
get_footer();
