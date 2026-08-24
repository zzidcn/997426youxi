<?php
/**
 * 普通页面 / 兜底模板。
 */
get_header();
?>
<div class="g99-container">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<h1 class="g99-page-title"><?php the_title(); ?></h1>
			<div class="g99-entry"><?php the_content(); ?></div>
		</article>
		<?php
	endwhile;
	?>
</div>
<?php
get_footer();
