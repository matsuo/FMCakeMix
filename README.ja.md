FMCakeMix
=========

FMCakeMixは、MVCフレームワークであるCakePHP用のFileMakerデータソースドライバー
です。FMCakeMixを利用すると、SQLデータベースと同じようにFileMakerとCakePHPを統
合できます。すなわち、モダンなWebアプリケーションフレームワークを使って、
FileMakerデータベースと連係するWebアプリケーションを迅速に開発できるようになります。

CakePHPに関する詳細については次のWebサイトを参照してください：
http://cakephp.org/

FMCakeMixの使い方についてはUser Guide.pdf（英文）を参照してください。

注意点
------

このソフトウェアは開発途上版であることに留意し、各自で検証しながら自己責任の下で使
用してください。フィードバックや不具合の修正等も歓迎しています。なお、本ドライバー
はCakePHP 2.10に対応しています（現在のところCakePHP 3.xには対応していません）。

インストール
------------

http://cakephp.org/ からCakePHPをダウンロードして、Webサイトのマニュアルにある
インストール手順にならってインストールおよび設定を行います。

FX.phpは、Chris Hansen氏が中心になって開発した、PHPからFileMaker Proデータベー
スに接続するためのライブラリクラスです。FMCakeMixは、FileMaker Proデータベースに
接続する際に内部的にFX.phpを利用しています。http://fx.iviking.org/ からFX.php
のファイルをダウンロードして、FX.phpとlibフォルダをapp/Vendorフォルダの直下に配置
します。

FileMaker Proデータベースとの接続にXMLを利用しているため、XMLを使用したカスタム
Web公開機能をサポートしているFileMaker ServerもしくはFileMaker Server Advanced
でデータベースをホストしなければなりません。手順についてはFileMaker Serverに付
属のマニュアルを参照してください。

CakePHP 2.xの場合には、app/Model/Datasource/DatabaseフォルダにFilemaker.php
ファイルを配置します。おそらくDatasourceフォルダにおいてDatabaseという名称の
ディレクトリを作成する必要があるでしょう。

FileMaker Data API ドライバ
---------------------------

XMLではなくFileMaker Data APIを使ってFileMaker Serverと通信するドライバ
FilemakerDataAPI.phpも同梱しています。このドライバは新居雅行氏が開発した
FMDataAPI（https://github.com/msyk/FMDataAPI）を利用しており、PHP 8.1以降が
必要です。XML版のドライバ（Filemaker.php）も引き続き利用でき、接続ごとに
どちらのドライバを使うかを選択できます。

必要な環境：PHP 8.1以降、およびPHP拡張の「curl」と「json」。

1. FMDataAPIをダウンロードし、srcフォルダを app/Vendor/FMDataAPI/src として
   配置します（ComposerでFMDataAPIを読み込み済みの場合は不要です）。必要な
   のは次のファイルだけで、FMDataAPIリポジトリのtestやsamplesなどのフォルダ
   は不要です。別の場所に配置する場合は、接続設定の'vendorPath'にsrcフォルダ
   のパスを指定します。

```
app/Vendor/FMDataAPI/src/
├── FMDataAPI.php
└── Supporting/
    ├── CommunicationProvider.php
    ├── FileMakerLayout.php
    └── FileMakerRelation.php
```

2. Filemaker.phpとFilemakerDataAPI.phpの両方を
   app/Model/Datasource/Databaseフォルダに配置します（FilemakerDataAPIは
   Filemakerを継承しています）。Data API版ではFX.phpは不要です。XML版の
   ドライバも使用する場合にのみ必要です。

```
app/Model/Datasource/Database/
├── Filemaker.php
└── FilemakerDataAPI.php
```

3. FileMaker ServerでData APIを有効にし、ドライバが使用するアカウントの
   拡張アクセス権「fmrest」を有効にします。
4. app/Config/database.phpで接続を定義します。

```php
public $default = array(
    'datasource' => 'Database/FilemakerDataAPI',
    'host' => 'fms.example.com',
    'port' => 443,
    'scheme' => 'https',
    'login' => 'username',
    'password' => 'password',
    'database' => 'SolutionName',
    'defaultLayout' => 'LayoutName',
    // 以下は省略可能
    // 'certValidating' => true, // サーバー証明書を検証するかどうか
    // 'timeout' => 30,          // リクエストのタイムアウト（秒）
    // 'maxRecords' => 1000000,  // limit未指定時に取得する最大レコード数
    // 'vendorPath' => '/path/to/FMDataAPI/src/',
);
```

CakePHPがFileMaker Serverと同じマシンで動作している場合は、'host'に
'localserver'を指定できます。この場合は http://127.0.0.1:3000 に直接接続し、
'port'と'scheme'の設定は使用されません。

```php
public $default = array(
    'datasource' => 'Database/FilemakerDataAPI',
    'host' => 'localserver',
    'login' => 'username',
    'password' => 'password',
    'database' => 'SolutionName',
);
```

接続設定の'defaultLayout'は省略できます。レイアウトはモデルの$defaultLayout
が優先され、モデルで指定されていない場合にのみ接続設定の値が使用されます。
どちらも指定されていない場合はエラーになります。

```php
class Article extends AppModel {
    public $defaultLayout = 'Article';
}
```

Data API版ドライバの注意点：

* 'or'を含む検索条件は個別の検索リクエストとして送信されるため、正しく論理和
  （OR）検索として動作します。
* '-script'、'-script.param'、'-script.prefind'、'-script.presort'などの
  スクリプト関連のパラメータは、Data APIの対応するパラメータに変換されます。
* XML版と同様にトランザクションには対応していません。
* FX.phpを直接使用しているコード（`new FX(...)`や`FMView()`など）は、Data API版
  では動作しません。レイアウトの値一覧を取得する場合は、
  `ConnectionManager::getDataSource($model->useDbConfig)->getValueLists('レイアウト名')`
  を使用してください。`array('値一覧名' => array('値1', ...))`の形式の配列が返り
  ます。
* XML APIと異なり、Data APIには認証なしのゲスト接続がありません。ログイン
  要求には必ずAuthorizationヘッダーが必要で、以降のリクエストではセッション
  トークン（Bearer）を使用します。'login'と'password'の両方が空の場合は、空の
  アカウント名とパスワードでBasic認証のログインを行います。Guestアカウントで
  接続できるかどうかは未検証のため、拡張アクセス権「fmrest」を有効にした
  アカウントを指定することをおすすめします。

サポート
-------

免責事項については下記のライセンス条項をご覧ください。（つまり、自力で頑張って
ということです。）

クレジット
------

* 作者：Alex Gibbons <alex_g@beezwax.net>
* メンテナー：Atsushi Matsuo <famlog@gmail.com>

謝辞
------

Thanks to [Beezwax Datatools, Inc.](http://beezwax.net)

MIT License
-----------

Copyright (c) 2009 Beezwax Datatools, Inc., Alex Gibbons

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
