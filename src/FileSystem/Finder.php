<?php
/**
 * This file is part of the Borsch package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package   Borsch\FileSystem
 * @author    Alexandre DEBUSSCHERE (debuss-a)
 * @copyright Copyright (c) Alexandre Debusschere <alexandre@debuss-a.me>
 * @licence   MIT
 */

namespace Borsch\FileSystem;

use Countable;
use Iterator;
use IteratorAggregate;
use ArrayIterator;
use AppendIterator;
use FilesystemIterator;
use RecursiveArrayIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use CallbackFilterIterator;
use RecursiveCallbackFilterIterator;
use SplFileInfo;
use InvalidArgumentException;

/**
 * Finder finds files and directories via a set of rules.
 * It is a thin wrapper around several specialized iterator classes.
 * All rules may be invoked several times.
 * All methods return the current Finder object to allow easy chaining:
 * <code>$finder = Finder::create()->files()->name('*.php')->in(__DIR__);</code>
 *
 * @package Borsch\FileSystem
 * @author  Alexandre Debusschere (debuss-a)
 * @implements IteratorAggregate
 * @implements Countable
 */
class Finder implements IteratorAggregate, Countable
{

    const ONLY_FILES = 1;

    const ONLY_DIRECTORIES = 2;

    /** @ignore */
    private $mode;

    /** @ignore */
    private $dirs = [];

    /** @ignore */
    private $filters = [];

    /** @ignore */
    private $sorts = [];

    /** @ignore */
    private $excluded_dirs = [];

    /** @ignore */
    private $ignore_vcs = true;

    /** @ignore */
    private $ignore_unreadable_dirs = true;

    /** @ignore */
    private $flags;

    /** @ignore */
    private $vcs_list = ['.svn', '.cvs', '.idea', '.DS_Store', '.git', '.hg'];

    /** @ignore */
    private $depth = -1;

    /** @ignore */
    private $glob_braces;

    /**
     * Finder constructor.
     * No parameters needed.
     */
    public function __construct()
    {
        $this->flags = FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS;
        $this->glob_braces = defined('GLOB_BRACE') ? GLOB_BRACE : 0;
    }

    /**
     * Create a Finder instance and returns it.
     * Equivalent to : <code>$finder = (new Finder())->in(__DIR__);</code>
     *
     * @return Finder
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Restricts the matching to files only.
     *
     * @return Finder
     */
    public function files()
    {
        $this->mode = self::ONLY_FILES;

        return $this;
    }

    /**
     * Restricts the matching to directories only.
     *
     * @return Finder
     */
    public function directories()
    {
        $this->mode = self::ONLY_DIRECTORIES;

        return $this;
    }

    /**
     * Searches files and/or directories in the given path(s).
     *
     * @param string|array $directories
     * @return Finder
     */
    public function in($directories)
    {
        $new_directories = array_map(function ($directory) {
            if (is_dir($directory)) {
                return $directory;
            }

            return glob($directory, $this->glob_braces|GLOB_ONLYDIR);
        }, $this->flattenParameters($directories));

        $this->dirs = array_unique(array_merge(
            $this->dirs,
            $this->flattenParameters($new_directories)
        ));

        return $this;
    }

    /**
     * Searches files and/or directories except in the given path(s).
     *
     * @param $directories
     * @return Finder
     */
    public function exclude($directories)
    {
        $this->excluded_dirs = array_merge(
            $this->excluded_dirs,
            $this->flattenParameters($directories)
        );

        return $this;
    }

    /**
     * Tells Finder to ignore (or not) unreadable directories.
     *
     * @param bool $ignore
     * @return Finder
     */
    public function ignoreUnreadableDirs($ignore = true)
    {
        $this->ignore_unreadable_dirs = (bool)$ignore;

        return $this;
    }

    /**
     * Tells Finder to ignore (or not) dot directories.
     * Will remove current directory and parent directory ("." and "..") as well as file starting with ".".
     *
     * @param bool $ignore
     * @return Finder
     * @uses FilesystemIterator::SKIP_DOTS
     */
    public function ignoreDots($ignore = true)
    {
        if ($ignore) {
            $this->flags |= FilesystemIterator::SKIP_DOTS;
        } else {
            $this->flags &= ~FilesystemIterator::SKIP_DOTS;
        }

        return $this;
    }

    /**
     * Tells Finder to ignore (or not) VCS files.
     *
     * @param bool $ignore
     * @return Finder
     */
    public function ignoreVCS($ignore = true)
    {
        $this->ignore_vcs = (bool)$ignore;

        return $this;
    }

    /**
     * Tells Finder to follow (or not) symbolic links.
     *
     * @param bool $ignore
     * @return Finder
     * @uses FilesystemIterator::FOLLOW_SYMLINKS
     */
    public function followLinks($ignore = true)
    {
        if ($ignore) {
            $this->flags |= FilesystemIterator::FOLLOW_SYMLINKS;
        } else {
            $this->flags &= ~FilesystemIterator::FOLLOW_SYMLINKS;
        }

        return $this;
    }

    /**
     * Sorts files and directories from a user defined function.
     * The anonymous function receives two \SplFileInfo instances to compare.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @param callable $callback
     * @return Finder
     * @uses ArrayIterator::uasort()
     */
    public function sort(callable $callback)
    {
        $this->sorts[] = $callback;

        return $this;
    }

    /**
     * Sorts files and directories by name.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortByName()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getFilename(), $b->getFilename());
        });
    }

    /**
     * Sorts files and directories by type.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortByType()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getType(), $b->getType());
        });
    }

    /**
     * Sorts files and directories by size.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortBySize()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getSize(), $b->getSize());
        });
    }

    /**
     * Sorts files and directories by file extension.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortByExtension()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getExtension(), $b->getExtension());
        });
    }

    /**
     * Sorts files and directories by path (without file name).
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortByPath()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getPath(), $b->getPath());
        });
    }

    /**
     * Sorts files and directories by permissions.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortByPermission()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getPerms(), $b->getPerms());
        });
    }

    /**
     * Sorts files and directories by accessed time.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortByAccessedTime()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getATime(), $b->getATime());
        });
    }

    /**
     * Sorts files and directories by modified time.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortByModifiedTime()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getMTime(), $b->getMTime());
        });
    }

    /**
     * Sorts files and directories by changed time.
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Finder
     */
    public function sortByChangedTime()
    {
        return $this->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($a->getCTime(), $b->getCTime());
        });
    }

    /**
     * Filters the iterator with a user defined function.
     * The anonymous function receives a SplFileInfo and must return false to remove files.
     *
     * @param callable $callback
     * @return Finder
     */
    public function filter(callable $callback)
    {
        $this->filters[] = $callback;

        return $this;
    }

    /**
     * Adds tests for file dates (last modified).
     * Remove the file if <code>$current->getCTime() >= strtotime($date)</code>.
     * The date must be something that strtotime() is able to parse.
     *
     * @param string $date
     * @return Finder
     */
    public function date($date)
    {
        return $this->filter(function (SplFileInfo $current) use ($date) {
            return $current->getCTime() >= strtotime($date);
        });
    }

    /**
     * Adds tests for file sizes in bytes.
     * Remove the file if <code>$current->getSize() >= $size</code>.
     *
     * @param string $size
     * @return Finder
     */
    public function size($size)
    {
        return $this->filter(function (SplFileInfo $current) use ($size) {
            return $current->getSize() >= $size;
        });
    }

    /**
     * Set the maximum allowed depth.
     *
     * @param int $depth
     * @return $this
     */
    public function depth($depth)
    {
        $this->depth = max(-1, (int)$depth);

        return $this;
    }

    /**
     * Merge an other Finder or Iterator or simple array instance with the current Finder instance.
     *
     * @param Finder|Iterator|array $iterator
     * @return Finder
     * @throws InvalidArgumentException
     */
    public function merge($iterator)
    {
        if (is_array($iterator)) {
            $this->in($iterator);
        } elseif ($iterator instanceof Finder) {
            $this->dirs = array_unique(array_merge($this->dirs, $iterator->dirs));
        } elseif ($iterator instanceof Iterator) {
            $this->in(iterator_to_array($iterator));
        } else {
            throw new InvalidArgumentException(sprintf(
                'The argument given to %s is not an instance of Finder/Iterator or an array.',
                __METHOD__
            ));
        }

        return $this;
    }

    /**
     * Retrieve an external iterator.
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Iterator An instance of an object implementing <b>Iterator</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        $iterator = new AppendIterator();

        if ($this->ignore_vcs) {
            $this->excluded_dirs = array_unique(array_merge(
                $this->excluded_dirs,
                $this->vcs_list
            ));
        }

        foreach ($this->dirs as $dir) {
            $directory = new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, $this->flags),
                function (SplFileInfo $current) {
                    if (in_array($current->getFilename(), $this->excluded_dirs)) {
                        return false;
                    }

                    return true;
                }
            );

            $directory = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);
            $directory->setMaxDepth($this->depth);

            if ($this->ignore_unreadable_dirs) {
                $directory = new CallbackFilterIterator($directory, function (SplFileInfo $current) {
                    return $current->isFile() || $current->isReadable();
                });
            }

            if ($this->mode === self::ONLY_DIRECTORIES) {
                $directory = new CallbackFilterIterator($directory, function (SplFileInfo $current) {
                    return $current->isDir();
                });
            } elseif ($this->mode === self::ONLY_FILES) {
                $directory = new CallbackFilterIterator($directory, function (SplFileInfo $current) {
                    return $current->isFile();
                });
            }

            foreach ($this->filters as $filter) {
                $directory = new CallbackFilterIterator($directory, $filter);
            }

            $iterator->append($directory);
        }

        $files = iterator_to_array($iterator);
        foreach ($this->sorts as $sort) {
            uasort($files, $sort);
        }

        return new ArrayIterator($files);
    }

    /**
     * Count elements of an object.
     *
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return iterator_count($this->getIterator());
    }

    /**
     * @param ...mixed
     * @return array Flatten parameters into a single array.
     */
    private function flattenParameters()
    {
        return iterator_to_array(
            new RecursiveIteratorIterator(new RecursiveArrayIterator(func_get_args())),
            false
        );
    }
}
