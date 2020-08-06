[![Build Status](https://img.shields.io/travis/FriendsOfCake/cakephp-csvview/master.svg?style=flat-square)](https://travis-ci.org/FriendsOfCake/cakephp-csvview)
[![Coverage Status](https://img.shields.io/codecov/c/github/FriendsOfCake/cakephp-csvview.svg?style=flat-square)](https://codecov.io/gh/FriendsOfCake/cakephp-csvview)
[![Total Downloads](https://img.shields.io/packagist/dt/friendsofcake/cakephp-csvview.svg?style=flat-square)](https://packagist.org/packages/friendsofcake/cakephp-csvview)
[![Latest Stable Version](https://img.shields.io/packagist/v/friendsofcake/cakephp-csvview.svg?style=flat-square)](https://packagist.org/packages/friendsofcake/cakephp-csvview)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)

# CsvView Plugin

Quickly enable CSV output of your model data.

## Background

I needed to quickly export CSVs of stuff in the database. Using a view class to
iterate manually would be a chore to replicate for each export method, so I
figured it would be much easier to do this with a custom view class,
like JsonView or XmlView.

## Installation

```
composer require friendsofcake/cakephp-csvview
```

### Enable plugin

Load the plugin by running command

    bin/cake plugin load CsvView

## Usage

To export a flat array as a CSV, one could write the following code:

```php
public function export()
{
    $data = [
        ['a', 'b', 'c'],
        [1, 2, 3],
        ['you', 'and', 'me'],
    ];

    $this->set(compact('data'));
    $this->viewBuilder()
        ->setClassName('CsvView.Csv')
        ->setOption('serialize', 'data');
}
```

All variables that are to be included in the csv must be specified in the
`serialize` view option, exactly how `JsonView` or `XmlView` work.

It is possible to have multiple variables in the csv output:

```php
public function export()
{
    $data = [['a', 'b', 'c']];
    $data_two = [[1, 2, 3]];
    $data_three = [['you', 'and', 'me']];

    $serialize = ['data', 'data_two', 'data_three'];

    $this->set(compact('data', 'data_two', 'data_three'));
    $this->viewBuilder()
        ->setClassName('CsvView.Csv')
        ->setOption('serialize', $serialize);
}
```

If you want headers or footers in your CSV output, you can specify either a
`header` or `footer` view option. Both are completely optional:

```php
public function export()
{
    $data = [
        ['a', 'b', 'c'],
        [1, 2, 3],
        ['you', 'and', 'me'],
    ];

    $header = ['Column 1', 'Column 2', 'Column 3'];
    $footer = ['Totals', '400', '$3000'];

    $this->set(compact('data'));
    $this->viewBuilder()
        ->setClassName('CsvView.Csv')
        ->setOptions([
            'serialize' => 'data',
            'header' => $header,
            'footer' => $footer,
        ]);
}
```

You can also specify the delimiter, end of line, newline, escape characters and
byte order mark (BOM) sequence using `delimiter`, `eol`, `newline`, `enclosure`
and `bom` respectively:

```php
public function export()
{
    $data = [
        ['a', 'b', 'c'],
        [1, 2, 3],
        ['you', 'and', 'me'],
    ];

    $this->set(compact('data'));
    $this->viewBuilder()
        ->setClassName('CsvView.Csv')
        ->setOptions([
            'serialize' => 'data',
            'delimiter' => chr(9),
            'enclosure' => '"',
            'newline' => '\r\n',
            'eol' => '~',
            'bom' => true,
        ]);
}
```

The defaults for these options are:

* `delimiter`: `,`
* `enclosure`: `"`
* `newline`: `\n`
* `eol`: `\n`
* `bom`: false
* `setSeparator`: false

The `eol` option is the one used to generate newlines in the output.
`newline`, however, is the character that should replace the newline characters
in the actual data. It is recommended to use the string representation of the
newline character to avoid rendering invalid output.

Some reader software incorrectly renders UTF-8 encoded files which do not
contain byte order mark (BOM) byte sequence. The `bom` option is the one used
to add byte order mark (BOM) byte sequence beginning of the generated CSV output
stream. See [`Wikipedia article about byte order mark`](http://en.wikipedia.org/wiki/Byte_order_mark)
for more information.

The `setSeparator` option can be used to set the separator explicitly in the
first line of the CSV. Some readers need this in order to display the CSV correctly.

If you have complex model data, you can use the `extract` view option to
specify the individual [`Hash::extract()`-compatible](http://book.cakephp.org/4/en/core-libraries/hash.html) paths
or a callable for each record:

```php
public function export()
{
    $posts = $this->Posts->find();
    $header = ['Post ID', 'Title', 'Created'];
    $extract = [
        'id',
        function (array $row) {
            return $row['title'];
        },
        'created'
    ];

    $this->set(compact('posts'));
    $this->viewBuilder()
        ->setClassName('CsvView.Csv')
        ->setOptions([
            'serialize' => 'posts',
            'header' => $header,
            'extract' => $extract,
        ]);
}
```

If your model data contains some null values or missing keys, you can use the
`null` option, just like you'd use `delimiter`, `eol`, and `enclosure`,
to set how null values should be displayed in the CSV.

`null` defaults to `''`.

#### Automatic view class switching

You can use router's extension parsing feature and the `RequestHandlerComponent` to
automatically have the CsvView class switched in as follows.

Enable `csv` extension parsing for all routes using `Router::extensions('csv')`
in your app's `routes.php` or using `$routes->addExtensions()` within required
scope.

```php
// PostsController.php

// In your controller's initialize() method:
$this->loadComponent('RequestHandler');

// Controller action
public function index()
{
    $posts = $this->Posts->find();
    $this->set(compact('posts'));

    if ($this->request->is('csv')) {
        $serialize = 'posts';
        $header = array('Post ID', 'Title', 'Created');
        $extract = array('id', 'title', 'created');

        $this->viewBuilder()->setOptions(compact('serialize', 'header', 'extract'));
    }
}
```

With the above controller you can now access `/posts.csv` or use `Accept` header
`text/csv` to get the data as csv and use `/posts` to get normal HTML page.

For really complex CSVs, you can also use your own view files. To do so, either
leave `serialize` unspecified or set it to null. The view files will be located
in the `csv` subdirectory of your current controller:

```php
// View used will be in templates/Posts/csv/export.php
public function export()
{
    $posts = $this->Posts->find();
    $this->set(compact('posts'));
    $this->viewBuilder()
        ->setClassName('CsvView.Csv')
        ->setOption('serialize', null);
}
```

#### Setting a different encoding to the file

If you need to have a different encoding in you csv file you have to set the
encoding of your data you are passing to the view and also set the encoding you
want for the csv file. This can be done by using `dataEncoding` and `csvEncoding`:

The defaults are:

* `dataEncoding`: `UTF-8`
* `csvEncoding`: `UTF-8`

** Only if those two variable are different your data will be converted to another encoding.

CsvView uses the `iconv` extension by default to encode your data. You can change
the php extension used to encode your data by setting the `transcodingExtension` option:

```php
$this->viewBuilder()->setOption('transcodingExtension', 'mbstring');
```

The currently supported encoding extensions are as follows:

- `iconv`
- `mbstring`

#### Setting the downloaded file name

By default, the downloaded file will be named after the last segment of the URL
used to generate it. Eg: `example.com/my-controller/my-action` would download
`my-action.csv`, while `example.com/my-controller/my-action/first-param` would
download `first-param.csv`.

> In IE you are required to set the filename, otherwise it will download as a text file.

To set a custom file name, use the `Response::withDownload()` method. The following
snippet can be used to change the downloaded file from `export.csv` to `my-file.csv`:

```php
public function export()
{
    $data = [
        ['a', 'b', 'c'],
        [1, 2, 3],
        ['you', 'and', 'me'],
    ];

    $this->setResponse($this->getResponse()->withDownload('my-file.csv'));
    $this->set(compact('data'));
    $this->viewBuilder()
        ->setClassName('CsvView.Csv')
        ->setOption('serialize', 'data');
}
```

#### Using a specific View Builder

In some cases, it is better not to use the current controller's View Builder
`$this->viewBuilder()` as any call to `$this->render()` will compromise any
subsequent rendering.

For example, in the course of your current controller's action, if you need to
render some data as CSV in order to simply save it into a file on the server.

Do not forget to add to your controller:

```php
use Cake\View\ViewBuilder;
```
So you can create a specific View Builder:

```php
// Your data array
$data = [];

// Options
$serialize = 'data';
$delimiter = ',';
$enclosure = '"';
$newline = '\r\n';

// Create the builder
$builder = new ViewBuilder();
$builder
    ->setLayout(false)
    ->setClassName('CsvView.Csv')
    ->setOptions(compact('serialize', 'delimiter', 'enclosure', 'newline'));

// Then the view
$view = $builder->build($data);
$view->set(compact('data'));

// And Save the file
file_put_contents('/full/path/to/file.csv', $view->render());
```

## License

[The MIT License (MIT)](LICENSE.txt)
