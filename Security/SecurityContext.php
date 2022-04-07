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

namespace BackBee\Security;

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\SecurityContext as sfSecurityContext;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\FirewallMap;
use BackBee\BBApplication;
use BackBee\Routing\Matcher\RequestMatcher;
use BackBee\Security\Authentication\AuthenticationManager;
use BackBee\Security\Context\ContextInterface;
use BackBee\Security\Exception\SecurityException;
use BackBee\Security\Listeners\LogoutListener;

/**
 * @category    BackBee
 *
 *
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class SecurityContext extends sfSecurityContext
{
    private $application;
    private $logger;
    private $dispatcher;
    private $firewall;
    private $firewallmap;
    private $authmanager;
    private $authproviders;
    private $userproviders;
    private $aclprovider;
    private $logout_listener;
    private $config;
    private $logout_listener_added = false;
    private $contexts = array();

    /**
     * An encoder factory.
     *
     * @var \Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface
     */
    private $_encoderfactory;

    public function __construct(BBApplication $application, AuthenticationManagerInterface $authenticationManager, AccessDecisionManagerInterface $accessDecisionManager)
    {
        $this->application = $application;
        $this->logger = $this->application->getLogging();
        $this->dispatcher = $this->application->getEventDispatcher();

        if (null === $securityConfig = $this->application->getConfig()->getSecurityConfig()) {
            trigger_error('None security configuration found', E_USER_NOTICE);

            return;
        }
        $this->config = $securityConfig;

        $this->authmanager = $authenticationManager;

        $this->createEncoderFactory($securityConfig);
        $this->createProviders($securityConfig);
        $this->createACLProvider($securityConfig);
        $this->createFirewallMap($securityConfig);
        $this->registerFirewall();

        parent::__construct($this->authmanager, $accessDecisionManager);
    }

    /**
     * Returns the encoder factory or null if not defined.
     *
     * @return \Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface|null
     */
    public function getEncoderFactory()
    {
        return $this->_encoderfactory;
    }

    public function createFirewall($name, $config)
    {
        $config['firewall_name'] = $name;
        $listeners = array();

        if (null === $this->firewallmap) {
            return $this;
        }

        $requestMatcher = new RequestMatcher();

        if (array_key_exists('pattern', $config)) {
            $requestMatcher->matchPath($config['pattern']);
        }

        if (array_key_exists('requirements', $config)) {
            foreach ($config['requirements'] as $key => $value) {
                if (0 === strpos($key, 'HTTP-')) {
                    $requestMatcher->matchHeader(substr($key, 5), $value);
                }
            }
        }

        if (array_key_exists('security', $config) && false === $config['security']) {
            $this->firewallmap->add($requestMatcher, array(), null);

            return $this;
        }

        $defaultProvider = reset($this->userproviders);
        if (array_key_exists('provider', $config) && array_key_exists($config['provider'], $this->userproviders)) {
            $defaultProvider = $this->userproviders[$config['provider']];
        }

        if (array_key_exists('contexts', $this->config)) {
            $listeners = $this->loadContexts($config);
        }

        if (null !== $this->logout_listener && false === $this->logout_listener_added) {
            $this->application->getContainer()->set('security.logout_listener', $this->logout_listener);
            if (false === $this->dispatcher->isRestored()) {
                $this->dispatcher->addListener(
                    'frontcontroller.request.logout',
                    array('@security.logout_listener', 'handle')
                );
            }

            $this->logout_listener_added = true;
        }

        if (0 == count($listeners)) {
            throw new SecurityException(sprintf('No authentication listener registered for firewall "%s".', $name));
        }

        $this->firewallmap->add($requestMatcher, $listeners, null);

        return $this;
    }

    public function loadContexts($config)
    {
        $listeners = array();
        foreach ($this->config['contexts'] as $namespace => $classnames) {
            foreach ($classnames as $classname) {
                $class = implode(NAMESPACE_SEPARATOR, array($namespace, $classname));
                $context = null;
                if (false === array_key_exists($class, $this->contexts)) {
                    $context = new $class($this);
                    if ($context instanceof ContextInterface) {
                        $this->contexts[$class] = $context;
                    }
                }

                $context = true === isset($this->contexts[$class]) ? $this->contexts[$class] : null;
                if (null !== $context) {
                    $listeners = array_merge($listeners, $context->loadListeners($config));
                }
            }
        }

        return $listeners;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return \Symfony\Component\Security\Acl\Dbal\MutableAclProvider
     */
    public function getACLProvider()
    {
        return $this->aclprovider;
    }

    public function createProviders($config)
    {
        $this->userproviders = array();

        if (false === array_key_exists('providers', $config)) {
            return $this;
        }

        $providers = (array) $config['providers'];
        foreach ($providers as $name => $provider) {
            $key = array_key_exists('secret', $provider) ? $provider['secret'] : 'bb4_secret_key';

            if (array_key_exists('entity', $provider)) {
                $manager = $this->application->getEntityManager();
                if (null !== $manager) {
                    if (array_key_exists('manager_name', $provider['entity'])) {
                        $manager = $provider['entity']['manager_name']->getEntityManager();
                    }

                    if (isset($provider['entity']['class']) && isset($provider['entity']['provider'])) {
                        $providerClass = $provider['entity']['provider'];
                        $this->userproviders[$name] = new $providerClass(
                            $manager->getRepository($provider['entity']['class'])
                        );
                    } elseif (array_key_exists('class', $provider['entity'])) {
                        $this->userproviders[$name] = $manager->getRepository($provider['entity']['class']);
                    }
                }
            }

            if (array_key_exists('webservice', $provider)) {
                if (array_key_exists('class', $provider['webservice'])) {
                    $userprovider = $provider['webservice']['class'];
                    $this->userproviders[$name] = new $userprovider($this->getApplication());
                }
            }
        }

        return $this;
    }

    public function addAuthProvider(AuthenticationProviderInterface $provider, $key = null)
    {
        if (is_null($key)) {
            $this->authproviders[] = $provider;
        } else {
            $this->authproviders[$key] = $provider;
        }
    }

    /**
     * Get auth provider.
     *
     * @param string $key
     *
     * @return AuthenticationProviderInterface
     *
     * @throws InvalidArgumentException if provider not found
     */
    public function getAuthProvider($key)
    {
        if (array_key_exists($key, $this->authproviders)) {
            return $this->authproviders[$key];
        }

        throw \InvalidArgumentException(sprintf("Auth provider doesn't exists", $key));
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string                                                      $name
     * @param \Symfony\Component\Security\Core\User\UserProviderInterface $provider
     */
    public function addUserProvider($name, UserProviderInterface $provider)
    {
        $this->userproviders[$name] = $provider;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param type $requestMatcher
     * @param type $listeners
     */
    public function addFirewall($requestMatcher, $listeners)
    {
        $this->firewallmap->add($requestMatcher, $listeners, null);
    }

    /**
     * @codeCoverageIgnore
     *
     * @return BBApplication
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return type
     */
    public function getUserProviders()
    {
        return $this->userproviders;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return AuthenticationManager
     */
    public function getAuthenticationManager()
    {
        return $this->authmanager;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getLogoutListener()
    {
        return $this->logout_listener;
    }

    public function setLogoutListener(LogoutListener $listener)
    {
        if (null === $this->logout_listener) {
            $this->logout_listener = $listener;
        }
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    private function createFirewallMap($config)
    {
        $this->firewallmap = new FirewallMap();

        if (false === array_key_exists('firewalls', $config)) {
            return $this;
        }

        $firewalls = (array) $config['firewalls'];
        foreach ($firewalls as $name => $firewall) {
            $this->createFirewall($name, $firewall);
        }

        return $this;
    }

    private function createACLProvider($config)
    {
        if (true === array_key_exists('acl', $config) && null !== $this->getApplication()->getEntityManager()) {
            if (true === isset($config['acl']['connection']) && 'default' === $config['acl']['connection']) {
                if (false === $this->application->getContainer()->has('security.acl_provider')) {
                    $this->aclprovider = new \Symfony\Component\Security\Acl\Dbal\MutableAclProvider(
                        $this->getApplication()->getEntityManager()->getConnection(),
                        new \Symfony\Component\Security\Acl\Domain\PermissionGrantingStrategy(),
                        array(
                            'class_table_name'         => 'acl_classes',
                            'entry_table_name'         => 'acl_entries',
                            'oid_table_name'           => 'acl_object_identities',
                            'oid_ancestors_table_name' => 'acl_object_identity_ancestors',
                            'sid_table_name'           => 'acl_security_identities',
                    ));
                } else {
                    $this->aclprovider = $this->application->getContainer()->get('security.acl_provider');
                }
            }
        }

        return $this;
    }

    /**
     * Create an encoders factory if need.
     *
     * @param array $config
     *
     * @return \BackBee\Security\SecurityContext
     */
    private function createEncoderFactory(array $config)
    {
        if (true === array_key_exists('encoders', $config)) {
            $this->_encoderfactory = new EncoderFactory($config['encoders']);
        }

        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    private function registerFirewall()
    {
        $this->firewall = new Firewall($this->firewallmap, $this->dispatcher);
        $this->application->getContainer()->set('security.firewall', $this->firewall);
        if (false === $this->dispatcher->isRestored()) {
            $this->dispatcher->addListener(
                'frontcontroller.request',
                array('@security.firewall', 'onKernelRequest')
            );
        }
    }
}
