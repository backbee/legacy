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

/**
 * @author      n.dufreche <nicolas.dufreche@lp-digital.fr>
 */
class Dir
{
    /**
     * Recursive path copy for php.
     *
     * @param string $start_path
     * @param string $copy_path
     *
     * @return boolean
     */
    public static function copy($start_path, $copy_path, $dir_mode = 0777)
    {
        $return = mkdir($copy_path, $dir_mode, true);
        $files = self::getContent($start_path);
        foreach ($files as $file) {
            if (is_dir($start_path . DIRECTORY_SEPARATOR . $file)) {
                $return = self::copy(
                    rtrim($start_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file,
                    $copy_path . DIRECTORY_SEPARATOR . $file,
                    $dir_mode
                );
            } else {
                $return = copy(
                    rtrim($start_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file,
                    $copy_path . DIRECTORY_SEPARATOR . $file
                );
            }
        }

        return $return;
    }

    /**
     * rm -rf commande like.
     *
     * @param type $path
     *
     * @return boolean
     */
    public static function delete($path)
    {
        $files = self::getContent($path);
        foreach ($files as $file) {
            if (is_dir($path . DIRECTORY_SEPARATOR . $file)) {
                self::delete($path . DIRECTORY_SEPARATOR . $file);
            } else {
                @unlink($path . DIRECTORY_SEPARATOR . $file);
            }
        }

        return @rmdir($path);
    }

    /**
     * Parse dir content.
     *
     * @param string $path path location
     *
     * @return array
     */
    public static function getContent($path)
    {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), array('.', '..'));

            return $files;
        } else {
            throw new \Exception(sprintf('Incorrect path "%s" name in %s at line %s', $path, __FILE__, __LINE__));
        }
    }

    /**
     * Recursive copy for php.
     * The callback structure is an array containing (object, method, params) without keys.
     *
     * @param string $path Path to move
     * @param string $new_path Target
     * @param type   $dir_mode octal
     * @param array  $callback function to call behind copy and delete
     *
     * @return type
     */
    public static function move($path, $new_path, $dir_mode = 0777, array $callback = null)
    {
        $return = self::copy($path, $new_path, $dir_mode);
        if ($callback !== null && is_array($callback)) {
            self::execCallback($callback);
        }
        if ($return) {
            $return = self::delete($path);
        }

        return $return;
    }

    /**
     * Execute callback function.
     * The callback structure is an array containing (object, method, params) without keys.
     *
     * @param array $callback
     */
    private static function execCallback(array $callback)
    {
        if (count($callback) >= 2 && count($callback) <= 3) {
            try {
                if (count($callback) == 2) {
                    return call_user_func_array(array($callback[0], $callback[1]));
                }
                if (count($callback) == 3) {
                    return call_user_func_array(array($callback[0], $callback[1]), (array)$callback[2]);
                }
            } catch (\Exception $e) {
                unset($e);
            }
        }
    }
}
