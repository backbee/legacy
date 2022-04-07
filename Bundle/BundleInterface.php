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

use BackBee\Security\Acl\Domain\ObjectIdentifiableInterface;

/**
 * BundleInterface which define somes methods to implements for BackBee bundles; it also define some constants.
 *
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
interface BundleInterface extends ObjectIdentifiableInterface, \JsonSerializable
{
    /**
     * service id pattern (for bundle and bundle's config).
     */
    const BUNDLE_SERVICE_ID_PATTERN = 'bundle.%bundle_name%';
    const CONFIG_SERVICE_ID_PATTERN = '%bundle_service_id%.config';
    const BUNDLE_ADMIN_URL_PATTERN = '/bundle/%bundle_id%/%controller_id%/%action_id%';

    /**
     * Config directories names.
     */
    const CONFIG_DIRECTORY_NAME = 'Config';
    const OLD_CONFIG_DIRECTORY_NAME = 'Ressources';

    const DEFAULT_CONFIG_PER_SITE_VALUE = true;

    /**
     * Returns the bundle id.
     *
     * @return string bundle id
     */
    public function getId();

    /**
     * Returns bundle base directory.
     *
     * @return string bundle base directory
     */
    public function getBaseDirectory();

    /**
     * Returns bundle property if you provide key, else every properties; a bundle
     * property is any key/value defined in 'bundle'section in config.yml.
     *
     * @param string $key name of the property
     *
     * @return string|array value of the property if key is not null, else an array which contains every properties
     */
    public function getProperty($key = null);

    /**
     * Method to call when we get the bundle for the first time.
     */
    public function start();

    /**
     * Method to call before stop or destroy of current bundle.
     */
    public function stop();

    /**
     * Returns the application current bundle is registered into.
     *
     * @return BackBee\BBApplication application that own current bundle
     */
    public function getApplication();

    /**
     * Current bundle entity manager.
     *
     * @return Doctrine\ORM\EntityManager
     */
    public function getEntityManager();

    /**
     * Defines if current bundle is started or not.
     *
     * @return boolean true if the bundle is started, else false
     */
    public function isStarted();

    /**
     * Switch current bundle as started (so self::isStarted() will return true).
     */
    public function started();

    /**
     * Defines if current bundle require different config per site or not.
     *
     * @return boolean true if current bundle require a different config per site, else false
     */
    public function isConfigPerSite();

    /**
     * Checks if current bundle is enabled or not (it also defines if it is
     * loaded by BundleLoader into application).
     *
     * @return boolean true if the bundle is enabled, else false
     */
    public function isEnabled();
}
