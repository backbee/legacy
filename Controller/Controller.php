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

namespace BackBee\Controller;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validation;
use BackBee\ApplicationInterface;

/**
 * Base Controler.
 *
 * @category    BackBee
 *
 * @copyright   Lp system
 * @author      k.golovin
 */
class Controller implements ContainerAwareInterface
{
    /**
     * Current application.
     *
     * @var \BackBee\ApplicationInterface
     */
    protected $application;

    /**
     * Current application's DIC.
     *
     * @var BackBee\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * Class constructor.
     *
     * @access public
     *
     * @param ApplicationInterface $application The current BBapplication
     */
    public function __construct(ApplicationInterface $application = null)
    {
        if (null !== $application) {
            $this->application = $application;
            $this->container = $application->getContainer();
        }
    }

    /**
     * Returns current application.
     *
     * @access public
     *
     * @return \BackBee\ApplicationInterface
     */
    public function getApplication()
    {
        return $this->container->get('bbapp');
    }

    /**
     * Application's dependency injection container setters.
     *
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->application = null !== $container ? $this->container->get('bbapp') : null;
    }

    /**
     * Returns the application's DIC.
     *
     * @access public
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Returns the current request.
     *
     * @access public
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->application->getRequest();
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->application->getEntityManager();
    }

    /**
     * @return FormBuilderInterface
     */
    public function createFormBuilder($data)
    {
        if (!class_exists('Symfony\\Component\\Form\\Forms')) {
            throw new \RuntimeException('Unable to use ``createFormBuilder`` function as the Symfony Form Component is not installed.');
        }

        $validator = Validation::createValidator();

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->addExtension(new HttpFoundationExtension())
            ->getFormFactory()
        ;

        return $formFactory->createBuilder('form', $data);
    }

    /**
     * @param string                                     $view
     * @param array                                      $parameters
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($view, array $parameters = array(), Response $response = null)
    {
        if (null === $response) {
            $response = new Response();
        }

        // locate the full path to the view
        $matches = null;
        preg_match("/Bundle\\\([a-zA-Z0-9]+Bundle)\\\/i", get_class($this), $matches);
        if (isset($matches[1])) {
            $bundleName = $matches[1];

            // check that the view is not the full path already
            $matchesView = null;
            preg_match("/Bundle\\\\([a-zA-Z0-9]+Bundle)\\\\/i", $view, $matchesView);

            if (!isset($matchesView[1])) {
                // view is not the full path, so prepend the bundle views dir
                $bundle = $this->getApplication()->getBundle($bundleName);
                $bundle->getBaseDir();
                $view = $bundle->getBaseDir().'/Ressources/views/'.$view;
            }
        } else {
            $view = $this->getApplication()->getBBDir().'/Resources/views/'.$view;
        }

        try {
            $this->renderer = $this->getApplication()->getRenderer();
            $content = $this->renderer->partial($view, $parameters);
            $response->setContent($content);
        } catch (\Exception $e) {
            throw $e;
        }

        return $response;
    }

    /**
     * Returns the validator service.
     *
     * @access public
     *
     * @return \Symfony\Component\Validator\ValidatorInterface
     */
    public function getValidator()
    {
        return $this->application->getValidator();
    }

    /**
     * Shortcut for Symfony\Component\Security\Core\SecurityContext::isGranted().
     *
     * @see \Symfony\Component\Security\Core\SecurityContext::isGranted()
     *
     * @param string $permission
     * @param mixed  $object
     *
     * @return bool
     */
    protected function isGranted($attributes, $object = null)
    {
        return $this->getContainer()->get('security.context')->isGranted($attributes, $object);
    }

    /**
     * Get a user from the Security Context.
     *
     * @return mixed
     *
     * @see Symfony\Component\Security\Core\Authentication\Token\TokenInterface::getUser()
     */
    public function getUser()
    {
        if (!$this->getContainer()->has('security.context')) {
            throw new \LogicException('Security context is not defined in your application.');
        }

        if (null === $token = $this->getContainer()->get('security.context')->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            return;
        }

        return $user;
    }
}
