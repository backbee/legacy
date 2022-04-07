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

namespace BackBee\MetaData;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\ContentSet;
use BackBee\ClassContent\Element\File;
use BackBee\ClassContent\Element\Keyword;
use BackBee\ClassContent\Element\Text;
use BackBee\ClassContent\Revision;
use BackBee\Config\Config;
use BackBee\NestedNode\Page;

use Doctrine\ORM\EntityManager;

/**
 * Default metadata resolver.
 *
 * Metadata instance is composed by a name and a set of key/value attributes
 * The attribute can be staticaly defined in yaml file or to be computed:
 *
 *     description:
 *       name: 'description'
 *       content:
 *         default: "Default value"
 *         layout:
 *           f5da92419743370d7581089605cdbc6e: $ContentSet[0]->$actu[0]->$chapo
 *           actualite: $ContentSet[0]->$actu[0]->$chapo
 *       lang: 'en'
 *
 * In this example, the attribute `lang` is static and set to `fr`, the attribute
 * `content` will be set to `Default value`:
 *     <meta name="description" content="Default value" lang="en">
 *
 * But if the page has the layout with uid `f5da92419743370d7581089605cdbc6e`  or
 * the layout with label `news` the attribute `content` will set according to the
 * scheme:
 * value of the element `chapo` of the first `content `actu` in the first column.
 *
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class MetaDataResolver implements MetaDataResolverInterface
{
    const MATCH_PATTERN = '/(\$([a-z_\/\\\\]+)(\[([0-9]+)\]){0,1}(->){0,1})+/i';
    const ITEM_PATTERN  = '/\$([a-z_\/\\\\]+)(\[([0-9]+)\]){0,1}/i';
    const CONST_PATTERN = '/^%([a-z]+)$/i';

    /**
     * Metadata definitions.
     *
     * @var array
     */
    private $definitions;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * Class constructor.
     *
     * @param array $definitions
     */
    public function __construct(array $definitions = null)
    {
        $this->setDefinitions($definitions);
    }

    /**
     * Sets Metadata definitions.
     *
     * @param  array $definitions
     *
     * @return MetaDataResolver
     */
    public function setDefinitions(array $definitions = null)
    {
        $this->definitions = $definitions;

        return $this;
    }

    /**
     * Reads metadata definitions from configuration.
     *
     * @param Config $config
     * @param string $sectionName
     *
     * @return MetaDataResolver
     */
    public function setDefinitionsFromConfig(Config $config, $sectionName = 'metadata')
    {
        return $this->setDefinitions($config->getSection($sectionName));
    }

    /**
     * Sets the entity manager.
     *
     * @param EntityManager $entityManager
     *
     * @return MetaDataResolver
     */
    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;

        return $this;
    }

    /**
     * Returns computed metadata from provided $page.
     *
     * @param  Page|NULL $page
     *
     * @return MetaDataBag
     */
    public function resolve(Page $page = null)
    {
        if (null === $page || (null === $metadataBag = $page->getMetaData())) {
            $metadataBag = new MetaDataBag();
        }

        if (!is_array($this->definitions)) {
            return $metadataBag;
        }

        foreach ($this->definitions as $name => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (null === $metadata = $metadataBag->get($name)) {
                $metadata = new MetaData($name);
                $metadataBag->add($metadata);
            }

            $this->parseDefinition($definition, $metadata, $page);
        }

        return clone $metadataBag;
    }

    /**
     * Parses definition and resolve value for $metadata.
     *
     * @param array     $definition
     * @param MetaData  $metadata
     * @param Page|NULL $page
     */
    private function parseDefinition(array $definition, MetaData $metadata, Page $page = null)
    {
        foreach ($definition as $attrname => $attrvalue) {
            if (!is_array($attrvalue)) {
                $attrvalue = [$attrvalue];
            }

            if (!$metadata->isComputed($attrname)) {
                continue;
            }
            $scheme = ('' === $metadata->getAttribute($attrname)) ? reset($attrvalue) : $metadata->getAttribute($attrname);

	    if (
                    'title' === $metadata->getName()
                    && null !== $page
                    && null !== $page->getTitle()
                    && array_key_exists('default', $attrvalue)
            ) {
                $scheme = $page->getTitle();
            } elseif (array_key_exists('default', $attrvalue)) {
                $scheme = $attrvalue['default'];
            }

            if (
                    null !== $page
                    && null !== $page->getLayout()
                    && array_key_exists('layout', $attrvalue)
                    && array_key_exists($page->getLayout()->getUid(), $attrvalue['layout'])
            ) {
                $scheme = $attrvalue['layout'][$page->getLayout()->getUid()];
            }

            if (
                    null !== $page
                    && null !== $page->getLayout()
                    && array_key_exists('layout', $attrvalue)
                    && array_key_exists($page->getLayout()->getLabel(), $attrvalue['layout'])
            ) {
                $scheme = $attrvalue['layout'][$page->getLayout()->getLabel()];
            }

            $matches = [];
            $isComputed = true;
            if (null !== $page && preg_match(self::MATCH_PATTERN, $scheme)) {
                $value = $this->resolveScheme($scheme, $page);
            } elseif (preg_match(self::CONST_PATTERN, $scheme, $matches)) {
                $value = $this->resolveConst($matches[1], $page);
            } else {
                $value = $scheme;
                $scheme = null;
                $isComputed = false;
            }
            $metadata->setAttribute($attrname, $value, $scheme, $isComputed);
        }
    }

    /**
     * Resolves $scheme against provided $content.
     *
     * @param  string $scheme
     * @param  Page   $page
     *
     * @return string
     */
    private function resolveScheme($scheme, Page $page)
    {
        $functions = explode('||', $scheme);
        $value = array_shift($functions);
        $content = $page->getContentSet();

        $matches = [];
        if (false !== preg_match_all(self::MATCH_PATTERN, $scheme, $matches, PREG_PATTERN_ORDER)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $resolvedContent = $this->parseScheme($content, $matches[0][$i]);

                if ($resolvedContent instanceof AbstractClassContent && $resolvedContent->isElementContent()) {
                    $scheme = str_replace($matches[0][$i], $this->getElementValue($resolvedContent, $page), $scheme);
                } elseif (is_array($resolvedContent)) {
                    $scheme = str_replace($matches[0][$i], $this->getArrayValue($resolvedContent), $scheme);
                } else {
                    $resolvedContent = trim(str_replace(["\n", "\r"], '', strip_tags($resolvedContent)));
                    $scheme = str_replace($matches[0][$i], $resolvedContent, $scheme);
                }
            }
            $value = $scheme;
        }

        return $this->callback($value, $functions);
    }

    /**
     * Resolves constant value.
     *
     * @param  string    $const
     * @param  Page|NULL $page
     *
     * @return string
     */
    protected function resolveConst($const, Page $page = null)
    {
        $value = '';
        switch (strtolower($const)) {
            case 'url':
                $value = (null !== $page) ? $page->getUrl() : '';
            default:
                break;
        }

        return $value;
    }

    /**
     * Applies callback functions on $value.
     *
     * @param  string $value
     * @param  array  $callbacks
     *
     * @return string
     */
    private function callback($value, array $callbacks)
    {
        foreach ($callbacks as $fct) {
            $parts = explode(':', $fct);
            $functionName = array_shift($parts);
            array_unshift($parts, $value);
            $value = call_user_func_array($functionName, $parts);
        }

        return $value;
    }

    /**
     * Returns value for Element content.
     *
     * @param  AbstractClassContent $content
     *
     * @return string
     */
    private function getElementValue(AbstractClassContent $content, Page $page)
    {
        $draft = $this->releaseDraft($content);

        if ($content instanceof File) {
            $value = $content->path;
        } elseif ($content instanceof Keyword) {
            $value = $this->getKeyword($content);
        } else {
            $value = trim(str_replace(array("\n", "\r"), '', strip_tags(''.$content)));
        }

        $this->setMainNode($content, $page);
        $this->restoreDraft($content, $draft);

        return $value;
    }

    /**
     * Sets the main node value of $content to $page.
     * Recompute $content if need.
     *
     * @param AbstractClassContent $content
     * @param Page                 $page
     */
    private function setMainNode(AbstractClassContent $content, Page $page)
    {
        if (null !== $content->getMainNode()) {
            return;
        }

        $content->setMainNode($page);

        if (null !== $this->entityManager) {
            $uow = $this->entityManager->getUnitOfWork();
            $meta = $this->entityManager->getClassMetadata(get_class($content));
            if ($uow->isEntityScheduled($content)) {
                $uow->recomputeSingleEntityChangeSet($meta, $content);
            } elseif ($uow->isEntityScheduled($page)) {
                $uow->computeChangeSet($meta, $content);
            }
        }
    }

    /**
     * Returns value for array.
     *
     * @param  array $array
     *
     * @return string
     */
    private function getArrayValue(array $array)
    {
        $value = [];
        foreach ($array as $item) {
            if ($item instanceof Keyword) {
                $item = $this->getKeyword($item);
            }
            $value[] = trim(str_replace(array("\n", "\r"), '', strip_tags('' . $item)));
        }

        array_walk($value, function(&$item) { return trim(str_replace(array("\n", "\r"), '', strip_tags('' . $item))); });

        return implode(',', array_filter($value));
    }

    /**
     * Returns literal keyword for $item
     *
     * @param  Keyword $item
     *
     * @return string
     */
    private function getKeyword(Keyword $item)
    {
        if (null === $this->entityManager) {
            return '';
        }

        if (null === $keyword = $this->entityManager->find('BackBee\NestedNode\KeyWord', $item->value)) {
            return '';
        }

        return $keyword->getKeyWord();
    }

    /**
     * Parses $expr against $content
     *
     * @param  mixed  $content
     * @param  string $expr
     *
     * @return mixed
     */
    private function parseScheme($content, $expr)
    {
        foreach (explode('->', $expr) as $scheme) {
            $draft = $this->releaseDraft($content);

            $resolvedContent = $content;
            $matches = [];
            if (preg_match(self::ITEM_PATTERN, $scheme, $matches)) {
                $resolvedContent = $this->resolveContent($content, $matches);
            }

            $this->restoreDraft($content, $draft);
            $content = $resolvedContent;
        }

        return $content;
    }

    /**
     * Release content draft and returns it.
     *
     * @param  AbstractClassContent $content
     *
     * @return Revision|NULL
     */
    private function releaseDraft($content)
    {
        $draft = null;
        if (
                $content instanceof AbstractClassContent
                && (null !== $draft = $content->getDraft())
        ) {
            $content->releaseDraft();
        }

        return $draft;
    }

    /**
     *
     * @param AbstractClassContent $content
     * @param type $draft
     */
    private function restoreDraft($content, Revision $draft = null)
    {
        if (
                null !== $draft
                && $content instanceof AbstractClassContent
        ) {
            $content->setDraft($draft);
        }
    }

    /**
     * Resolves $content value.
     *
     * @param  mixed $content
     * @param  array $matches
     *
     * @return mixed
     */
    private function resolveContent($content, array $matches)
    {
        if (3 < count($matches) && $content instanceof ContentSet) {
            return $this->resolveContentSet($content, $matches);
        }

        if (1 < count($matches) && is_object($content)) {
            $property = $matches[1];
            try {
                return $content->$property;
            } catch (\Exception $e) {
                return new Text();
            }
        }

        return $content;
    }

    /**
     * Resolves $content value.
     *
     * @param  ContentSet $content
     * @param  array      $matches
     *
     * @return mixed
     */
    private function resolveContentSet(ContentSet $content, array $matches)
    {
        if ('ContentSet' === $matches[1]) {
            return $content->item($matches[3]);
        }

        $index = intval($matches[3]);
        $classname = AbstractClassContent::getFullClassname(str_replace('/', NAMESPACE_SEPARATOR, $matches[1]));

        foreach ($content as $subcontent) {
            if (get_class($subcontent) !== $classname) {
                continue;
            }

            if (0 === $index) {
                return $subcontent;
            }

            $index--;
        }

        return $content;
    }
}
