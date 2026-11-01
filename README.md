FMCakeMix
=========

FMCakeMix is a FileMaker datasource driver for the CakePHP MVC framework.
FMCakeMix enables FileMaker databases to integrate into Cake as if they were
native SQL based sources, allowing for rapid development of FileMaker based web
solutions in a modern web application development framework.

To get more familiar with CakePHP visit: http://cakephp.org/

See the User Guide.pdf for usage information

Notice
------

This product should be considered a work in progress, please test and use at 
your own risk and contribute back any changes or fixes you may have. This 
driver is compatible with CakePHP 2.10 (This driver doesn't support CakePHP 
3.x now).


Installation
------------

Download and follow the installation instructions from the cake website
http://cakephp.org/.

FX.php is PHP class created by Chris Hansen to speak with FileMaker via XML.
The FMCakeMix driver uses FX.php to send queries to FileMaker and is necessary 
for the driver’s functionality. Install FX.php by downloading the files from 
http://fx.iviking.org/ and placing the FX.php and lib folder at the root of 
the yourcakeinstall/app/Vendor folder.

Because the driver uses XML to communicate with FileMaker, your FileMaker
solutions must be hosted on a version of FileMaker Server that supports web
publishing and xml access. See the FileMaker Server documentation for
instructions on enabling these features.

In case of CakePHP 2.x, install the Filemaker.php file into
yourcakeinstall/app/Model/Datasource/Database, you’ll likely have to create the
Database directory in the Datasource folder.

FileMaker Data API driver
-------------------------

FMCakeMix also includes FilemakerDataAPI.php, a driver which talks to FileMaker
Server via the FileMaker Data API instead of XML. It uses FMDataAPI
(https://github.com/msyk/FMDataAPI) by Masayuki Nii and requires PHP 8.1 or
later. The XML driver (Filemaker.php) is still available, and you can choose
either driver per connection.

Requirements: PHP 8.1 or later with the "curl" and "json" extensions.

1. Download FMDataAPI and place its "src" folder as
   yourcakeinstall/app/Vendor/FMDataAPI/src (if FMDataAPI is already loaded
   via Composer, this step can be skipped). Only the following files are
   needed; other folders of the FMDataAPI repository such as "test" and
   "samples" are not required. To place them in another location, set the
   path of the "src" folder to 'vendorPath' in the connection settings.

```
app/Vendor/FMDataAPI/src/
├── FMDataAPI.php
└── Supporting/
    ├── CommunicationProvider.php
    ├── FileMakerLayout.php
    └── FileMakerRelation.php
```

2. Install both Filemaker.php and FilemakerDataAPI.php into
   yourcakeinstall/app/Model/Datasource/Database (FilemakerDataAPI extends
   Filemaker). FX.php is not required for the Data API driver; it is needed
   only if you also use the XML driver.

```
app/Model/Datasource/Database/
├── Filemaker.php
└── FilemakerDataAPI.php
```

3. Enable the Data API in FileMaker Server and the "fmrest" extended privilege
   for the account used by the driver.
4. Define the connection in app/Config/database.php:

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
    // optional
    // 'certValidating' => true, // validate the server certificate
    // 'timeout' => 30,          // request timeout in seconds
    // 'maxRecords' => 1000000,  // records returned when no limit is given
    // 'vendorPath' => '/path/to/FMDataAPI/src/',
);
```

If CakePHP runs on the same machine as FileMaker Server, you can specify
'localserver' as 'host'. The driver then connects to http://127.0.0.1:3000
directly, and 'port' and 'scheme' are ignored.

```php
public $default = array(
    'datasource' => 'Database/FilemakerDataAPI',
    'host' => 'localserver',
    'login' => 'username',
    'password' => 'password',
    'database' => 'SolutionName',
);
```

'defaultLayout' in the connection settings is optional. The layout is taken
from $defaultLayout of the model first, and the connection setting is used
only when the model doesn't define it. If neither is set, requests fail.

```php
class Article extends AppModel {
    public $defaultLayout = 'Article';
}
```

Notes on the Data API driver:

* Conditions with 'or' are sent as separate find requests, so they work as a
  real logical OR.
* Script parameters such as '-script', '-script.param', '-script.prefind' and
  '-script.presort' are converted to the Data API equivalents.
* Transactions are not supported (same as the XML driver).
* Code which uses FX.php directly (e.g. `new FX(...)` and `FMView()`) does not
  work with the Data API driver. To get value lists of a layout, use
  `ConnectionManager::getDataSource($model->useDbConfig)->getValueLists('LayoutName')`,
  which returns an array such as `array('ValueListName' => array('value1', ...))`.
* Unlike the XML API, the Data API has no unauthenticated (guest) access: the
  login request always needs an Authorization header, and later requests use
  the session token (Bearer). If both 'login' and 'password' are empty, the
  driver logs in with an empty account name and password (Basic auth). Whether
  this works with the Guest account has not been verified, so specifying an
  account with the "fmrest" extended privilege is recommended.

Support
-------

See "License" below for the warranty and support disclaimer (that is, you're on
your own, buddy).

Credit
------

* Author: Alex Gibbons <alex_g@beezwax.net>
* Maintainer: Atsushi Matsuo <famlog@gmail.com>

Thanks
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
