<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBee\Util\File;

use BackBee\Exception\ApplicationException;
use BackBee\Exception\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use ZipArchive;

/**
 * Set of utility methods to deal with files
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class File
{
    /**
     * Acceptable prefices of SI
     *
     * @var array
     */
    protected static $prefixes = array('', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y');

    /**
     * Returns canonicalized absolute pathname
     *
     * @param string $path
     *
     * @return boolean|string
     */
    public static function realpath($path)
    {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        if (false === $parse_url = parse_url($path)) {
            return false;
        }

        if (!array_key_exists('host', $parse_url)) {
            return realpath($path);
        }

        if (!array_key_exists('scheme', $parse_url)) {
            return false;
        }

        $parts = [];
        $pathArray = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $parse_url['path']));

        foreach ($pathArray as $part) {
            if ('.' === $part) {
                continue;
            } elseif ('..' === $part) {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        $path = (isset($parse_url['scheme']) ? $parse_url['scheme'] . '://' : '') .
            (isset($parse_url['user']) ? $parse_url['user'] : '') .
            (isset($parse_url['pass']) ? ':' . $parse_url['pass'] : '') .
            (isset($parse_url['user']) || isset($parse_url['pass']) ? '@' : '') .
            (isset($parse_url['host']) ? $parse_url['host'] : '') .
            (isset($parse_url['port']) ? ':' . $parse_url['port'] : '') .
            implode('/', $parts);

        if (!file_exists($path)) {
            return false;
        }

        return $path;
    }

    /**
     * Normalize a file path according to the system characteristics
     *
     * @param string  $filepath       the path to normalize
     * @param string  $separator      The directory separator to use
     * @param boolean $removeTrailing Removing trailing separators to the end of path
     *
     * @return string  The normalize file path
     */
    public static function normalizePath($filepath, $separator = DIRECTORY_SEPARATOR, $removeTrailing = true)
    {
        $scheme = '';
        if (false !== $parseUrl = parse_url($filepath)) {
            $auth = isset($parseUrl['user']) ? $parseUrl['user'] : '';
            $auth .= isset($parseUrl['pass']) ? ':' . $parseUrl['pass'] : '';
            $auth .= (isset($parseUrl['user']) || isset($parseUrl['pass'])) ? '@' : '';
            $scheme .= isset($parseUrl['scheme']) ? $parseUrl['scheme'] . ':' : '';
            $scheme .= isset($parseUrl['host']) ? '//' . $auth . $parseUrl['host'] : '';
            $scheme .= isset($parseUrl['port']) ? ':' . $parseUrl['port'] : '';

            if (isset($parseUrl['path'])) {
                $filepath = $parseUrl['path'];
            }

            if (isset($parseUrl['scheme']) && isset($parseUrl['host'])) {
                $separator = '/';
            }
        }

        $patterns = array('/\//', '/\\\\/', '/\/+/');
        $replacements = array_fill(0, 3, '/');

        if (true === $removeTrailing) {
            $patterns[] = '/\/$/';
            $replacements[] = '';
        }

        return str_replace('/', $separator, $scheme . preg_replace($patterns, $replacements, $filepath));
    }

    /**
     * Tranformation to human-readable format
     *
     * @param int $size      Size in bytes
     * @param int $precision Presicion of result (default 2)
     *
     * @return string Transformed size
     */
    public static function readableFilesize($size, $precision = 2)
    {
        $result = $size;
        $index = 0;
        while ($result > 1024 && $index < count(self::$prefixes)) {
            $result = $result / 1024;
            $index++;
        }

        return sprintf('%1.' . $precision . 'f %sB', $result, self::$prefixes[$index]);
    }

    /**
     * Try to find the real path to the provided file name
     * Can be invoke by array_walk()
     *
     * @param string $filename The reference to the file to looking for
     * @param string $key      The optionnal array key to be invoke by array_walk
     * @param array  $options  optionnal options to
     *                         - include_path The path to include directories
     *                         - base_dir The base directory
     */
    public static function resolveFilepath(&$filename, $key = null, $options = array())
    {
        $filename = self::normalizePath($filename);
        $realname = self::realpath($filename);

        if ($filename !== $realname) {
            $basedir = (array_key_exists('base_dir', $options)) ? self::normalizePath($options['base_dir']) : '';

            if (array_key_exists('include_path', $options)) {
                foreach ((array)$options['include_path'] as $path) {
                    $path = self::normalizePath($path);
                    if (!is_dir($path)) {
                        $path = ($basedir) ? $basedir . DIRECTORY_SEPARATOR : '' . $path;
                    }

                    if (file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
                        $filename = $path . DIRECTORY_SEPARATOR . $filename;
                        break;
                    }
                }
            } elseif (!empty($basedir)) {
                $filename = $basedir . DIRECTORY_SEPARATOR . $filename;
            }
        }

        if (false !== $realname = self::realpath($filename)) {
            $filename = $realname;
        }
    }

    public static function resolveMediapath(&$filename, $key = null, $options = array())
    {
        $matches = array();
        if (preg_match('/^(.*)([a-z0-9]{32})\.(.*)$/i', $filename, $matches)) {
            $filename = $matches[1] . implode(DIRECTORY_SEPARATOR, str_split($matches[2], 4)) . '.' . $matches[3];
        }

        self::resolveFilepath($filename, $key, $options);
    }

    /**
     * Returns the extension file base on its name
     *
     * @param string  $filename
     * @param Boolean $withDot
     *
     * @return string
     */
    public static function getExtension($filename, $withDot = true)
    {
        $filename = basename($filename);
        if (false === strrpos($filename, '.')) {
            return '';
        }

        return substr($filename, strrpos($filename, '.') - strlen($filename) + ($withDot ? 0 : 1));
    }

    /**
     * Removes the extension file from its name
     *
     * @param string $filename
     *
     * @return string
     */
    public static function removeExtension($filename)
    {
        if (false === strrpos($filename, '.')) {
            return $filename;
        }

        return substr($filename, 0, strrpos($filename, '.'));
    }

    /**
     * Makes directory
     *
     * @param string $path The directory path
     *
     * @return boolean                                           Returns TRUE on success
     * @throws \BackBee\Utils\Exception\InvalidArgumentException Occures if directory cannot be created
     */
    public static function mkdir($path)
    {
        if (is_dir($path)) {
            if (is_writable($path)) {
                return true;
            }

            throw new InvalidArgumentException(sprintf("Directory %s already exists and is no writable.", $path));
        }

        if (!is_writable(dirname($path)) || false === @mkdir($path, 0755, true)) {
            throw new InvalidArgumentException(sprintf("Unable to create directory %s.", $path));
        }

        return true;
    }

    /**
     * Copies file
     *
     * @param string $from The source file path
     * @param string $to   The target file path
     *
     * @return boolean                                            Returns TRUE on success
     * @throws \BackBee\Utils\Exception\InvalidArgumentsException Occures if either $from or $to is invalid
     * @throws \BackBee\Utils\Exception\ApplicationException      Occures if the copy fails
     */
    public static function copy($from, $to)
    {
        if (false === $frompath = self::realpath($from)) {
            throw new InvalidArgumentException(sprintf("The file %s cannot be accessed.", $from));
        }

        if (!is_readable($frompath) || is_dir($frompath)) {
            throw new InvalidArgumentException(sprintf("The file %s doesn't exists or cannot be read.", $from));
        }

        $topath = self::normalizePath($to);
        if (!is_writable(dirname($topath))) {
            self::mkdir(dirname($topath));
        }

        if (false === copy($frompath, $topath)) {
            throw new ApplicationException(sprintf("Enable to copy file %s to %s.", $from, $to));
        }

        return true;
    }

    /**
     * Moves file
     *
     * @param string $from The source file path
     * @param string $to   The target file path
     *
     * @return boolean                                           Returns true on success
     * @throws \BackBee\Utils\Exception\InvalidArgumentException Occures if either $from or $to is invalid
     * @throws \BackBee\Utils\Exception\ApplicationException     Occures if $from file can not be deleted
     */
    public static function move($from, $to)
    {
        if (false === $frompath = self::realpath($from)) {
            throw new InvalidArgumentException(sprintf("The file %s cannot be accessed.", $from));
        }

        if (!is_writable($frompath) || is_dir($frompath)) {
            throw new InvalidArgumentException(sprintf("The file %s doesn't exists or cannot be written.", $from));
        }

        self::copy($from, $to);

        if (false === @unlink($frompath)) {
            throw new ApplicationException(sprintf("Enable to delete file %s.", $from));
        }

        return true;
    }

    /**
     * Looks recursively in $basedir for files with $extension
     *
     * @param string $basedir
     * @param string $extension
     *
     * @return array
     * @throws \BackBee\Utils\Exception\InvalidArgumentException Occures if $basedir is unreachable
     */
    public static function getFilesRecursivelyByExtension($basedir, $extension)
    {
        if (!is_readable($basedir)) {
            throw new InvalidArgumentException(sprintf("Cannot read the directory %s .", $basedir));
        }

        $files = [];
        parse_url($basedir);

        $directory = new RecursiveDirectoryIterator($basedir);
        $iterator = new RecursiveIteratorIterator($directory);

        if (empty($extension)) {
            // extension is empty - assume user wants files without extension only
            $regex = '/^(.*?(\/|\\\\))?[^\.]+$/i';
        } else {
            $regex = '#^.+\.' . ltrim($extension, '.') . '$#i';
        }

        $regex = new RegexIterator($iterator, $regex, RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $file) {
            $files[] = $file[0];
        }

        sort($files);

        return $files;
    }

    /**
     * Looks in $basedir for files with $extension
     *
     * @param string $basedir
     * @param string $extension
     *
     * @return array
     * @throws \BackBee\Exception\InvalidArgumentException Occures if $basedir is unreachable
     */
    public static function getFilesByExtension($basedir, $extension)
    {
        if (!is_readable($basedir)) {
            throw new InvalidArgumentException(sprintf('Cannot read the directory %s', $basedir));
        }

        $files = [];
        $parse_url = parse_url($basedir);
        if (false !== $parse_url && isset($parse_url['scheme'])) {
            foreach (Dir::getContent($basedir) as $file) {
                if (is_dir($file)) {
                    continue;
                }

                if (
                    ('' === $extension && false === strpos(basename($file), '.'))
                    || ('' !== $extension && $extension === substr($file, -1 * strlen($extension)))
                ) {
                    $files[] = $basedir . DIRECTORY_SEPARATOR . $file;
                }
            }
        } else {
            $pattern = '';

            if (!empty($extension)) {
                foreach (str_split($extension) as $letter) {
                    $pattern .= '[' . strtolower($letter) . strtoupper($letter) . ']';
                }
                $pattern = $basedir . DIRECTORY_SEPARATOR . '*.' . $pattern;

                $files = glob($pattern);
            } else {
                $pattern = $basedir . DIRECTORY_SEPARATOR . '*';
                $allFiles = glob($pattern);

                foreach ($allFiles as $filePath) {
                    if (false === strrpos($filePath, '.')) {
                        $files[] = $filePath;
                    }
                }
                unset($allFiles);
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Extracts a zip archive into a specified directory
     *
     * @param string $file           zip         archive file
     * @param string $destinationDir where the files will be extracted to
     * @param bool   $createDir      should destination dir be created if it doesn't exist
     *
     * @throws \BackBee\Utils\Exception\ApplicationException Occures if there is an issue with $destinationDir
     */
    public static function extractZipArchive($file, $destinationDir, $createDir = false)
    {
        if (!file_exists($destinationDir)) {
            if (false === $createDir) {
                throw new ApplicationException(sprintf("Destination directory does not exist: %s .", $destinationDir));
            }

            $folderCreation = mkdir($destinationDir, 0777, true);
            if (false === $folderCreation) {
                throw new ApplicationException(
                    sprintf("Destination directory cannot be created: %s .", $destinationDir)
                );
            }

            if (!is_readable($destinationDir)) {
                throw new ApplicationException(sprintf("Destination directory is not readable: %s .", $destinationDir));
            }
        } elseif (!is_dir($destinationDir)) {
            throw new ApplicationException(
                sprintf(
                    "Destination directory cannot be created as a file with that name already exists: %s .",
                    $destinationDir
                )
            );
        }

        $archive = new ZipArchive();

        if (false === $archive->open($file)) {
            throw new ApplicationException(sprintf("Could not open archive: %s .", $archive));
        }
        try {
            $archive->extractTo($destinationDir);
        } catch (\Exception $e) {
            throw new ApplicationException(sprintf("Could not extract archive from path: %s .", $file));
        }

        $archive->close();
    }
}
