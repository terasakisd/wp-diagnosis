<?php
/**
 * 結果ページ
 *
 * @var array $items         絞り込み済みの結果アイテム
 * @var array $result_fields 表示項目定義 [{key,label},...]
 * @var array $answers
 * @var array $settings
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$wpd_custom_css   = isset( $settings['result_custom_css'] ) ? trim( $settings['result_custom_css'] ) : '';
$wpd_result_style = isset( $settings['result_style'] ) && in_array( $settings['result_style'], array( 'A', 'C', 'D' ), true )
	? $settings['result_style']
	: 'A';
if ( '' !== $wpd_custom_css ) :
	// サニタイズは保存時にwp_strip_all_tagsで実施済み。<style>内に出力するため改めてエスケープしない。
	?>
	<style id="wpd-result-custom-css"><?php echo $wpd_custom_css; ?></style>
<?php endif; ?>
<div class="wpd-result-wrap wpd-result-style-<?php echo esc_attr( $wpd_result_style ); ?>">

	<?php if ( empty( $items ) ) : ?>
		<div class="wpd-empty-result">
			<p>条件に一致する結果が見つかりませんでした。<br>条件を変えて再度診断してみてください。</p>
		</div>
	<?php else : ?>

		<?php foreach ( $items as $idx => $item ) :
			$rank      = $idx + 1;
			$banner    = ! empty( $item['banner_url'] ) ? $item['banner_url'] : '';
			$detail    = ! empty( $item['detail_url'] ) ? $item['detail_url'] : '#';
			$catch     = ! empty( $item['catch'] ) ? $item['catch'] : '';
			$banner_id = ! empty( $item['banner_id'] ) ? $item['banner_id'] : '';
			$cta_id    = ! empty( $item['cta_id'] ) ? $item['cta_id'] : '';
			?>
			<article class="wpd-card-result wpd-rank-<?php echo (int) $rank; ?>">
				<div class="wpd-card-result-head">
					<span class="wpd-rank-badge"><?php echo (int) $rank; ?>位</span>
					<span class="wpd-pr-tag">【PR】<?php echo esc_html( $item['name'] ); ?></span>
				</div>

				<?php if ( $catch ) : ?>
					<p class="wpd-catch"><?php echo esc_html( $catch ); ?></p>
				<?php endif; ?>

				<?php if ( $banner ) : ?>
					<a href="<?php echo esc_url( $detail ); ?>" class="wpd-banner"<?php if ( $banner_id ) : ?> id="<?php echo esc_attr( $banner_id ); ?>"<?php endif; ?>>
						<img src="<?php echo esc_url( $banner ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>のバナー" loading="lazy">
					</a>
				<?php endif; ?>

				<?php
				// 表示順を「単一行フィールドを先に、複数行(textarea)フィールドを後に」並べ替える。
				// こうしないと、単一行フィールドの間にtextareaが挟まると4列グリッドの最終行に
				// 空きセル（グレーの余白）ができてレイアウトが崩れるため。
				$_wpd_single_fields = array();
				$_wpd_multi_fields  = array();
				foreach ( $result_fields as $f ) {
					if ( isset( $f['type'] ) && 'textarea' === $f['type'] ) {
						$_wpd_multi_fields[] = $f;
					} else {
						$_wpd_single_fields[] = $f;
					}
				}
				$_wpd_sorted_fields = array_merge( $_wpd_single_fields, $_wpd_multi_fields );

				// グリッド列数は単一行フィールド数で決定。
				$single_line_count = count( $_wpd_single_fields );
				$col_class = $single_line_count > 0 ? $single_line_count : 1;
				?>
				<?php if ( ! empty( $_wpd_sorted_fields ) ) : ?>
					<div class="wpd-spec wpd-spec-cols-<?php echo (int) $col_class; ?>">
						<?php foreach ( $_wpd_sorted_fields as $field ) :
							$val = isset( $item['fields'][ $field['key'] ] ) ? $item['fields'][ $field['key'] ] : '';
							if ( '' === $val ) continue;
							$is_textarea = isset( $field['type'] ) && 'textarea' === $field['type'];
							$item_classes = 'wpd-spec-item' . ( $is_textarea ? ' wpd-spec-item-multiline' : '' );
							?>
							<div class="<?php echo esc_attr( $item_classes ); ?>">
								<dt><?php echo esc_html( $field['label'] ); ?></dt>
								<dd><?php
									// 値に改行が含まれる場合のみ <br> へ変換（単一行はそのまま）。
									// 申込条件のような textarea 型はもちろん、text 型でも値に改行を含むケース
									// （例：横浜銀行の金利「年1.5〜14.6％\n（変動金利）」）に対応する。
									echo nl2br( esc_html( $val ) );
								?></dd>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $item['points'] ) && is_array( $item['points'] ) ) : ?>
					<div class="wpd-points">
						<div class="wpd-points-title"><?php echo esc_html( $item['name'] ); ?>のおすすめポイント</div>
						<ul>
							<?php foreach ( $item['points'] as $point ) : ?>
								<li><?php echo esc_html( $point ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div class="wpd-cta">
					<a href="<?php echo esc_url( $detail ); ?>" class="wpd-cta-btn"<?php if ( $cta_id ) : ?> id="<?php echo esc_attr( $cta_id ); ?>"<?php endif; ?>>
						<?php echo esc_html( $settings['item_button_text'] ); ?>
					</a>
				</div>
			</article>
		<?php endforeach; ?>

	<?php endif; ?>
</div>
