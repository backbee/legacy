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

namespace BackBee\ClassContent\Iconizer;

use BackBee\ClassContent\AbstractContent;
use BackBee\Routing\RouteCollection;

/**
 * Iconizer returning URI define by the class content property `iconized-by`.
 *
 * @category    BackBee
 *
 *
 * @author      Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class PropertyIconizer implements IconizerInterface
{
    /**
     * @var RouteCollection
     */
    private $routeCollection;

    /**
     * Class constructor.
     *
     * @param RouteCollection $routeCollection
     */
    public function __construct(RouteCollection $routeCollection)
    {
        $this->routeCollection = $routeCollection;
    }

    /**
     * Returns the URI of the icon of the provided content.
     *
     * @param  AbstractContent $content The content.
     *
     * @return string|null              The icon URL if found, null otherwise.
     */
    public function getIcon(AbstractContent $content)
    {
        if (null === $property = $content->getProperty('iconized-by')) {
            return null;
        }

        return $this->parseProperty($content, $property);
    }

    /**
     * Parses the content property and return the icon URL if found.
     *
     * @param  AbstractContent $content  The content.
     * @param  string          $property The property to be parsed.
     *
     * @return string|null               The icon URL if found, null otherwise.
     */
    private function parseProperty(AbstractContent $content, $property)
    {
        $currentContent = $content;
        foreach (explode('->', $property) as $part) {
            if ('@' === substr($part, 0, 1)) {
                return $this->iconizeByParam($currentContent, substr($part, 1));
            } elseif ($currentContent->hasElement($part)) {
                $currentContent = $this->iconizedByElement($currentContent, $part);
            }

            if ($currentContent instanceof AbstractContent) {
                continue;
            } else {
                return $currentContent;
            }

            return null;
        }
    }

    /**
     * Returns the icon URL from the parameter value.
     *
     * @param  AbstractContent $content   The content.
     * @param  string          $paramName The parameter name.
     *
     * @return string|null                The icon URL.
     */
    private function iconizeByParam(AbstractContent $content, $paramName)
    {
        if (null === $parameter = $content->getParam($paramName)) {
            return null;
        }

        if (empty($parameter['value'])) {
            return null;
        }

        return $this->getUri($parameter['value']);
    }

    /**
     * Returns the icon URL from the element value if $elementName is scalar, the subcontent otherwise.
     *
     * @param  AbstractContent $content     The content.
     * @param  string          $elementName The element name.
     *
     * @return AbstractContent|string       If $content->$elementName is a content, the subcontent, otherwise the icon URL.
     */
    private function iconizedByElement(AbstractContent $content, $elementName)
    {
        if ($content->$elementName instanceof AbstractContent) {
            return $content->$elementName;
        }

        if (empty($content->$elementName)) {
            return null;
        }

        return $this->getUri($content->$elementName, is_a($content, 'BackBee\ClassContent\Element\Image') ? RouteCollection::IMAGE_URL : null);
    }

    /**
     * Returns the icon URL.
     *
     * @param  string      $pathinfo  The pathinfo to treate.
     * @param  int|null    $urlType   Optional, the URL prefix to use.
     *
     * @return string                 The icon URL.
     */
    private function getUri($pathinfo, $urlType = RouteCollection::DEFAULT_URL)
    {
        return $this->routeCollection->getUri($pathinfo, null, null, $urlType);
    }
}
