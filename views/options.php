<div class="wrap">
<div class="sbs-cocial-count-cache">

<?php

// 古いバージョンからアップデート時のエラーメッセージ
if( empty( $this->sbs_original_tag ) ) { // 1.3.3以前のバージョンからアップデートした場合

	// オリジナルタグの項目がセットされていない場合に表示されるエラーメッセージ
	add_settings_error(
	    'sbs_original_tag-empty', // エラーのスラッグ
	    'sbs_original_tag-empty', // エラーのコード　<div>のidに割り振られる
	    __('1.3.3以前のバージョンからアップデートした場合、1度プラグインを停止し、再度有効にしてください。', 'sbs_social_count_cache'), // エラーメッセージ,ローカライゼーションする気ないので第2引数はいらない
	    'error' // メッセージタイプ。error もしくは notice
	);
	settings_errors('sbs_original_tag-empty'); // 引数でエラーのスラッグを指定するとエラーを限定できる

}

// 設定保存時のメッセージ
if( empty( $this->sbs_facebook_app_token ) ) { // FacebookのApp Tokenが設定されていない場合

	// FacebookのApp Tokenが入力されるまで表示されるエラーメッセージ
	add_settings_error(
	    'app-token-empty', // エラーのスラッグ
	    'app-token-empty', // エラーのコード　<div>のidに割り振られる
	    __('FacebookのApp Tokenを入力するまで「いいね」はカウントされません。', 'sbs_social_count_cache'), // エラーメッセージ,ローカライゼーションする気ないので第2引数はいらない
	    'error' // メッセージタイプ。error もしくは notice
	);
	settings_errors('app-token-empty'); // 引数でエラーのスラッグを指定するとエラーを限定できる

} else { // tokenが正しいかチェック

	$url = site_url(); // テスト用に適当にURLを取得
	$graph_url = 'https://graph.facebook.com/v2.4/' . rawurlencode($url) . '?access_token=' . $this->sbs_facebook_app_token;
	$result = wp_remote_get( $graph_url, array( 'timeout' => 5 ) ); // たまに異常に重い時があるので注意
	$decoded_response = json_decode( $result["body"], true ); // jsonをデコード。trueで連想配列に変換

	if( $decoded_response["error"]["type"] == "OAuthException" ) {
		// FacebookのApp Tokenが間違っている場合にエラーメッセージ
		add_settings_error(
		    'app-token-invalid',
		    'app-token-invalid',
		    __('FacebookのApp Tokenが誤っています。値を確認してください。', 'sbs_social_count_cache'),
		    'error'
		);
		settings_errors('app-token-invalid');
	}
}

if( $this->sbs_facebook_app_token == "validation_error" ) {

	// 不正な入力値が入っていた場合のエラーメッセージ
	add_settings_error(
	    'app-token-validation_error',
	    'app-token-validation_error',
	    __('有効なFacebookのApp Tokenを入力してください。', 'sbs_social_count_cache'), 
	    'error'
	);
	$this->sbs_facebook_app_token = "";
	settings_errors('app-token-validation_error');
}


if( $this->sbs_active_sns['rss_url'] == "url_error" ) {

	// 不正な入力値が入っていた場合のエラーメッセージ
	add_settings_error(
	    'rss-url-validation_error',
	    'rss-url-validation_error',
	    __('RSSのURLが誤っています。URLスキーム（http）から入力してください。', 'sbs_social_count_cache'), 
	    'error'
	);
	settings_errors('rss-url-validation_error');
}

?>

<h2>SBS Social Count Cache</h2>

<form method="post" action="options.php">

<?php
	// formのひな形
	settings_fields( 'sbs-social-count-cache' );
	do_settings_sections( 'sbs-social-count-cache' );
?>

<h3><?php _e('FacebookのApp Token', 'sbs_social_count_cache'); ?></h3>

<p><?php _e('Facebook API 2.4で「いいね」をカウントするのに必要です。<br />App Tokenについては<a href="https://developers.facebook.com/docs/facebook-login/access-tokens#apptokens"><strong>Facebookの解説ページ</strong></a>をご覧ください。', 'sbs_social_count_cache'); ?></p>

<table class="form-table">
	<tbody>
	<tr>
		<th><label>App Token</label></th>
		<td><input type="text" name="sbs_facebook_app_token" value="<?php if(isset($this->sbs_facebook_app_token)) echo esc_html( $this->sbs_facebook_app_token ); ?>" size="45"></td>
	</tr>
	</tbody>
</table>

<hr>

<h3><?php _e('FeedlyでカウントするRSSのURL', 'sbs_social_count_cache'); ?></h3>

<p><?php _e('カスタムのフィードを使用している場合はこちらでURLを入力してください。デフォルトはWordPressで自動的に生成されるRSS2のフィードを使用します。', 'sbs_social_count_cache'); ?></p>

<table class="form-table">
	<tbody>
	<tr>
		<th><label><?php _e('RSSフィードのURL', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<label>
				<input type="text" name="sbs_active_sns['rss_url']" value="<?php if(isset($this->sbs_active_sns['rss_url'])) echo esc_html( $this->sbs_active_sns['rss_url'] ); ?>" size="45">
			</label>
		</fieldset>
		</td>
		</tr>
	</tbody>
</table>

<hr>

<h3><?php _e('カウントをキャッシュするSNS', 'sbs_social_count_cache'); ?></h3>

<p><?php _e('実行速度に直結するため、カウントするSNSだけチェックしてください。', 'sbs_social_count_cache'); ?></p>

<table class="form-table">
	<tbody>
	<tr>
		<th><label><?php _e('有効にするSNS', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<label for="twitter">
				<input type="hidden" name="sbs_active_sns[twitter]" value="0">
				<input name="sbs_active_sns[twitter]" type="checkbox" id="twitter" value="1" <?php if( !empty( $this->sbs_active_sns['twitter'] ) ){ echo 'checked="checked"'; } ?>>Twitter （Twitterのカウントは事前に<a href="https://jsoon.digitiminimi.com/">widgetoon.js & count.jsoon</a>でサイト登録をする必要があります。）</input>
			</label><br>
			<label for="facebook">
				<input type="hidden" name="sbs_active_sns[facebook]" value="0">
				<input name="sbs_active_sns[facebook]" type="checkbox" id="facebook" value="1" <?php if( !empty( $this->sbs_active_sns['facebook'] ) ){ echo 'checked="checked"'; } ?>>Facebook</input>
			</label><br>
			<label for="google">
				<input type="hidden" name="sbs_active_sns[google]" value="0">
				<input name="sbs_active_sns[google]" type="checkbox" id="google" value="1" <?php if( !empty( $this->sbs_active_sns['google'] ) ){ echo 'checked="checked"'; } ?>>Google+</input>
			</label><br>
			<label for="hatena">
				<input type="hidden" name="sbs_active_sns[hatena]" value="0">
				<input name="sbs_active_sns[hatena]" type="checkbox" id="hatena" value="1" <?php if( !empty( $this->sbs_active_sns['hatena'] ) ){ echo 'checked="checked"'; } ?>><?php _e('はてなブックマーク', 'sbs_social_count_cache'); ?></input>
			</label><br>
			<label for="pocket">
				<input type="hidden" name="sbs_active_sns[pocket]" value="0">
				<input name="sbs_active_sns[pocket]" type="checkbox" id="pocket" value="1" <?php if( !empty( $this->sbs_active_sns['pocket'] ) ){ echo 'checked="checked"'; } ?>>Pocket</input>
			</label><br>
			<label for="feedly">
				<input type="hidden" name="sbs_active_sns[feedly]" value="0">
				<input name="sbs_active_sns[feedly]" type="checkbox" id="feedly" value="1" <?php if( !empty( $this->sbs_active_sns['feedly'] ) ){ echo 'checked="checked"'; } ?>>feedly</input>
			</label>
		</fieldset>

		</td>
		</tr>
	</tbody>
</table>

<hr>

<h3><?php _e('SNSのカウントをキャッシュする期間', 'sbs_social_count_cache'); ?></h3>

<p><?php _e('投稿の最終更新日から「1日以内」「1日～1週間以内」「1週間以降」の3段階でキャッシュの期間を設定します。', 'sbs_social_count_cache'); ?></p>

<table class="form-table">
	<tbody>
	<tr>
		<th scope="row"><?php _e('「1日以内」の投稿', 'sbs_social_count_cache'); ?></th>
		<td>
			<lavel for="1day-day">日：</lavel>
			<select name="sbs_cache_time[1day][day]" id="1day-day">
			<?php $this->time_loop( 0, 30, intval( $this->sbs_cache_time['1day']['day'] ), "日数" ); ?>
			</select>

			<lavel for="1day-hour">時間：</lavel>
			<select name="sbs_cache_time[1day][hour]" id="1day-hour">
			<?php $this->time_loop( 0, 24, intval( $this->sbs_cache_time['1day']['hour'] ), "時間" ); ?>
			</select>

			<lavel for="1day-minute">分：</lavel>
			<select name="sbs_cache_time[1day][minute]" id="1day-minute">
			<?php $this->time_loop( 0, 60, intval( $this->sbs_cache_time['1day']['minute'] ), "分数" ); ?>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e('「1日～1週間以内」の投稿', 'sbs_social_count_cache'); ?></th>
		<td>
			<lavel for="1week-day">日：</lavel>
			<select name="sbs_cache_time[1week][day]" id="1week-day">
			<?php $this->time_loop( 0, 30, intval( $this->sbs_cache_time['1week']['day'] ), "日数" ); ?>
			</select>

			<lavel for="1week-hour">時間：</lavel>
			<select name="sbs_cache_time[1week][hour]" id="1week-hour">
			<?php $this->time_loop( 0, 24, intval( $this->sbs_cache_time['1week']['hour'] ), "時間" ); ?>
			</select>

			<lavel for="1week-minute">分：</lavel>
			<select name="sbs_cache_time[1week][minute]" id="1week-minute">
			<?php $this->time_loop( 0, 60, intval( $this->sbs_cache_time['1week']['minute'] ), "分数" ); ?>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php _e('「1週間以上」経過した投稿', 'sbs_social_count_cache'); ?></th>
		<td>
			<lavel for="after-day">日：</lavel>
			<select name="sbs_cache_time[after][day]" id="after-day">
			<?php $this->time_loop( 0, 30, intval( $this->sbs_cache_time['after']['day'] ), "日数" ); ?>
			</select>

			<lavel for="after-hour">時間：</lavel>
			<select name="sbs_cache_time[after][hour]" id="after-hour">
			<?php $this->time_loop( 0, 24, intval( $this->sbs_cache_time['after']['hour'] ), "時間" ); ?>
			</select>

			<lavel for="after-minute">分：</lavel>
			<select name="sbs_cache_time[after][minute]" id="after-minute">
			<?php $this->time_loop( 0, 60, intval( $this->sbs_cache_time['after']['minute'] ), "分数" ); ?>
			</select>
		</td>
	</tr>
	</tbody>
</table>

<hr>

<h3><?php _e('キャッシュのプリロード', 'sbs_social_count_cache'); ?></h3>

<p><?php _e('チェックするとバックグラウンドで全ページのキャッシュを取得します。（通常はページにアクセス時にキャッシュを保存します。）', 'sbs_social_count_cache'); ?></p>

<?php if( wp_next_scheduled( 'sbs_preload_cron' ) ) { echo '<span style="color: red;">現在キャッシュのプリロード中です。</span>'; } ?>

<table class="form-table">
	<tbody>
	<tr>
		<th><label><?php _e('プリロード', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<label for="preload">
			<input type="hidden" name="sbs_preload" value="0">
			<input name="sbs_preload" id="preload" type="checkbox" value="1"<?php if( wp_next_scheduled( 'sbs_preload_cron' ) ) { echo ' disabled="disabled"'; } ?>>有効にする</input>
			</label>
		</fieldset>
		</td>
		</tr>
	</tbody>
</table>

<hr>

<h3><?php _e('バルーンタイプもしくはスクエアタイプのタグ', 'sbs_social_count_cache'); ?></h3>
<p><?php _e('sbs_balloon_style() もしくは sbs_square_style() のタグをカスタマイズします。（空欄にするとデフォルトのコードになります。）', 'sbs_social_count_cache'); ?></p>

<h4><?php _e('以下の専用タグを使用することができます。', 'sbs_social_count_cache'); ?></h4>

<ul>
<li><?php _e('[[url]] = ページのURL', 'sbs_social_count_cache'); ?></li>
<li><?php _e('[[site_title]] = サイト名', 'sbs_social_count_cache'); ?></li>
<li><?php _e('[[title]] = ページのタイトル', 'sbs_social_count_cache'); ?></li>
<li><?php _e('[[count]] = それぞれのSNSのカウント数', 'sbs_social_count_cache'); ?></li>
</ul>

<?php

	// 値が空の場合はデフォルト値を入れる
	$original_tag_arg = array(
		"hatena_balloon", "hatena_square",
		"twitter_balloon", "twitter_square",
		"google_balloon", "google_square",
		"facebook_balloon", "facebook_square"
	);

	foreach( $original_tag_arg as $original_tag ) {

		if ( $this->sbs_original_tag[$original_tag] == "" ){
			$sns_arg[$original_tag] = $this->sbs_original_tag_default[$original_tag];
		} else {
			$sns_arg[$original_tag] = $this->sbs_original_tag[$original_tag];
		}
	}

?>

<hr>

<h2><?php _e('バルーンタイプ', 'sbs_social_count_cache'); ?></h2>

<table class="form-table">
	<tbody>
	<tr>
		<th><label><?php _e('Twitter', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<?php
				// htmlの入力に対応したフォーム（値のエスケープを忘れずに）
				wp_editor( esc_html( $sns_arg['twitter_balloon'] ), 'twitter_message', array(
					'tinymce' => false,
					'quicktags' => false,
					'teeny' => false,
					'wpautop' => false,
					'media_buttons' => false,
					'textarea_name' => 'sbs_original_tag[twitter_balloon]',
					'textarea_rows' => 5
				) );
			?>
		</fieldset>
		</td>
	</tr>
	<tr>
		<th><label><?php _e('Facebook', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<?php
				wp_editor( esc_html( $sns_arg['facebook_balloon'] ), 'facebook_message', array(
					'tinymce' => false,
					'quicktags' => false,
					'teeny' => false,
					'wpautop' => false,
					'media_buttons' => false,
					'textarea_name' => 'sbs_original_tag[facebook_balloon]',
					'textarea_rows' => 3
				) );
			?>
		</fieldset>
		</td>
	</tr>
	<tr>
		<th><label><?php _e('Google+', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<?php
				wp_editor( esc_html( $sns_arg['google_balloon'] ), 'google_message', array(
					'tinymce' => false,
					'quicktags' => false,
					'teeny' => false,
					'wpautop' => false,
					'media_buttons' => false,
					'textarea_name' => 'sbs_original_tag[google_balloon]',
					'textarea_rows' => 3
				) );
			?>
		</fieldset>
		</td>
	</tr>
	<tr>
		<th><label><?php _e('はてなブックマーク', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<?php
				wp_editor( esc_html( $sns_arg['hatena_balloon'] ), 'hatena_message', array(
					'tinymce' => false,
					'quicktags' => false,
					'teeny' => false,
					'wpautop' => false,
					'media_buttons' => false,
					'textarea_name' => 'sbs_original_tag[hatena_balloon]',
					'textarea_rows' => 3
				) );
			?>
		</fieldset>
		</td>
	</tr>
	</tbody>
</table>

<hr>

<h2><?php _e('スクエアタイプ', 'sbs_social_count_cache'); ?></h2>

<table class="form-table">
	<tbody>
	<tr>
		<th><label><?php _e('Twitter', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<?php
				// htmlの入力に対応したフォーム（値のエスケープを忘れずに）
				wp_editor( esc_html( $sns_arg['twitter_square'] ), 'twitter_message', array(
					'tinymce' => false,
					'quicktags' => false,
					'teeny' => false,
					'wpautop' => false,
					'media_buttons' => false,
					'textarea_name' => 'sbs_original_tag[twitter_square]',
					'textarea_rows' => 5
				) );
			?>
		</fieldset>
		</td>
	</tr>
	<tr>
		<th><label><?php _e('Facebook', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<?php
				wp_editor( esc_html( $sns_arg['facebook_square'] ), 'facebook_message', array(
					'tinymce' => false,
					'quicktags' => false,
					'teeny' => false,
					'wpautop' => false,
					'media_buttons' => false,
					'textarea_name' => 'sbs_original_tag[facebook_square]',
					'textarea_rows' => 3
				) );
			?>
		</fieldset>
		</td>
	</tr>
	<tr>
		<th><label><?php _e('Google+', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<?php
				wp_editor( esc_html( $sns_arg['google_square'] ), 'google_message', array(
					'tinymce' => false,
					'quicktags' => false,
					'teeny' => false,
					'wpautop' => false,
					'media_buttons' => false,
					'textarea_name' => 'sbs_original_tag[google_square]',
					'textarea_rows' => 3
				) );
			?>
		</fieldset>
		</td>
	</tr>
	<tr>
		<th><label><?php _e('はてなブックマーク', 'sbs_social_count_cache'); ?></label></th>
		<td>
		<fieldset>
			<?php
				wp_editor( esc_html( $sns_arg['hatena_square'] ), 'hatena_message', array(
					'tinymce' => false,
					'quicktags' => false,
					'teeny' => false,
					'wpautop' => false,
					'media_buttons' => false,
					'textarea_name' => 'sbs_original_tag[hatena_square]',
					'textarea_rows' => 3
				) );
			?>
		</fieldset>
		</td>
	</tr>
	</tbody>
</table>

<hr>

<?php submit_button(); // 送信ボタン ?>

</form>

</div><!-- .sbs-cocial-count-cache -->
</div><!-- .wrap -->
