/* ============================================================
 * WP 診断ツール - 管理画面エディタJS
 *
 * 役割:
 *   - タブ切り替え
 *   - 質問・選択肢の追加/削除/並び替え（UI）
 *   - 表示項目（フィールド定義）の追加/削除/並び替え
 *   - 結果アイテムの追加/削除/並び替え（動的にフィールド入力欄を生成）
 *   - 除外ルールの追加/削除（質問IDと選択肢valueをセレクトボックスで選択）
 *   - 並び替えルールの追加/削除（選択された答えに応じて表示順を指定）
 *   - 保存前にJSONをhidden inputに書き出す
 * ============================================================ */
(function ($) {
	'use strict';

	// 初期データ
	var state = {
		questions:  [],
		fields:     [],
		items:      [],
		exclusions: [],
		reorders:   []
	};

	$(function () {
		if (window.WPDInitialData) {
			state.questions  = Array.isArray(WPDInitialData.questions)  ? WPDInitialData.questions  : [];
			state.fields     = Array.isArray(WPDInitialData.fields)     ? WPDInitialData.fields     : [];
			state.items      = Array.isArray(WPDInitialData.items)      ? WPDInitialData.items      : [];
			state.exclusions = Array.isArray(WPDInitialData.exclusions) ? WPDInitialData.exclusions : [];
			state.reorders   = Array.isArray(WPDInitialData.reorders)   ? WPDInitialData.reorders   : [];
			// 結果アイテムは保存済みの order でソートして管理画面に表示
			state.items.sort(function (a, b) {
				return (parseInt(a.order, 10) || 999) - (parseInt(b.order, 10) || 999);
			});
		}

		bindTabs();
		bindQuestions();
		bindFields();
		bindItems();
		bindExclusions();
		bindReorders();

		renderQuestions();
		renderFields();
		renderItems();
		renderExclusions();
		renderReorders();

		// 保存前にhidden inputsへ最新JSONを書き込む
		$('#post').on('submit', function () {
			$('#wpd-questions-json').val(JSON.stringify(state.questions));
			$('#wpd-fields-json').val(JSON.stringify(state.fields));
			$('#wpd-items-json').val(JSON.stringify(state.items));
			$('#wpd-exclusions-json').val(JSON.stringify(state.exclusions));
			$('#wpd-reorders-json').val(JSON.stringify(state.reorders));
		});

		// サンプル（カードローン診断）インポート
		$(document).on('click', '.wpd-import-sample', function () {
			if (!confirm('サンプルデータ（カードローン診断）をインポートします。\n現在の「質問項目／表示項目／結果アイテム／除外ルール」が上書きされます。よろしいですか？')) {
				return;
			}
			var sample = buildCardLoanSample();
			state.questions  = sample.questions;
			state.fields     = sample.fields;
			state.items      = sample.items;
			state.exclusions = sample.exclusions;
			state.reorders   = sample.reorders || [];

			// 基本設定の表示中インプットも反映（保存はsubmit時にPHP側で行う）
			if (sample.settings) {
				$('input[name="wpd_settings[title_text]"]').val(sample.settings.title_text || '');
				$('input[name="wpd_settings[lead_text]"]').val(sample.settings.lead_text || '');
				$('input[name="wpd_settings[cta_button_text]"]').val(sample.settings.cta_button_text || '');
				$('input[name="wpd_settings[item_button_text]"]').val(sample.settings.item_button_text || '');
			}

			renderQuestions();
			renderFields();
			renderItems();
			renderExclusions();
			renderReorders();
			alert('サンプルデータを読み込みました。\n内容を確認のうえ「更新」ボタンで保存してください。');
		});

		// =====================================================
		// JSON 入出力
		// =====================================================
		$(document).on('click', '.wpd-toggle-json', function () {
			$('.wpd-json-panel').toggle();
		});

		$(document).on('click', '.wpd-json-load', function () {
			var snapshot = snapshotState();
			$('.wpd-json-textarea').val(JSON.stringify(snapshot, null, 2));
			setJsonStatus('現在の内容を読み込みました。', 'ok');
		});

		// ファイル選択（ボタン → 隠しinput）
		$(document).on('click', '.wpd-json-pick', function () {
			$('.wpd-json-file-input').val('').trigger('click');
		});

		$(document).on('change', '.wpd-json-file-input', function (e) {
			var files = e.target.files;
			if (files && files.length) {
				readJsonFile(files[0]);
			}
		});

		// ドラッグ&ドロップ（ドロップゾーン）
		$(document).on('dragover', '.wpd-json-dropzone', function (e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).addClass('is-dragover');
			if (e.originalEvent && e.originalEvent.dataTransfer) {
				e.originalEvent.dataTransfer.dropEffect = 'copy';
			}
		});

		$(document).on('dragleave drop', '.wpd-json-dropzone', function (e) {
			$(this).removeClass('is-dragover');
		});

		$(document).on('drop', '.wpd-json-dropzone', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var dt = e.originalEvent && e.originalEvent.dataTransfer;
			if (!dt) return;
			if (dt.files && dt.files.length) {
				readJsonFile(dt.files[0]);
			} else if (dt.getData) {
				var text = dt.getData('text');
				if (text) {
					$('.wpd-json-textarea').val(text);
					setJsonStatus('テキストを貼り付けました。「JSONを反映」で適用できます。', 'ok');
				}
			}
		});

		// ダウンロード
		$(document).on('click', '.wpd-json-download', function () {
			var snapshot = snapshotState();
			var json = JSON.stringify(snapshot, null, 2);
			var blob = new Blob([json], { type: 'application/json;charset=utf-8' });
			var url = URL.createObjectURL(blob);

			// ファイル名は投稿タイトルから生成（フォールバックあり）
			var title = ($('#title').val() || $('input[name="post_title"]').val() || 'wpd-diagnosis').trim();
			var safe = title.replace(/[\\/:*?"<>|]/g, '_').replace(/\s+/g, '-');
			var ts = new Date();
			var pad = function (n) { return String(n).padStart(2, '0'); };
			var stamp = ts.getFullYear() + pad(ts.getMonth() + 1) + pad(ts.getDate());
			var filename = safe + '-' + stamp + '.json';

			var a = document.createElement('a');
			a.href = url;
			a.download = filename;
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
			setJsonStatus(filename + ' をダウンロードしました。', 'ok');
		});

		$(document).on('click', '.wpd-json-clear', function () {
			$('.wpd-json-textarea').val('');
			setJsonStatus('', '');
		});

		$(document).on('click', '.wpd-json-apply', function () {
			var raw = $('.wpd-json-textarea').val().trim();
			if (!raw) {
				setJsonStatus('JSONが空です。', 'err');
				return;
			}
			var data;
			try {
				data = JSON.parse(raw);
			} catch (e) {
				setJsonStatus('JSONのパースに失敗しました: ' + e.message, 'err');
				return;
			}
			var validation = validateImportData(data);
			if (validation.errors.length) {
				setJsonStatus('反映できません: ' + validation.errors.join(' / '), 'err');
				return;
			}
			if (!confirm('JSONの内容で「質問項目／表示項目／結果アイテム／除外ルール／並び替えルール」' +
				(data.settings ? '＋基本設定' : '') +
				'を上書きします。よろしいですか？')) {
				return;
			}

			state.questions  = validation.cleaned.questions;
			state.fields     = validation.cleaned.fields;
			state.items      = validation.cleaned.items;
			state.exclusions = validation.cleaned.exclusions;
			state.reorders   = validation.cleaned.reorders;

			if (data.settings && typeof data.settings === 'object') {
				applySettingsToInputs(data.settings);
			}

			renderQuestions();
			renderFields();
			renderItems();
			renderExclusions();
			renderReorders();

			var warn = validation.warnings.length ? '（警告: ' + validation.warnings.join(' / ') + '）' : '';
			setJsonStatus('JSONを反映しました。「更新」ボタンで保存してください。' + warn, 'ok');
		});

		// クリップボードコピー
		$(document).on('click', '.wpd-copy', function () {
			var text = $(this).text();
			if (navigator.clipboard) {
				navigator.clipboard.writeText(text);
				var $el = $(this);
				var orig = $el.text();
				$el.text('コピーしました！');
				setTimeout(function () { $el.text(orig); }, 1200);
			}
		});
	});

	function bindTabs() {
		$('.wpd-tab').on('click', function () {
			var tab = $(this).data('tab');
			$('.wpd-tab').removeClass('is-active');
			$(this).addClass('is-active');
			$('.wpd-panel').removeClass('is-active');
			$('.wpd-panel[data-panel="' + tab + '"]').addClass('is-active');

			// 切り替え時に最新の状態で再描画（除外/並び替えルールは質問/アイテムを参照するので必要）
			if (tab === 'exclusions') renderExclusions();
			if (tab === 'reorders') renderReorders();
			if (tab === 'items') renderItems();
		});
	}

	// =====================================================
	// 質問項目
	// =====================================================
	function bindQuestions() {
		$('.wpd-add-question').on('click', function () {
			var n = state.questions.length + 1;
			state.questions.push({
				id: 'q' + n,
				label: '',
				type: 'single',
				options: [{ value: '', label: '' }]
			});
			renderQuestions();
		});

		$('#wpd-questions-list').on('click', '.wpd-q-delete', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (confirm('この質問を削除しますか？')) {
				state.questions.splice(idx, 1);
				renderQuestions();
			}
		});

		$('#wpd-questions-list').on('click', '.wpd-q-up', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (idx > 0) {
				var t = state.questions[idx];
				state.questions[idx] = state.questions[idx - 1];
				state.questions[idx - 1] = t;
				renderQuestions();
			}
		});

		$('#wpd-questions-list').on('click', '.wpd-q-down', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (idx < state.questions.length - 1) {
				var t = state.questions[idx];
				state.questions[idx] = state.questions[idx + 1];
				state.questions[idx + 1] = t;
				renderQuestions();
			}
		});

		$('#wpd-questions-list').on('input', '.wpd-q-id, .wpd-q-label, .wpd-q-type', function () {
			var $card = $(this).closest('.wpd-card');
			var idx = parseInt($card.data('index'), 10);
			state.questions[idx].id    = $card.find('.wpd-q-id').val();
			state.questions[idx].label = $card.find('.wpd-q-label').val();
			state.questions[idx].type  = $card.find('.wpd-q-type').val();
		});

		$('#wpd-questions-list').on('input', '.wpd-op-value, .wpd-op-label', function () {
			var $card = $(this).closest('.wpd-card');
			var qIdx = parseInt($card.data('index'), 10);
			var $row = $(this).closest('.wpd-op-row');
			var oIdx = parseInt($row.data('opindex'), 10);
			state.questions[qIdx].options[oIdx].value = $row.find('.wpd-op-value').val();
			state.questions[qIdx].options[oIdx].label = $row.find('.wpd-op-label').val();
		});

		$('#wpd-questions-list').on('click', '.wpd-op-add', function () {
			var qIdx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			state.questions[qIdx].options.push({ value: '', label: '' });
			renderQuestions();
		});

		$('#wpd-questions-list').on('click', '.wpd-op-delete', function () {
			var $card = $(this).closest('.wpd-card');
			var qIdx = parseInt($card.data('index'), 10);
			var oIdx = parseInt($(this).closest('.wpd-op-row').data('opindex'), 10);
			state.questions[qIdx].options.splice(oIdx, 1);
			renderQuestions();
		});
	}

	function renderQuestions() {
		var $list = $('#wpd-questions-list');
		if (state.questions.length === 0) {
			$list.html('<div class="wpd-empty">まだ質問がありません。「質問を追加」ボタンから追加してください。</div>');
			return;
		}

		var html = '';
		state.questions.forEach(function (q, idx) {
			html += '<div class="wpd-card" data-index="' + idx + '">';
			html += '  <div class="wpd-card-head">';
			html += '    <span class="wpd-card-num">Q' + (idx + 1) + '</span>';
			html += '    <span class="wpd-card-actions">';
			html += '      <button type="button" class="button-link wpd-q-up" title="上に移動">▲</button>';
			html += '      <button type="button" class="button-link wpd-q-down" title="下に移動">▼</button>';
			html += '      <button type="button" class="button-link wpd-q-delete" title="削除">削除</button>';
			html += '    </span>';
			html += '  </div>';
			html += '  <div class="wpd-card-body">';
			html += '    <div class="wpd-grid">';
			html += '      <label>質問ID <span class="wpd-required">*</span><br>';
			html += '        <input type="text" class="wpd-q-id widefat" value="' + escAttr(q.id) + '" placeholder="q1"></label>';
			html += '      <label>選択タイプ<br>';
			html += '        <select class="wpd-q-type widefat">';
			html += '          <option value="single"' + (q.type === 'single' ? ' selected' : '') + '>単一選択</option>';
			html += '          <option value="multi"' + (q.type === 'multi' ? ' selected' : '') + '>複数選択</option>';
			html += '        </select></label>';
			html += '    </div>';
			html += '    <label>質問文 <span class="wpd-required">*</span><br>';
			html += '      <input type="text" class="wpd-q-label widefat" value="' + escAttr(q.label) + '" placeholder="例：借入件数は？"></label>';

			html += '    <div class="wpd-subhead">選択肢</div>';
			(q.options || []).forEach(function (op, oIdx) {
				html += '    <div class="wpd-op-row" data-opindex="' + oIdx + '">';
				html += '      <input type="text" class="wpd-op-value" value="' + escAttr(op.value) + '" placeholder="値（例: 4plus）">';
				html += '      <input type="text" class="wpd-op-label" value="' + escAttr(op.label) + '" placeholder="表示ラベル（例: 4件以上）">';
				html += '      <button type="button" class="button-link wpd-op-delete">削除</button>';
				html += '    </div>';
			});
			html += '    <p><button type="button" class="button button-small wpd-op-add">＋ 選択肢を追加</button></p>';
			html += '  </div>';
			html += '</div>';
		});

		$list.html(html);
	}

	// =====================================================
	// 表示項目（フィールド定義）
	// =====================================================
	function bindFields() {
		$('.wpd-add-field').on('click', function () {
			state.fields.push({ key: '', label: '', type: 'text' });
			renderFields();
		});

		$('#wpd-fields-list').on('input change', '.wpd-f-key, .wpd-f-label, .wpd-f-type', function () {
			var $row = $(this).closest('.wpd-f-row');
			var idx = parseInt($row.data('index'), 10);
			state.fields[idx].key   = $row.find('.wpd-f-key').val();
			state.fields[idx].label = $row.find('.wpd-f-label').val();
			state.fields[idx].type  = $row.find('.wpd-f-type').val();
			// 入力欄タイプが変わったら結果アイテムも再描画
			if ($(this).hasClass('wpd-f-type')) renderItems();
		});

		$('#wpd-fields-list').on('click', '.wpd-f-delete', function () {
			var idx = parseInt($(this).closest('.wpd-f-row').data('index'), 10);
			if (confirm('この表示項目を削除しますか？')) {
				state.fields.splice(idx, 1);
				renderFields();
			}
		});

		$('#wpd-fields-list').on('click', '.wpd-f-up', function () {
			var idx = parseInt($(this).closest('.wpd-f-row').data('index'), 10);
			if (idx > 0) {
				var t = state.fields[idx];
				state.fields[idx] = state.fields[idx - 1];
				state.fields[idx - 1] = t;
				renderFields();
			}
		});

		$('#wpd-fields-list').on('click', '.wpd-f-down', function () {
			var idx = parseInt($(this).closest('.wpd-f-row').data('index'), 10);
			if (idx < state.fields.length - 1) {
				var t = state.fields[idx];
				state.fields[idx] = state.fields[idx + 1];
				state.fields[idx + 1] = t;
				renderFields();
			}
		});
	}

	function renderFields() {
		var $list = $('#wpd-fields-list');
		if (state.fields.length === 0) {
			$list.html('<div class="wpd-empty">表示項目がありません。例：金利、限度額、審査時間など、結果カードに出したい項目を追加してください。</div>');
			return;
		}
		var html = '';
		state.fields.forEach(function (f, idx) {
			var type = f.type === 'textarea' ? 'textarea' : 'text';
			html += '<div class="wpd-f-row" data-index="' + idx + '">';
			html += '  <input type="text" class="wpd-f-key"   value="' + escAttr(f.key)   + '" placeholder="キー（例: rate）">';
			html += '  <input type="text" class="wpd-f-label" value="' + escAttr(f.label) + '" placeholder="表示ラベル（例: 金利）">';
			html += '  <select class="wpd-f-type">';
			html += '    <option value="text"'     + (type === 'text'     ? ' selected' : '') + '>1行</option>';
			html += '    <option value="textarea"' + (type === 'textarea' ? ' selected' : '') + '>複数行</option>';
			html += '  </select>';
			html += '  <span class="wpd-f-actions">';
			html += '    <button type="button" class="button-link wpd-f-up">▲</button>';
			html += '    <button type="button" class="button-link wpd-f-down">▼</button>';
			html += '    <button type="button" class="button-link wpd-f-delete">削除</button>';
			html += '  </span>';
			html += '</div>';
		});
		$list.html(html);
	}

	// =====================================================
	// 結果アイテム
	// =====================================================
	function bindItems() {
		$('.wpd-add-item').on('click', function () {
			var n = state.items.length;
			state.items.push({
				id: 'item' + (n + 1),
				name: '',
				order: (n + 1) * 10,
				catch: '',
				banner_url: '',
				detail_url: '',
				banner_id: '',
				cta_id: '',
				disabled: false,
				fields: {},
				points: ['']
			});
			renderItems();
		});

		$('#wpd-items-list').on('input change',
			'.wpd-it-id, .wpd-it-name, .wpd-it-catch, .wpd-it-banner, .wpd-it-detail, .wpd-it-banner-id, .wpd-it-cta-id, .wpd-it-field, .wpd-it-point',
			function () {
				var $card = $(this).closest('.wpd-card');
				var idx = parseInt($card.data('index'), 10);
				if (!state.items[idx]) return;
				state.items[idx].id        = $card.find('.wpd-it-id').val();
				state.items[idx].name      = $card.find('.wpd-it-name').val();
				state.items[idx].catch     = $card.find('.wpd-it-catch').val();
				state.items[idx].banner_url= $card.find('.wpd-it-banner').val();
				state.items[idx].detail_url= $card.find('.wpd-it-detail').val();
				state.items[idx].banner_id = $card.find('.wpd-it-banner-id').val();
				state.items[idx].cta_id    = $card.find('.wpd-it-cta-id').val();
				state.items[idx].fields    = {};
				$card.find('.wpd-it-field').each(function () {
					state.items[idx].fields[$(this).data('key')] = $(this).val();
				});
				state.items[idx].points = [];
				$card.find('.wpd-it-point').each(function () {
					state.items[idx].points.push($(this).val());
				});
			}
		);

		// 非掲載トグル（カード見た目を即時更新するため input 用ハンドラとは分離）
		$('#wpd-items-list').on('change', '.wpd-it-disabled', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (!state.items[idx]) return;
			state.items[idx].disabled = $(this).is(':checked');
			renderItems();
		});

		$('#wpd-items-list').on('click', '.wpd-it-delete', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (confirm('この結果アイテムを削除しますか？')) {
				state.items.splice(idx, 1);
				renderItems();
			}
		});

		$('#wpd-items-list').on('click', '.wpd-it-up', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (idx > 0) {
				var t = state.items[idx];
				state.items[idx] = state.items[idx - 1];
				state.items[idx - 1] = t;
				renumberItemOrders();
				renderItems();
			}
		});

		$('#wpd-items-list').on('click', '.wpd-it-down', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (idx < state.items.length - 1) {
				var t = state.items[idx];
				state.items[idx] = state.items[idx + 1];
				state.items[idx + 1] = t;
				renumberItemOrders();
				renderItems();
			}
		});

		$('#wpd-items-list').on('click', '.wpd-point-add', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (!state.items[idx].points) state.items[idx].points = [];
			state.items[idx].points.push('');
			renderItems();
		});

		$('#wpd-items-list').on('click', '.wpd-point-delete', function () {
			var $card = $(this).closest('.wpd-card');
			var idx = parseInt($card.data('index'), 10);
			var pIdx = parseInt($(this).closest('.wpd-point-row').data('pindex'), 10);
			state.items[idx].points.splice(pIdx, 1);
			renderItems();
		});
	}

	function renderItems() {
		var $list = $('#wpd-items-list');
		if (state.items.length === 0) {
			$list.html('<div class="wpd-empty">結果アイテムがありません。先に「表示項目」を設定してから、こちらにアイテム（金融機関など）を追加してください。</div>');
			return;
		}

		var html = '';
		state.items.forEach(function (it, idx) {
			var isDisabled = !!it.disabled;
			var cardCls = 'wpd-card' + (isDisabled ? ' wpd-card-disabled' : '');
			html += '<div class="' + cardCls + '" data-index="' + idx + '">';
			html += '  <div class="wpd-card-head" title="ドラッグして並び替え">';
			html += '    <span class="wpd-drag-grip" aria-hidden="true">⋮⋮</span>';
			html += '    <span class="wpd-card-num">' + (idx + 1) + '. ' + escHtml(it.name || '(名称未設定)');
			if (isDisabled) {
				html += ' <span class="wpd-badge-disabled">非掲載</span>';
			}
			html += '</span>';
			html += '    <span class="wpd-card-actions">';
			html += '      <label class="wpd-it-disabled-label" title="チェックすると結果ページから一時的に外します（データは削除されません）">';
			html += '        <input type="checkbox" class="wpd-it-disabled"' + (isDisabled ? ' checked' : '') + '> 非掲載';
			html += '      </label>';
			html += '      <button type="button" class="button-link wpd-it-up">▲</button>';
			html += '      <button type="button" class="button-link wpd-it-down">▼</button>';
			html += '      <button type="button" class="button-link wpd-it-delete">削除</button>';
			html += '    </span>';
			html += '  </div>';
			html += '  <div class="wpd-card-body">';

			html += '    <div class="wpd-grid">';
			html += '      <label>ID（半角英数）<span class="wpd-required">*</span><br>';
			html += '        <input type="text" class="wpd-it-id widefat" value="' + escAttr(it.id) + '" placeholder="acom"></label>';
			html += '      <label>表示名 <span class="wpd-required">*</span><br>';
			html += '        <input type="text" class="wpd-it-name widefat" value="' + escAttr(it.name) + '" placeholder="アコム"></label>';
			html += '    </div>';

			html += '    <label>キャッチコピー<br>';
			html += '      <input type="text" class="wpd-it-catch widefat" value="' + escAttr(it.catch) + '" placeholder="30日間無利息"></label>';

			html += '    <div class="wpd-grid">';
			html += '      <label>バナー画像URL<br>';
			html += '        <input type="text" class="wpd-it-banner widefat" value="' + escAttr(it.banner_url) + '" placeholder="https://..."></label>';
			html += '      <label>申込ボタンのリンク先<br>';
			html += '        <input type="text" class="wpd-it-detail widefat" value="' + escAttr(it.detail_url) + '" placeholder="https://..."></label>';
			html += '    </div>';

			html += '    <div class="wpd-grid">';
			html += '      <label>バナー画像のHTML id（JSリンク用）<br>';
			html += '        <input type="text" class="wpd-it-banner-id widefat" value="' + escAttr(it.banner_id || '') + '" placeholder="例: bt01"></label>';
			html += '      <label>申込ボタンのHTML id（JSリンク用）<br>';
			html += '        <input type="text" class="wpd-it-cta-id widefat" value="' + escAttr(it.cta_id || '') + '" placeholder="例: bt02"></label>';
			html += '    </div>';
			html += '    <p class="wpd-hint">空欄の場合は id 属性が出力されません。半角英数・ハイフン・アンダースコアのみ使えます（同一ページ内で重複しないように）。</p>';

			if (state.fields.length > 0) {
				html += '    <div class="wpd-subhead">項目値</div>';
				html += '    <div class="wpd-fields-grid">';
				state.fields.forEach(function (f) {
					var v = (it.fields && it.fields[f.key]) || '';
					var isTextarea = f.type === 'textarea';
					html += '      <label class="' + (isTextarea ? 'wpd-field-full' : '') + '">';
					html += '        <span class="wpd-field-label">' + escHtml(f.label) + '</span>';
					if (isTextarea) {
						html += '<textarea class="wpd-it-field" data-key="' + escAttr(f.key) + '" rows="4">' + escHtml(v) + '</textarea>';
					} else {
						html += '<input type="text" class="wpd-it-field" data-key="' + escAttr(f.key) + '" value="' + escAttr(v) + '">';
					}
					html += '      </label>';
				});
				html += '    </div>';
			} else {
				html += '    <p class="wpd-hint">※「表示項目」タブで先に項目を定義すると、ここに入力欄が表示されます。</p>';
			}

			html += '    <div class="wpd-subhead">おすすめポイント</div>';
			(it.points || []).forEach(function (p, pIdx) {
				html += '    <div class="wpd-point-row" data-pindex="' + pIdx + '">';
				html += '      <input type="text" class="wpd-it-point" value="' + escAttr(p) + '" placeholder="例：初めての利用は30日間金利ゼロ">';
				html += '      <button type="button" class="button-link wpd-point-delete">削除</button>';
				html += '    </div>';
			});
			html += '    <p><button type="button" class="button button-small wpd-point-add">＋ ポイントを追加</button></p>';

			html += '  </div>';
			html += '</div>';
		});

		$list.html(html);

		// ドラッグ&ドロップで並び替え可能に
		if ($list.sortable) {
			if ($list.hasClass('ui-sortable')) {
				$list.sortable('destroy');
			}
			$list.sortable({
				handle: '.wpd-card-head',
				cancel: '.wpd-card-actions, .wpd-card-actions *, input, button, select, textarea, label',
				cursor: 'move',
				opacity: 0.7,
				placeholder: 'wpd-card-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				update: function () {
					var newItems = [];
					$list.find('> .wpd-card').each(function () {
						var oldIdx = parseInt($(this).data('index'), 10);
						if (!isNaN(oldIdx) && state.items[oldIdx]) {
							newItems.push(state.items[oldIdx]);
						}
					});
					state.items = newItems;
					renumberItemOrders();
					renderItems();
				}
			});
		}
	}

	// 並び替え後に order を 10, 20, 30... と振り直す
	function renumberItemOrders() {
		state.items.forEach(function (it, i) {
			it.order = (i + 1) * 10;
		});
	}

	// =====================================================
	// 除外ルール
	// =====================================================
	function bindExclusions() {
		$('.wpd-add-exclusion').on('click', function () {
			state.exclusions.push({
				question_id: state.questions.length > 0 ? state.questions[0].id : '',
				value: '',
				exclude: []
			});
			renderExclusions();
		});

		$('#wpd-exclusions-list').on('change', '.wpd-ex-question', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			state.exclusions[idx].question_id = $(this).val();
			state.exclusions[idx].value = '';
			renderExclusions();
		});

		$('#wpd-exclusions-list').on('change', '.wpd-ex-value', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			state.exclusions[idx].value = $(this).val();
		});

		$('#wpd-exclusions-list').on('change', '.wpd-ex-item', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			var arr = [];
			$row.find('.wpd-ex-item:checked').each(function () {
				arr.push($(this).val());
			});
			state.exclusions[idx].exclude = arr;
		});

		$('#wpd-exclusions-list').on('click', '.wpd-ex-delete', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (confirm('この除外ルールを削除しますか？')) {
				state.exclusions.splice(idx, 1);
				renderExclusions();
			}
		});
	}

	function renderExclusions() {
		var $list = $('#wpd-exclusions-list');

		if (state.questions.length === 0) {
			$list.html('<div class="wpd-empty">先に「質問項目」タブで質問を作成してください。</div>');
			return;
		}
		if (state.items.length === 0) {
			$list.html('<div class="wpd-empty">先に「結果アイテム」タブでアイテムを追加してください。</div>');
			return;
		}
		if (state.exclusions.length === 0) {
			$list.html('<div class="wpd-empty">除外ルールがありません。「追加」ボタンから設定してください。</div>');
			return;
		}

		var html = '';
		state.exclusions.forEach(function (rule, idx) {
			var selectedQ = state.questions.find(function (q) { return q.id === rule.question_id; });

			html += '<div class="wpd-card" data-index="' + idx + '">';
			html += '  <div class="wpd-card-head">';
			html += '    <span class="wpd-card-num">ルール ' + (idx + 1) + '</span>';
			html += '    <span class="wpd-card-actions">';
			html += '      <button type="button" class="button-link wpd-ex-delete">削除</button>';
			html += '    </span>';
			html += '  </div>';
			html += '  <div class="wpd-card-body">';

			html += '    <p><strong>もし、次の質問で…</strong></p>';
			html += '    <select class="wpd-ex-question">';
			state.questions.forEach(function (q) {
				html += '<option value="' + escAttr(q.id) + '"' + (q.id === rule.question_id ? ' selected' : '') + '>'
				     +  '[' + escHtml(q.id) + '] ' + escHtml(q.label) + '</option>';
			});
			html += '    </select>';

			html += '    <p><strong>この答えが選ばれたら…</strong></p>';
			html += '    <select class="wpd-ex-value">';
			html += '      <option value="">— 選択 —</option>';
			if (selectedQ && selectedQ.options) {
				selectedQ.options.forEach(function (op) {
					html += '<option value="' + escAttr(op.value) + '"' + (op.value === rule.value ? ' selected' : '') + '>'
					     +  escHtml(op.label) + '（' + escHtml(op.value) + '）</option>';
				});
			}
			html += '    </select>';

			html += '    <p><strong>これらのアイテムを非表示にする：</strong></p>';
			html += '    <div class="wpd-ex-items">';
			state.items.forEach(function (it) {
				var checked = (rule.exclude || []).indexOf(it.id) >= 0 ? ' checked' : '';
				html += '<label class="wpd-ex-checkbox">';
				html += '  <input type="checkbox" class="wpd-ex-item" value="' + escAttr(it.id) + '"' + checked + '>';
				html += '  <span>' + escHtml(it.name) + '<small>（' + escHtml(it.id) + '）</small></span>';
				html += '</label>';
			});
			html += '    </div>';

			html += '  </div>';
			html += '</div>';
		});

		$list.html(html);
	}

	// =====================================================
	// 並び替えルール
	// =====================================================
	function bindReorders() {
		$('.wpd-add-reorder').on('click', function () {
			state.reorders.push({
				question_id: state.questions.length > 0 ? state.questions[0].id : '',
				value: '',
				order: []
			});
			renderReorders();
		});

		$('#wpd-reorders-list').on('change', '.wpd-ro-question', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			state.reorders[idx].question_id = $(this).val();
			state.reorders[idx].value = '';
			renderReorders();
		});

		$('#wpd-reorders-list').on('change', '.wpd-ro-value', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			state.reorders[idx].value = $(this).val();
		});

		// 並び順にアイテムを追加
		$('#wpd-reorders-list').on('click', '.wpd-ro-add-item', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			var id = $row.find('.wpd-ro-item-select').val();
			if (!id) return;
			if (!Array.isArray(state.reorders[idx].order)) state.reorders[idx].order = [];
			if (state.reorders[idx].order.indexOf(id) < 0) {
				state.reorders[idx].order.push(id);
			}
			renderReorders();
		});

		$('#wpd-reorders-list').on('click', '.wpd-ro-item-up', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			var oIdx = parseInt($(this).closest('.wpd-ro-order-row').data('oindex'), 10);
			var order = state.reorders[idx].order;
			if (oIdx > 0) {
				var t = order[oIdx];
				order[oIdx] = order[oIdx - 1];
				order[oIdx - 1] = t;
				renderReorders();
			}
		});

		$('#wpd-reorders-list').on('click', '.wpd-ro-item-down', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			var oIdx = parseInt($(this).closest('.wpd-ro-order-row').data('oindex'), 10);
			var order = state.reorders[idx].order;
			if (oIdx < order.length - 1) {
				var t = order[oIdx];
				order[oIdx] = order[oIdx + 1];
				order[oIdx + 1] = t;
				renderReorders();
			}
		});

		$('#wpd-reorders-list').on('click', '.wpd-ro-item-delete', function () {
			var $row = $(this).closest('.wpd-card');
			var idx = parseInt($row.data('index'), 10);
			var oIdx = parseInt($(this).closest('.wpd-ro-order-row').data('oindex'), 10);
			state.reorders[idx].order.splice(oIdx, 1);
			renderReorders();
		});

		$('#wpd-reorders-list').on('click', '.wpd-ro-delete', function () {
			var idx = parseInt($(this).closest('.wpd-card').data('index'), 10);
			if (confirm('この並び替えルールを削除しますか？')) {
				state.reorders.splice(idx, 1);
				renderReorders();
			}
		});
	}

	function renderReorders() {
		var $list = $('#wpd-reorders-list');

		if (state.questions.length === 0) {
			$list.html('<div class="wpd-empty">先に「質問項目」タブで質問を作成してください。</div>');
			return;
		}
		if (state.items.length === 0) {
			$list.html('<div class="wpd-empty">先に「結果アイテム」タブでアイテムを追加してください。</div>');
			return;
		}
		if (state.reorders.length === 0) {
			$list.html('<div class="wpd-empty">並び替えルールがありません。「追加」ボタンから設定してください。</div>');
			return;
		}

		var html = '';
		state.reorders.forEach(function (rule, idx) {
			var selectedQ = state.questions.find(function (q) { return q.id === rule.question_id; });
			var order = Array.isArray(rule.order) ? rule.order : [];

			html += '<div class="wpd-card" data-index="' + idx + '">';
			html += '  <div class="wpd-card-head">';
			html += '    <span class="wpd-card-num">ルール ' + (idx + 1) + '</span>';
			html += '    <span class="wpd-card-actions">';
			html += '      <button type="button" class="button-link wpd-ro-delete">削除</button>';
			html += '    </span>';
			html += '  </div>';
			html += '  <div class="wpd-card-body">';

			html += '    <p><strong>もし、次の質問で…</strong></p>';
			html += '    <select class="wpd-ro-question">';
			state.questions.forEach(function (q) {
				html += '<option value="' + escAttr(q.id) + '"' + (q.id === rule.question_id ? ' selected' : '') + '>'
				     +  '[' + escHtml(q.id) + '] ' + escHtml(q.label) + '</option>';
			});
			html += '    </select>';

			html += '    <p><strong>この答えが選ばれたら…</strong></p>';
			html += '    <select class="wpd-ro-value">';
			html += '      <option value="">— 選択 —</option>';
			if (selectedQ && selectedQ.options) {
				selectedQ.options.forEach(function (op) {
					html += '<option value="' + escAttr(op.value) + '"' + (op.value === rule.value ? ' selected' : '') + '>'
					     +  escHtml(op.label) + '（' + escHtml(op.value) + '）</option>';
				});
			}
			html += '    </select>';

			html += '    <p><strong>この順番で先頭から表示する：</strong></p>';
			if (order.length === 0) {
				html += '    <p class="wpd-hint">まだアイテムが指定されていません。下のセレクトから追加してください。</p>';
			}
			order.forEach(function (id, oIdx) {
				var it = state.items.find(function (i) { return i.id === id; });
				var label = it ? it.name : '（アイテムが見つかりません）';
				html += '    <div class="wpd-ro-order-row" data-oindex="' + oIdx + '">';
				html += '      <span class="wpd-ro-order-num">' + (oIdx + 1) + '.</span> ';
				html += '      <span>' + escHtml(label) + '<small>（' + escHtml(id) + '）</small></span>';
				html += '      <span class="wpd-card-actions">';
				html += '        <button type="button" class="button-link wpd-ro-item-up">▲</button>';
				html += '        <button type="button" class="button-link wpd-ro-item-down">▼</button>';
				html += '        <button type="button" class="button-link wpd-ro-item-delete">削除</button>';
				html += '      </span>';
				html += '    </div>';
			});

			var remaining = state.items.filter(function (it) { return order.indexOf(it.id) < 0; });
			if (remaining.length > 0) {
				html += '    <p>';
				html += '      <select class="wpd-ro-item-select">';
				remaining.forEach(function (it) {
					html += '<option value="' + escAttr(it.id) + '">' + escHtml(it.name) + '（' + escHtml(it.id) + '）</option>';
				});
				html += '      </select> ';
				html += '      <button type="button" class="button button-small wpd-ro-add-item">＋ 並び順に追加</button>';
				html += '    </p>';
			}
			html += '    <p class="wpd-hint">ここで指定しなかったアイテムは、指定したアイテムの後ろに元の表示順のまま続きます。</p>';

			html += '  </div>';
			html += '</div>';
		});

		$list.html(html);
	}

	// =====================================================
	// JSON 入出力ヘルパー
	// =====================================================
	function snapshotState() {
		var snap = {
			settings: readSettingsFromInputs(),
			questions:  state.questions,
			fields:     state.fields,
			items:      state.items,
			exclusions: state.exclusions,
			reorders:   state.reorders
		};
		return snap;
	}

	function readSettingsFromInputs() {
		return {
			title_text:       $('input[name="wpd_settings[title_text]"]').val() || '',
			lead_text:        $('input[name="wpd_settings[lead_text]"]').val() || '',
			cta_button_text:  $('input[name="wpd_settings[cta_button_text]"]').val() || '',
			item_button_text: $('input[name="wpd_settings[item_button_text]"]').val() || ''
		};
	}

	function applySettingsToInputs(s) {
		if (typeof s.title_text       === 'string') $('input[name="wpd_settings[title_text]"]').val(s.title_text);
		if (typeof s.lead_text        === 'string') $('input[name="wpd_settings[lead_text]"]').val(s.lead_text);
		if (typeof s.cta_button_text  === 'string') $('input[name="wpd_settings[cta_button_text]"]').val(s.cta_button_text);
		if (typeof s.item_button_text === 'string') $('input[name="wpd_settings[item_button_text]"]').val(s.item_button_text);
		if (typeof s.style === 'string' && /^[ABC]$/.test(s.style)) {
			$('input[name="wpd_settings[style]"][value="' + s.style + '"]').prop('checked', true);
		}
	}

	/**
	 * JSONファイルを読み込んでテキストエリアに反映
	 */
	function readJsonFile(file) {
		if (!file) return;
		// 拡張子/MIMEタイプの緩いチェック（警告のみ）
		var nameOk = /\.json$/i.test(file.name || '');
		var typeOk = !file.type || /json/i.test(file.type);
		var reader = new FileReader();
		reader.onload = function (e) {
			var text = e.target.result || '';
			$('.wpd-json-textarea').val(text);
			// 軽くバリデーション（パースだけ試す。エラー時はメッセージのみ）
			try {
				JSON.parse(text);
				if (!nameOk && !typeOk) {
					setJsonStatus('ファイルを読み込みました（拡張子が .json ではありませんが内容は有効なJSONでした）。「JSONを反映」で適用できます。', 'ok');
				} else {
					setJsonStatus(file.name + ' を読み込みました。「JSONを反映」で適用できます。', 'ok');
				}
			} catch (err) {
				setJsonStatus('ファイルを読み込みましたが、JSONとして無効です: ' + err.message, 'err');
			}
		};
		reader.onerror = function () {
			setJsonStatus('ファイルの読み込みに失敗しました。', 'err');
		};
		reader.readAsText(file);
	}

	function setJsonStatus(msg, kind) {
		var $s = $('.wpd-json-status');
		$s.removeClass('is-ok is-err').text(msg);
		if (kind === 'ok')  $s.addClass('is-ok');
		if (kind === 'err') $s.addClass('is-err');
	}

	/**
	 * JSON の構造を緩めに検証し、安全な形にクリーンアップする
	 */
	function validateImportData(data) {
		var errors = [];
		var warnings = [];

		if (!data || typeof data !== 'object') {
			return { errors: ['ルートはオブジェクトである必要があります'], warnings: [], cleaned: null };
		}

		// 質問
		var questions = [];
		if (Array.isArray(data.questions)) {
			data.questions.forEach(function (q, i) {
				if (!q || typeof q !== 'object') return;
				if (!q.id || !q.label) { warnings.push('質問[' + i + ']: id/labelが空のためスキップ'); return; }
				var type = (q.type === 'multi') ? 'multi' : 'single';
				var opts = [];
				if (Array.isArray(q.options)) {
					q.options.forEach(function (op) {
						if (!op || typeof op !== 'object') return;
						if (!op.value) return;
						opts.push({ value: String(op.value), label: String(op.label == null ? '' : op.label) });
					});
				}
				questions.push({ id: String(q.id), label: String(q.label), type: type, options: opts });
			});
		} else if (data.questions !== undefined) {
			errors.push('questionsは配列である必要があります');
		}

		// 表示項目
		var fields = [];
		if (Array.isArray(data.fields)) {
			data.fields.forEach(function (f, i) {
				if (!f || typeof f !== 'object') return;
				if (!f.key || !f.label) { warnings.push('表示項目[' + i + ']: key/labelが空のためスキップ'); return; }
				var ftype = (f.type === 'textarea') ? 'textarea' : 'text';
				fields.push({ key: String(f.key), label: String(f.label), type: ftype });
			});
		} else if (data.fields !== undefined) {
			errors.push('fieldsは配列である必要があります');
		}

		// 結果アイテム
		var items = [];
		if (Array.isArray(data.items)) {
			data.items.forEach(function (it, i) {
				if (!it || typeof it !== 'object') return;
				if (!it.id || !it.name) { warnings.push('結果アイテム[' + i + ']: id/nameが空のためスキップ'); return; }
				var f = {};
				if (it.fields && typeof it.fields === 'object') {
					Object.keys(it.fields).forEach(function (k) {
						f[String(k)] = String(it.fields[k] == null ? '' : it.fields[k]);
					});
				}
				var pts = [];
				if (Array.isArray(it.points)) {
					it.points.forEach(function (p) {
						pts.push(String(p == null ? '' : p));
					});
				}
				items.push({
					id:         String(it.id),
					name:       String(it.name),
					order:      parseInt(it.order, 10) || 999,
					catch:      String(it['catch'] == null ? '' : it['catch']),
					banner_url: String(it.banner_url == null ? '' : it.banner_url),
					detail_url: String(it.detail_url == null ? '' : it.detail_url),
					banner_id:  String(it.banner_id == null ? '' : it.banner_id),
					cta_id:     String(it.cta_id == null ? '' : it.cta_id),
					disabled:   !!it.disabled,
					fields:     f,
					points:     pts
				});
			});
		} else if (data.items !== undefined) {
			errors.push('itemsは配列である必要があります');
		}

		// 除外ルール
		var exclusions = [];
		if (Array.isArray(data.exclusions)) {
			data.exclusions.forEach(function (r, i) {
				if (!r || typeof r !== 'object') return;
				if (!r.question_id || !r.value) { warnings.push('除外ルール[' + i + ']: question_id/valueが空のためスキップ'); return; }
				var ex = [];
				if (Array.isArray(r.exclude)) {
					r.exclude.forEach(function (s) { ex.push(String(s)); });
				}
				exclusions.push({
					question_id: String(r.question_id),
					value:       String(r.value),
					exclude:     ex
				});
			});
		} else if (data.exclusions !== undefined) {
			errors.push('exclusionsは配列である必要があります');
		}

		// 並び替えルール
		var reorders = [];
		if (Array.isArray(data.reorders)) {
			data.reorders.forEach(function (r, i) {
				if (!r || typeof r !== 'object') return;
				if (!r.question_id || !r.value) { warnings.push('並び替えルール[' + i + ']: question_id/valueが空のためスキップ'); return; }
				var order = [];
				if (Array.isArray(r.order)) {
					r.order.forEach(function (s) { order.push(String(s)); });
				}
				reorders.push({
					question_id: String(r.question_id),
					value:       String(r.value),
					order:       order
				});
			});
		} else if (data.reorders !== undefined) {
			errors.push('reordersは配列である必要があります');
		}

		// 整合性チェック（警告のみ）
		var qIds = questions.map(function (q) { return q.id; });
		var itIds = items.map(function (it) { return it.id; });
		exclusions.forEach(function (r, i) {
			if (qIds.indexOf(r.question_id) < 0) {
				warnings.push('除外ルール[' + i + ']: question_id "' + r.question_id + '" は質問に存在しません');
			}
			r.exclude.forEach(function (s) {
				if (itIds.indexOf(s) < 0) {
					warnings.push('除外ルール[' + i + ']: exclude "' + s + '" は結果アイテムに存在しません');
				}
			});
		});
		reorders.forEach(function (r, i) {
			if (qIds.indexOf(r.question_id) < 0) {
				warnings.push('並び替えルール[' + i + ']: question_id "' + r.question_id + '" は質問に存在しません');
			}
			r.order.forEach(function (s) {
				if (itIds.indexOf(s) < 0) {
					warnings.push('並び替えルール[' + i + ']: order "' + s + '" は結果アイテムに存在しません（表示時は無視されます）');
				}
			});
		});

		return {
			errors:   errors,
			warnings: warnings,
			cleaned:  { questions: questions, fields: fields, items: items, exclusions: exclusions, reorders: reorders }
		};
	}

	// =====================================================
	// サンプル：カードローン（おまとめローン）診断
	// 画像仕様：Q1借入件数 / Q2借入総額 / Q3毎月返済 / Q4年収 / Q6重視したいこと
	//           結果6社（SMBCモビット〜住信SBI）／除外ルール3つ
	// =====================================================
	function buildCardLoanSample() {
		return {
			settings: {
				title_text:       'あなたにおすすめのおまとめローン診断',
				lead_text:        '＼ 登録不要！約1分で回答できます ／',
				cta_button_text:  '診断スタート',
				item_button_text: '詳細はこちら'
			},
			questions: [
				{ id: 'q1', label: '現在の借入件数を教えてください', type: 'single', options: [
					{ value: '1',     label: '1件' },
					{ value: '2',     label: '2件' },
					{ value: '3',     label: '3件' },
					{ value: '4plus', label: '4件以上' }
				]},
				{ id: 'q2', label: '現在の借入総額に近いものを選んでください', type: 'single', options: [
					{ value: 'lt50',    label: '〜50万円' },
					{ value: '50_100',  label: '50〜100万円' },
					{ value: '100_200', label: '100〜200万円' },
					{ value: 'gt200',   label: '200万円以上' }
				]},
				{ id: 'q3', label: '毎月の返済負担はいかがですか？', type: 'single', options: [
					{ value: 'ok',    label: '問題なく返済できている' },
					{ value: 'tough', label: '少し厳しい' },
					{ value: 'hard',  label: 'かなり厳しい' },
					{ value: 'late',  label: '延滞したことがある' }
				]},
				{ id: 'q4', label: '現在の年収を教えてください', type: 'single', options: [
					{ value: 'lt200',   label: '〜200万円' },
					{ value: '200_400', label: '200〜400万円' },
					{ value: '400_600', label: '400〜600万円' },
					{ value: 'gt600',   label: '600万円以上' }
				]},
				{ id: 'q6', label: 'おまとめローンで重視したいことは？', type: 'multi', options: [
					{ value: 'reduce_pay', label: '月々の返済を減らしたい' },
					{ value: 'low_rate',   label: '金利を下げたい' },
					{ value: 'approval',   label: '審査通過を重視したい' },
					{ value: 'discreet',   label: 'バレにくさを重視したい' }
				]}
			],
			fields: [
				{ key: 'limit',       label: 'お借入限度額', type: 'text' },
				{ key: 'rate',        label: '金利',         type: 'text' },
				{ key: 'review_time', label: '審査時間',     type: 'text' },
				{ key: 'conditions',  label: '申込条件',     type: 'textarea' }
			],
			items: [
				{
					id: 'smbc_mobit', name: 'SMBCモビット おまとめローン', order: 10,
					catch: '', banner_url: '', detail_url: '',
					fields: {
						limit: '〜800万円まで',
						rate: '3.0〜18.0％',
						review_time: '最短15分※',
						conditions: '20歳から65歳までで収入が安定している人'
					},
					points: [
						'返済期間が13年4ヶ月！月々の返済額を抑えられる',
						'クレジットカードのキャッシングもおまとめできる',
						'返済日の自由度が高いので都合に合わせて返済可能'
					]
				},
				{
					id: 'promise', name: 'プロミス おまとめローン', order: 20,
					catch: '', banner_url: '', detail_url: '',
					fields: {
						limit: '300万円まで',
						rate: '6.3〜17.8％',
						review_time: '最短3分※',
						conditions: '満20歳以上で安定した収入と返済能力がある人'
					},
					points: [
						'安定収入があればパート・アルバイトでも申込可能',
						'自動契約機からの申込で契約がスピーディー',
						'4つの期日から返済日を選んで返済忘れを防げる'
					]
				},
				{
					id: 'lake', name: 'レイク de おまとめ', order: 30,
					catch: '', banner_url: '', detail_url: '',
					fields: {
						limit: '1万円〜500万円',
						rate: '6.0〜17.5％',
						review_time: '最短10分※',
						conditions: '年齢20歳以上65歳以下で本人の収入が安定している人'
					},
					points: [
						'他社借入の確認書類が不要で申込時の手間が省ける',
						'全国に設置の自動契約機で契約可能できるので出先でも申し込める',
						'銀行カードローンやクレジットカードのおまとめもできる'
					]
				},
				{
					id: 'aiful', name: 'アイフル おまとめローン', order: 40,
					catch: '', banner_url: '', detail_url: '',
					fields: {
						limit: '1万円〜800万円',
						rate: '3.0〜17.5％',
						review_time: '最短14分※',
						conditions: '満20歳〜70歳までの収入が安定している人'
					},
					points: [
						'貸金業者以外の借り入れもおまとめできる',
						'WEB申込ができ来店する手間がかからない',
						'郵送物なしでもおまとめが可能でバレにくい'
					]
				},
				{
					id: 'yokohama', name: '横浜銀行 おまとめローン', order: 50,
					catch: '', banner_url: '', detail_url: '',
					fields: {
						limit: '10万円〜1,000万円（10万円単位）',
						rate: '年1.5〜14.6％\n（変動金利）',
						review_time: '最短即日',
						conditions: '・契約時に満20歳以上69歳以下の人\n・安定した収入のある人、およびその配偶者\n（パート・アルバイトは可。学生は不可。年収は年金も含む）\n【次の地域に居住またはお勤めの人】\n・神奈川県\n・東京都\n・群馬県（前橋市、高崎市、桐生市）'
					},
					points: [
						'申込時に口座がなくても申し込みできる',
						'リボ払いも一緒にまとめて返済可能',
						'消費者金融での借り入れもまとめて利息負担を軽減できる'
					]
				},
				{
					id: 'sbi', name: '住信SBIネット銀行 カードローン', order: 60,
					catch: '', banner_url: '', detail_url: '',
					fields: {
						limit: '10万円〜1,000万円',
						rate: '年2.64〜14.94％',
						review_time: '数日',
						conditions: '満20歳〜満65歳以下で安定継続収入がある人'
					},
					points: [
						'SBI証券の口座があれば年14.44％と低金利',
						'提携ATM手数料が無料！気にせず何度でも利用できる',
						'WEB・アプリ完結で利便性が高い'
					]
				}
			],
			exclusions: [
				{ question_id: 'q1', value: '4plus', exclude: ['yokohama', 'sbi'] },
				{ question_id: 'q3', value: 'late',  exclude: ['yokohama', 'sbi'] },
				{ question_id: 'q4', value: 'lt200', exclude: ['yokohama', 'sbi'] }
			]
		};
	}

	// ヘルパー
	function escAttr(v) {
		return String(v == null ? '' : v)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}
	function escHtml(v) {
		return String(v == null ? '' : v)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}
})(jQuery);
