<?php
/**
 * 診断データの保存・取得・絞り込み
 *
 * 投稿メタ:
 *   _wpd_questions       : 質問配列
 *   _wpd_result_fields   : 表示項目定義（金利、限度額…のような項目名）
 *   _wpd_result_items    : 結果アイテム配列（金融機関等）
 *   _wpd_exclusions      : 除外ルール配列
 *   _wpd_reorders        : 並び替えルール配列
 *   _wpd_settings        : 基本設定（style, result_page_id, etc）
 *
 * 構造:
 *   questions = [
 *     { id: 'q1', label: '...', type: 'single'|'multi',
 *       options: [ {value: 'v', label: 'l'}, ... ] },
 *     ...
 *   ]
 *   result_fields = [
 *     { key: 'rate', label: '金利', type: 'text' },
 *     { key: 'conditions', label: '申込条件', type: 'textarea' },
 *     ...
 *   ]
 *   ※ type は UI レンダリング用のヒント（text = 1行 / textarea = 複数行）。
 *     ストレージは常に改行を保持するサニタイズを行う。
 *   result_items = [
 *     { id: 'acom', name: 'アコム', order: 10,
 *       fields: { rate: '2.4〜17.9%', limit: '1〜800万円', ... },
 *       banner_url: '...', detail_url: '...', catch: '...',
 *       points: [ '...', '...' ] },
 *     ...
 *   ]
 *   exclusions = [
 *     { question_id: 'q1', value: '4plus', exclude: ['yokohama','sbi'] }
 *   ]
 *   reorders = [
 *     { question_id: 'q2', value: 'must_avoid', order: ['acom','lake'] }
 *   ]
 *   ※ 回答が一致したら order の並びで先頭から表示。order に無いアイテムは
 *     元の順序のまま後ろに続く。複数ルールに一致しても先頭の1件のみ適用。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPD_Data {

	const META_QUESTIONS = '_wpd_questions';
	const META_FIELDS    = '_wpd_result_fields';
	const META_ITEMS     = '_wpd_result_items';
	const META_EXCLUSION = '_wpd_exclusions';
	const META_REORDER   = '_wpd_reorders';
	const META_SETTINGS  = '_wpd_settings';

	/**
	 * 質問の取得
	 */
	public static function get_questions( $post_id ) {
		$v = get_post_meta( $post_id, self::META_QUESTIONS, true );
		return is_array( $v ) ? $v : array();
	}

	public static function set_questions( $post_id, $questions ) {
		update_post_meta( $post_id, self::META_QUESTIONS, self::sanitize_questions( $questions ) );
	}

	public static function get_result_fields( $post_id ) {
		$v = get_post_meta( $post_id, self::META_FIELDS, true );
		return is_array( $v ) ? $v : array();
	}

	public static function set_result_fields( $post_id, $fields ) {
		update_post_meta( $post_id, self::META_FIELDS, self::sanitize_fields( $fields ) );
	}

	public static function get_result_items( $post_id ) {
		$v = get_post_meta( $post_id, self::META_ITEMS, true );
		return is_array( $v ) ? $v : array();
	}

	public static function set_result_items( $post_id, $items ) {
		update_post_meta( $post_id, self::META_ITEMS, self::sanitize_items( $items ) );
	}

	public static function get_exclusions( $post_id ) {
		$v = get_post_meta( $post_id, self::META_EXCLUSION, true );
		return is_array( $v ) ? $v : array();
	}

	public static function set_exclusions( $post_id, $rules ) {
		update_post_meta( $post_id, self::META_EXCLUSION, self::sanitize_exclusions( $rules ) );
	}

	public static function get_reorders( $post_id ) {
		$v = get_post_meta( $post_id, self::META_REORDER, true );
		return is_array( $v ) ? $v : array();
	}

	public static function set_reorders( $post_id, $rules ) {
		update_post_meta( $post_id, self::META_REORDER, self::sanitize_reorders( $rules ) );
	}

	public static function get_settings( $post_id ) {
		$v = get_post_meta( $post_id, self::META_SETTINGS, true );
		if ( ! is_array( $v ) ) $v = array();
		$defaults = array(
			'style'              => 'A',
			'result_style'       => 'A', // 結果ページの見た目：A/C/D（フォームと独立）
			'display_mode'       => 'all', // 'all' = 全問一覧 / 'step' = 1問ずつ
			'result_page_id'     => 0,
			'lead_text'          => '＼ 登録不要！約1分で回答できます ／',
			'title_text'         => '',
			'cta_button_text'    => '診断スタート',
			'item_button_text'   => '公式サイトで申し込む',
			'form_custom_css'    => '',
			'form_custom_js'     => '',
			'result_custom_css'  => '',
			'result_custom_js'   => '',
		);
		return wp_parse_args( $v, $defaults );
	}

	public static function set_settings( $post_id, $settings ) {
		$clean = array(
			'style'             => in_array( $settings['style'] ?? 'A', array( 'A', 'C', 'D' ), true ) ? $settings['style'] : 'A',
			'result_style'      => in_array( $settings['result_style'] ?? 'A', array( 'A', 'C', 'D' ), true ) ? $settings['result_style'] : 'A',
			'display_mode'      => in_array( $settings['display_mode'] ?? 'all', array( 'all', 'step' ), true ) ? $settings['display_mode'] : 'all',
			'result_page_id'    => (int) ( $settings['result_page_id'] ?? 0 ),
			'lead_text'         => sanitize_text_field( $settings['lead_text'] ?? '' ),
			'title_text'        => sanitize_text_field( $settings['title_text'] ?? '' ),
			'cta_button_text'   => sanitize_text_field( $settings['cta_button_text'] ?? '診断スタート' ),
			'item_button_text'  => sanitize_text_field( $settings['item_button_text'] ?? '公式サイトで申し込む' ),
			'form_custom_css'   => self::sanitize_custom_css( $settings['form_custom_css'] ?? '' ),
			'form_custom_js'    => self::sanitize_custom_js( $settings['form_custom_js'] ?? '' ),
			'result_custom_css' => self::sanitize_custom_css( $settings['result_custom_css'] ?? '' ),
			'result_custom_js'  => self::sanitize_custom_js( $settings['result_custom_js'] ?? '' ),
		);
		update_post_meta( $post_id, self::META_SETTINGS, $clean );
	}

	/**
	 * カスタムCSSのサニタイズ：HTMLタグ（特に</style>や<script>）を除去して
	 * styleブロックを抜け出せないようにする。CSS文法のチェックはしない。
	 */
	private static function sanitize_custom_css( $css ) {
		if ( ! is_string( $css ) ) return '';
		$css = wp_strip_all_tags( $css );
		// <style> ブロックの早期終了を防ぐため、念のため</style>相当のシーケンスも除去。
		$css = preg_replace( '#</\s*style\s*>#i', '', $css );
		return trim( $css );
	}

	/**
	 * カスタムJSのサニタイズ：</script>で早期終了できないように除去。
	 * 中身のJSはユーザーがそのまま実行することを意図しているのでタグ以外は保持。
	 */
	private static function sanitize_custom_js( $js ) {
		if ( ! is_string( $js ) ) return '';
		// </script>相当を除去（大小文字・空白違い含む）
		$js = preg_replace( '#</\s*script\s*>#i', '', $js );
		return trim( $js );
	}

	/**
	 * サニタイズ
	 */
	private static function sanitize_questions( $questions ) {
		if ( ! is_array( $questions ) ) return array();
		$out = array();
		foreach ( $questions as $q ) {
			if ( empty( $q['id'] ) || empty( $q['label'] ) ) continue;
			$opts = array();
			if ( ! empty( $q['options'] ) && is_array( $q['options'] ) ) {
				foreach ( $q['options'] as $op ) {
					if ( empty( $op['value'] ) || ! isset( $op['label'] ) ) continue;
					$opts[] = array(
						'value' => sanitize_key( $op['value'] ),
						'label' => sanitize_text_field( $op['label'] ),
					);
				}
			}
			$out[] = array(
				'id'      => sanitize_key( $q['id'] ),
				'label'   => sanitize_text_field( $q['label'] ),
				'type'    => in_array( $q['type'] ?? 'single', array('single','multi'), true ) ? $q['type'] : 'single',
				'options' => $opts,
			);
		}
		return $out;
	}

	private static function sanitize_fields( $fields ) {
		if ( ! is_array( $fields ) ) return array();
		$out = array();
		foreach ( $fields as $f ) {
			if ( empty( $f['key'] ) || empty( $f['label'] ) ) continue;
			$type = isset( $f['type'] ) && 'textarea' === $f['type'] ? 'textarea' : 'text';
			$out[] = array(
				'key'   => sanitize_key( $f['key'] ),
				'label' => sanitize_text_field( $f['label'] ),
				'type'  => $type,
			);
		}
		return $out;
	}

	private static function sanitize_items( $items ) {
		if ( ! is_array( $items ) ) return array();
		$out = array();
		foreach ( $items as $it ) {
			if ( empty( $it['id'] ) || empty( $it['name'] ) ) continue;
			$fields = array();
			if ( ! empty( $it['fields'] ) && is_array( $it['fields'] ) ) {
				foreach ( $it['fields'] as $k => $v ) {
					// 改行を許容（複数行の申込条件などに対応）。タグはストリップされる。
					$fields[ sanitize_key( $k ) ] = sanitize_textarea_field( $v );
				}
			}
			$points = array();
			if ( ! empty( $it['points'] ) && is_array( $it['points'] ) ) {
				foreach ( $it['points'] as $p ) {
					$p = sanitize_text_field( $p );
					if ( '' !== $p ) $points[] = $p;
				}
			}
			$out[] = array(
				'id'         => sanitize_key( $it['id'] ),
				'name'       => sanitize_text_field( $it['name'] ),
				'order'      => isset( $it['order'] ) ? (int) $it['order'] : 999,
				'catch'      => sanitize_text_field( $it['catch'] ?? '' ),
				'banner_url' => esc_url_raw( $it['banner_url'] ?? '' ),
				'detail_url' => esc_url_raw( $it['detail_url'] ?? '' ),
				// アンカー用HTML id（任意）。a-z, A-Z, 0-9, -, _ のみ許可。
				'banner_id'  => sanitize_html_class( $it['banner_id'] ?? '' ),
				'cta_id'     => sanitize_html_class( $it['cta_id'] ?? '' ),
				// 非掲載フラグ。trueなら結果ページに表示しない（データは保持）。
				'disabled'   => ! empty( $it['disabled'] ),
				'fields'     => $fields,
				'points'     => $points,
			);
		}
		return $out;
	}

	private static function sanitize_exclusions( $rules ) {
		if ( ! is_array( $rules ) ) return array();
		$out = array();
		foreach ( $rules as $r ) {
			if ( empty( $r['question_id'] ) || empty( $r['value'] ) ) continue;
			$exclude = array();
			if ( ! empty( $r['exclude'] ) && is_array( $r['exclude'] ) ) {
				foreach ( $r['exclude'] as $slug ) {
					$exclude[] = sanitize_key( $slug );
				}
			}
			$out[] = array(
				'question_id' => sanitize_key( $r['question_id'] ),
				'value'       => sanitize_key( $r['value'] ),
				'exclude'     => $exclude,
			);
		}
		return $out;
	}

	private static function sanitize_reorders( $rules ) {
		if ( ! is_array( $rules ) ) return array();
		$out = array();
		foreach ( $rules as $r ) {
			if ( empty( $r['question_id'] ) || empty( $r['value'] ) ) continue;
			$order = array();
			if ( ! empty( $r['order'] ) && is_array( $r['order'] ) ) {
				foreach ( $r['order'] as $slug ) {
					$order[] = sanitize_key( $slug );
				}
			}
			$out[] = array(
				'question_id' => sanitize_key( $r['question_id'] ),
				'value'       => sanitize_key( $r['value'] ),
				'order'       => $order,
			);
		}
		return $out;
	}

	/**
	 * 回答に基づいて結果アイテムを絞り込み
	 */
	public static function filter_items_by_answers( $post_id, $answers ) {
		$items = self::get_result_items( $post_id );
		$rules = self::get_exclusions( $post_id );

		$excluded = array();
		foreach ( $rules as $rule ) {
			if ( empty( $rule['question_id'] ) || ! isset( $answers[ $rule['question_id'] ] ) ) continue;
			$ans = $answers[ $rule['question_id'] ];
			$ans_values = is_array( $ans ) ? $ans : array( $ans );
			if ( in_array( $rule['value'], $ans_values, true ) ) {
				foreach ( (array) $rule['exclude'] as $slug ) {
					$excluded[ $slug ] = true;
				}
			}
		}

		$items = array_filter( $items, function( $it ) use ( $excluded ) {
			// 非掲載フラグが立っているものは除外
			if ( ! empty( $it['disabled'] ) ) return false;
			return empty( $excluded[ $it['id'] ] );
		});

		usort( $items, function( $a, $b ) {
			return ( (int) ( $a['order'] ?? 999 ) ) - ( (int) ( $b['order'] ?? 999 ) );
		});

		$items = self::apply_reorders( array_values( $items ), self::get_reorders( $post_id ), $answers );

		return array_values( $items );
	}

	/**
	 * 並び替えルールの適用。回答に一致する最初のルール1件のみ使う。
	 * order に無いアイテムは元の順序のまま後ろへ、order 内の未知のidは無視。
	 */
	private static function apply_reorders( $items, $rules, $answers ) {
		foreach ( $rules as $rule ) {
			if ( empty( $rule['question_id'] ) || ! isset( $answers[ $rule['question_id'] ] ) ) continue;
			$ans = $answers[ $rule['question_id'] ];
			$ans_values = is_array( $ans ) ? $ans : array( $ans );
			if ( ! in_array( $rule['value'], $ans_values, true ) ) continue;

			$rest = array();
			foreach ( $items as $it ) {
				$rest[ $it['id'] ] = $it;
			}
			$head = array();
			foreach ( (array) ( $rule['order'] ?? array() ) as $slug ) {
				if ( isset( $rest[ $slug ] ) ) {
					$head[] = $rest[ $slug ];
					unset( $rest[ $slug ] );
				}
			}
			return array_merge( $head, array_values( $rest ) );
		}
		return $items;
	}
}
