<?php
/**
 * 短代码与前端资源。
 *
 * 短代码：
 *   [games_grid limit="12" category=""]        游戏卡片网格
 *   [game_leaderboard game_id="" limit="10" period="all"] 排行榜
 *   [user_points]                              当前用户积分/徽章面板
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAME997426_Shortcodes {

	/** 注册短代码并加载 SDK 脚本。 */
	public static function register_assets() {
		wp_register_script(
			'game997426-sdk',
			plugins_url( 'assets/js/game997426-sdk.js', dirname( __FILE__ ) ),
			array(),
			GAME997426_VERSION,
			true
		);
		wp_localize_script(
			'game997426-sdk',
			'Game997426Config',
			array(
				'restUrl' => esc_url_raw( rest_url( 'game997426/v1' ) ),
				'nonce'   => wp_create_nonce( 'game997426_submit' ),
				'userId'  => get_current_user_id(),
			)
		);
	}

	/** 游戏卡片网格。 */
	public static function games_grid( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => 12,
				'category' => '',
			),
			$atts,
			'games_grid'
		);

		$args = array(
			'post_type'      => 'game',
			'posts_per_page' => (int) $atts['limit'],
			'post_status'    => 'publish',
			'no_found_rows'  => true, // 无需分页计数，省一次 COUNT 查询。
		);
		if ( $atts['category'] ) {
			$args['tax_query'] = array( // phpcs:ignore
				array(
					'taxonomy' => 'game_category',
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['category'] ),
				),
			);
		}

		// 卡片网格缓存 5 分钟（后台保存游戏时自动失效）。
		$cache_key = 'g99_grid_' . md5( (string) wp_json_encode( $args ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$games = new WP_Query( $args );

		ob_start();
		echo '<div class="g99-grid">';
		while ( $games->have_posts() ) {
			$games->the_post();
			$thumb = get_the_post_thumbnail( get_the_ID(), 'medium', array( 'class' => 'g99-card-thumb' ) );
			?>
			<a class="g99-card" href="<?php echo esc_url( get_permalink() ); ?>">
				<div class="g99-card-img">
					<?php
					if ( $thumb ) {
						echo $thumb; // phpcs:ignore
					} else {
						echo '<div class="g99-card-placeholder">🎮</div>';
					}
					?>
				</div>
				<div class="g99-card-body">
					<h3 class="g99-card-title"><?php the_title(); ?></h3>
					<div class="g99-card-meta">
						<span><?php echo esc_html( get_post_meta( get_the_ID(), 'game997426_plays', true ) ? (int) get_post_meta( get_the_ID(), 'game997426_plays', true ) . ' 次游玩' : '新游戏' ); ?></span>
					</div>
				</div>
			</a>
			<?php
		}
		echo '</div>';
		wp_reset_postdata();
		$html = ob_get_clean();

		set_transient( $cache_key, $html, 5 * MINUTE_IN_SECONDS );
		return $html;
	}

	/** 排行榜。 */
	public static function leaderboard( $atts ) {
		$atts = shortcode_atts(
			array(
				'game_id' => 0,
				'limit'   => 10,
				'period'  => 'all',
			),
			$atts,
			'game_leaderboard'
		);

		global $post;
		$game_id = (int) ( $atts['game_id'] ? $atts['game_id'] : ( $post ? $post->ID : 0 ) );
		if ( ! $game_id ) {
			return '';
		}

		$rows = GAME997426_Leaderboard::top( $game_id, (int) $atts['limit'], $atts['period'] );

		ob_start();
		?>
		<div class="g99-leaderboard" data-game-id="<?php echo esc_attr( $game_id ); ?>" data-period="<?php echo esc_attr( $atts['period'] ); ?>">
			<h3 class="g99-lb-title">🏆 排行榜</h3>
			<ol class="g99-lb-list">
				<?php if ( empty( $rows ) ) : ?>
					<li class="g99-lb-empty">暂无成绩，快来抢占第一吧！</li>
				<?php else : ?>
					<?php foreach ( $rows as $i => $row ) : ?>
						<li class="g99-lb-row <?php echo $i < 3 ? 'g99-top' . ( (int) $i + 1 ) : ''; ?>">
							<span class="g99-lb-rank"><?php echo (int) $i + 1; ?></span>
							<span class="g99-lb-name"><?php echo esc_html( $row->user_name ?: '游客' ); ?></span>
							<span class="g99-lb-score"><?php echo number_format_i18n( (int) $row->score ); ?></span>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ol>
		</div>
		<?php
		return ob_get_clean();
	}

	/** 用户积分面板。 */
	public static function user_points() {
		$user_id = get_current_user_id();
		ob_start();
		?>
		<div class="g99-userpanel">
		<?php if ( ! $user_id ) : ?>
			<p>👋 <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">登录</a> 后成绩计入排行榜并获得积分荣誉。</p>
		<?php else : ?>
			<div class="g99-userpanel-head">
				<strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong>
				<span class="g99-points">💎 <?php echo number_format_i18n( GAME997426_Points::get( $user_id ) ); ?> 积分</span>
			</div>
			<div class="g99-badges">
				<?php foreach ( GAME997426_Points::get_badges( $user_id ) as $key => $time ) : ?>
					<?php $defs = GAME997426_Points::badge_definitions(); ?>
					<span class="g99-badge" title="<?php echo esc_attr( isset( $defs[ $key ][1] ) ? $defs[ $key ][1] : $key ); ?>">
						<?php echo esc_html( isset( $defs[ $key ][2] ) ? $defs[ $key ][2] : '🎖️' ); ?> <?php echo esc_html( isset( $defs[ $key ][0] ) ? $defs[ $key ][0] : $key ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

add_shortcode( 'games_grid', array( 'GAME997426_Shortcodes', 'games_grid' ) );
add_shortcode( 'game_leaderboard', array( 'GAME997426_Shortcodes', 'leaderboard' ) );
add_shortcode( 'user_points', array( 'GAME997426_Shortcodes', 'user_points' ) );

/**
 * 保存/更新游戏时清空相关缓存（保持卡片与游玩次数即时刷新）。
 */
function game997426_flush_caches( $post_id ) {
	if ( 'game' !== get_post_type( $post_id ) ) {
		return;
	}
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_g99_grid_%' OR option_name LIKE '_transient_timeout_g99_grid_%'" // phpcs:ignore
	);
}
add_action( 'save_post', 'game997426_flush_caches' );
