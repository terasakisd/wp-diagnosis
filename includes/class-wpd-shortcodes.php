<?php
/**
 * ショートコード
 *  [wpd_diagnosis id="123"]         診断フォーム
 *  [wpd_diagnosis_result id="123"]  結果ページ
 *
 * style属性で見た目の上書きも可能: [wpd_diagnosis id="123" style="B"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPD_Shortcodes {

	public function register() {
		add_shortcode( 'wpd_diagnosis', array( $this, 'render_form' ) );
		add_shortcode( 'wpd_diagnosis_result', array( $this, 'render_result' ) );
	}

	public function render_form( $atts ) {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'wpd_diagnosis' );

		$post_id = (int) $atts['id'];
		if ( ! $post_id || get_post_type( $post_id ) !== WPD_POST_TYPE ) {
			return '<p>診断IDが指定されていません。</p>';
		}

		wp_enqueue_style( 'wpd-style' );
		wp_enqueue_script( 'wpd-script' );

		$questions = WPD_Data::get_questions( $post_id );
		$settings  = WPD_Data::get_settings( $post_id );

		$style = isset( $settings['style'] ) && in_array( $settings['style'], array( 'A', 'C', 'D' ), true )
			? $settings['style']
			: 'A';

		// 結果ページのURLをJSに渡す
		$result_page_id = (int) $settings['result_page_id'];
		$result_url     = $result_page_id ? get_permalink( $result_page_id ) : '';

		$post = get_post( $post_id );
		$title_text = ! empty( $settings['title_text'] ) ? $settings['title_text'] : $post->post_title;

		$template_path = WPD_PATH . 'templates/form-style-' . strtolower( $style ) . '.php';
		if ( ! file_exists( $template_path ) ) {
			$template_path = WPD_PATH . 'templates/form-style-a.php';
		}

		ob_start();
		include $template_path;
		$html = ob_get_clean();

		// 診断フォーム用カスタムCSS
		$custom_css = isset( $settings['form_custom_css'] ) ? trim( $settings['form_custom_css'] ) : '';
		if ( '' !== $custom_css ) {
			// サニタイズは保存時にwp_strip_all_tagsで実施済み。<style>内出力のため改めてエスケープしない。
			$html = '<style id="wpd-form-custom-css">' . $custom_css . '</style>' . $html;
		}

		// 診断フォーム用カスタムJS
		$custom_js = isset( $settings['form_custom_js'] ) ? trim( $settings['form_custom_js'] ) : '';
		if ( '' !== $custom_js ) {
			// サニタイズは保存時に</script>除去済み。<script>内出力のため改めてエスケープしない。
			$html .= '<script id="wpd-form-custom-js">' . $custom_js . '</script>';
		}

		return $html;
	}

	public function render_result( $atts ) {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'wpd_diagnosis_result' );

		$post_id = (int) $atts['id'];
		if ( ! $post_id || get_post_type( $post_id ) !== WPD_POST_TYPE ) {
			return '<p>診断IDが指定されていません。</p>';
		}

		wp_enqueue_style( 'wpd-style' );

		$answers      = $this->parse_answers_from_url( $post_id );
		$items        = WPD_Data::filter_items_by_answers( $post_id, $answers );
		$result_fields= WPD_Data::get_result_fields( $post_id );
		$settings     = WPD_Data::get_settings( $post_id );

		ob_start();
		include WPD_PATH . 'templates/result-page.php';
		$html = ob_get_clean();

		// 結果ページ用カスタムJS
		$custom_js = isset( $settings['result_custom_js'] ) ? trim( $settings['result_custom_js'] ) : '';
		if ( '' !== $custom_js ) {
			// サニタイズは保存時に</script>除去済み。<script>内出力のため改めてエスケープしない。
			$html .= '<script id="wpd-result-custom-js">' . $custom_js . '</script>';
		}

		return $html;
	}

	private function parse_answers_from_url( $post_id ) {
		$answers   = array();
		$questions = WPD_Data::get_questions( $post_id );

		foreach ( $questions as $q ) {
			$key = $q['id'];
			if ( ! isset( $_GET[ $key ] ) ) continue;
			$raw = wp_unslash( $_GET[ $key ] );

			if ( isset( $q['type'] ) && 'multi' === $q['type'] ) {
				$vals = array_filter( array_map( 'sanitize_text_field', explode( ',', $raw ) ) );
				$answers[ $key ] = $vals;
			} else {
				$answers[ $key ] = sanitize_text_field( $raw );
			}
		}
		return $answers;
	}
}
