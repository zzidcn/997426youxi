<?php
/**
 * 游戏归档页（/game/、游戏分类、标签、搜索）。
 */
get_header();
?>
<div class="g99-container">
	<header class="g99-archive-head">
		<h1><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<p class="g99-muted"><?php echo esc_html( get_the_archive_description() ?: '全部游戏，点击即玩' ); ?></p>
	</header>

	<div class="g99-grid">
		<?php
		// 主查询分页版（归档页需要分页，不能用短代码缓存版）。
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				$plays = (int) get_post_meta( get_the_ID(), '_game997426_plays', true );
				?>
				<a class="g99-card" href="<?php the_permalink(); ?>">
					<div class="g99-card-img">
						<?php
						if ( has_post_thumbnail() ) {
							the_post_thumbnail( 'medium', array( 'class' => 'g99-card-thumb', 'loading' => 'lazy' ) );
						} else {
							echo '<div class="g99-card-placeholder">🎮</div>';
						}
						?>
					</div>
					<div class="g99-card-body">
						<h3 class="g99-card-title"><?php the_title(); ?></h3>
						<div class="g99-card-meta">
							<span><?php echo $plays ? esc_html( number_format_i18n( $plays ) . ' 次游玩' ) : esc_html__( '新游戏', 'game997426' ); ?></span>
						</div>
					</div>
				</a>
				<?php
			endwhile;
		else :
			echo '<p class="g99-muted">没有找到游戏。</p>';
		endif;
		?>
	</div>

	<nav class="g99-pagination">
		<?php
		the_posts_pagination(
			array(
				'mid_size'  => 2,
				'prev_text' => '←',
				'next_text' => '→',
			)
		);
		?>
	</nav>
</div>
<?php
get_footer();
