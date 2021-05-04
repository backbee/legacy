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

namespace BackBee\Rest\EventListener;

use BackBee\Security\Authorization\ExpressionLanguage;
use BackBee\Security\Token\AnonymousToken;
use Metadata\MetadataFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * SecurityListener handles security restrictions on controllers.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>, k.golovin
 */
class SecurityListener
{
    private $securityContext;
    private $language;
    private $trustResolver;
    private $roleHierarchy;
    private $metadataFactory;

    public function __construct(SecurityContextInterface $securityContext, ExpressionLanguage $language, AuthenticationTrustResolverInterface $trustResolver, RoleHierarchyInterface $roleHierarchy)
    {
        $this->securityContext = $securityContext;
        $this->language = $language;
        $this->trustResolver = $trustResolver;
        $this->roleHierarchy = $roleHierarchy;
    }

    public function setMetadataFactory(MetadataFactoryInterface $factory)
    {
        $this->metadataFactory = $factory;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        $controller = $event->getController();
        $metadata = $this->getControllerActionMetadata($controller);
        if (null === $metadata || 0 === count($metadata->security)) {
            return;
        }

        foreach ($metadata->security as $annotation) {
            if (!$this->language->evaluate($annotation->expression, $this->getVariables($request))) {
                throw new AccessDeniedHttpException(sprintf(
                    'Expression "%s" denied access.',
                    $annotation->expression
                ));
            }
        }
    }

    // code should be sync with Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter
    private function getVariables(Request $request)
    {
        $token = $this->securityContext->getToken();
        if (!$token) {
            $token = new AnonymousToken('anon.', 'anon.', []);
        }

        if (null !== $this->roleHierarchy) {
            $roles = $this->roleHierarchy->getReachableRoles($token->getRoles());
        } else {
            $roles = $token->getRoles();
        }

        $variables = [
            'token'            => $token,
            'user'             => $token->getUser(),
            'object'           => $request,
            'request'          => $request,
            'roles'            => array_map(function (Role $role) {
                return $role->getRole();
            }, $roles),
            'trust_resolver'   => $this->trustResolver,
            'security_context' => $this->securityContext,
        ];

        // controller variables should also be accessible
        return array_merge($request->attributes->all(), $variables);
    }

    /**
     * @param mixed $controller
     *
     * @return \BackBee\Rest\Mapping\ActionMetadata
     */
    protected function getControllerActionMetadata($controller)
    {
        $controllerClass = get_class($controller[0]);

        $metadata = $this->metadataFactory->getMetadataForClass($controllerClass);

        $controllerMetadata = $metadata->getOutsideClassMetadata();

        $action_metadatas = null;
        if (array_key_exists($controller[1], $controllerMetadata->methodMetadata)) {
            $action_metadatas = $controllerMetadata->methodMetadata[$controller[1]];
        }

        return $action_metadatas;
    }
}
