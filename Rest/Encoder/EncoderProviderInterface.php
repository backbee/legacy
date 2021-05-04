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

/**
 * Defines the interface of encoder providers.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
interface EncoderProviderInterface
{
    /**
     * Check if a certain format is supported.
     *
     * @param string $format Format for the requested decoder.
     *
     * @return Boolean
     */
    public function supports($format);

    /**
     * Provides decoders, possibly lazily.
     *
     * @param string $format Format for the requested decoder.
     *
     * @return FOS\RestBundle\Decoder\DecoderInterface
     */
    public function getEncoder($format);
}
