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

namespace BackBee\Site\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class SiteRepository extends EntityRepository
{
    public function findByServerName($serverName)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s._server_name = :server_name')
            ->setParameters([
                'server_name' => $serverName
            ])
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Returns site entity according to custom server_name if it exists in sites_config.
     *
     * @param string $server_name
     * @param array  $sites_config
     *
     * @return null|BackBee\Site\Site
     */
    public function findByCustomServerName($server_name, array $sites_config)
    {
        $site_label = null;
        foreach ($sites_config as $key => $data) {
            if ($server_name === $data['domain']) {
                $site_label = $key;
                break;
            }
        }

        $site = null;
        if (null !== $site_label) {
            $site = $this->findOneBy(array(
                '_label' => $site_label,
            ));
        }

        return $site;
    }
}
