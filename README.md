# Borsch - Finder

This Finder class finds files and directories via a set of rules.

This package is part of the Borsch framework.

## Installation

Via composer :

```
$ composer require borsch/finder
```

## Usage

#### List files and folders in a directory

```php
use Borsch\FileSystem\Finder;

foreach (Finder::create()->in(__DIR__) as $file) {
    // $file is an instance of SplFileInfo
}
```

#### List only files or only folders in a directory

```php
use Borsch\FileSystem\Finder;

foreach (Finder::create()->files()->in(__DIR__) as $file) {
    // $file is an instance of SplFileInfo
}

foreach (Finder::create()->directories()->in(__DIR__) as $file) {
    // $file is an instance of SplFileInfo
}
```

#### Select a directory

The only compulsory parameter is a path.  
You may use the _in()_ method with glob() like path.  
The _in()_ method can be chained to include many paths or an array of path can be provided.

```php
use Borsch\FileSystem\Finder;

foreach (Finder::create()->in(__DIR__.'/*/*/test') as $file) {
    // $file is an instance of SplFileInfo
}

foreach (Finder::create()->in(__DIR__)->in('/home/borsch/docs') as $file) {
    // $file is an instance of SplFileInfo
}

// Multidimentional array of any depth can be used
$finder = new Finder();
$finder->in([
    __DIR__,
    '/home/borsch/docs',
    [
        '/some/other/path',
        [
            '/some/more/other/path'
        ]
    ]
]);

foreach ($finder as $file) {
    // $file is an instance of SplFileInfo
}
```

#### Sorting

Some sorting method are already provided but you can also supply your own.

```php
use Borsch\FileSystem\Finder;

foreach (Finder::create()->in(__DIR__)->sortByName() as $file) {
    // $file is an instance of SplFileInfo
}

$finder = Finder::create()->in(__DIR__)->sort(function (SplFileInfo $a, SplFileInfo $b) {
    // Equivalent to the method sortByName()
    return strcmp($a->getFilename(), $b->getFilename())
});

foreach ($finder as $file) {
    // $file is an instance of SplFileInfo
}
```

#### Filtering

Some filtering method are already provided but you can also supply your own.

```php
use Borsch\FileSystem\Finder;

$finder = Finder::create()->in(__DIR__)->filter(function (SplFileInfo $current) {
    // Remove index.php files
    return $current->getBasename() !== 'index.php';
});

foreach ($finder as $file) {
    // $file is an instance of SplFileInfo
}
```

## License

```
MIT License

Copyright (c) 2018 Alexandre DEBUSSCHERE

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
