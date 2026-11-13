/* ============================================================
 * WP 診断ツール - フロントJS
 *
 * 3スタイル（A/B/C）共通の挙動:
 *   - .wpd-form がフォームのルート
 *   - .wpd-question が質問ブロック（data-qid, data-type）
 *   - .wpd-opt が選択肢ボタン（data-value）
 *   - submit時に URLパラメータを付けて結果ページへ遷移
 * ============================================================ */
(function () {
	'use strict';

	function initForm(root) {
		var resultUrl   = root.getAttribute('data-result-url') || '';
		var displayMode = root.getAttribute('data-display-mode') || 'all';
		var questions   = root.querySelectorAll('.wpd-question');

		questions.forEach(function (qEl) {
			var type = qEl.getAttribute('data-type') || 'single';
			var opts = qEl.querySelectorAll('.wpd-opt');

			opts.forEach(function (btn) {
				btn.addEventListener('click', function () {
					if (type === 'single') {
						opts.forEach(function (b) { b.classList.remove('is-selected'); });
						btn.classList.add('is-selected');
					} else {
						btn.classList.toggle('is-selected');
					}
					// 選択したらエラー表示をクリア
					clearError(qEl);
				});
			});
		});

		var form = root.querySelector('form');
		if (!form) return;

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			submit(root, resultUrl);
		});

		if (displayMode === 'step') {
			initStepMode(root, questions);
		}
	}

	// 1問ずつ表示モード
	function initStepMode(root, questions) {
		if (!questions.length) return;
		var current = 0;
		var total   = questions.length;

		var $nav    = root.querySelector('.wpd-step-nav');
		var $cur    = root.querySelector('.wpd-step-current');
		var $bar    = root.querySelector('.wpd-step-bar-fill');
		var $prev   = root.querySelector('.wpd-step-prev');
		var $next   = root.querySelector('.wpd-step-next');
		var $submit = root.querySelector('.wpd-step-submit');

		function isAnswered(qEl) {
			return qEl.querySelectorAll('.wpd-opt.is-selected').length > 0;
		}

		function render() {
			questions.forEach(function (qEl, i) {
				if (i === current) {
					qEl.classList.add('is-current');
					qEl.removeAttribute('hidden');
				} else {
					qEl.classList.remove('is-current');
					qEl.setAttribute('hidden', 'hidden');
				}
			});
			if ($cur) $cur.textContent = String(current + 1);
			if ($bar) $bar.style.width = ((current + 1) / total * 100) + '%';
			if ($prev) $prev.disabled = (current === 0);
			var isLast = (current === total - 1);
			if ($next)   $next.style.display   = isLast ? 'none' : '';
			if ($submit) $submit.style.display = isLast ? '' : 'none';
			// フォーム上端が画面外に出ている場合だけスクロール（見えているときは動かさない）
			if (current > 0) {
				var rect = root.getBoundingClientRect();
				if (rect.top < 0) {
					window.scrollTo({top: rect.top + window.pageYOffset - 20, behavior: 'smooth'});
				}
			}
		}

		if ($next) {
			$next.addEventListener('click', function () {
				if (!isAnswered(questions[current])) {
					showError(questions[current]);
					return;
				}
				clearError(questions[current]);
				if (current < total - 1) {
					current++;
					render();
				}
			});
		}
		if ($prev) {
			$prev.addEventListener('click', function () {
				if (current > 0) {
					current--;
					render();
				}
			});
		}
		render();
	}

	function clearError(qEl) {
		qEl.classList.remove('wpd-question-error');
		var msg = qEl.querySelector('.wpd-question-error-msg');
		if (msg) msg.remove();
	}

	function showError(qEl) {
		if (qEl.classList.contains('wpd-question-error')) return;
		qEl.classList.add('wpd-question-error');
		var msg = document.createElement('p');
		msg.className = 'wpd-question-error-msg';
		msg.setAttribute('role', 'alert');
		msg.textContent = '※この質問を選択してください';
		qEl.appendChild(msg);
	}

	function submit(root, resultUrl) {
		var params = [];
		var questions = root.querySelectorAll('.wpd-question');
		var firstUnanswered = null;
		var unansweredCount = 0;

		questions.forEach(function (qEl) {
			var qid  = qEl.getAttribute('data-qid');
			var type = qEl.getAttribute('data-type') || 'single';
			var selected = qEl.querySelectorAll('.wpd-opt.is-selected');

			if (selected.length === 0) {
				unansweredCount++;
				showError(qEl);
				if (!firstUnanswered) firstUnanswered = qEl;
				return;
			}
			clearError(qEl);

			var values = [];
			selected.forEach(function (b) { values.push(b.getAttribute('data-value')); });

			if (type === 'multi') {
				params.push(encodeURIComponent(qid) + '=' + encodeURIComponent(values.join(',')));
			} else {
				params.push(encodeURIComponent(qid) + '=' + encodeURIComponent(values[0]));
			}
		});

		if (unansweredCount > 0) {
			if (firstUnanswered) {
				firstUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
			return;
		}

		if (!resultUrl) {
			alert('診断結果ページが設定されていません。管理画面の「基本設定」で結果ページを選択してください。');
			return;
		}

		var sep = resultUrl.indexOf('?') >= 0 ? '&' : '?';
		var url = params.length ? resultUrl + sep + params.join('&') : resultUrl;
		window.location.href = url;
	}

	function initAll() {
		document.querySelectorAll('.wpd-form').forEach(initForm);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
