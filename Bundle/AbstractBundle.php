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

use Symfony\Component\Security\Core\Util\ClassUtils;
use BackBee\ApplicationInterface;
use BackBee\Bundle\Event\BundleStartEvent;
use BackBee\Routing\RouteCollection;
use BackBee\Security\Acl\Domain\ObjectIdentifiableInterface;

/**
 * Abstract class for BackBee's bundle.
 *
 * @category    BackBee
 *
 *
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
abstract class AbstractBundle implements BundleInterface
{
    /**
     * Application this bundle belongs to.
     *
     * @var BackBee\ApplicationInterface
     */
    private $application;

    /**
     * Bundle base directory.
     *
     * @var string
     */
    private $baseDir;

    /**
     * Bundle identifier.
     *
     * @var string
     */
    private $id;

    /**
     * Define if this bundle is already started or not.
     *
     * @var boolean
     */
    private $started;

    /**
     * Formatted list of this bundle exposed actions.
     *
     * @var array
     */
    private $exposedActions;

    /**
     * Indexed by a unique name (controller name + action name), it contains every (controller; action)
     * callbacks.
     *
     * @var array
     */
    private $exposedActionsCallbacks;

    /**
     * AbstractBaseBundle's constructor.
     *
     * @param ApplicationInterface $application application to link current bundle with
     */
    public function __construct(ApplicationInterface $application, $id = null, $baseDir = null)
    {
        $this->application = $application;
        $bundleLoader = $this->application->getContainer()->get('bundle.loader');

        $this->baseDir = $baseDir ?: $bundleLoader->buildBundleBaseDirectoryFromClassname(get_class($this));
        $this->id = $id ?: basename($this->baseDir);

        $bundleLoader->loadConfigDefinition($this->getConfigServiceId(), $this->baseDir);

        $this->exposedActions = array();
        $this->exposedActionsCallbacks = array();
        $this->initBundleExposedActions();
        $this->started = false;
    }

    /**
     * @see BackBee\Bundle\BundleInterface::getId
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @see BackBee\Bundle\BundleInterface::getBaseDirectory
     */
    public function getBaseDirectory()
    {
        return $this->baseDir;
    }

    /**
     * @return the path to the Resources folder
     */
    public function getResourcesDirectory()
    {
        return $this->baseDir.DIRECTORY_SEPARATOR.'Resources';
    }

    /**
     * @see BackBee\Bundle\BundleInterface::getProperty
     */
    public function getProperty($key = null)
    {
        $properties = null;
        if (null !== $this->getConfig()) {
            $properties = $this->getConfig()->getSection('bundle') ?: array();
        }

        if (null === $key) {
            return $properties;
        }

        $property = null;
        if (true === array_key_exists($key, $properties)) {
            $property = $properties[$key];
        }

        return $property;
    }

    /**
     * @see BackBee\Bundle\BundleInterface::getConfig
     */
    public function getConfig()
    {
        return $this->application->getContainer()->get($this->getConfigServiceId());
    }

    /**
     * Bundle's config service id getter.
     *
     * @return string
     */
    public function getConfigServiceId()
    {
        return strtolower(str_replace(
            '%bundle_service_id%',
            str_replace('%bundle_name%', $this->id, BundleInterface::BUNDLE_SERVICE_ID_PATTERN),
            BundleInterface::CONFIG_SERVICE_ID_PATTERN
        ));
    }

    /**
     * Bundle base directory getter.
     *
     * @return string
     */
    public function getConfigDirectory()
    {
        $directory = $this->baseDir.DIRECTORY_SEPARATOR.BundleInterface::CONFIG_DIRECTORY_NAME;
        if (false === is_dir($directory)) {
            $directory = $this->baseDir.DIRECTORY_SEPARATOR.BundleInterface::OLD_CONFIG_DIRECTORY_NAME;
        }

        return $directory;
    }

    /**
     * @see BackBee\Bundle\BundleInterface::isConfigPerSite
     */
    public function isConfigPerSite()
    {
        return null !== $this->getProperty('config_per_site')
            ? $this->getProperty('config_per_site')
            : BundleInterface::DEFAULT_CONFIG_PER_SITE_VALUE
        ;
    }

    /**
     * @see BackBee\Bundle\BundleInterface::getApplication
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @see BackBee\Bundle\BundleInterface::getEntityManager
     */
    public function getEntityManager()
    {
        return $this->application->getEntityManager();
    }

    /**
     * @see BackBee\Bundle\BundleInterface::isStarted
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * @see BackBee\Bundle\BundleInterface::started
     */
    public function started()
    {
        $this->started = true;

        if ($this->application->getContainer()->has('event.dispatcher')) {
            $this->application
                ->getContainer()
                ->get('event.dispatcher')
                ->dispatch(sprintf('bundle.%s.started', $this->getId()), new BundleStartEvent($this));
        }
    }

    /**
     * @see BackBee\Bundle\BundleInterface::isEnabled
     */
    public function isEnabled()
    {
        return true === $this->getProperty('enable');
    }

    /**
     * enable property setter.
     *
     * @param boolean $enable
     *
     * @return self
     */
    public function setEnable($enable)
    {
        $properties = $this->getProperty();
        $properties['enable'] = (boolean) $enable;
        $this->getConfig()->setSection('bundle', $properties, true);

        return $this;
    }

    /**
     * category property setter.
     *
     * @param string|array $category
     *
     * @return self
     */
    public function setCategory($category)
    {
        $properties = $this->getProperty();
        $properties['category'] = (array) $category;
        $this->getConfig()->setSection('bundle', $properties, true);

        return $this;
    }

    /**
     * config_per_site property setter.
     *
     * @param boolean $v
     *
     * @return self
     */
    public function setConfigPerSite($v)
    {
        $properties = $this->getProperty();
        $properties['config_per_site'] = (boolean) $v;
        $this->getConfig()->setSection('bundle', $properties, true);

        return $this;
    }

    /**
     * @see JsonSerializable::jsonSerialize
     */
    public function jsonSerialize()
    {
        $obj = new \stdClass();
        $obj->id = $this->getId();

        foreach ($this->getProperty() as $key => $value) {
            if ('bundle_loader_recipes' !== $key) {
                $obj->$key = $value;
            }
        }

        if (false === property_exists($obj, 'enable')) {
            $obj->enable = true;
        }

        if (false === property_exists($obj, 'config_per_site')) {
            $obj->config_per_site = false;
        }

        if (false === property_exists($obj, 'category')) {
            $obj->category = array();
        }

        if (false === property_exists($obj, 'thumbnail')) {
            $obj->thumbnail = $this->getApplication()->getContainer()->get('routing')
                ->getUri('img/extnd-x/picto-extnd.png', null, null, RouteCollection::RESOURCE_URL)
            ;
        }

        if (false !== property_exists($obj, 'admin_entry_point')) {
            $entrieDefinition = explode('.', $obj->admin_entry_point);
            $controller = $entrieDefinition[0];
            $action = isset($entrieDefinition[1]) ? $entrieDefinition[1] : 'index';
            $obj->admin_entry_point = (new BundleControllerResolver($this->application))->resolveBaseAdminUrl($obj->id, $controller, $action);
        }

        $obj->exposed_actions = $this->getExposedActionsMapping();

        return $obj;
    }

    /**
     * @codeCoverageIgnore
     *
     * @see Symfony\Component\Security\Acl\Model\DomainObjectInterface::getObjectIdentifier
     */
    public function getObjectIdentifier()
    {
        return $this->getType().'('.$this->getIdentifier().')';
    }

    /**
     * @codeCoverageIgnore
     *
     * @see BackBee\Security\Acl\Domain\IObjectIdentifiable::getIdentifier
     */
    public function getIdentifier()
    {
        return $this->getId();
    }

    /**
     * @codeCoverageIgnore
     *
     * @see BackBee\Security\Acl\Domain\IObjectIdentifiable::getType
     */
    public function getType()
    {
        return ClassUtils::getRealClass($this);
    }

    /**
     * @codeCoverageIgnore
     *
     * @see BackBee\Security\Acl\Domain\IObjectIdentifiable::equals
     */
    public function equals(ObjectIdentifiableInterface $identity)
    {
        return ($this->getType() === $identity->getType() && $this->getIdentifier() === $identity->getIdentifier());
    }

    /**
     * Bundle's exposed actions getter.
     *
     * @return array
     */
    public function getExposedActionsMapping()
    {
        return $this->exposedActions;
    }

    /**
     * Returns the associated callback if "controller name/action name" couple is valid.
     *
     * @param string $controllerName the controller name (ex.: BackBee\Controller\FrontController => front)
     * @param string $actionName     the action name (ex.: FrontController::defaultAction => default)
     *
     * @return callable|null the callback if there is one associated to "controller name/action name" couple, else null
     */
    public function getExposedActionCallback($controllerName, $actionName)
    {
        $uniqueName = $controllerName.'_'.$actionName;

        return array_key_exists($uniqueName, $this->exposedActionsCallbacks)
            ? $this->exposedActionsCallbacks[$uniqueName]
            : null
        ;
    }

    /**
     * Initialize bundle exposed actions by building exposed_actions array and exposed_actions_callback array.
     */
    private function initBundleExposedActions()
    {
        if (true === $this->isEnabled()) {
            $container = $this->getApplication()->getContainer();
            foreach ((array) $this->getProperty('exposed_actions') as $controllerId => $actions) {
                if (false === $container->has($controllerId)) {
                    throw new \InvalidArgumentException(
                        "Exposed controller with id `$controllerId` not found for ".$this->getId()
                    );
                }

                $controller = $container->get($controllerId);
                $this->formatAndInjectExposedAction($controller, $actions);
            }
        }
    }

    /**
     * Format a valid map between controller and actions and hydrate.
     *
     * @param BundleExposedControllerInterface $controller
     * @param array                            $actions
     */
    private function formatAndInjectExposedAction($controller, $actions)
    {
        $controllerId = explode('\\', get_class($controller));
        $controllerId = str_replace('controller', '', strtolower(array_pop(($controllerId))));
        $this->exposedActions[$controllerId] = array('actions' => array());

        if ($controller instanceof BundleExposedControllerInterface) {
            $this->exposedActions[$controllerId]['label'] = $controller->getLabel();
            $this->exposedActions[$controllerId]['description'] = $controller->getDescription();
            array_unshift($actions, 'indexAction');
            $actions = array_unique($actions);
        }

        foreach ($actions as $action) {
            if (method_exists($controller, $action)) {
                $actionId = str_replace('action', '', strtolower($action));
                $uniqueName = $controllerId.'_'.$actionId;

                $this->exposedActionsCallbacks[$uniqueName] = array($controller, $action);
                $this->exposedActions[$controllerId]['actions'][] = $actionId;
            }
        }
    }
}
