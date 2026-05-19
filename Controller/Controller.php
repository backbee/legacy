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

namespace BackBee\Controller;

use BackBee\BBApplication;
use BackBee\Renderer\RendererInterface;
use BackBee\Routing\RouteCollection;
use BackBee\Security\User;
use Doctrine\ORM\EntityManager;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
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
use Symfony\Component\Validator\ValidatorInterface;
use function get_class;
use function is_object;

/**
 * Base Controller.
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
    protected ApplicationInterface $application;

    /**
     * Current application's DIC.
     *
     * @var \BackBee\DependencyInjection\ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var \BackBee\Renderer\RendererInterface
     */
    protected RendererInterface $renderer;

    /**
     * @var \BackBee\Routing\RouteCollection
     */
    protected RouteCollection $routing;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Class constructor.
     *
     * @access public
     *
     * @param null|\BackBee\ApplicationInterface $application The current BBApplication
     */
    public function __construct(ApplicationInterface $application = null)
    {
        if ($application !== null) {
            $this->application = $application;
            $this->container = $application->getContainer();
            $this->renderer = $application->getRenderer();
            $this->routing = $application->getRouting();
            $this->logger = $application->getLogging();
        }
    }

    /**
     * Returns current application.
     *
     * @access public
     *
     * @return \BackBee\ApplicationInterface|object
     */
    public function getApplication(): BBApplication
    {
        return $this->container->get('bbapp');
    }

    /**
     * Application's dependency injection container setters.
     *
     * @param ContainerInterface|object|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
        $this->application = $container !== null ? $this->container->get('bbapp') : null;
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
    public function getRequest(): Request
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
     * Create form builder.
     *
     * @param $data
     *
     * @return FormBuilderInterface
     */
    public function createFormBuilder($data): FormBuilderInterface
    {
        if (!class_exists(Forms::class)) {
            throw new RuntimeException(
                'Unable to use ``createFormBuilder`` function as the Symfony Form Component is not installed.'
            );
        }

        $validator = Validation::createValidator();

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->addExtension(new HttpFoundationExtension())
            ->getFormFactory();

        return $formFactory->createBuilder('form', $data);
    }

    /**
     * Render.
     *
     * @param string                                          $view
     * @param array                                           $parameters
     * @param null|\Symfony\Component\HttpFoundation\Response $response
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function render(string $view, array $parameters = array(), Response $response = null): ?Response
    {
        if ($response === null) {
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

                if ($bundle) {
                    $bundle->getBaseDir();
                    $view = $bundle->getBaseDir() . '/Ressources/views/' . $view;
                }
            }
        } else {
            $view = $this->getApplication()->getBBDir() . '/Resources/views/' . $view;
        }

        $content = $this->renderer->partial($view, $parameters);
        $response->setContent($content);

        return $response;
    }

    /**
     * Returns the validator service.
     *
     * @access public
     *
     * @return \Symfony\Component\Validator\ValidatorInterface
     */
    public function getValidator(): ValidatorInterface
    {
        return $this->application->getValidator();
    }

    /**
     * Shortcut for Symfony\Component\Security\Core\SecurityContext::isGranted().
     *
     * @param       $attributes
     * @param mixed $object
     *
     * @return bool
     * @see \Symfony\Component\Security\Core\SecurityContext::isGranted()
     *
     */
    protected function isGranted($attributes, $object = null): bool
    {
        return $this->getContainer()->get('security.context')->isGranted($attributes, $object);
    }

    /**
     * Get a user from the Security Context.
     *
     * @return mixed
     *
     * @see \Symfony\Component\Security\Core\Authentication\Token\TokenInterface::getUser()
     */
    public function getUser(): ?User
    {
        if (!$this->getContainer()->has('security.context')) {
            throw new LogicException('Security context is not defined in your application.');
        }

        if (($token = $this->getContainer()->get('security.context')->getToken()) === null ||
            !is_object($user = $token->getUser())
        ) {
            return null;
        }

        return $user;
    }
}
