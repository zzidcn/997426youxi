<?php
/**
 * 首页：最新游戏 + 分类导航。
 */
get_header();
?>
<div class="g99-container">
	<section class="g99-hero">
		<h1>🎮 997426小游戏</h1>
		<p>即点即玩 · 电脑手机全支持 · 全站积分排行</p>
	</section>

	<section>
		<h2 class="g99-section-title">热门游戏</h2>
		<?php echo do_shortcode( '[games_grid limit="12"]' ); ?>
	</section>

	<section>
		<h2 class="g99-section-title">🏆 全站玩家荣誉榜</h2>
		<?php
		$top = GAME997426_Leaderboard::site_top_players( 10 );
		if ( $top && class_exists( 'GAME997426_Leaderboard' ) ) :
			?>
			<ol class="g99-lb-list g99-site-lb">
				<?php foreach ( $top as $i => $row ) : ?>
					<li class="g99-lb-row <?php echo $i < 3 ? 'g99-top' . ( (int) $i + 1 ) : ''; ?>">
						<span class="g99-lb-rank"><?php echo (int) $i + 1; ?></span>
						<span class="g99-lb-name"><?php echo esc_html( $row->user_name ); ?></span>
						<span class="g99-lb-score">💎 <?php echo number_format_i18n( (int) $row->total_score ); ?> · <?php echo (int) $row->games_played; ?> 款游戏</span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<p class="g99-muted">暂无数据，玩一局就能上榜！</p>
		<?php endif; ?>
	</section>
</div>
<?php
get_footer();
