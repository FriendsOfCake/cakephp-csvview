# CsvView Plugin [![Build Status](https://travis-ci.org/josegonzalez/CsvView.png?branch=master)](https://travis-ci.org/josegonzalez/CsvView)

Quickly enable CSV output of your model data

## Background

I needed to quickly export CSVs of stuff in the database. Using a view class to iterate manually would be a chore to replicate for each export method, so I figured it would be much easier to do this with a custom view class, like JsonView or XmlView.

## Requirements

* CakePHP 2.x (custom view files are only supported in 2.1+)
* PHP5
* Patience

## Installation

_[Manual]_

* Download this: [http://github.com/josegonzalez/CsvView/zipball/master](http://github.com/josegonzalez/CsvView/zipball/master)
* Unzip that download.
* Copy the resulting folder to `app/Plugin`
* Rename the folder you just copied to `CsvView`

_[GIT Submodule]_

In your app directory type:

	git submodule add -b master git://github.com/josegonzalez/CsvView.git Plugin/CsvView
	git submodule init
	git submodule update

_[GIT Clone]_

In your `Plugin` directory type:

	git clone -b master git://github.com/josegonzalez/CsvView.git CsvView

### Enable plugin

In 2.0 you need to enable the plugin your `app/Config/bootstrap.php` file:

	CakePlugin::load('CsvView');

If you are already using `CakePlugin::loadAll();`, then this is not necessary.

## Usage

To export a flat array as a CSV, one could write the following code:

```php
<?php
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
<?php
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
<?php
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

You can also specify the delimiter, end of line, and escape characters using `$_delimiter`, `$_eol`, and `$_enclosure`, respectively:

```php
<?php
public function export() {
	$data = array(
		array('a', 'b', 'c'),
		array(1, 2, 3),
		array('you', 'and', 'me'),
	);

	$_serialize = 'data';
	$_delimiter = '\t';
	$_enclosure = '"';
	$_eol = '~';

	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('data', '_serialize', '_delimiter', '_enclosure', '_eol'));
}
```

The defaults for these variables are:

* `_delimiter`: `,`
* `_enclosure`: `"`
* `_eol`: `\n`

If you have complex model data, you can use the `$_extract` view variable to specify the individual paths for each record. This is an array of `Hash::extract()`-compatible syntax:

```php
<?php
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
<?php
// In your routes.php file:
Router::parseExtensions('csv');

// In your controller:
public $components = array('RequestHandler');

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
<?php
// View used will be in app/View/Posts/csv/export.ctp
public function export() {
	$posts = $this->Post->find('all');
	$_serialize = null;
	$this->viewClass = 'CsvView.Csv';
	$this->set(compact('posts', '_serialize');
}
```

### CsvView Component Usage
The CsvView component provides a few methods to help you quickly export the results of complex Model `find('all')` calls.

*Note:* nested `belongsTo` associations are handled no problem. Others (eg. `hasMany`) will be ignored (I can't see why you'd want them in a CSV export, or how you'd include them gracefully).

To use the component, include it in your Components array:

```php
<?php
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
<?php
$results = $this->MyModel->find('all');
$this->CsvView->quickExport($results);
```

*Example 2 - using quickExport, advanced use:*

```php
<?php
$results = $this->MyModel->find('all');
$excludePaths = array('City.id', 'State.id', 'State.Country.id'); // Exclude all id fields
$customHeaders = array('City.population' => 'No. of People');

$this->CsvView->quickExport($results, $excludePaths, $customHeaders);
```

*Example 3 - NOT using quickExport:*

```php
<?php
$results = $this->MyModel->find('all');

$excludePaths = array('City.id', 'State.id', 'State.Country.id'); // Exclude all id fields
$_export = $this->CsvView->prepareExtractFromFindResults($results, $excludePaths);

$customHeaders = array('City.population' => 'No. of People');
$_header = $this->CsvView->prepareHeaderFromExtract($_extract, $customHeaders);

$_serialize = 'results';
$this->viewClass = 'CsvView.Csv';
$this->set(compact('results' ,'_serialize', '_header', '_extract'));
```

## TODO

* Unit Tests

## License

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
