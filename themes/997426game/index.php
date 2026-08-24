<?php
/**
 * 首页：最新游戏 + 分类导航。
 *
 * 兼容降级：997426-game-core 插件未启用时显示提示而非报错。
 */
get_header();

$plugin_active = class_exists( 'GAME997426_Leaderboard' ) && shortcode_exists( 'games_grid' );
?>
<div class="g99-container">
	<section class="g99-hero">
		<h1>🎮 997426小游戏</h1>
		<p>即点即玩 · 电脑手机全支持 · 全站积分排行</p>
	</section>

	<?php if ( ! $plugin_active ) : ?>
		<div class="g99-notice">
			<p><strong>⚠️ 平台核心插件未启用</strong></p>
			<p>请到「插件 → 安装插件 → 上传」安装 <code>997426-game-core-plugin.zip</code> 并激活，然后刷新本页。</p>
			<p>下载地址：<a href="https://github.com/zzidcn/997426youxi/releases/latest" target="_blank" rel="noopener">GitHub Releases</a></p>
		</div>

		<!-- 未启用插件时兜底展示普通页面列表 -->
		<section>
			<h2 class="g99-section-title">最新内容</h2>
			<div class="g99-grid">
				<?php
				$fallback = new WP_Query(
					array(
						'post_type'      => 'any',
						'post_status'    => 'publish',
						'posts_per_page' => 8,
						'no_found_rows'  => true,
					)
				);
				while ( $fallback->have_posts() ) :
					$fallback->the_post();
					?>
					<a class="g99-card" href="<?php the_permalink(); ?>">
						<div class="g99-card-body">
							<h3 class="g99-card-title"><?php the_title(); ?></h3>
						</div>
					</a>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php else : ?>
		<section>
			<h2 class="g99-section-title">热门游戏</h2>
			<?php
			if ( shortcode_exists( 'games_grid' ) ) {
				echo do_shortcode( '[games_grid limit="12"]' );
			} else {
				echo '<p class="g99-muted">安装游戏插件后，这里会展示游戏列表。已装游戏可直接访问其页面（如 /game-snake/）。</p>';
			}
			?>
		</section>

		<section>
			<h2 class="g99-section-title">🏆 全站玩家荣誉榜</h2>
			<?php
			// 依赖游戏核心插件；未启用时显示占位（v2 架构下各游戏自带榜单）。
			if ( class_exists( 'GAME997426_Leaderboard' ) ) {
				$top = GAME997426_Leaderboard::site_top_players( 10 );
				if ( $top ) :
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
				<?php endif;
			} else {
				echo '<p class="g99-muted">各游戏排行榜请见对应游戏页面。</p>';
			}
			?>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
