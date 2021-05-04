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

namespace BackBee\ClassContent\Iconizer;

use BackBee\ClassContent\AbstractContent;

/**
 * Iconizer returning the first not null URI returned by one of the chained iconizers.
 * 
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class ChainIconizer implements IconizerInterface
{
    /**
     * The chained inconizers.
     * 
     * @var IconizerInterface[]
     */
    private $iconizers;

    /**
     * Class constructor.
     * 
     * @param IconizerInterface[] $iconizers The chained iconizers.
     * 
     * @throws \InvalidArgumentException     Raises if one element of the chain isn't an IconizerInterface.
     */
    public function __construct(array $iconizers)
    {
        $this->iconizers = [];
        foreach ($iconizers as $iconizer) {
            if (!$iconizer instanceof IconizerInterface) {
                throw new \InvalidArgumentException(sprintf('Witing for an objet implementing IconizerInterface but got %s', get_class($iconizer)));
            }
            $this->iconizers[] = $iconizer;
        }
    }

    /**
     * Returns the URL of the icon of the provided content.
     * 
     * @param  AbstractContent $content The content.
     * 
     * @return string|null              The icon URL if found.
     */
    public function getIcon(AbstractContent $content)
    {
        $iconUri = null;
        foreach ($this->iconizers as $iconizer) {
            if (null !== $iconUri = $iconizer->getIcon($content)) {
                break;
            }            
        }
        return $iconUri;
    }
}
