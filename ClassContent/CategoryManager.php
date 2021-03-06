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

namespace BackBee\ClassContent;

use BackBee\ApplicationInterface;
use BackBee\AutoLoader\Exception\ClassNotFoundException;
use BackBee\Util\File\File;

/**
 * CategoryManager provides every classcontent categories of the current application.
 *
 * @category    BackBee
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class CategoryManager
{
    /**
     * Contains every class content categories (type: BackBee\ClassContent\Category)
     * of current application and its bundles.
     *
     * @var array
     */
    private $categories;

    /**
     * Categories common options (thumbnail_url_pattern, etc.).
     *
     * @var array
     */
    private $options;

    /**
     * CategoryManager's constructor.
     *
     * @param ApplicationInterface $application application from where we will extract classcontent's categories
     */
    public function __construct(ApplicationInterface $application)
    {
        $this->categories = [];
        $this->options = [
            'thumbnail_url_pattern' => $application->getRouting()->getUrlByRouteName(
                'bb.classcontent_thumbnail', [
                    'filename' => '%s.'.$application->getContainer()->getParameter('classcontent_thumbnail.extension'),
                ]
            ),
        ];

        $this->loadCategoriesFromClassContentDirectories($application->getClassContentDir());
    }

    /**
     * Returns category by name or id.
     *
     * @param string $v category name or id
     *
     * @return Category|null return category object if provided name/id exists, else null
     */
    public function getCategory($v)
    {
        $v = $this->buildCategoryId($v);

        return isset($this->categories[$v]) ? $this->categories[$v] : null;
    }

    /**
     * Categories attribute getter.
     *
     * @return array
     */
    public function getCategories()
    {
        return $this->categories;
    }

    public function getClassContentClassnamesByCategory($name)
    {
        $category = $this->getCategory($name);
        if (null === $category) {
            throw new \InvalidArgumentException("`$name` is not a valid classcontent category.");
        }

        $classnames = [];
        foreach ($category->getBlocks() as $block) {
            $classnames[] = AbstractClassContent::getClassnameByContentType($block->type);
        }

        return $classnames;
    }

    /**
     * Parse classcontent directories and hydrate categories attribute.
     *
     * @param array $directories classcontent directories
     */
    private function loadCategoriesFromClassContentDirectories($directories)
    {
        $classcontents = [];
        foreach ($directories as $directory) {
            $classcontents = array_merge($classcontents, self::getClassContentClassnamesFromDir($directory));
        }

        foreach ($classcontents as $class) {
            try {
                if (class_exists($class)) {
                    $this->buildCategoryFromClassContent(new $class());
                }
            } catch (ClassNotFoundException $e) {
                // nothing to do
            }
        }
    }

    /**
     * Build and/or hydrate Category object with provided classcontent.
     *
     * @param AbstractClassContent $content
     */
    private function buildCategoryFromClassContent(AbstractClassContent $content)
    {
        foreach ((array) $content->getProperty('category') as $category) {
            $visible = true;
            if ('!' === $category[0]) {
                $visible = false;
                $category = substr($category, 1);
            }

            $id = $this->buildCategoryId($category);
            if (false === array_key_exists($id, $this->categories)) {
                $this->categories[$id] = new Category($category, $this->options);
                ksort($this->categories);
            }

            $this->categories[$id]->addBlock($content, $visible, $this->options);
        }
    }

    /**
     * Build id for category by sluggify its name.
     *
     * @param string $name category's name
     *
     * @return string
     */
    private function buildCategoryId($name)
    {
        return mb_strtolower(str_replace(' ', '_', $name), 'UTF-8');
    }

    /**
     * Returns all content classnames found  in $directory.
     *
     * @param  string $directory The directory to look at.
     *
     * @return string[]          An array of content classnames found in directory.
     */
    public static function getClassContentClassnamesFromDir($directory)
    {
        if (!is_dir($directory)) {
            return [];
        }

        return array_map(
            function ($path) use ($directory) {
                return str_replace(
                    [DIRECTORY_SEPARATOR, '\\\\'],
                    [NAMESPACE_SEPARATOR, NAMESPACE_SEPARATOR],
                    AbstractClassContent::CLASSCONTENT_BASE_NAMESPACE.str_replace([$directory, '.yml'], ['', ''], $path)
                );
            },
            File::getFilesRecursivelyByExtension($directory, 'yml')
        );
    }
}
