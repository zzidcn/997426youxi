<?php
/**
 * 首页：游戏大厅入口 + 全站积分排行（聚合各游戏插件数据表）。
 *
 * v2 架构：游戏即插件，每款游戏有自己的成绩表
 *   wp_game997426_<slug>_scores (user_id, user_name, ip_hash, score, ...)
 * 本页聚合所有匹配该结构的数据表，取每人总积分前 10 名。
 */
get_header();

global $wpdb;

/**
 * 聚合所有 997426 游戏成绩表的积分排行。
 * 积分规则与游戏内一致：每人每游戏最高分的 1%。
 */
function theme997426_site_top( $limit = 10 ) {
	global $wpdb;
	$tables = $wpdb->get_col(
		"SHOW TABLES LIKE '{$wpdb->prefix}game997426_%_scores'"
	);
	if ( empty( $tables ) ) {
		return array();
	}

	$union = array();
	foreach ( $tables as $t ) {
		// 每表先按人（登录）或设备（游客 ip_hash）取最高分。
		$union[] = "SELECT CASE WHEN user_id > 0 THEN CONCAT('u', user_id) ELSE CONCAT('g', SUBSTRING(ip_hash,1,8)) END AS uid_key,
			MAX(user_id) AS user_id,
			MAX(CASE WHEN user_id > 0 THEN user_name ELSE '游客' END) AS user_name,
			FLOOR(MAX(score)/100) AS pts,
			MAX(score) AS best_score
			FROM {$t} GROUP BY uid_key";
	}

	$sub = implode( ' UNION ALL ', $union );
	// 再跨游戏汇总总积分，取前 N。
	$sql = "SELECT uid_key, MAX(user_name) AS user_name,
			SUM(pts) AS total_pts,
			COUNT(*) AS games,
			MAX(best_score) AS top_score
		FROM ( {$sub} ) allgames
		GROUP BY uid_key
		HAVING total_pts > 0
		ORDER BY total_pts DESC
		LIMIT %d";

	return $wpdb->get_results( $wpdb->prepare( $sql, $limit ) ); // phpcs:ignore
}

/** 收集已注册的游戏入口（来自大厅注册表），用于"挑战"跳转。 */
function theme997426_game_links() {
	$registry = get_option( 'game997426_hub_registry', array() );
	$links    = array();
	foreach ( $registry as $slug => $g ) {
		if ( ! empty( $g['plugin'] ) && ! function_exists( 'is_plugin_active' ) ) {
			continue;
		}
		if ( ! empty( $g['plugin'] ) && is_plugin_active( $g['plugin'] ) ) {
			$links[] = $g;
		}
	}
	return $links;
}

$site_top = function_exists( 'theme997426_site_top' ) ? theme997426_site_top( 10 ) : array();
$hub_url  = get_permalink( get_page_by_path( 'games-hub', OBJECT, array( 'page' ) ) );
?>
<div class="g99-container g99-home">
	<section class="g99-hero">
		<h1>🎮 997426小游戏</h1>
		<p>即点即玩 · 电脑手机全支持 · 全站积分排行</p>
		<?php if ( $hub_url ) : ?>
			<p class="g99-hero-actions">
				<a class="g99-hero-btn" href="<?php echo esc_url( $hub_url ); ?>">🎮 进入游戏大厅</a>
			</p>
		<?php endif; ?>
	</section>

	<section class="g99-home-section">
		<h2 class="g99-section-title">🏆 全站积分排行 TOP10</h2>
		<?php if ( ! empty( $site_top ) ) : ?>
			<ol class="g99-lb-list g99-site-lb g99-rank-table">
				<?php foreach ( $site_top as $i => $row ) : ?>
					<li class="g99-lb-row <?php echo $i < 3 ? 'g99-top' . ( (int) $i + 1 ) : ''; ?>">
						<span class="g99-lb-rank"><?php echo (int) $i + 1; ?></span>
						<span class="g99-lb-name">
							<?php echo esc_html( $row->user_name ); ?>
							<small class="g99-lb-sub"><?php echo (int) $row->games; ?> 款游戏 · 单局最高 <?php echo number_format_i18n( (int) $row->top_score ); ?></small>
						</span>
						<span class="g99-lb-score">💎 <?php echo number_format_i18n( (int) $row->total_pts ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
			<p class="g99-muted g99-rule-note">积分 = 各游戏个人最高分 ÷ 100 的总和</p>
		<?php else : ?>
			<p class="g99-muted">暂无积分记录——进入任意游戏玩一局即可上榜！</p>
		<?php endif; ?>
	</section>

	<section class="g99-home-section">
		<h2 class="g99-section-title">🎯 挑战记录 · 选择一款游戏开战</h2>
		<div class="g99-grid g99-challenge-grid">
			<?php
			$games = function_exists( 'theme997426_game_links' ) ? theme997426_game_links() : array();
			if ( empty( $games ) ) {
				echo '<p class="g99-muted">安装游戏插件后自动出现在这里。前往 <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">插件管理</a> 上传游戏插件。</p>';
			}
			foreach ( $games as $g ) :
				?>
				<a class="g99-card g99-challenge-card" href="<?php echo esc_url( $g['url'] ); ?>">
					<div class="g99-challenge-icon"><?php echo esc_html( $g['icon'] ); ?></div>
					<div class="g99-card-body">
						<h3 class="g99-card-title"><?php echo esc_html( preg_replace( '/^[^\w\s\x{4e00}-\x{9fa5}]+/u', '', $g['title'] ) ); ?></h3>
						<div class="g99-card-meta"><?php echo esc_html( $g['desc'] ); ?></div>
						<span class="g99-challenge-btn">⚡ 挑战记录</span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
</div>
<?php
get_footer();
