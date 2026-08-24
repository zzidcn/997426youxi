<?php
/**
 * 主题头部。
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="g99-header">
	<div class="g99-container g99-header-inner">
		<a class="g99-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">🎮 <span>997426</span>小游戏</a>
		<nav class="g99-nav">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'depth'          => 1,
					)
				);
			}
			?>
		</nav>
		<form class="g99-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="搜索游戏…" value="<?php echo esc_attr( get_search_query() ); ?>">
			<input type="hidden" name="post_type" value="game">
		</form>
	</div>
</header>
<main class="g99-main">
