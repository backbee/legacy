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

namespace BackBee\Bundle;

/**
 * This interface ensure that an exposed controller has an entry point (indexAction),
 * a label getter and a description getter.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
interface BundleExposedControllerInterface
{
    /**
     * This exposed controller entry point.
     *
     * @return Symfony\Component\HttpFoundation\Response the response object to send
     */
    public function indexAction();

    /**
     * This exposed controller entry point label.
     *
     * @return string
     */
    public function getLabel();

    /**
     * This exposed controller description.
     *
     * @return string
     */
    public function getDescription();
}
