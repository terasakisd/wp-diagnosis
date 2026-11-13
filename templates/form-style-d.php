<?php
/**
 * 診断フォーム - スタイルD：クリーンブルー（#006fbb）
 * 企業サイト・保険・通信系向けの清潔感と信頼感のあるデザイン。
 * ブルーのグラデーションヘッダー + 白カード + 青アクセント。
 *
 * @var array  $questions
 * @var array  $settings
 * @var string $title_text
 * @var int    $post_id
 * @var string $result_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wpd_display_mode = isset( $settings['display_mode'] ) && 'step' === $settings['display_mode'] ? 'step' : 'all';
?>
<div class="wpd-form wpd-style-d wpd-display-<?php echo esc_attr( $wpd_display_mode ); ?>"
     data-post-id="<?php echo (int) $post_id; ?>"
     data-result-url="<?php echo esc_attr( $result_url ); ?>"
     data-display-mode="<?php echo esc_attr( $wpd_display_mode ); ?>">

	<div class="wpd-d-card">
		<div class="wpd-d-head">
			<?php if ( ! empty( $settings['lead_text'] ) ) : ?>
				<div class="wpd-d-eyebrow"><?php echo esc_html( $settings['lead_text'] ); ?></div>
			<?php endif; ?>
			<div class="wpd-d-title"><?php echo wp_kses_post( $title_text ); ?></div>
		</div>

		<form class="wpd-d-form">
			<?php foreach ( $questions as $idx => $q ) :
				$is_multi = isset( $q['type'] ) && 'multi' === $q['type'];
				?>
				<div class="wpd-d-question wpd-question"
				     data-qid="<?php echo esc_attr( $q['id'] ); ?>"
				     data-type="<?php echo $is_multi ? 'multi' : 'single'; ?>">
					<div class="wpd-d-qhead">
						<span class="wpd-d-qnum">Q<?php echo (int) ( $idx + 1 ); ?></span>
						<div class="wpd-d-qlabel"><?php echo esc_html( $q['label'] ); ?></div>
					</div>
					<div class="wpd-d-options">
						<?php foreach ( $q['options'] as $op ) : ?>
							<button type="button"
							        class="wpd-opt wpd-d-opt"
							        data-value="<?php echo esc_attr( $op['value'] ); ?>">
								<?php echo esc_html( $op['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<?php if ( $is_multi ) : ?>
						<p class="wpd-hint">※複数選択できます</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<?php if ( 'step' === $wpd_display_mode ) : ?>
				<div class="wpd-step-nav">
					<div class="wpd-step-progress">
						<span class="wpd-step-current">1</span> / <span class="wpd-step-total"><?php echo count( $questions ); ?></span>
					</div>
					<div class="wpd-step-bar"><div class="wpd-step-bar-fill"></div></div>
					<div class="wpd-step-buttons">
						<button type="button" class="wpd-step-prev" disabled>← 戻る</button>
						<button type="button" class="wpd-step-next">次へ →</button>
						<button type="submit" class="wpd-d-submit wpd-step-submit" style="display:none;">
							<?php echo esc_html( $settings['cta_button_text'] ); ?>
						</button>
					</div>
				</div>
			<?php else : ?>
				<div class="wpd-d-submit-wrap">
					<button type="submit" class="wpd-d-submit">
						<?php echo esc_html( $settings['cta_button_text'] ); ?>
					</button>
				</div>
			<?php endif; ?>
		</form>
	</div>
</div>
