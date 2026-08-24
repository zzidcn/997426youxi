<?php
/**
 * 游戏单页：iframe 自适应全屏播放器 + 说明 + 排行榜。
 *
 * 自定义字段：
 *   _game997426_url      游戏文件地址（必填，指向 index.html 或单个 .html）
 *   _game997426_width    设计宽度（可选，默认 960）
 *   _game997426_height   设计高度（可选，默认 640）
 */
get_header();
the_post();

$game_url = esc_url( get_post_meta( get_the_ID(), '_game997426_url', true ) );
$w        = (int) get_post_meta( get_the_ID(), '_game997426_width', true ) ?: 960;
$h        = (int) get_post_meta( get_the_ID(), '_game997426_height', true ) ?: 640;
$ratio    = $h > 0 ? $w / $h : 1.5;
?>
<div class="g99-container">
	<article <?php post_class( 'g99-gamepage' ); ?>>
		<h1 class="g99-game-title"><?php the_title(); ?></h1>

		<?php if ( $game_url ) : ?>
			<div class="g99-player-wrap">
				<div id="g99-player" class="g99-player" style="aspect-ratio:<?php echo esc_attr( $ratio ); ?>;">
					<iframe id="g99-game-frame"
						src="<?php echo esc_url( add_query_arg( 'game_id', get_the_ID(), $game_url ) ); ?>"
						title="<?php the_title_attribute(); ?>"
						allow="fullscreen; autoplay; gamepad; accelerometer; gyroscope"
						allowfullscreen
						loading="lazy"></iframe>
					<button id="g99-fullscreen-btn" class="g99-fs-btn" type="button" title="全屏">⛶ 全屏</button>
				</div>
			</div>
			<p class="g99-controls-hint">
				💻 电脑：方向键 / WASD / 鼠标　📱 手机：触屏滑动与点按
			</p>
		<?php else : ?>
			<p class="g99-muted">⚠️ 尚未配置游戏地址（自定义字段 <code>_game997426_url</code>）。</p>
		<?php endif; ?>

		<div class="g99-game-layout">
			<section class="g99-game-desc">
				<h2>游戏介绍</h2>
				<div class="g99-entry"><?php the_content(); ?></div>
				<?php echo do_shortcode( '[user_points]' ); ?>
			</section>
			<aside class="g99-game-side">
				<?php echo do_shortcode( '[game_leaderboard limit="10" period="all"]' ); ?>
			</aside>
		</div>
	</article>
</div>
<?php
get_footer();
