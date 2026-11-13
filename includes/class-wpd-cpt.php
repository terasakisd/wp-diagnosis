<?php
/**
 * 診断のカスタム投稿タイプ登録
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPD_CPT {

	public function register() {
		register_post_type(
			WPD_POST_TYPE,
			array(
				'label'        => '診断ツール',
				'labels'       => array(
					'name'               => '診断ツール',
					'singular_name'      => '診断',
					'menu_name'          => '診断ツール',
					'add_new'            => '新規追加',
					'add_new_item'       => '診断を新規追加',
					'edit_item'          => '診断を編集',
					'new_item'           => '新しい診断',
					'view_item'          => '診断を表示',
					'search_items'       => '診断を検索',
					'not_found'          => '診断が見つかりませんでした',
					'not_found_in_trash' => 'ゴミ箱に診断はありません',
					'all_items'          => 'すべての診断',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 60,
				'menu_icon'       => 'dashicons-list-view',
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'capability_type' => 'page',
				'show_in_rest'    => false,
			)
		);
	}
}
