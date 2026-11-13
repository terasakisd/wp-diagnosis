<?php
/**
 * 管理画面：診断の編集画面にタブ式メタボックスを追加
 *
 * タブ:
 *   1. 基本設定（タイトル文言・デザインスタイル・結果ページ・ボタン文言）
 *   2. 質問項目（質問・選択肢をフォームUIで編集）
 *   3. 表示項目（結果カードに出す項目を定義：金利、限度額、保険料、etc.）
 *   4. 結果アイテム（金融機関・保険会社など、診断の結果として推薦するもの）
 *   5. 除外ルール（Q◯で◯を選んだら◯◯を非表示）
 *   6. 並び替えルール（Q◯で◯を選んだら指定順で先頭に表示）
 *   7. ショートコード（コピペ用）
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPD_Admin {

	const NONCE_ACTION = 'wpd_save_meta';
	const NONCE_FIELD  = 'wpd_meta_nonce';

	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . WPD_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . WPD_POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . WPD_POST_TYPE . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
	}

	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== WPD_POST_TYPE ) {
			return;
		}
		wp_enqueue_style( 'wpd-admin', WPD_URL . 'assets/css/admin.css', array(), WPD_VERSION );
		wp_enqueue_script( 'wpd-admin', WPD_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), WPD_VERSION, true );
	}

	public function admin_columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( $k === 'title' ) {
				$new['wpd_shortcode'] = 'フォーム用';
				$new['wpd_shortcode_result'] = '結果用';
			}
		}
		return $new;
	}

	public function admin_column_content( $col, $post_id ) {
		if ( $col === 'wpd_shortcode' ) {
			echo '<code>[wpd_diagnosis id="' . esc_html( $post_id ) . '"]</code>';
		}
		if ( $col === 'wpd_shortcode_result' ) {
			echo '<code>[wpd_diagnosis_result id="' . esc_html( $post_id ) . '"]</code>';
		}
	}

	public function add_meta_boxes() {
		add_meta_box(
			'wpd_diagnosis_editor',
			'診断の設定',
			array( $this, 'render_meta_box' ),
			WPD_POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$questions = WPD_Data::get_questions( $post->ID );
		$fields    = WPD_Data::get_result_fields( $post->ID );
		$items     = WPD_Data::get_result_items( $post->ID );
		$exclusions= WPD_Data::get_exclusions( $post->ID );
		$reorders  = WPD_Data::get_reorders( $post->ID );
		$settings  = WPD_Data::get_settings( $post->ID );

		// データをJSONとしてJSに渡す（フォーム入力UI用）
		$json_data = wp_json_encode( array(
			'questions'  => $questions,
			'fields'     => $fields,
			'items'      => $items,
			'exclusions' => $exclusions,
			'reorders'   => $reorders,
		), JSON_UNESCAPED_UNICODE );

		?>
		<div class="wpd-editor">

			<div class="wpd-editor-toolbar">
				<button type="button" class="button wpd-import-sample">
					＋ サンプルをインポート
				</button>
				<button type="button" class="button wpd-toggle-json">
					&lt;/&gt; JSONで入出力
				</button>
				<span class="wpd-editor-toolbar-hint">サンプル投入 / JSON貼り付け / 別の診断にコピーするためのエクスポートができます。</span>
			</div>

			<div class="wpd-json-panel" style="display:none;">
				<p class="description">
					<strong>JSON入出力：</strong>診断の内容（基本設定・質問・表示項目・結果アイテム・除外ルール・並び替えルール）を JSON で一括投入／取り出しできます。<br>
					「現在の内容を読み込む」で現在の状態をテキストエリアに出力、編集して「JSONを反映」で適用します。<br>
					JSONファイルは下のエリアに<strong>ドラッグ&amp;ドロップ</strong>、または「ファイルを選択」から読み込めます。<br>
					<strong>反映後に「更新」ボタンを押すまでは保存されません。</strong>
				</p>
				<div class="wpd-json-actions">
					<button type="button" class="button wpd-json-load">現在の内容を読み込む</button>
					<button type="button" class="button wpd-json-download">現在の内容をダウンロード</button>
					<button type="button" class="button wpd-json-pick">ファイルを選択…</button>
					<button type="button" class="button wpd-json-apply button-primary">JSONを反映</button>
					<button type="button" class="button-link wpd-json-clear">クリア</button>
					<input type="file" class="wpd-json-file-input" accept="application/json,.json" hidden>
					<span class="wpd-json-status" aria-live="polite"></span>
				</div>
				<div class="wpd-json-dropzone">
					<textarea class="wpd-json-textarea" rows="14" spellcheck="false" placeholder='ここに JSON ファイルをドラッグ&ドロップ、または貼り付けてください

{
  "settings": { "title_text": "...", "lead_text": "...", "cta_button_text": "...", "item_button_text": "..." },
  "questions": [ { "id": "q1", "label": "...", "type": "single", "options": [ {"value":"v","label":"l"} ] } ],
  "fields":    [ { "key": "rate", "label": "金利", "type": "text" } ],
  "items":     [ { "id": "acom", "name": "アコム", "order": 10, "fields": {"rate":"..."}, "points": ["..."] } ],
  "exclusions":[ { "question_id": "q1", "value": "4plus", "exclude": ["yokohama"] } ],
  "reorders":  [ { "question_id": "q2", "value": "must_avoid", "order": ["acom","lake"] } ]
}'></textarea>
					<div class="wpd-json-dropzone-overlay">JSONファイルをドロップ</div>
				</div>
			</div>

			<div class="wpd-tabs">
				<button type="button" class="wpd-tab is-active" data-tab="basic">1. 基本設定</button>
				<button type="button" class="wpd-tab" data-tab="questions">2. 質問項目</button>
				<button type="button" class="wpd-tab" data-tab="fields">3. 表示項目</button>
				<button type="button" class="wpd-tab" data-tab="items">4. 結果アイテム</button>
				<button type="button" class="wpd-tab" data-tab="exclusions">5. 除外ルール</button>
				<button type="button" class="wpd-tab" data-tab="reorders">6. 並び替えルール</button>
				<button type="button" class="wpd-tab" data-tab="shortcode">7. ショートコード</button>
			</div>

			<!-- 基本設定 -->
			<div class="wpd-panel is-active" data-panel="basic">
				<table class="form-table">
					<tr>
						<th><label>デザインスタイル</label></th>
						<td>
							<div class="wpd-style-grid">
								<label class="wpd-radio-card">
									<input type="radio" name="wpd_settings[style]" value="A" <?php checked( $settings['style'], 'A' ); ?>>
									<span class="wpd-radio-card-body">
										<span class="wpd-radio-card-title">ポップ</span>
									</span>
								</label>
								<label class="wpd-radio-card">
									<input type="radio" name="wpd_settings[style]" value="C" <?php checked( $settings['style'], 'C' ); ?>>
									<span class="wpd-radio-card-body">
										<span class="wpd-radio-card-title">アンバー×グレー</span>
									</span>
								</label>
								<label class="wpd-radio-card">
									<input type="radio" name="wpd_settings[style]" value="D" <?php checked( $settings['style'], 'D' ); ?>>
									<span class="wpd-radio-card-body">
										<span class="wpd-radio-card-title">クリーンブルー</span>
									</span>
								</label>
							</div>
						</td>
					</tr>

					<tr>
						<th><label>結果ページのデザイン</label></th>
						<td>
							<div class="wpd-style-grid">
								<label class="wpd-radio-card">
									<input type="radio" name="wpd_settings[result_style]" value="A" <?php checked( $settings['result_style'], 'A' ); ?>>
									<span class="wpd-radio-card-body">
										<span class="wpd-radio-card-title">ポップ</span>
									</span>
								</label>
								<label class="wpd-radio-card">
									<input type="radio" name="wpd_settings[result_style]" value="C" <?php checked( $settings['result_style'], 'C' ); ?>>
									<span class="wpd-radio-card-body">
										<span class="wpd-radio-card-title">アンバー×グレー</span>
									</span>
								</label>
								<label class="wpd-radio-card">
									<input type="radio" name="wpd_settings[result_style]" value="D" <?php checked( $settings['result_style'], 'D' ); ?>>
									<span class="wpd-radio-card-body">
										<span class="wpd-radio-card-title">クリーンブルー</span>
									</span>
								</label>
							</div>
							<p class="description">フォームのデザインとは独立して選択できます。</p>
						</td>
					</tr>

					<tr>
						<th><label>質問の表示方式</label></th>
						<td>
							<div class="wpd-style-grid">
								<label class="wpd-radio-card">
									<input type="radio" name="wpd_settings[display_mode]" value="all" <?php checked( $settings['display_mode'], 'all' ); ?>>
									<span class="wpd-radio-card-body">
										<span class="wpd-radio-card-title">全問一覧表示</span>
										<span class="wpd-radio-card-desc">すべての質問を1ページに縦並びで表示。最後にまとめて送信。</span>
									</span>
								</label>
								<label class="wpd-radio-card">
									<input type="radio" name="wpd_settings[display_mode]" value="step" <?php checked( $settings['display_mode'], 'step' ); ?>>
									<span class="wpd-radio-card-body">
										<span class="wpd-radio-card-title">1問ずつ表示</span>
										<span class="wpd-radio-card-desc">1問ずつ切り替えて表示。進捗バー＋「戻る／次へ」ボタン付き。</span>
									</span>
								</label>
							</div>
						</td>
					</tr>

					<tr>
						<th><label for="wpd_title_text">表示タイトル（任意）</label></th>
						<td>
							<input type="text" name="wpd_settings[title_text]" id="wpd_title_text"
							       value="<?php echo esc_attr( $settings['title_text'] ); ?>"
							       class="regular-text" placeholder="<?php echo esc_attr( $post->post_title ); ?>">
							<p class="description">未入力の場合は投稿タイトルが使われます。改行は <code>&lt;br&gt;</code> でOK。</p>
						</td>
					</tr>

					<tr>
						<th><label for="wpd_lead_text">サブテキスト</label></th>
						<td>
							<input type="text" name="wpd_settings[lead_text]" id="wpd_lead_text"
							       value="<?php echo esc_attr( $settings['lead_text'] ); ?>"
							       class="regular-text">
						</td>
					</tr>

					<tr>
						<th><label for="wpd_cta_button_text">診断スタートボタンの文言</label></th>
						<td>
							<input type="text" name="wpd_settings[cta_button_text]" id="wpd_cta_button_text"
							       value="<?php echo esc_attr( $settings['cta_button_text'] ); ?>"
							       class="regular-text">
						</td>
					</tr>

					<tr>
						<th><label for="wpd_item_button_text">結果アイテムのボタン文言</label></th>
						<td>
							<input type="text" name="wpd_settings[item_button_text]" id="wpd_item_button_text"
							       value="<?php echo esc_attr( $settings['item_button_text'] ); ?>"
							       class="regular-text">
							<p class="description">例：「公式サイトで申し込む」「資料請求する」など</p>
						</td>
					</tr>

					<tr>
						<th><label for="wpd_form_custom_css">診断フォーム用カスタムCSS（任意）</label></th>
						<td>
							<textarea name="wpd_settings[form_custom_css]" id="wpd_form_custom_css"
							          rows="10" class="large-text code" spellcheck="false"
							          placeholder="/* 例：選択肢ボタンの色を変える */
.wpd-opt { border-color: #006fbb; }
.wpd-opt.is-selected { background: #006fbb; border-color: #006fbb; color: #fff; }

/* 例：送信ボタンの色を変える */
.wpd-form button[type=submit] { background: #006fbb; color: #fff; }"><?php echo esc_textarea( $settings['form_custom_css'] ); ?></textarea>
							<p class="description">
								診断フォームの見た目を上書きするCSSを記述できます。空欄ならデフォルトのままです。<br>
								よく使うセレクタ：
								<code>.wpd-form</code>（フォーム全体）、
								<code>.wpd-question</code>（各質問ブロック）、
								<code>.wpd-opt</code>（選択肢ボタン）、
								<code>.is-selected</code>（選択中の選択肢）、
								<code>.wpd-step-bar-fill</code>（進捗バー）、
								<code>.wpd-step-next</code>（次へボタン）、
								<code>button[type=submit]</code>（送信ボタン）
							</p>
						</td>
					</tr>

					<tr>
						<th><label for="wpd_result_custom_css">結果ページ用カスタムCSS（任意）</label></th>
						<td>
							<textarea name="wpd_settings[result_custom_css]" id="wpd_result_custom_css"
							          rows="10" class="large-text code" spellcheck="false"
							          placeholder="/* 例：項目の値の文字色を変える */
.wpd-spec-item dd { color: #2271b1; }

/* 例：申込ボタンの背景色・文字色を変える */
.wpd-cta-btn {
    background: #2271b1;
    color: #fff;
    box-shadow: 0 4px 0 #135e96, 0 6px 14px rgba(0,0,0,.12);
}
.wpd-cta-btn:hover { box-shadow: 0 2px 0 #135e96, 0 3px 8px rgba(0,0,0,.12); }
.wpd-cta-btn:active { box-shadow: 0 0 0 #135e96; }"><?php echo esc_textarea( $settings['result_custom_css'] ); ?></textarea>
							<p class="description">
								結果ページの見た目を上書きするCSSを記述できます。空欄ならデフォルトのままです。<br>
								よく使うセレクタ：
								<code>.wpd-pr-tag</code>（商品名）、
								<code>.wpd-catch</code>（キャッチコピー）、
								<code>.wpd-spec-item dt</code>（項目ラベル）、
								<code>.wpd-spec-item dd</code>（項目の値）、
								<code>.wpd-spec-item-multiline dd</code>（申込条件など複数行の値）、
								<code>.wpd-points-title</code>（おすすめポイント見出し）、
								<code>.wpd-points li</code>（ポイントの各行）、
								<code>.wpd-cta-btn</code>（申込ボタン）、
								<code>.wpd-rank-badge</code>（順位バッジ）
							</p>
						</td>
					</tr>

					<tr>
						<th><label for="wpd_form_custom_js">診断フォーム用カスタムJS（任意）</label></th>
						<td>
							<textarea name="wpd_settings[form_custom_js]" id="wpd_form_custom_js"
							          rows="10" class="large-text code" spellcheck="false"
							          placeholder="// 例：単一選択の選択肢をクリックしたら次の質問までスクロール
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.wpd-opt');
  if (!btn) return;
  var q = btn.closest('.wpd-question');
  if (!q || q.getAttribute('data-type') === 'multi') return;
  var next = q.nextElementSibling;
  while (next && !next.classList.contains('wpd-question')) next = next.nextElementSibling;
  var target = next || q.closest('form').querySelector('button[type=submit]');
  if (!target) return;
  setTimeout(function () {
    var top = target.getBoundingClientRect().top + window.pageYOffset - 80;
    window.scrollTo({ top: top, behavior: 'smooth' });
  }, 150);
});"><?php echo esc_textarea( $settings['form_custom_js'] ); ?></textarea>
							<p class="description">
								診断フォームに追加のJavaScriptを差し込めます。空欄なら何も出力しません。<br>
								スクリプトタグは不要（自動的に <code>&lt;script&gt;</code> 内に出力されます）。<br>
								よく使うセレクタ：
								<code>.wpd-form</code>（フォーム全体）、
								<code>.wpd-question</code>（各質問。data-type=&quot;single&quot;|&quot;multi&quot;）、
								<code>.wpd-opt</code>（選択肢ボタン。data-value=&quot;...&quot;）、
								<code>.is-selected</code>（選択中クラス）
							</p>
						</td>
					</tr>

					<tr>
						<th><label for="wpd_result_custom_js">結果ページ用カスタムJS（任意）</label></th>
						<td>
							<textarea name="wpd_settings[result_custom_js]" id="wpd_result_custom_js"
							          rows="10" class="large-text code" spellcheck="false"
							          placeholder="// 例：申込ボタンのクリックを計測する
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.wpd-cta-btn');
  if (!btn) return;
  // 計測タグの呼び出しなど
  console.log('CTA clicked:', btn.href);
});"><?php echo esc_textarea( $settings['result_custom_js'] ); ?></textarea>
							<p class="description">
								結果ページに追加のJavaScriptを差し込めます。空欄なら何も出力しません。<br>
								スクリプトタグは不要（自動的に <code>&lt;script&gt;</code> 内に出力されます）。<br>
								よく使うセレクタ：
								<code>.wpd-result-wrap</code>（結果ページ全体）、
								<code>.wpd-card-result</code>（各結果カード）、
								<code>.wpd-cta-btn</code>（申込ボタン）、
								<code>.wpd-rank-badge</code>（順位バッジ）
							</p>
						</td>
					</tr>

					<tr>
						<th><label for="wpd_result_page_id">診断結果ページ</label></th>
						<td>
							<?php
							// 下書き／非公開／予約投稿のページも選択できるようにする
							wp_dropdown_pages( array(
								'name'              => 'wpd_settings[result_page_id]',
								'selected'          => $settings['result_page_id'],
								'show_option_none'  => '— 結果ページを選択 —',
								'option_none_value' => '0',
								'post_status'       => array( 'publish', 'private', 'draft', 'pending', 'future' ),
							) );

							// 選択中ページのステータスを表示（公開以外なら注意書きを添える）
							$selected_page_id = (int) $settings['result_page_id'];
							if ( $selected_page_id ) {
								$selected_page = get_post( $selected_page_id );
								if ( $selected_page && 'publish' !== $selected_page->post_status ) {
									$status_labels = array(
										'draft'   => '下書き',
										'private' => '非公開',
										'pending' => 'レビュー待ち',
										'future'  => '予約投稿',
										'trash'   => 'ゴミ箱',
									);
									$label = isset( $status_labels[ $selected_page->post_status ] )
										? $status_labels[ $selected_page->post_status ]
										: $selected_page->post_status;
									echo ' <span class="wpd-page-status wpd-page-status-' . esc_attr( $selected_page->post_status ) . '">（現在のステータス：' . esc_html( $label ) . '）</span>';
								}
							}
							?>
							<p class="description">「診断スタート」を押したときに遷移するページを選択してください。下書き／非公開／予約投稿のページも選択できます。<br>そのページの本文に <code>[wpd_diagnosis_result id="<?php echo (int) $post->ID; ?>"]</code> を貼り付けてください。</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- 質問項目 -->
			<div class="wpd-panel" data-panel="questions">
				<p class="description">質問と選択肢を追加してください。<strong>質問ID</strong>は除外ルールで参照する識別子です（半角英数）。</p>
				<div id="wpd-questions-list" class="wpd-list"></div>
				<p>
					<button type="button" class="button button-secondary wpd-add-question">＋ 質問を追加</button>
				</p>
				<input type="hidden" name="wpd_questions_json" id="wpd-questions-json" value="">
			</div>

			<!-- 表示項目 -->
			<div class="wpd-panel" data-panel="fields">
				<p class="description">結果アイテムに表示する項目（金利・限度額・審査時間など）を定義します。<br>
				<strong>キー</strong>は内部識別子（半角英数）、<strong>ラベル</strong>は画面に表示される名前です。</p>
				<div id="wpd-fields-list" class="wpd-list"></div>
				<p>
					<button type="button" class="button button-secondary wpd-add-field">＋ 表示項目を追加</button>
				</p>
				<input type="hidden" name="wpd_fields_json" id="wpd-fields-json" value="">
			</div>

			<!-- 結果アイテム -->
			<div class="wpd-panel" data-panel="items">
				<p class="description">診断結果として表示するアイテム（金融機関・保険会社など）を登録します。<br>
				「表示項目」タブで定義した項目に値を入力してください。「表示順」が小さい順に並びます。</p>
				<div id="wpd-items-list" class="wpd-list"></div>
				<p>
					<button type="button" class="button button-secondary wpd-add-item">＋ 結果アイテムを追加</button>
				</p>
				<input type="hidden" name="wpd_items_json" id="wpd-items-json" value="">
			</div>

			<!-- 除外ルール -->
			<div class="wpd-panel" data-panel="exclusions">
				<p class="description"><strong>「ある質問でこの答えが選ばれたら、これらの結果アイテムを非表示にする」</strong>というルールを設定します。</p>
				<p class="description">例：Q1（借入件数）で<code>4件以上</code>を選択 → 横浜銀行・住信SBIを非表示</p>
				<div id="wpd-exclusions-list" class="wpd-list"></div>
				<p>
					<button type="button" class="button button-secondary wpd-add-exclusion">＋ 除外ルールを追加</button>
				</p>
				<input type="hidden" name="wpd_exclusions_json" id="wpd-exclusions-json" value="">
			</div>

			<!-- 並び替えルール -->
			<div class="wpd-panel" data-panel="reorders">
				<p class="description"><strong>「ある質問でこの答えが選ばれたら、指定した順番で結果アイテムを先頭から並べる」</strong>というルールを設定します。</p>
				<p class="description">指定しなかったアイテムは、指定したアイテムの後ろに元の表示順のまま続きます。複数のルールに一致した場合は、上にあるルール1件だけが適用されます。除外ルールで非表示になったアイテムは並び替えに含まれていても表示されません。</p>
				<div id="wpd-reorders-list" class="wpd-list"></div>
				<p>
					<button type="button" class="button button-secondary wpd-add-reorder">＋ 並び替えルールを追加</button>
				</p>
				<input type="hidden" name="wpd_reorders_json" id="wpd-reorders-json" value="">
			</div>

			<!-- ショートコード -->
			<div class="wpd-panel" data-panel="shortcode">
				<h3>使い方</h3>
				<ol>
					<li>診断フォームを置きたい固定ページ／投稿に下記コードを貼ります（クラシックエディタは「テキスト」モード）。</li>
					<li>「基本設定」タブで結果ページを選択し、そのページに結果用ショートコードを貼ります。</li>
				</ol>

				<h4>診断フォーム用</h4>
				<p>
					<code class="wpd-copy">[wpd_diagnosis id="<?php echo (int) $post->ID; ?>"]</code>
				</p>
				<p class="description">スタイル上書きしたい場合: <code>[wpd_diagnosis id="<?php echo (int) $post->ID; ?>" style="B"]</code></p>

				<h4>診断結果用</h4>
				<p>
					<code class="wpd-copy">[wpd_diagnosis_result id="<?php echo (int) $post->ID; ?>"]</code>
				</p>
			</div>

		</div>

		<script type="text/javascript">
			window.WPDInitialData = <?php echo $json_data; ?>;
		</script>
		<?php
	}

	/**
	 * 保存処理
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) return;
		if ( ! wp_verify_nonce( $_POST[ self::NONCE_FIELD ], self::NONCE_ACTION ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( $post->post_type !== WPD_POST_TYPE ) return;

		// 基本設定
		$raw_settings = isset( $_POST['wpd_settings'] ) ? wp_unslash( $_POST['wpd_settings'] ) : array();
		WPD_Data::set_settings( $post_id, $raw_settings );

		// 質問
		$questions_json = isset( $_POST['wpd_questions_json'] ) ? wp_unslash( $_POST['wpd_questions_json'] ) : '[]';
		$questions = json_decode( $questions_json, true );
		WPD_Data::set_questions( $post_id, is_array( $questions ) ? $questions : array() );

		// 表示項目
		$fields_json = isset( $_POST['wpd_fields_json'] ) ? wp_unslash( $_POST['wpd_fields_json'] ) : '[]';
		$fields = json_decode( $fields_json, true );
		WPD_Data::set_result_fields( $post_id, is_array( $fields ) ? $fields : array() );

		// 結果アイテム
		$items_json = isset( $_POST['wpd_items_json'] ) ? wp_unslash( $_POST['wpd_items_json'] ) : '[]';
		$items = json_decode( $items_json, true );
		WPD_Data::set_result_items( $post_id, is_array( $items ) ? $items : array() );

		// 除外ルール
		$ex_json = isset( $_POST['wpd_exclusions_json'] ) ? wp_unslash( $_POST['wpd_exclusions_json'] ) : '[]';
		$ex = json_decode( $ex_json, true );
		WPD_Data::set_exclusions( $post_id, is_array( $ex ) ? $ex : array() );

		// 並び替えルール
		$ro_json = isset( $_POST['wpd_reorders_json'] ) ? wp_unslash( $_POST['wpd_reorders_json'] ) : '[]';
		$ro = json_decode( $ro_json, true );
		WPD_Data::set_reorders( $post_id, is_array( $ro ) ? $ro : array() );
	}
}
