<?php
/**
 * 搜索结果页（仅搜索游戏）。
 *
 * 修复：此前无 search.php，搜索结果回落到 index.php（首页），
 * 导致显示"平台核心插件未启用"提示或与搜索无关的内容。
 */
get_header();

global $wp_query;
$found = (int) $wp_query->found_posts;
$query = get_search_query();
?>
<div class="g99-container g99-home">
	<header class="g99-archive-head" style="text-align:center;margin:32px 0 8px;">
		<h1>
			<?php
			if ( $query ) {
				printf( '🔍「%s」的搜索结果', esc_html( $query ) );
			} else {
				echo '🔍 搜索';
			}
			?>
		</h1>
		<p class="g99-muted"><?php echo number_format_i18n( $found ); ?> 个结果</p>

		<form class="g99-search g99-search-inline" role="search" method="get" style="margin-top:14px;" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="搜索游戏…" value="<?php echo esc_attr( $query ); ?>">
			<input type="hidden" name="post_type" value="game">
		</form>
	</header>

	<section style="margin-top:16px;">
		<div class="g99-grid g99-challenge-grid">
			<?php
			if ( have_posts() ) :
				while ( have_posts() ) :
					the_post();
					$plays = (int) get_post_meta( get_the_ID(), 'game997426_plays', true );
					?>
					<a class="g99-card g99-challenge-card" href="<?php the_permalink(); ?>">
						<div class="g99-challenge-icon">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'thumbnail', array( 'style' => 'width:56px;height:56px;border-radius:12px;object-fit:cover;' ) ); ?>
							<?php else : ?>
								🎮
							<?php endif; ?>
						</div>
						<div class="g99-card-body">
							<h3 class="g99-card-title"><?php the_title(); ?></h3>
							<div class="g99-card-meta">
								<?php echo $plays ? esc_html( number_format_i18n( $plays ) . ' 次游玩' ) : esc_html__( '新游戏', 'game997426' ); ?>
							</div>
							<span class="g99-challenge-btn">▶ 进入游戏</span>
						</div>
					</a>
					<?php
				endwhile;
			else :
				echo '<p class="g99-muted">没有找到与「' . esc_html( $query ) . '」相关的游戏。<br>试试其他关键词，或去<a href="' . esc_url( get_permalink( get_page_by_path( 'games-hub' ) ) ) . '">游戏大厅</a>逛逛。</p>';
			endif;
			?>
		</div>
	</section>

	<nav class="g99-pagination">
		<?php the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => '←', 'next_text' => '→' ) ); ?>
	</nav>
</div>
<?php
get_footer();
