<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

namespace BackBee\Renderer\Helper;

use BackBee\MetaData\MetaData as bbMetaData;
use BackBee\NestedNode\Page;
use BackBee\Renderer\AbstractRenderer;

/**
 * Helper generating <META> tag for the page being rendered
 * if none available, the default metadata are generaed.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class metadata extends AbstractHelper
{

    /**
     * The current dependency injection container.
     *
     * @var \BackBee\DependencyInjection\Container
     */
    private $container;

    /**
     * Class constructor.
     *
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $this->container = $this->getRenderer()
                ->getApplication()
                ->getContainer();
    }

    /**
     * @return string The generated metadata for the page.
     */
    public function __invoke()
    {
        if (null === $page = $this->getRenderer()->getCurrentPage()) {
            return '';
        }

        $metadata = $page->getMetadata();
        if (null === $metadata || $metadata->count() === 0) {
            $page = $this->saveIfEmpty($page);
        }

        $result = '';
        foreach ($page->getMetadata() as $meta) {
            $result .= $this->generateMeta($meta);
        }

        return $result;
    }

    /**
     * Compute and save metadata if $page has no one.
     *
     * @param  Page $page
     *
     * @return Page
     */
    private function saveIfEmpty(Page $page)
    {
        $metadata = $this->container
                ->get('nestednode.metadata.resolver')
                ->resolve($page);

            $page->setMetaData($metadata);
            if ($this->container->get('em')->contains($page)) {
                $this->container->get('em')->flush($page);
            }

            return $page;
    }

    /**
     * Generates HTML tag according to the metadata.
     *
     * @param  bbMetaData $meta
     *
     * @return string
     */
    private function generateMeta(bbMetaData $meta)
    {
        if (0 === $meta->count() || 'title' === $meta->getName()) {
            return '';
        }

        $result = '<meta ';
        foreach ($meta as $attribute => $value) {
            if (false !== strpos($meta->getName(), 'keyword') && 'content' === $attribute) {
                $keywords = explode(',', $value);
                foreach ($this->getKeywordObjects($keywords) as $object) {
                    $value = trim(str_replace($object->getUid(), $object->getKeyWord(), $value), ',');
                }
            }

            if ('content' === $attribute && empty($value)) {
                $result = '';
                break;
            }

            $result .= $attribute . '="' . html_entity_decode($value, ENT_COMPAT, 'UTF-8') . '" ';
        }

        return empty($result) ? $result : $result.'/>'.PHP_EOL;
    }

    /**
     * Returns KeyWord entities with provided array.
     *
     * @param  array  $keywords
     * @return array
     */
    private function getKeywordObjects(array $keywords)
    {
        return $this->getRenderer()
            ->getApplication()
            ->getEntityManager()
            ->getRepository('BackBee\NestedNode\KeyWord')
            ->getKeywordsFromElements($keywords)
        ;
    }

}
