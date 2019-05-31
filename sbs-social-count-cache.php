<?php
/*
Plugin Name: SBS Social Count Cache
Plugin URI: https://wordpress.org/plugins/step-by-step-social-count-cache/
Description: ソーシャルブックマークのカウントをキャッシュするプラグイン
Version: 1.7
Author: oxynotes
Author URI: http://oxynotes.com
License: GPL2

// お決まりのGPL2の文言（省略や翻訳不可）
Copyright 2015 oxy (email : oxy@oxynotes.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/




// インストールパスのディレクトリが定義されているか調べる（プラグインのテンプレ）
if ( !defined('ABSPATH') ) { exit(); }




require_once dirname(__FILE__) . '/lib/sbs_cron.php';




/**
 * インストール時と、停止時に実行される関数の定義
 * 
 * activate_plugin関数という特別な関数で呼び出されるため、
 * この関数の実行時点ではグローバル変数にアクセス権を持たない
 * インスタンスも作成されているわけではないので$thisも使えない
 * そのため初期設定の値を取りたい場合は静的変数で処理する（$sbs_db_versionなど）
 * 直接呼び出す関数public、間接的に呼び出す関数はprivateで大丈夫
 * 
 * ちなみにプラグインの更新時に呼び出されないので注意
 */
register_activation_hook( __FILE__, array( 'SBS_SocialCountCache', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SBS_SocialCountCache', 'deactivate' ) );



// クラスが定義済みか調べる
if ( !class_exists('SBS_SocialCountCache') ) {

class SBS_SocialCountCache {

	// FacebookのApp Token用
	public $sbs_facebook_app_token = "";

	// 設定画面のキャシュ期間と有効なSNSに関するユーザー設定
	public $sbs_user_settings = "";

	// テーブルアップデート用テーブルバージョンの指定
	public static $sbs_db_version = "1.1";




	/**
	 * 初期設定
	 * 
	 * cache有効期限（日数）
	 * 設定ページで入力した数値を取得
	 * 
	 * FacebookのApp Tokenも取得
	 * 
	 * add_action系もコンストラクタ内で処理する
	 */
	public function __construct() {

		$this->sbs_facebook_app_token = get_site_option('sbs_facebook_app_token');
		$this->sbs_active_sns = get_site_option('sbs_active_sns');
		$this->sbs_cache_time = get_site_option('sbs_cache_time');
		$this->sbs_original_tag = get_site_option('sbs_original_tag');
		$this->sbs_original_tag_default = get_site_option('sbs_original_tag_default');

		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_mysettings' ) );

		$SBS_Cron = new SBS_Cron();

	}




	// インストール時の初期設定
	public static function activate() {

		// カウントを保存するためのテーブルを作る（既にテーブルがある場合は作らない）
		self::create_tables();

		// インストールされているテーブルのバージョンを調べて、異なればアップデートする
		self::update_tables();

		// オプションのデフォルト値を保存する
		self::set_user_settings();

	}




	// アンインストール時の設定
	public static function deactivate() {

		// データベースとオプションを削除する
		self::uninstall();

		// プリロードのcronを削除
		$SBS_Cron = new SBS_Cron();
		$SBS_Cron->stop_cron();

	}




	/**
	 * テーブルを作る関数（アクティベーション用関数）
	 * 既にテーブルがある場合は作らない
	 */
	private static function create_tables() {
		global $wpdb;

		$sql = "";
		$charset_collate = "";

		// 接頭辞の追加（socal_count_cache）
		$table_name = $wpdb->prefix . 'socal_count_cache';

		// charsetを指定する
		if ( !empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} ";

		// 照合順序を指定する（ある場合。通常デフォルトのutf8_general_ci）
		if ( !empty($wpdb->collate) )
			$charset_collate .= "COLLATE {$wpdb->collate}";

		// SQL文でテーブルを作る
		$sql = "
			CREATE TABLE {$table_name} (
				postid bigint(20) NOT NULL,
				day datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				all_count bigint(20) DEFAULT 0,
				twitter_count bigint(20) DEFAULT 0,
				facebook_count bigint(20) DEFAULT 0,
				google_count bigint(20) DEFAULT 0,
				hatena_count bigint(20) DEFAULT 0,
				pocket_count bigint(20) DEFAULT 0,
				feedly_count bigint(20) DEFAULT 0,
				PRIMARY KEY  (postid)
			) {$charset_collate};";

		// 現在のテーブル構造を走査し比較して作成・更新してくれるdbDeltaを読み込みSQLを実行する
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		// オプションでテーブルーのバージョンを指定
		// 解説ではadd_option()となっているが、
		// update_optionを使えば、対応する名前のオプションが無い場合add_option()と同じように作成してくれるのでこちらを使う
		// ちなみにオプションは<接頭辞>optionsテーブルに保存される
		update_option( 'sbs_db_version', self::$sbs_db_version );

	} // end __create_tables




	/**
	 * テーブルアップデート用関数
	 */
	private static function update_tables() {
		global $wpdb;

		$installed_ver = get_option( "sbs_db_version" );

		// セットされているデータベースのバージョンと、
		// プラグインの先頭で指定したデータベースのバージョンを照らし合わせる
		if ( $installed_ver != self::$sbs_db_version ) {

			$sql = "";
			$charset_collate = "";

			// 接頭辞の追加（socal_count）
			$table_name = $wpdb->prefix . 'socal_count_cache';

			// charsetを指定する
			if ( !empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} ";

			// 照合順序を指定する（ある場合。通常デフォルトのutf8_general_ci）
			if ( !empty($wpdb->collate) )
				$charset_collate .= "COLLATE {$wpdb->collate}";

			// SQL文でテーブルを作る
			$sql = "
				CREATE TABLE {$table_name} (
					postid bigint(20) NOT NULL,
					day datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
					all_count bigint(20) DEFAULT 0,
					twitter_count bigint(20) DEFAULT 0,
					facebook_count bigint(20) DEFAULT 0,
					google_count bigint(20) DEFAULT 0,
					hatena_count bigint(20) DEFAULT 0,
					pocket_count bigint(20) DEFAULT 0,
					feedly_count bigint(20) DEFAULT 0,
					PRIMARY KEY  (postid)
				) {$charset_collate};";

			// 現在のテーブル構造を走査し比較して作成・更新してくれるdbDeltaを読み込みSQLを実行する
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);

			// オプションでテーブルーのバージョンを指定
			update_option( 'sbs_db_version', self::$sbs_db_version );

		} // if ( $installed_ver != $jal_db_version )
	} // end __update_tables()




	/**
	 * オプションのデフォルト値をセットする
	 */
	private static function set_user_settings() {

		// 設定のデフォルトの値
		$default_active_sns = array(
			'twitter' => 1,
			'facebook' => 1,
			'google' => 1,
			'hatena' => 1,
			'pocket' => 1,
			'feedly' => 1,
			'rss_url' => ''
		);

		$default_cache_time = array(
			'1day' => array(
				'day' => 0,
				'hour' => 0,
				'minute' => 30
			),
			'1week' => array(
				'day' => 1,
				'hour' => 0,
				'minute' => 0
			),
			'after' => array(
				'day' => 7,
				'hour' => 0,
				'minute' => 0
			)
		);

		// スクエアとバルーンタイプのデフォルト値を保存しておくための配列
		// pocketとfeedlyはカスタマイズしないため、デフォルト値を持たない
		$default_original_tag = array(
			'hatena_square' => '<li class="hatena_count"><a href="http://b.hatena.ne.jp/add?mode=confirm&url=[[url]]" class="count" target="_blank"></a><span class="count">[[count]]</span></li>',
			'twitter_square' => '<li class="twitter_count"><a href="https://twitter.com/intent/tweet?original_referer=[[url]]&text=[[title]]%20%7C%20[[site_title]]&tw_p=tweetbutton&url=[[url]]&via=[[site_title]]" class="count" target="_blank"></a><span class="count">[[count]]</span></li>',
			'google_square' => '<li class="google_count"><a href="https://plus.google.com/share?url=[[url]]" class="count" target="_blank"></a><span class="count">[[count]]</span></li>',
			'facebook_square' => '<li class="facebook_count"><a href="https://www.facebook.com/sharer/sharer.php?u=[[url]]" class="count" target="_blank"></a><span class="count">[[count]]</span></li>',
			'hatena_balloon' => '<li class="hatena_count"><div class="bubble"><a href="http://b.hatena.ne.jp/add?mode=confirm&url=[[url]]" class="count" target="_blank">[[count]]</a></div><a class="bgimage" href="http://b.hatena.ne.jp/add?mode=confirm&url=[[url]]" class="count" target="_blank"></a></li>',
			'twitter_balloon' => '<li class="twitter_count"><div class="bubble"><a href="https://twitter.com/intent/tweet?original_referer=[[url]]&text=[[title]]%20%7C%20[[site_title]]&tw_p=tweetbutton&url=[[url]]&via=[[site_title]]" class="count" target="_blank">[[count]]</a></div><a class="bgimage" href="https://twitter.com/intent/tweet?original_referer=[[url]]&text=[[title]]%20%7C%20[[site_title]]&tw_p=tweetbutton&url=[[url]]&via=[[site_title]]" class="count" target="_blank"></a></li>',
			'google_balloon' => '<li class="google_count"><div class="bubble"><a href="https://plus.google.com/share?url=[[url]]" class="count" target="_blank">[[count]]</a></div><a class="bgimage" href="https://plus.google.com/share?url=[[url]]" class="count" target="_blank"></a></li>',
			'facebook_balloon' => '<li class="facebook_count"><div class="bubble"><a href="https://www.facebook.com/sharer/sharer.php?u=[[url]]" class="count" target="_blank">[[count]]</a></div><a class="bgimage" href="https://www.facebook.com/sharer/sharer.php?u=[[url]]" class="count" target="_blank"></a></li>'
		);

		update_option( 'sbs_active_sns', $default_active_sns );
		update_option( 'sbs_cache_time', $default_cache_time );
		update_option( 'sbs_original_tag', $default_original_tag );
		update_option( 'sbs_original_tag_default', $default_original_tag ); // デフォルト値を保存するため同じものを別の設定に追加

	}




	/**
	 * プラグイン削除時の処理
	 * 
	 * 追加したテーブルと、オプションテーブルのカラムを削除
	 */
	private static function uninstall() {

	    global $wpdb;

		$table_name = $wpdb->prefix . 'socal_count_cache';

		// 有効にした時点でテーブルが無いことはありえないが一応IF EXISTSを追加
		$wpdb->query("DROP TABLE IF EXISTS $table_name");

		delete_option('sbs_db_version');
		delete_option('sbs_facebook_app_token');
		delete_option('sbs_active_sns');
		delete_option('sbs_cache_time');
		delete_option('sbs_preload');
		delete_option('sbs_delete_apc_cache');
		delete_option('sbs_original_tag');

		// APCキャッシュの削除
		self::delete_apc_cache();

	}




	/**
	 * オプションページを追加する
	 * 
	 * 第4引数はオプションページのスラッグ
	 * 第5引数の関数でオプションページを呼び出すコールバック関数を指定している
	 */
	public function add_plugin_admin_menu() {
		add_options_page(
			'SBS Social Count Cache', // page_title
			'SBS Social Count Cache', // menu_title
			'administrator', // capability
			'sbs-social-count-cache', // menu_slug
			array( $this, 'display_plugin_admin_page' ) // function
		);
	}




	/**
	 * オプションページを表示するためのphpファイルとCSSを指定
	 * add_plugin_admin_menu()で呼び出された関数
	 * 
	 * ルートディレクトリにファイルを設置すると以下のエラーで読み込めない謎
	 * Fatal error: Cannot redeclare _wp_menu_output()と出る
	 */
	public function display_plugin_admin_page() {
		include_once( 'views/options.php' );
		wp_enqueue_style( "sbs-cocial-count-cache", plugins_url( 'style/options.css', __FILE__ ) );
	}




	/**
	 * オプションページで追加するオプションの項目を追加
	 * 
	 * admin_initはオプション画面が読み込まれる前に実行される
	 * register_settingの第一引数はオプションページのスラッグ
	 * 第2引数は各オプションのnameと一致させる
	 * delete_apc_cacheという項目は無いがオプションページを更新時のコールバック関数として記述。
	 * 複数配列で入力する場合、バリデーション時Keyがnameの配列、valが入力値
	 */
	public function register_mysettings() {
		register_setting( 'sbs-social-count-cache', 'sbs_facebook_app_token', array( $this, 'token_validation' ) );
		register_setting( 'sbs-social-count-cache', 'sbs_active_sns', array( $this, 'active_sns_validation' ) );
		register_setting( 'sbs-social-count-cache', 'sbs_cache_time', array( $this, 'cache_time_validation' ) );
		register_setting( 'sbs-social-count-cache', 'sbs_preload', array( $this, 'preload_cron' ) );
		register_setting( 'sbs-social-count-cache', 'sbs_delete_apc_cache', array( $this, 'delete_apc_cache' ) );
		register_setting( 'sbs-social-count-cache', 'sbs_original_tag', array( $this, 'sbs_original_tag_validation' ) );
	}




	/**
	 * 開始ナンバーと、終了ナンバーを入れると
	 * 数字の数だけプルダウンメニューを作成する関数
	 *
	 * @since	1.0.0
	 * @param	int		開始number
	 * @param	int		開始number
	 * @param	int		初期選択値
	 * @param	str		最初の項目
	 */
	private function time_loop( $start, $end, $first = NULL, $str ){	
		echo '<option value="$str">' . $str . '</option>';
		if ( isset($first) ){
			for( $i = $start; $i <= $end; $i++ ){
				if ( $first == $i ) {
					echo '<option value="' . $i . '" selected="selected">' . $i . '</option>';
				} else {
					echo '<option value="' . $i . '">' . $i . '</option>';
				}
			}
		} else {
			for( $i = $start; $i <= $end; $i++ ){
				echo '<option value="' . $i . '">' . $i . '</option>';
			}
		}
	}




	/**
	 * 設定でプリロードにチェックが入っている場合に実行
	 *
	 * @param	int		0 or 1
	 */
	function preload_cron( $input ){	
		if ( ! empty( $input ) ){

			$SBS_Cron = new SBS_Cron();
			$SBS_Cron->start_cron();
		}
		return $input;
	}




	/**
	 * このプラグインで作成したAPCのキャッシュを削除する関数
	 * APCuだとinfoがkeyに変更されているため、APCのみにあるtypeで条件分岐
	 */
	private static function delete_apc_cache() {
		//すべてのユーザキャッシュを取得する
		if ( function_exists( 'apc_store' ) && ini_get( 'apc.enabled' ) ) { // apcモジュール読み込まれており、更に有効かどうか調べる
			$userCache = apc_cache_info('user');
			$sbs_apc_key = "sbs_db_cache_" . md5( __FILE__ ); // md5で一意性確保（作成時と合わせるべし）

			if ( isset( $userCache['cache_list'][0]["type"] ) ){ // APCの場合
				foreach($userCache['cache_list'] as $key => $cacheList){
					//  キーに$this->sbs_apc_keyが含まれているキャッシュを削除する
					if( strpos( $cacheList['info'], $sbs_apc_key ) !== false ){
						apc_delete( $cacheList['info'] );
					}
				}
			} else { // APCuの場合
				foreach($userCache['cache_list'] as $key => $cacheList){
					//  キーに$this->sbs_apc_keyが含まれているキャッシュを削除する
					if( strpos( $cacheList['key'], $sbs_apc_key ) !== false ){
						apc_delete( $cacheList['key'] );
					}
				}
			}
		}
	}




	/**
	 * SNSのオンオフ用のバリデーション関数
	 * 後にRSSフィードのURLも追加
	 * 数字はintval()で数値に変換。誤った値が入ると0になる
	 *
	 * @param	int		1か0、もしくはURL用の文字列
	 * @return	arr		返り値は1か0、もしくはURLかエラーメッセージ
	 */
	function active_sns_validation( $input ) {

		foreach( $input as $key => $val ){

			if ( $key == "rss_url" && empty( $input["rss_url"] ) ) {
			    $input = $input;
			} elseif ( $key == "rss_url" && filter_var( $val, FILTER_VALIDATE_URL ) && preg_match( '@^https?+://@i', $val ) ) {
			    $input = $input;
			} elseif ( $key == "rss_url" ) {
				$input[$key] = "url_error";
			}

			if ( $key == "twitter" || $key == "facebook" || $key == "google" || $key == "hatena" || $key == "pocket" || $key == "feedly" ){
				$input[$key] = intval( $input[$key] );
			}
		}

		return $input;
	}




	/**
	 * キャッシュ期間用のバリデーション関数
	 * intval()で数値に変換。誤った値が入ると0になる
	 *
	 * @param	int		キャッシュの期間
	 */
	function cache_time_validation( $input ) {

		foreach( $input as $key1 => $val1 ){
			foreach( $val1 as $key2 => $val2 ){
				$input[$key1][$key2] = intval( $input[$key1][$key2] );
			}
		}

		return $input;
	}




	/**
	 * Facebook App Token用のバリデーション関数
	 * 英数字と記号以外が入力されているとエラーを返す
	 * （英数字とバーティカルバーだけだと思ったらハイフンなんかもあった。
	 * どれだけあるかわからないので記号全般を許可した）
	 *
	 * @param	str		Facebook App Token
	 */
	function token_validation( $input ) {

		if( empty( $input ) || preg_match( "/^[[:graph:]|[:space:]]+$/i", $input ) ) {
			return $input;
		}else{
			return 'validation_error';
		}

	}



	/**
	 * スクエアタイプ、バルーンタイプの表示用のバリデーション関数
	 * カスタマイズ用なので基本的には大抵の入力値は許可
	 * 空だった場合はデフォルト値をセットする。
	 *
	 * @param	str		ユーザーの入力値
	 */
	function sbs_original_tag_validation( $input ) {

		if ( $input["hatena"] == "" ){
			$input["hatena"] = $this->sbs_original_tag_default["hatena"];
		}

		if ( $input["twitter"] == "" ){
			$input["twitter"] = $this->sbs_original_tag_default["twitter"];
		}

		if ( $input["google"] == "" ){
			$input["google"] = $this->sbs_original_tag_default["google"];
		}

		if ( $input["facebook"] == "" ){
			$input["facebook"] = $this->sbs_original_tag_default["facebook"];
		}

		return $input;

	}




	/**
	 * 基準の日時から有効期限を算出する
	 *
	 * @param	int		基準の時間（現在の時間）
	 * @param	int		○日
	 * @param	int		○時間
	 * @param	int		○分
	 * @return	int		返り値はUNIX Time
	 */
	public function exp_time( $current_time, $day, $hour, $minute ) {

			// 0の場合があるので、それぞれ個別に出して足してる
			$u_day = ($day * 24 * 60 * 60);
			$u_hour = ($hour * 60 * 60);
			$u_minute = ($minute * 60);
			return $current_time + ( $u_day + $u_hour + $u_minute );
	}




	/**
	 * twitterのカウントを返す jsoon APIを利用
	 *
	 * @since	1.5
	 * @param	str		投稿のURL
	 * @return	int		返り値はカウント
	 */
	public function get_twitter( $url ) {

		//$twit_uri = 'http://urls.api.twitter.com/1/urls/count.json?url=' . rawurlencode($url);
		$twit_uri = 'http://jsoon.digitiminimi.com/twitter/count.json?url=' . rawurlencode($url);

		$result = wp_remote_get( $twit_uri, array( 'timeout' => 5 ) );

		if ( !is_wp_error( $result ) && $result["response"]["code"] === 200 ) {
			$array = json_decode( $result["body"], true ); // jsonをデコード。trueで連想配列に変換
			return (int) $array["count"];
		} else { // エラー処理が面倒なので0を返す
			return 0;
		}
	}




	/**
	 * facebookのカウントを返す
	 *
	 * @param	str		投稿のURL
	 * @global	str		オプションページで設定したFacebookのApp Token
	 * @return	int		返り値はカウント
	 */
	public function get_facebook( $url ) {

		if ( empty($this->sbs_facebook_app_token) ) {
			return "エラー：設定ページでApp Tokenを入力してください";
		}

		// Facebook APIの2.4を利用した方法。アクセストークンが必要になった。
		$like_uri = 'https://graph.facebook.com/v2.4/' . rawurlencode($url) . '?access_token=' . $this->sbs_facebook_app_token;
		$result = wp_remote_get( $like_uri, array( 'timeout' => 5 ) ); // たまに異常に重い時があるので注意

		if ( !is_wp_error( $result ) && $result["response"]["code"] === 200 ) {
			$array = json_decode( $result["body"], true ); // jsonをデコード。trueで連想配列に変換

			// Facebookはカウントが存在しないURLだとNULLを返すので分岐する
			if( is_null( $array["share"]["share_count"] ) ) {
				return 0;
			} else {
				return (int) $array["share"]["share_count"];
			}
		} else { // エラー処理が面倒なので0を返す
			return 0;
		}
	}




	/**
	 * googleのカウントを返す（Googleカウント停止のためペンディング）
	 *
	 * @since	1.6.1
	 * @param	str		投稿のURL
	 * @return	int		返り値はカウント
	 */
	public function get_google( $url ){
/*
		$result = wp_remote_get( "https://plusone.google.com/_/+1/fastbutton?url=" . $url, array( 'timeout' => 5 ) );
		if ( !is_wp_error( $result ) && $result["response"]["code"] === 200 ) {
			$doc = new DOMDocument();
			libxml_use_internal_errors(true); // Warning: DOMDocument::loadHTML():対策
			$doc->loadHTML($result["body"]);
			$counter = $doc->getElementById('aggregateCount');
			return (int) $counter->nodeValue; // 仕組み上文字列になるので数列にキャスト
		} else { // エラー処理が面倒なので0を返す
			return 0;
		}
*/
			return 0;
	}




	/**
	 * はてなのカウントを返す
	 *
	 * @param	str		投稿のURL
	 * @return	int		返り値はカウント
	 */
	public function get_hatena( $url ) {
		$hate_uri = 'http://b.hatena.ne.jp/entry/jsonlite/?url=' . rawurlencode($url); //カウントのみならjsonliteのほうがより高速
		$result = wp_remote_get( $hate_uri, array( 'timeout' => 5 ) );

		if ( !is_wp_error( $result ) && $result["response"]["code"] === 200 ) {
			$array = json_decode( $result["body"], true ); // jsonをデコード。trueで連想配列に変換

			// はてなはカウントが存在しないURLだとNULLを返すので分岐する
			if( is_null( $array["count"] ) ) {
				return 0;
			} else {
				return (int) $array["count"];
			}
		} else { // エラー処理が面倒なので0を返す
			return 0;
		}
	}




	/**
	 * Pocketのカウントを返す
	 *
	 * @param	str		投稿のURL
	 * @return	int		返り値はカウント
	 */
	public function get_pocket( $url ) {
		$pocket_uri = 'https://widgets.getpocket.com/v1/button?label=pocket&count=horizontal&v=1&url=' . rawurlencode($url) . '&src=' . rawurlencode($url);
		$result = wp_remote_get( $pocket_uri, array( 'timeout' => 5 ) );

		if ( !is_wp_error( $result ) && $result["response"]["code"] === 200 ) {
			$dom = new DOMDocument('1.0', 'UTF-8');
			$dom->preserveWhiteSpace = false;
			$dom->loadHTML($result["body"]);
			$xpath = new DOMXPath($dom);
			$content = $xpath->query('//em[@id = "cnt"]')->item(0);
			return (int) $content->nodeValue;
		} else { // エラー処理が面倒なので0を返す
			return 0;
		}

	}




	/**
	 * feedlyのカウントを返す
	 *
	 * since	2017/10/01	SSL化に対応
	 * @return	int		返り値はカウント
	 */
	public function get_feedly(){

		// デフォルトはRSS2、設定でユーザーの入力値がある場合は、そのフィードをカウントする
		if ( @$this->sbs_active_sns["rss_url"] == "" ) {
			$feed_url = rawurlencode( get_bloginfo( 'rss2_url' ) );
		} else {
			$feed_url = rawurlencode( $this->sbs_active_sns["rss_url"] );
		}

		$result = wp_remote_get( 'https://cloud.feedly.com/v3/feeds/feed%2F' . $feed_url );

		$array = json_decode( $result["body"], true );

		if ( !is_wp_error( $result ) && $result["response"]["code"] === 200 ) {
			// カウントが無いと[]を返すので対策
			if( !isset( $array['subscribers'] ) ){
				return 0;
			}else{
				return (int) $array['subscribers'];
			}
		} else { // エラー処理が面倒なので0を返す
			return 0;
		}
	}




	/**
	 * カウントを取得してデータベースに保存し、カウントを返す
	 * 一つだけを呼び出した場合でもオプションページで設定したものは全部取得する
	 * @param	int			投稿のID
	 * @param	str			投稿のURL
	 * @param	str			取得するsnsの指定
	 * @return	int|arr		返り値は$active_snsで指定したSNSのカウント
	 */
	function add_cache( $postid, $url, $active_sns = null ) {

		global $wpdb;

		// テーブルの接頭辞と名前を指定
		$table_name = $wpdb->prefix . "socal_count_cache";

		$socials = array();

		if( !empty( $this->sbs_active_sns['twitter'] ) ) {
			$socials['twitter'] = $this->get_twitter($url);
		} else {
			$socials['twitter'] = 0;
		}

		if( !empty( $this->sbs_active_sns['facebook'] ) ) {
			$socials['facebook'] = $this->get_facebook($url);
		} else {
			$socials['facebook'] = 0;
		}

		if( !empty( $this->sbs_active_sns['google'] ) ) {
			$socials['google'] = $this->get_google($url);
		} else {
			$socials['google'] = 0;
		}

		if( !empty( $this->sbs_active_sns['hatena'] ) ) {
			$socials['hatena'] = $this->get_hatena($url);
		} else {
			$socials['hatena'] = 0;
		}

		if( !empty( $this->sbs_active_sns['pocket'] ) ) {
			$socials['pocket'] = $this->get_pocket($url);
		} else {
			$socials['pocket'] = 0;
		}

		if( !empty( $this->sbs_active_sns['feedly'] ) ) {
			$socials['feedly'] = $this->get_feedly();
		} else {
			$socials['feedly'] = 0;
		}

		$socials['all'] = $socials['twitter'] + $socials['facebook'] + $socials['google'] + $socials['hatena'] + $socials['pocket'];

		$now = current_time('mysql'); // ブログ時間を取得するWordPressの関数。（YYYY-MM-DD HH:MM:SS）

		// 取得したカウントを日時とともにデータベースへ書き込み
		// ON DUPLICATE KEY UPDATEでプライマリキーのpostidをフラグに無ければINSERT、あればUPDATE
		// 2017/09/29 prepareの後方参照が使えなくなった模様 対応版に変更
		$sql = "INSERT INTO {$table_name}
			(postid, day, all_count, twitter_count, facebook_count, google_count, hatena_count, pocket_count, feedly_count)
			VALUES (%d, %s, %d, %d, %d, %d, %d, %d, %d)
			ON DUPLICATE KEY UPDATE
			day = %s,
			all_count = %d,
			twitter_count = %d,
			facebook_count = %d,
			google_count = %d,
			hatena_count = %d,
			pocket_count = %d,
			feedly_count = %d;";

		$sql = $wpdb->prepare(
			$sql,
			$postid,
			$now,
			$socials['all'],
			$socials['twitter'],
			$socials['facebook'],
			$socials['google'],
			$socials['hatena'],
			$socials['pocket'],
			$socials['feedly'],
			$now,
			$socials['all'],
			$socials['twitter'],
			$socials['facebook'],
			$socials['google'],
			$socials['hatena'],
			$socials['pocket'],
			$socials['feedly']
		);

		$wpdb->query($sql);

		// 値を返すSNSを引数から指定
		// 出力用に整形
		if( $active_sns == "all" ) {
			$socials['all'] = $socials['all'];
			$socials['twitter'] = $socials['twitter'];
			$socials['facebook'] = $socials['facebook'];
			$socials['google'] = $socials['google'];
			$socials['hatena'] = $socials['hatena'];
			$socials['pocket'] = $socials['pocket'];
			$socials['feedly'] = $socials['feedly'];
		} elseif ( $active_sns == "twitter" ) {
			$socials = $socials['twitter'];
		} elseif ( $active_sns == "facebook" ) {
			$socials = $socials['facebook'];
		} elseif ( $active_sns == "google" ) {
			$socials = $socials['google'];
		} elseif ( $active_sns == "hatena" ) {
			$socials = $socials['hatena'];
		} elseif ( $active_sns == "pocket" ) {
			$socials = $socials['pocket'];
		} elseif ( $active_sns == "feedly" ) {
			$socials = $socials['feedly'];
		}

		return $socials;
	}




	/**
	 * データベースもしくはAPCから取得したキャッシュの値を元にカウントを返す
	 * @param	arr			キャッシュしたデータベースの値
	 * @param	str			取得するsnsの指定
	 * @return	int|arr		返り値は$active_snsで指定したSNSのカウント
	 */
	function get_cache( $result, $active_sns = null ) {

		// 値を返すSNSを引数から指定
		// オプションで無効なSNSはデータベースに値があっても0を返す
		// 有効期限内なのでデータベースの値をそのまま渡す
		if ( $active_sns == "all") {
			$socials = array();

			if( !empty( $this->sbs_active_sns['twitter'] ) ) { // !付きemptyなので0の場合もfalse
				$socials['twitter'] = esc_html( $result->twitter_count );
			} else {
				$socials['twitter'] = 0;
			}

			if( !empty( $this->sbs_active_sns['facebook'] ) ) {
				$socials['facebook'] = esc_html( $result->facebook_count );
			} else {
				$socials['facebook'] = 0;
			}

			if( !empty( $this->sbs_active_sns['google'] ) ) {
				$socials['google'] = esc_html( $result->google_count );
			} else {
				$socials['google'] = 0;
			}

			if( !empty( $this->sbs_active_sns['hatena'] ) ) {
				$socials['hatena'] = esc_html( $result->hatena_count );
			} else {
				$socials['hatena'] = 0;
			}

			if( !empty( $this->sbs_active_sns['pocket'] ) ) {
				$socials['pocket'] = esc_html( $result->pocket_count );
			} else {
				$socials['pocket'] = 0;
			}

			if( !empty( $this->sbs_active_sns['feedly'] ) ) {
				$socials['feedly'] = esc_html( $result->feedly_count );
			} else {
				$socials['feedly'] = 0;
			}

		$socials['all'] = $socials['twitter'] + $socials['facebook'] + $socials['google'] + $socials['hatena'] + $socials['pocket'];

		} elseif ( $active_sns == 'twitter' ) {
			if( !empty( $this->sbs_active_sns['twitter'] ) ) {
				$socials = esc_html( $result->twitter_count );
			} else {
				$socials = 0;
			}
		} elseif ( $active_sns == 'facebook' ) {
			if( !empty( $this->sbs_active_sns['facebook'] ) ) {
				$socials = esc_html( $result->facebook_count );
			} else {
				$socials = 0;
			}
		} elseif ( $active_sns == 'google' ) {
			if( !empty( $this->sbs_active_sns['google'] ) ) {
				$socials = esc_html( $result->google_count );
			} else {
				$socials = 0;
			}
		} elseif ( $active_sns == 'hatena' ) {
			if( !empty( $this->sbs_active_sns['hatena'] ) ) {
				$socials = esc_html( $result->hatena_count );
			} else {
				$socials = 0;
			}
		} elseif ( $active_sns == 'pocket' ) {
			if( !empty( $this->sbs_active_sns['pocket'] ) ) {
				$socials = esc_html( $result->pocket_count );
			} else {
				$socials = 0;
			}
		} elseif ( $active_sns == 'feedly' ) {
			if( !empty( $this->sbs_active_sns['feedly'] ) ) {
				$socials = esc_html( $result->feedly_count );
			} else {
				$socials = 0;
			}
		}

		return $socials;
	}




	/**
	 * スタイルを追加する関数
	 */
	function balloon_style() {
		// 第1引数がid、第2引数がファイル
		wp_enqueue_style( 'balloon_style', plugins_url( 'style/balloon_style.css', __FILE__) );
	}



	/**
	 * スタイルを追加する関数
	 */
	function square_style() {
		wp_enqueue_style( 'square_style', plugins_url( 'style/square_style.css', __FILE__) );
	}




	/**
	 * ブランケットのタグを実際のデータに置換する関数
	 * @param	str		SNSボタンのタグ（ブランケット）
	 * @param	int		各SNSのカウント
	 * @return	str		ブランケットのタグを置き換えたボタンのタグ

	 * since	1.5.2	エスケープ対応(＃等を含むと正常に動作しなかった)
	 */
	function tag_rep( $arg, $count = "" ) {
		$url = get_permalink();
		$site_title = get_bloginfo( 'name' );

		// html_entity_decode()を挟まないと"や'が文字参照で表示されてしまうので注意
		$title = urlencode( html_entity_decode( get_the_title(), ENT_COMPAT, 'UTF-8' ) );

		$search = array( '[[url]]', '[[site_title]]', '[[title]]', '[[count]]' );
		$replace = array( $url, $site_title, $title, $count );
		$rep_txt = str_replace( $search, $replace, $arg );

		return $rep_txt;
	}




	/**
	 * テストモード時に各パーツから受け取ったコメントを書き出す処理をまとめたもの
	 * 書き出されたコメントはコールバック関数に渡される
	 * @param	str		各コメントの要素を受け取る
	 * @return	str		コールバック関数に渡すコメント
	 * 
	 * since	1.5.3	テストモード用に追加
	 */
	function output_testmode_comment( $buffer ) {
		$url = get_permalink();
		$site_title = get_bloginfo( 'name' );

		// html_entity_decode()を挟まないと"や'が文字参照で表示されてしまうので注意
		$title = urlencode( html_entity_decode( get_the_title(), ENT_COMPAT, 'UTF-8' ) );

		$search = array( '[[url]]', '[[site_title]]', '[[title]]', '[[count]]' );
		$replace = array( $url, $site_title, $title, $count );
		$rep_txt = str_replace( $search, $replace, $arg );

		return $rep_txt;
	}





} // end class

} // if class




// インスタンスの作成（コンストラクタの実行）
$SBS_SocialCountCache = new SBS_SocialCountCache();



/**
 * テスト用のコールバックをグローバル変数に追加する
 *
 * @param	string	$key
 * @param	mixed	$callback
 * @return	void
 */
function add_callback( $key, $callback ) {
	$GLOBALS['_sbs_callbacks'][$key] = $callback;
}




/**
 * ソーシャルメディアのカウントを返す
 *
 * 投稿の更新から1日まで、1日～1週間まで、それ以降の三段階で異なるキャッシュの有効期間を持つ
 * キャッシュが有効期間の場合はデータベースに保存したキャッシュを返す
 * キャッシュが有効期限外の場合はそれぞれのAPIを利用してカウントを取得し直す
 * 
 * それぞれのキャッシュは取得日と共に<接頭辞>socal_count_cacheテーブルに保存される
 *
 * @global	object		wpdb
 * @global	array		オプションページで設定したキャッシュ期間の設定
 * @global	array		ユーザーの設定したキャッシュの有効期限
 * @param	str			取得するsnsの指定
 * @return	array|int	返り値は全てのソーシャル名とカウントの配列か数列
 */
function sbs_get_socal_count( $active_sns = null ) {

	global $wpdb;

	// ユーザー設定の取得
	$sbs = new SBS_SocialCountCache();
	$sbs_active_sns = $sbs->sbs_active_sns;
	$sbs_cache_time = $sbs->sbs_cache_time;

	// テーブルの接頭辞と名前を指定
	$table_name = $wpdb->prefix . "socal_count_cache";

	// 投稿のIDとURLを取得する
	$postid = get_the_ID();
	$url = get_permalink( $postid );

	// デバッグモード
	//$debug_mode = false;
	$debug_mode = true;

	// デバッグモード用の配列作成
	$debug_Log = array();




	// 設定ページで指定したキャッシュの有効期限を算出する（UNIX time）

	/*
	WordPressではdateやstrtotimeの使用は設定によってずれるので推奨されず、
	代わりにcurrent_timeやget_date_from_gmtの使用が推奨されている。
	また投稿の最終更新日を取得するにはget_the_modified_timeを使う。
	*/

	$pfx_date = get_the_modified_time('U'); // 投稿の最終更新日時取得
	$current_time = current_time('timestamp'); // ブログのローカルタイム取得
	$day = $current_time - (1 * 24 * 60 * 60); // ブログのローカルタイムから1日前のUNIXタイムを取得
	$week = $day - (6 * 24 * 60 * 60); // 1週間

	// 最終更新日が1日以内、1週間以内、それ以上の場合で振り分け、現時点での有効期限を算出
	if ( $pfx_date > $day ) { // 最終更新日が1日以内の場合
		$exp_time = $sbs->exp_time($current_time, $sbs_cache_time['1day']['day'], $sbs_cache_time['1day']['hour'], $sbs_cache_time['1day']['minute']);
		$setting_cache_time = ($sbs_cache_time['1day']['day'] * 24 * 60 * 60) + ($sbs_cache_time['1day']['hour'] * 60 * 60) + ($sbs_cache_time['1day']['minute'] * 60);
		$debug_Log['投稿の最終更新日から'] = "1日以内";
	} elseif( $pfx_date > $week ) { // 1週間以内
		$exp_time = $sbs->exp_time($current_time, $sbs_cache_time['1week']['day'], $sbs_cache_time['1week']['hour'], $sbs_cache_time['1week']['minute']);
		$setting_cache_time = ($sbs_cache_time['1week']['day'] * 24 * 60 * 60) + ($sbs_cache_time['1week']['hour'] * 60 * 60) + ($sbs_cache_time['1week']['minute'] * 60);
		$debug_Log['投稿の最終更新日から'] = "1日以上、1週間以内";
	} else { // それ以上
		$exp_time = $sbs->exp_time($current_time, $sbs_cache_time['after']['day'], $sbs_cache_time['after']['hour'], $sbs_cache_time['after']['minute']);
		$setting_cache_time = ($sbs_cache_time['after']['day'] * 24 * 60 * 60) + ($sbs_cache_time['after']['hour'] * 60 * 60) + ($sbs_cache_time['after']['minute'] * 60);
		$debug_Log['投稿の最終更新日から'] = "1週間以上経過";
	}

	// クエリの組み立て
	$query = "SELECT day,twitter_count,facebook_count,google_count,hatena_count,pocket_count,feedly_count FROM {$table_name} WHERE postid = {$postid}";

	// acpが有効な場合はapcにデータを保存して再利用する
	if ( function_exists( 'apc_store' ) && ini_get( 'apc.enabled' ) ) { // apcモジュール読み込まれており、更に有効かどうか、キャッシュの有効期限が切れてないか調べる
		$debug_Log['サーバのapc cache'] = "有効";
		$sbs_apc_key = "sbs_db_cache_" . md5( __FILE__ ) . $postid; // md5で一意性確保

		if ( apc_fetch( $sbs_apc_key ) ) { // キャッシュがある場合

			$result = apc_fetch( $sbs_apc_key );
			$debug_Log['apc cache'] = "見つかった";

			// キャッシュの有効期限が切れている場合（古い設定のAPCキャッシュが残っていることがあるため）
			if ( 0 > strtotime($result->day)+$setting_cache_time-$current_time ) {
				$debug_Log['apc cacheの有効性'] = "古い設定のため新しいものに更新";

				$result = $wpdb->get_row( $query );
				apc_store( $sbs_apc_key, $result, strtotime($result->day)+$setting_cache_time-$current_time ); // 面倒なので保存した後に2度目からAPCで表示するので注意（有効期限が整数になる必要があるため）
			}

		} else { // キャッシュがない場合

			$result = $wpdb->get_row( $query );
			apc_store( $sbs_apc_key, $result, strtotime($result->day)+$setting_cache_time-$current_time );
			$debug_Log['apc cache'] = "見つからない";

		}

	} else { // apcが無効な場合

		$result = $wpdb->get_row( $query );
		$debug_Log['サーバのapc cache'] = "無効";

	}

	$debug_Log['サイトのローカルタイム'] = date("Y-m-d H:i:s",$current_time);
	$debug_Log['投稿の最終更新日時'] = date("Y-m-d H:i:s",$pfx_date);
	$debug_Log['キャッシュの取得時間'] = date("Y-m-d H:i:s",strtotime($result->day));
	$debug_Log['キャッシュの有効期限'] = date("Y-m-d H:i:s",strtotime($result->day)+$setting_cache_time);
	$debug_Log['キャッシュの残り時間'] = strtotime($result->day)+$setting_cache_time-$current_time . "秒";

	// キャッシュの取得日時が有効期限内の場合（キャッシュの有効期限がある場合）
	if ( strtotime($result->day)+$setting_cache_time-$current_time > 0 ) {

		$debug_Log['キャッシュ'] = "期限内";

		// デバックモード用の出力
		if ( $debug_mode ) {
			echo "<!--" . "\n";
			echo "SBS Social Count Cacheデバックモード" . "\n";
			foreach( $debug_Log as $key => $value ) {
				echo $key . ':' . $value . "\n";
			}
			echo "-->";
		}
		// キャッシュを利用して返す
		return $sbs->get_cache( $result, $active_sns );

	} else { // 有効期限切れの場合

		$debug_Log['キャッシュ'] = "期限切れ";

		// デバックモード用の出力
		if ( $debug_mode ) {
			echo "<!--" . "\n";
			echo "SBS Social Count Cacheデバックモード" . "\n";
			foreach( $debug_Log as $key => $value ) {
				echo $key . ':' . $value . "\n";
			}
			echo "-->";
		}

		return $sbs->add_cache( $postid, $url, $active_sns );

	}




}




/**
 * Template tag - sbs_get_socal_count()を使って全てのカウントを返す
 */
function sbs_get_all() {
	return sbs_get_socal_count( 'all' );
}




/**
 * Template tag - sbs_get_socal_count()を使ってtwitterのカウントを返す
 */
function sbs_get_twitter() {
	return sbs_get_socal_count( 'twitter' );
}




/**
 * Template tag - sbs_get_socal_count()を使ってfacebookのカウントを返す
 */
function sbs_get_facebook() {
	return sbs_get_socal_count( 'facebook' );
}




/**
 * Template tag - sbs_get_socal_count()を使ってgoogleのカウントを返す
 */
function sbs_get_google() {
	return sbs_get_socal_count( 'google' );
}




/**
 * Template tag - sbs_get_socal_count()を使ってhatenaのカウントを返す
 */
function sbs_get_hatena() {
	return sbs_get_socal_count( 'hatena' );
}




/**
 * Template tag - sbs_get_socal_count()を使ってpocketのカウントを返す
 */
function sbs_get_pocket() {
	return sbs_get_socal_count( 'pocket' );
}




/**
 * Template tag - sbs_get_socal_count()を使ってfeedlyのカウントを返す
 */
function sbs_get_feedly() {
	return sbs_get_socal_count( 'feedly' );
}




/**
 * カウントの多い投稿のIDを返す
 * 
 * @global	wpdb
 * @param	str			取得するsnsの指定
 * @param	int			取得する投稿の数
 * @param	str			取得する投稿のポストタイプ
 * @return	array		投稿のID
 */
function sbs_get_popular_post( $active_sns, $page, $post_type ) {

	global $wpdb;

	// postsテーブルのIDと、socal_count_cacheテーブルのpostidでINNER JOIN
	// 指定したSNSとポストタイプの値を取得
	$posts_table_name = $wpdb->prefix . "posts";
	$scc_table_name = $wpdb->prefix . "socal_count_cache";
	$query = "
		SELECT
			{$posts_table_name}.ID,
			{$posts_table_name}.post_type,
			{$posts_table_name}.post_status,
			{$scc_table_name}.all_count,
			{$scc_table_name}.twitter_count,
			{$scc_table_name}.facebook_count,
			{$scc_table_name}.google_count,
			{$scc_table_name}.hatena_count,
			{$scc_table_name}.pocket_count,
			{$scc_table_name}.feedly_count
		FROM
			{$posts_table_name}
		INNER JOIN
			{$scc_table_name}
		ON
			{$posts_table_name}.ID = {$scc_table_name}.postid
		WHERE
			post_type = '{$post_type}'
		ORDER BY
			{$active_sns}
		DESC LIMIT
			{$page}
		";

	if ( function_exists( 'apc_store' ) && ini_get( 'apc.enabled' ) ) { // apcモジュール読み込まれており、更に有効かどうか調べる
		$sbs_apc_key = "sbs_db_cache_" . md5( __FILE__ ) . $postid . $active_sns . $post_type; // md5で一意性確保
		// var_dump("apc有効");
		if ( apc_fetch( $sbs_apc_key ) ) { // キャッシュがある場合
			$result = apc_fetch( $sbs_apc_key );
			// var_dump("apcキャッシュ見つかった");
		} else { // キャッシュがない場合（データベースのキャッシュを取得）
			$result = $wpdb->get_results( $query );
			apc_store( $sbs_apc_key , $result, 300 ); // APCの有効期限は5分
			// var_dump("apcキャッシュ見つからない");
		}
	} else {
		$result = $wpdb->get_results( $query );
		// var_dump("apc無効");
	}

	if( isset( $result ) ){ 	
		$result_arr = json_decode(json_encode($result), true);

		foreach( $result_arr as $results ){
			foreach( $results as $key => $value){
				if( $key == "ID" ) $ids[] = $value;
			}
		}
	}

	return $ids;
}




/**
 * Template tag - 全てのカウントの合計が多い順に記事のIDを返す
 * 
 * @param	int			取得する投稿の数
 * @param	str			取得するポストタイプ
 */
function sbs_get_pp_all( $page = 10, $post_type = "post" ) {

	// 一応数列にキャストしてバリデーション
	$page = intval( $page );

	// 一応ポストタイプが存在するかバリデーション
	if ( ! post_type_exists( $post_type ) ) { 
		return "エラー：誤った引数";
	}

	return sbs_get_popular_post( 'all_count', $page, $post_type );
}




/**
 * Template tag - twitterのカウントが多い順に記事のIDを返す
 * 
 * @param	int			取得する投稿の数
 * @param	str			取得するポストタイプ
 */
function sbs_get_pp_twitter( $page = 10, $post_type = "post" ) {

	$page = intval( $page );

	if ( ! post_type_exists( $post_type ) ) { 
		return "エラー：誤った引数";
	}

	return sbs_get_popular_post( 'twitter_count', $page, $post_type );
}




/**
 * Template tag - facebookのカウントが多い順に記事のIDを返す
 * 
 * @param	int			取得する投稿の数
 * @param	str			取得するポストタイプ
 */
function sbs_get_pp_facebook( $page = 10, $post_type = "post" ) {

	$page = intval( $page );

	if ( ! post_type_exists( $post_type ) ) { 
		return "エラー：誤った引数";
	}

	return sbs_get_popular_post( 'facebook_count', $page, $post_type );
}




/**
 * Template tag - googleのカウントが多い順に記事のIDを返す
 * 
 * @param	int			取得する投稿の数
 * @param	str			取得するポストタイプ
 */
function sbs_get_pp_google( $page = 10, $post_type = "post" ) {

	$page = intval( $page );

	if ( ! post_type_exists( $post_type ) ) { 
		return "エラー：誤った引数";
	}

	return sbs_get_popular_post( 'google_count', $page, $post_type );
}




/**
 * Template tag - hatenaのカウントが多い順に記事のIDを返す
 * 
 * @param	int			取得する投稿の数
 * @param	str			取得するポストタイプ
 */
function sbs_get_pp_hatena( $page = 10, $post_type = "post" ) {

	$page = intval( $page );

	if ( ! post_type_exists( $post_type ) ) { 
		return "エラー：誤った引数";
	}

	return sbs_get_popular_post( 'hatena_count', $page, $post_type );
}




/**
 * Template tag - pocketのカウントが多い順に記事のIDを返す
 * 
 * @param	int			取得する投稿の数
 * @param	str			取得するポストタイプ
 */
function sbs_get_pp_pocket( $page = 10, $post_type = "post" ) {

	$page = intval( $page );

	if ( ! post_type_exists( $post_type ) ) { 
		return "エラー：誤った引数";
	}

	return sbs_get_popular_post( 'pocket_count', $page, $post_type );
}




/**
 * Template tag - バルーンタイプのカウントを出力する
 * 引数に何も入力しないとデフォルトの順番で表示する
 * 
 * @param	args		取得するポストタイプ表示順に配列で指定する
 * 
 * @return	str			バルーン用のHTMLとCSSを出力
 */
function sbs_balloon_style( $args = false , $url = '') {

	if ( ! $args ) {
		$args = array( "hatena", "twitter", "google", "facebook", "pocket", "feedly" );
	}

	$sbs = new SBS_SocialCountCache();
	$sbs->balloon_style(); // CSSの登録

    if ($url == ''){
        $url = get_permalink();
    }
	$site_title = get_bloginfo( 'name' );
	$title = get_the_title();
	$socal_count = sbs_get_all();

	// デフォルトはRSS2、設定でユーザーの入力値がある場合は、そのフィードをカウントする
	if ( @$sbs->sbs_active_sns["rss_url"] == "" ) {
		$feed_url = get_bloginfo( 'rss2_url' );
	} else {
		$feed_url = $sbs->sbs_active_sns["rss_url"];
	}

	echo '<div class="SBS_SocialCountCache_balloon">';
	echo '<ul>';

	$pocket_default = '<li class="pocket_count"><div class="bubble"><a href="https://getpocket.com/edit.php?url=' . $url . '" class="count" target="_blank">' . $socal_count["pocket"] . '</a></div><a class="bgimage" href="https://getpocket.com/edit.php?url=' . $url . '" class="count" target="_blank"></a></li>';
	$feedly_default = '<li class="feedly_count"><div class="bubble"><a href="http://feedly.com/i/subscription/feed/' . $feed_url . '" class="count" target="_blank">' . $socal_count["feedly"] . '</a></div><a class="bgimage" href="http://feedly.com/i/subscription/feed/' . $feed_url . '" class="count" target="_blank"></a></li>';

	foreach( $args as $arg ){
		if ( $arg == "hatena" ) {
			echo $sbs->tag_rep( $sbs->sbs_original_tag['hatena_balloon'], $socal_count['hatena'] ); // ブランケットのタグを置き換えて出力
		} elseif ( $arg == "twitter" ) {
			echo $sbs->tag_rep( $sbs->sbs_original_tag['twitter_balloon'], $socal_count['twitter'] );
		} elseif ( $arg == "google" ) {
			echo $sbs->tag_rep( $sbs->sbs_original_tag['google_balloon'], "―" );
		} elseif ( $arg == "facebook" ) {
			echo $sbs->tag_rep( $sbs->sbs_original_tag['facebook_balloon'], $socal_count['facebook'] );
		} elseif ( $arg == "pocket" ) {
			echo $pocket_default;
		} elseif ( $arg == "feedly" ) {
			echo $feedly_default;
		}
	}

	echo '</ul>';
	echo '</div><!-- .SBS_SocialCountCache_balloon -->';
}




/**
 * Template tag - スクエアタイプのカウントを出力する
 * 引数に何も入力しないとデフォルトの順番で表示する
 * 
 * @param	args		取得するポストタイプ表示順に配列で指定する
 * 
 * @return	str			スクエア用のHTMLとCSSを出力
 */
function sbs_square_style( $args = false ) {

	if ( ! $args ) {
		$args = array( "hatena", "twitter", "google", "facebook", "pocket", "feedly" );
	}

	$sbs = new SBS_SocialCountCache();
	$sbs->square_style(); // CSSの登録

	$url = get_permalink();
	$site_title = get_bloginfo( 'name' );
	$title = get_the_title();
	$socal_count = sbs_get_all();

	// デフォルトはRSS2、設定でユーザーの入力値がある場合は、そのフィードをカウントする
	if ( @$sbs->sbs_active_sns["rss_url"] == "" ) {
		$feed_url = get_bloginfo( 'rss2_url' );
	} else {
		$feed_url = $sbs->sbs_active_sns["rss_url"];
	}

	$pocket_default = '<li class="pocket_count"><a href="https://getpocket.com/edit.php?url=' . $url . '" class="count" target="_blank"></a><span class="count">' . $socal_count["pocket"] . '</span></li>';
	$feedly_default = '<li class="feedly_count"><a href="http://feedly.com/i/subscription/feed/' . $feed_url . '" class="count" target="_blank"></a><span class="count">' . $socal_count["feedly"] . '</span></li>';

	echo '<div class="SBS_SocialCountCache_square">';
	echo '<ul>';

	foreach( $args as $arg ){
		if ( $arg == "hatena" ) {
			echo $sbs->tag_rep( $sbs->sbs_original_tag['hatena_square'], $socal_count['hatena'] ); // ブランケットのタグを置き換えて出力
		} elseif ( $arg == "twitter" ) {
			echo $sbs->tag_rep( $sbs->sbs_original_tag['twitter_square'], $socal_count['twitter'] );
		} elseif ( $arg == "google" ) {
			echo $sbs->tag_rep( $sbs->sbs_original_tag['google_square'] );
		} elseif ( $arg == "facebook" ) {
			echo $sbs->tag_rep( $sbs->sbs_original_tag['facebook_square'], $socal_count['facebook'] );
		} elseif ( $arg == "pocket" ) {
			echo $pocket_default;
		} elseif ( $arg == "feedly" ) {
			echo $feedly_default;
		}
	}

	echo '</ul>';
	echo '</div><!-- .SBS_SocialCountCache_square -->';
}



