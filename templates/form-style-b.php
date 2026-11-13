<?php
/**
 * 診断フォーム - スタイルB：シンプル（ホワイト）
 * 銀行・士業・不動産系向けの落ち着いたデザイン。
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
<div class="wpd-form wpd-style-b wpd-display-<?php echo esc_attr( $wpd_display_mode ); ?>"
     data-post-id="<?php echo (int) $post_id; ?>"
     data-result-url="<?php echo esc_attr( $result_url ); ?>"
     data-display-mode="<?php echo esc_attr( $wpd_display_mode ); ?>">

	<div class="wpd-b-card">
		<div class="wpd-b-head">
			<span class="wpd-b-eyebrow">DIAGNOSIS</span>
			<div class="wpd-b-title"><?php echo wp_kses_post( $title_text ); ?></div>
			<?php if ( ! empty( $settings['lead_text'] ) ) : ?>
				<p class="wpd-b-lead"><?php echo esc_html( $settings['lead_text'] ); ?></p>
			<?php endif; ?>
			<div class="wpd-b-divider"></div>
		</div>

		<form class="wpd-b-form">
			<?php foreach ( $questions as $idx => $q ) :
				$is_multi = isset( $q['type'] ) && 'multi' === $q['type'];
				?>
				<div class="wpd-b-question wpd-question"
				     data-qid="<?php echo esc_attr( $q['id'] ); ?>"
				     data-type="<?php echo $is_multi ? 'multi' : 'single'; ?>">
					<div class="wpd-b-qhead">
						<span class="wpd-b-qnum"><?php echo (int) ( $idx + 1 ); ?></span>
						<div class="wpd-b-qlabel"><?php echo esc_html( $q['label'] ); ?></div>
					</div>
					<div class="wpd-b-options">
						<?php foreach ( $q['options'] as $op ) : ?>
							<button type="button"
							        class="wpd-opt wpd-b-opt"
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
						<button type="submit" class="wpd-b-submit wpd-step-submit" style="display:none;">
							<?php echo esc_html( $settings['cta_button_text'] ); ?>
						</button>
					</div>
				</div>
			<?php else : ?>
				<div class="wpd-b-submit-wrap">
					<button type="submit" class="wpd-b-submit">
						<?php echo esc_html( $settings['cta_button_text'] ); ?>
					</button>
				</div>
			<?php endif; ?>
		</form>
	</div>
</div>
