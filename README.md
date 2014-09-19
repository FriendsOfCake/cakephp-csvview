# CsvView Plugin [![Build Status](https://travis-ci.org/friendsofcake/cakephp-csvview.png?branch=master)](https://travis-ci.org/friendsofcake/cakephp-csvview) [![Coverage Status](https://coveralls.io/repos/friendsofcake/cakephp-csvview/badge.png?branch=master)](https://coveralls.io/r/friendsofcake/cakephp-csvview?branch=master) [![Total Downloads](https://poser.pugx.org/friendsofcake/cakephp-csvview/d/total.png)](https://packagist.org/packages/friendsofcake/cakephp-csvview) [![Latest Stable Version](https://poser.pugx.org/friendsofcake/cakephp-csvview/v/stable.png)](https://packagist.org/packages/friendsofcake/cakephp-csvview)


Quickly enable CSV output of your model data.

## Background

I needed to quickly export CSVs of stuff in the database. Using a view class to iterate manually
would be a chore to replicate for each export method, so I figured it would be much easier to do
this with a custom view class, like JsonView or XmlView.

## Requirements

* CakePHP 2.x (custom view files are only supported in 2.1+)
* PHP5
* Patience

## Installation

_[Using [Composer](http://getcomposer.org/)]_

[View on Packagist](https://packagist.org/packages/friendsofcake/cakephp-csvview), and copy
the JSON snippet for the latest version into your project's `composer.json`. Eg, v. 1.2.0 would look like this:

	{
		"require": {
			"friendsofcake/cakephp-csvview": "1.2.0"
		}
	}

Because this plugin has the type `cakephp-plugin` set in it's own `composer.json`, composer knows to install it inside your `/Plugins` directory, rather than in the usual vendors file. It is recommended that you add `/Plugins/CsvView` to your .gitignore file. (Why? [read this](http://getcomposer.org/doc/faqs/should-i-commit-the-dependencies-in-my-vendor-directory.md).)

_[Manual]_

* Download this: [http://github.com/friendsofcake/cakephp-csvview/zipball/master](http://github.com/friendsofcake/cakephp-csvview/zipball/master)
* Unzip that download.
* Copy the resulting folder to `app/Plugin`
* Rename the folder you just copied to `CsvView`

_[GIT Submodule]_

In your app directory type:

	git submodule add -b master git://github.com/friendsofcake/cakephp-csvview.git Plugin/CsvView
	git submodule init
	git submodule update

_[GIT Clone]_

In your `Plugin` directory type:

	git clone -b master git://github.com/friendsofcake/cakephp-csvview.git CsvView

### Enable plugin

In 2.0 you need to enable the plugin your `app/Config/bootstrap.php` file:

	CakePlugin::load('CsvView');

If you are already using `CakePlugin::loadAll();`, then this is not necessary.

## Usage

To export a flat array as a CSV, one could write the following code:

```php
public function export() {
	$data = array(
		array('a', 'b', 'c'),
		array(1, 2, 3),
		array('you', 'and', 'me'),
	);
	$_serialize = 'data';

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', '_serialize'));
}
```

All variables that are to be included in the csv must be specified in the `$_serialize` view variable, exactly how JsonView or XmlView work.

It is possible to have multiple variables in the csv output:

```php
public function export() {
	$data = array(array('a', 'b', 'c'));
	$data_two = array(array(1, 2, 3));
	$data_three = array(array('you', 'and', 'me'));

	$_serialize = array('data', 'data_two', 'data_three');

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', 'data_two', 'data_three', '_serialize'));
}
```

If you want headers or footers in your CSV output, you can specify either a `$_header` or `$_footer` view variable. Both are completely optional:

```php
public function export() {
	$data = array(
		array('a', 'b', 'c'),
		array(1, 2, 3),
		array('you', 'and', 'me'),
	);

	$_serialize = 'data';
	$_header = array('Column 1', 'Column 2', 'Column 3');
	$_footer = array('Totals', '400', '$3000');

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', '_serialize', '_header', '_footer'));
}
```

You can also specify the delimiter, end of line, newline, escape characters and byte order mark (BOM) sequence using
`$_delimiter`, `$_eol`, `$_newline`, `$_enclosure` and `$_bom` respectively:

```php
public function export() {
	$data = array(
		array('a', 'b', 'c'),
		array(1, 2, 3),
		array('you', 'and', 'me'),
	);

	$_serialize = 'data';
	$_delimiter = chr(9); //tab
	$_enclosure = '"';
	$_newline = '\r\n';
	$_eol = '~';
	$_bom = true;

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', '_serialize', '_delimiter', '_enclosure', '_newline', '_eol'));
}
```

The defaults for these variables are:

* `_delimiter`: `,`
* `_enclosure`: `"`
* `_newline`: `\n`
* `_eol`: `\n`
* `_bom`: false
* `_setSeparator`: false

The `_eol` variable is the one used to generate newlines in the output.
`_newline`, however, is the character that should replace the newline characters in the actual data.
It is recommended to use the string representation of the newline character to avoid rendering invalid output.

Some reader software incorrectly renders UTF-8 encoded files which do not contain byte order mark (BOM) byte sequence. The `_bom` variable is the one used to add byte order mark (BOM) byte sequence beginning of the generated CSV output stream. See [`Wikipedia article about byte order mark`](http://en.wikipedia.org/wiki/Byte_order_mark) for more information.

The `_setSeparator` flag can be used to set the separator explicitly in the first line of the CSV. Some readers need this in order to display the CSV correctly.

If you have complex model data, you can use the `$_extract` view variable to specify the individual paths for each record. This is an array of `Hash::extract()`-compatible syntax:

```php
public function export() {
	$posts = $this->Post->find('all');
	$_serialize = 'posts';
	$_header = array('Post ID', 'Title', 'Created');
	$_extract = array('Post.id', 'Post.title', 'Post.created');

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('posts', '_serialize', '_header', '_extract'));
}
```

If your model data contains some null values or missing keys, you can use the `$_null` variable, just like you'd use `$_delimiter`, `$_eol`, and `$_enclosure`, to set how null values should be displayed in the CSV.

`$_null` defaults to 'NULL'.

You can use `Router::parseExtensions()` and the `RequestHandlerComponent` to automatically have the CsvView class switched in as follows:

```php
// In your routes.php file:
Router::parseExtensions('csv');

// In your controller:
public $components = array(
	'RequestHandler' => array(
		'viewClassMap' => array('csv' => 'CsvView.Csv')
	)
);

public function export() {
	$posts = $this->Post->find('all');
	$_serialize = 'posts';
	$_header = array('Post ID', 'Title', 'Created');
	$_extract = array('Post.id', 'Post.title', 'Post.created');

	$this->set(compact('posts', '_serialize', '_header', '_extract'));
}
```

// Access /posts/export.csv to get the data as csv

For really complex CSVs, you can also simply use your own view files. This is only supported in 2.1+. To do so, either leave `$_serialize` unspecified or set it to null. The view files will be located in the `csv` subdirectory of your current controller:

```php
// View used will be in app/View/Posts/csv/export.ctp
public function export() {
	$posts = $this->Post->find('all');
	$_serialize = null;
	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('posts', '_serialize');
}
```

#### Setting the downloaded file name
By default, the downloaded file will be named after the last segment of the URL used to generate it. Eg: `example.com/my_controller/my_action` would download `my_action.csv`, while `example.com/my_controller/my_action/first_param` would download `first_param.csv`.

To set a custom file name, use the [`CakeResponse::download`](http://book.cakephp.org/2.0/en/controllers/request-response.html#sending-a-string-as-file) method. The following snippet can be used to change the downloaded file from `export.csv` to `my_file.csv`:

```php
public function export() {
	$data = array(
		array('a', 'b', 'c'),
		array(1, 2, 3),
		array('you', 'and', 'me'),
	);
	$_serialize = 'data';

	$this->response->download('my_file.csv'); // <= setting the file name
	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', '_serialize'));
}
```

### CsvView Component Usage
The CsvView component provides a few methods to help you quickly export the results of complex Model `find('all')` calls.

*Note:* nested `belongsTo` associations are handled no problem. Others (eg. `hasMany`) will be ignored (I can't see why you'd want them in a CSV export, or how you'd include them gracefully).

To use the component, include it in your Components array:

```php
// In your controller:
public $components = array('CsvView.CsvView');
```

The component has the following methods:

#### prepareExtractFromFindResults($data, $excludePaths = array())
Recursively searches `$data` and returns an array of all unique `Hash::extract()`-compatible paths, suitable for the $_extract variable

* *$data:* the results of a Model `find('all')` call.
* *$excludePaths (optional):* an array of paths to exclude from the returned array, using `Hash::extract()`-compatible syntax. Eg. `array('MyModel.column_name')`

#### prepareHeaderFromExtract($extract, $customHeaders = array())

Returns an array of user-friendly colum titles, suitable for use as the `$_header`, based on the paths in `$extract`. Eg, the path 'City.Country.name' becomes 'Country Name'.

* *$extract:* an array of paths, using `Hash::extract()`-compatible syntax.
* *$customHeaders (optional):* an array of 'path' => 'Custom Title' pairs, eg. `array('City.population' => 'No. of People')`. These custom headers, when specified, override the default generated headers.

#### quickExport($data, $excludePaths = array(), $customHeaders = array(), $includeHeader = true)

Quickly export an the results of a Model `find('all')` call in one line of code.

* *$data* - the results of a Model `find('all')` call.
* *$excludePaths (optional):* Same use as in prepareExtractFromFindResults method, above
* *$customHeaders (optional):* Same use as in prepareHeaderFromExtract method, above
* *$includeHeader (optional):* if true, a $_header will be included. Defaults to true.

*Example 1 - using quickExport, simplest use:*

```php
$results = $this->MyModel->find('all');
$this->CsvView->quickExport($results);
```

*Example 2 - using quickExport, advanced use:*

```php
$results = $this->MyModel->find('all');
$excludePaths = array('City.id', 'State.id', 'State.Country.id'); // Exclude all id fields
$customHeaders = array('City.population' => 'No. of People');

$this->CsvView->quickExport($results, $excludePaths, $customHeaders);
```

*Example 3 - NOT using quickExport:*

```php
$results = $this->MyModel->find('all');

$excludePaths = array('City.id', 'State.id', 'State.Country.id'); // Exclude all id fields
$_extract = $this->CsvView->prepareExtractFromFindResults($results, $excludePaths);

$customHeaders = array('City.population' => 'No. of People');
$_header = $this->CsvView->prepareHeaderFromExtract($_extract, $customHeaders);

$_serialize = 'results';
$this->viewClass = 'CsvView.Csv';
$this->set(compact('results' ,'_serialize', '_header', '_extract'));
```

## License

The MIT License (MIT)

Copyright (c) 2012 Jose Diaz-Gonzalez

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
