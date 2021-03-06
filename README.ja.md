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
