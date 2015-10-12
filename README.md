# CsvView Plugin [![Build Status](https://travis-ci.org/FriendsOfCake/cakephp-csvview.png?branch=master)](https://travis-ci.org/FriendsOfCake/cakephp-csvview) [![Coverage Status](https://coveralls.io/repos/friendsofcake/cakephp-csvview/badge.png?branch=master)](https://coveralls.io/r/friendsofcake/cakephp-csvview?branch=master) [![Total Downloads](https://poser.pugx.org/friendsofcake/cakephp-csvview/d/total.png)](https://packagist.org/packages/friendsofcake/cakephp-csvview) [![Latest Stable Version](https://poser.pugx.org/friendsofcake/cakephp-csvview/v/stable.png)](https://packagist.org/packages/friendsofcake/cakephp-csvview)

Quickly enable CSV output of your model data.

## Background

I needed to quickly export CSVs of stuff in the database. Using a view class to
iterate manually would be a chore to replicate for each export method, so I
figured it would be much easier to do this with a custom view class,
like JsonView or XmlView.

## Requirements

* CakePHP 3.x
* PHP 5.4.16 or greater
* Patience

## Installation

_[Using [Composer](http://getcomposer.org/)]_

```
composer require friendsofcake/cakephp-csvview:~2.0
```

### Enable plugin

Load the plugin in your app's `config/bootstrap.php` file:

	Plugin::load('CsvView');

## Usage

To export a flat array as a CSV, one could write the following code:

```php
public function export() {
	$data = [
		['a', 'b', 'c'],
		[1, 2, 3],
		['you', 'and', 'me'],
	];
	$_serialize = 'data';

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', '_serialize'));
}
```

All variables that are to be included in the csv must be specified in the
`$_serialize` view variable, exactly how JsonView or XmlView work.

It is possible to have multiple variables in the csv output:

```php
public function export() {
	$data = [['a', 'b', 'c']];
	$data_two = [[1, 2, 3]];
	$data_three = [['you', 'and', 'me']];

	$_serialize = ['data', 'data_two', 'data_three'];

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', 'data_two', 'data_three', '_serialize'));
}
```

If you want headers or footers in your CSV output, you can specify either a
`$_header` or `$_footer` view variable. Both are completely optional:

```php
public function export() {
	$data = [
		['a', 'b', 'c'],
		[1, 2, 3],
		['you', 'and', 'me'],
	];

	$_serialize = 'data';
	$_header = ['Column 1', 'Column 2', 'Column 3'];
	$_footer = ['Totals', '400', '$3000'];

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', '_serialize', '_header', '_footer'));
}
```

You can also specify the delimiter, end of line, newline, escape characters and
byte order mark (BOM) sequence using `$_delimiter`, `$_eol`, `$_newline`,
`$_enclosure` and `$_bom` respectively:

```php
public function export() {
	$data = [
		['a', 'b', 'c'],
		[1, 2, 3],
		['you', 'and', 'me'],
	];

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
`_newline`, however, is the character that should replace the newline characters
in the actual data. It is recommended to use the string representation of the
newline character to avoid rendering invalid output.

Some reader software incorrectly renders UTF-8 encoded files which do not
contain byte order mark (BOM) byte sequence. The `_bom` variable is the one used
to add byte order mark (BOM) byte sequence beginning of the generated CSV output
stream. See [`Wikipedia article about byte order mark`](http://en.wikipedia.org/wiki/Byte_order_mark)
for more information.

The `_setSeparator` flag can be used to set the separator explicitly in the
first line of the CSV. Some readers need this in order to display the CSV correctly.

If you have complex model data, you can use the `$_extract` view variable to
specify the individual paths for each record. This is an array of
`Hash::extract()`-compatible syntax:

```php
public function export() {
	$posts = $this->Post->find('all');
	$_serialize = 'posts';
	$_header = ['Post ID', 'Title', 'Created'];
	$_extract = ['Post.id', 'Post.title', 'Post.created'];

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('posts', '_serialize', '_header', '_extract'));
}
```

If your model data contains some null values or missing keys, you can use the
`$_null` variable, just like you'd use `$_delimiter`, `$_eol`, and `$_enclosure`,
to set how null values should be displayed in the CSV.

`$_null` defaults to 'NULL'.

You can use `Router::parseExtensions()` and the `RequestHandlerComponent` to
automatically have the CsvView class switched in as follows:

```php
// In your routes.php file:
Router::parseExtensions('csv');

// In your controller:
public $components = [
	'RequestHandler' => [
		'viewClassMap' => ['csv' => 'CsvView.Csv']
	]
];

public function export() {
	$posts = $this->Post->find('all');
	$_serialize = 'posts';
	$_header = ['Post ID', 'Title', 'Created'];
	$_extract = ['Post.id', 'Post.title', 'Post.created'];

	$this->set(compact('posts', '_serialize', '_header', '_extract'));
}
```

// Access /posts/export.csv to get the data as csv

For really complex CSVs, you can also simply use your own view files.
To do so, either leave `$_serialize` unspecified or set it to null.
The view files will be located in the `csv` subdirectory of your current controller:

```php
// View used will be in app/View/Posts/csv/export.ctp
public function export() {
	$posts = $this->Post->find('all');
	$_serialize = null;
	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('posts', '_serialize');
}
```
#### Setting a different encoding to the file

if you need to have a different encoding in you csv file you have to set the encoding of your data
you are passing to the view and also set the encoding you want for the csv file.
This can be done by using `_dataEncoding` and `_csvEncoding`:

The defaults are:
 
* `_dataEncoding`: `UTF-8`
* `_csvEncoding`: `UTF-8`

** Only if those two variable are different your data will be converted to another encoding.

#### Setting the downloaded file name
By default, the downloaded file will be named after the last segment of the URL
used to generate it. Eg: `example.com/my_controller/my_action` would download
`my_action.csv`, while `example.com/my_controller/my_action/first_param` would
download `first_param.csv`.

To set a custom file name, use the [`Response::download`](http://book.cakephp.org/3.0/en/controllers/request-response.html#sending-a-string-as-file) method.
The following snippet can be used to change the downloaded file from `export.csv` to `my_file.csv`:

```php
public function export() {
	$data = [
		['a', 'b', 'c'],
		[1, 2, 3],
		['you', 'and', 'me'],
	];
	$_serialize = 'data';

	$this->response->download('my_file.csv'); // <= setting the file name
	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', '_serialize'));
}
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
