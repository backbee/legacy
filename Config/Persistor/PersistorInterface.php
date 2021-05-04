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

namespace BackBee\Config\Persistor;

use BackBee\ApplicationInterface;
use BackBee\Config\Config;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
interface PersistorInterface
{
    /**
     *
     * @param ApplicationInterface $application           The BackBee application instance
     * @param boolean              $persistPerContext     Is one config is persisted per application context ?
     * @param boolean              $persistPerEnvironment Is one config is persisted per application environment ?
     */
    public function __construct(ApplicationInterface $application, $persistPerContext, $persistPerEnvironment);

    /**
     *
     * @param \BackBee\Config\Config $config    The BackBee configuration instance
     * @param array  $config_to_persist The configuration to perist
     *
     * @return boolean returns true if persisting operation succeed
     */
    public function persist(Config $config, array $config_to_persist);
}
