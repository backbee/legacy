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

namespace BackBee\Bundle;

use BackBee\ApplicationInterface;
use BackBee\Routing\RouteCollection;

/**
 * Bundle Manager.
 *
 * @author Djoudi Bensid <d.bensid@obione.eu>
 */
class BundleManager
{
    /**
     * @var \BackBee\ApplicationInterface
     */
    private $application;

    /**
     * @var \BackBee\Routing\RouteCollection
     */
    private $routeCollection;

    /**
     * Constructor.
     *
     * @param \BackBee\ApplicationInterface    $application
     * @param \BackBee\Routing\RouteCollection $routeCollection
     */
    public function __construct(ApplicationInterface $application, RouteCollection $routeCollection)
    {
        $this->application = $application;
        $this->routeCollection = $routeCollection;
    }

    /**
     * Get activated bundles.
     *
     * @return array
     */
    public function getActivatedBundles(): array
    {
        $bundles = [];

        foreach ($this->application->getBundles() as $bundle) {
            if (true === $bundle->getProperty('enable_user_right')) {
                $bundles[] = $bundle->getId();

                continue;
            }

            $expectedAdminEntryPointRouteName = sprintf(
                'bundle.%s.admin_entrypoint',
                $bundle->getId()
            );

            if ($this->routeCollection->get($expectedAdminEntryPointRouteName)) {
                $bundles[] = $bundle->getId();
            }
        }

        return $bundles;
    }
}
