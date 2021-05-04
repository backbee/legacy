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

namespace BackBee\Rest\Encoder;

use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * Provides encoders through the Symfony2 DIC.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class ContainerEncoderProvider extends ContainerAware implements EncoderProviderInterface
{
    /**
     * @var array
     */
    private $encoders;

    /**
     * Constructor.
     *
     * @param array $encoders List of key (format) value (service ids) of encoders
     */
    public function __construct(array $encoders)
    {
        $this->encoders = $encoders;
    }

    /**
     * @param string $format format
     *
     * @return boolean
     */
    public function supports($format)
    {
        return isset($this->encoders[$format]);
    }

    /**
     * @param string $format format
     *
     * @throws \InvalidArgumentException
     *
     * @return FOS\RestBundle\Decoder\DecoderInterface
     */
    public function getEncoder($format)
    {
        if (!$this->supports($format)) {
            throw new \InvalidArgumentException(sprintf("Format '%s' is not supported by ContainerDecoderProvider.", $format));
        }

        return $this->container->get($this->encoders[$format]);
    }
}
