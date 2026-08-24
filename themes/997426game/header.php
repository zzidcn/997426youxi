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
		<div class="g99-user">
			<?php if ( is_user_logged_in() ) : ?>
				<span class="g99-user-name">👤 <?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
				<?php
				// 积分显示依赖游戏核心插件；未启用时优雅跳过（v2 架构下该插件已废弃）。
				if ( class_exists( 'GAME997426_Points' ) ) {
					echo '<span class="g99-user-points">💎 ' . esc_html( number_format_i18n( GAME997426_Points::get( get_current_user_id() ) ) ) . '</span>';
				}
				?>
				<a class="g99-user-link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">退出</a>
			<?php else : ?>
				<a class="g99-user-link g99-login" href="<?php echo esc_url( wp_login_url( home_url( '/' ) ) ); ?>">登录</a>
				<?php if ( get_option( 'users_can_register' ) ) : ?>
					<a class="g99-user-link g99-register" href="<?php echo esc_url( wp_registration_url() ); ?>">注册</a>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</header>
<main class="g99-main">
