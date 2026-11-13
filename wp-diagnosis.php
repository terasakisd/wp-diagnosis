<?php
/**
 * Plugin Name: WP 診断ツール
 * Plugin URI:  https://example.com/
 * Description: 複数の診断を作成できるWordPressプラグイン。ボタン式の質問に答えるとおすすめの結果を表示します。カードローン診断、保険診断など、テーマを問わず横展開できます。
 * Version:     1.0.34
 * Author:      Your Name
 * Text Domain: wp-diagnosis
 * License:     GPL v2 or later
 *
 * 主な構造:
 *   - カスタム投稿タイプ「diagnosis」: 1投稿 = 1診断
 *   - 投稿メタにJSONで「質問・表示項目定義・結果アイテム・除外ルール・並び替えルール」を保持
 *   - ショートコード [wpd_diagnosis id="N"] / [wpd_diagnosis_result id="N"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPD_VERSION', '1.0.34' );
define( 'WPD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPD_URL', plugin_dir_url( __FILE__ ) );
define( 'WPD_POST_TYPE', 'wpd_diagnosis' );

require_once WPD_PATH . 'includes/class-wpd-cpt.php';
require_once WPD_PATH . 'includes/class-wpd-data.php';
require_once WPD_PATH . 'includes/class-wpd-shortcodes.php';
require_once WPD_PATH . 'includes/class-wpd-admin.php';

function wpd_init() {
	// CPT登録
	$cpt = new WPD_CPT();
	$cpt->register();

	// ショートコード
	$sc = new WPD_Shortcodes();
	$sc->register();

	// 管理画面メタボックス
	if ( is_admin() ) {
		$admin = new WPD_Admin();
		$admin->register();
	}
}
add_action( 'init', 'wpd_init' );

function wpd_enqueue_assets() {
	wp_register_style(
		'wpd-style',
		WPD_URL . 'assets/css/wpd.css',
		array(),
		WPD_VERSION
	);
	wp_register_script(
		'wpd-script',
		WPD_URL . 'assets/js/wpd.js',
		array(),
		WPD_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'wpd_enqueue_assets' );

/**
 * 有効化フック: パーマリンクをフラッシュ
 */
function wpd_activate() {
	$cpt = new WPD_CPT();
	$cpt->register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpd_activate' );

function wpd_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wpd_deactivate' );
