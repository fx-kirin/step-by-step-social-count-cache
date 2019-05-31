=== Step by Step Social Count Cache ===
Contributors: oxynotes
Donate link: https://wordpress.org/plugins/step-by-step-social-count-cache/
Tags: cache, count, sns, social
Requires at least: 4.2.4
Tested up to: 4.8.2
Stable tag: 1.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SNSのカウントを3段階に分けてキャッシュするプラグインです。

== Description ==

Step by Step Social Count CacheはSNSのカウントをキャッシュするプラグインです。

投稿の最終更新日から「**1日**」「**1日～1週間**」「**1週間以降**」の3つの段階で、キャッシュの有効期限を設定することができます。

カウントを取得できるSNSは**Twitter**、**Facebook**、**はてなブックマーク**、**Pocket**、**feedly**の6つです。
（Twitterのカウントはcount.jsoon APIを利用しています。カウントを有効にするには、事前に[widgetoon.js & count.jsoon](https://jsoon.digitiminimi.com/)でサイト登録をする必要があります。）

デフォルトの有効期限は「**1日以内**」の場合は**30分**。
「**1日～7日以内**」の場合は**1日**。
「**7日以降**」の場合は**1週間**となっています。
それぞれの有効期限はオプションページで変更が可能です。

詳しい使い方や解説は[作者の解説ページ](http://oxynotes.com/?p=9200)をご覧ください。

= カウントを表示する方法 =

投稿のキャッシュを全て取得して書き出す方法（こちらがおすすめ）

`<?php
	$socal_count = sbs_get_all();
	echo $socal_count["all"];
	echo $socal_count["twitter"];
	echo $socal_count["facebook"];
	echo $socal_count["hatena"];
	echo $socal_count["pocket"];
	echo $socal_count["feedly"];
?>`

もしくは個別に取得して書き出す方法

`<?php
	echo sbs_get_twitter();
	echo sbs_get_facebook();
	echo sbs_get_hatena();
	echo sbs_get_pocket();
	echo sbs_get_feedly();
?>`

= デザインされたSNSボタンを表示する方法 =

それぞれのタグは編集画面でカスタマイズすることができます。

**バルーンタイプ**

`<?php
	$args = array( "hatena", "twitter", "facebook", "pocket", "feedly" );
	sbs_balloon_style( $args );
?>`

**スクエアタイプ**

`<?php
	$args = array( "hatena", "twitter", "facebook", "pocket", "feedly" );
	sbs_square_style( $args );
?>`

= カウントの多い投稿のIDを取得する方法 =

SNSのカウントが多い順に投稿を表示する際に利用してください。

`<?php
	sbs_get_pp_all( $page, $post_type );
	sbs_get_pp_twitter( $page, $post_type );
	sbs_get_pp_facebook( $page, $post_type );
	sbs_get_pp_hatena( $page, $post_type );
	sbs_get_pp_pocket( $page, $post_type );
?>`

Facebookのいいねを取得する際に、Facebook API 2.4を利用するためApp Tokenの入力が必要です。

feedlyでカウントするフィードはRSS2です。カスタムのFeedを使用したい場合は設定画面で指定することができます。

設定画面でキャッシュを事前に作成するプリロードが可能です。


== Installation ==

1. プラグインの新規追加ボタンをクリックして、検索窓に「SBS Social Count Cache」と入力して「今すぐインストール」をクリックします。
1. もしくはこのページのzipファイルをダウンロードして解凍したフォルダを`/wp-content/plugins/`ディレクトリに保存します。
1. 設定画面のプラグインで **SBS Social Count Cache** を有効にしてください。

== Frequently asked questions ==

-

== Screenshots ==

1. Balloon type. Square type.
2. Option page.
3. Option page.

== Changelog ==

1.7
Google+はカウントを非表示へ仕様変更したため削除。（タグは使えますが、カウントの保存・表示はされません。）
WordPressのプレースホルダ仕様変更により修正。
feedlyのSSL化へ対応。

1.6
PocketのSSL化への対応。
キャッシュ周りの調整。
デバッグモードの実装。

1.5.2
タグのカスタマイズ時に生成されるURLをエンティティ化。FacebookのAccess Tokenが誤っている場合、エラーメッセージを表示するように変更。

1.5
count.jsoon APIを利用してTwitterのカウントを取得可能に変更。

1.4
バルーンタイプ、スクエアタイプのタグをカスタマイズ可能に変更。

1.3.3
Twitterのリンク修正。CSS微調整。Tokenのバリデーション変更。

1.3.2
細かなバグの修正。

1.3
Twitterのカウントを暫定的に停止。バルーンタイプとスクエアタイプの出力を追加。

1.2.1
細かなコードの最適化。4.3.1での動作確認。

1.2
カスタムのRSSを入力できるように変更。プリロード機能を追加。カウントの多い順に投稿のIDを取得できるように変更。

1.1.1
APCモジュールが無効になっている場合のバグを修正。

1.1
feedlyでカウントするフィードを選択可能に変更。APCもしくはAPCuが有効な場合、クエリをメモリ上に展開。他、バグ修正。

1.0
初めのバージョン。


== Upgrade notice ==

-