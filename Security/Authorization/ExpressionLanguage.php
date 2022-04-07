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

namespace BackBee\Security\Authorization;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage as BaseExpressionLanguage;

/**
 * Adds some function to the default Symfony Security ExpressionLanguage.
 *
 * @category    BackBee
 *
 * 
 * @author      e.chau <eric.chau@lp-digital.fr>, k.golovin, d.bensid <djoudi.bensid@lp-digital.fr>
 */
class ExpressionLanguage extends BaseExpressionLanguage
{
    protected function registerFunctions()
    {
        parent::registerFunctions();

        $this->register('is_anonymous', function () {
            return '$trust_resolver->isAnonymous($token)';
        }, function (array $variables) {
            return $variables['trust_resolver']->isAnonymous($variables['token']);
        });

        $this->register('is_authenticated', function () {
            return '$token && !$trust_resolver->isAnonymous($token)';
        }, function (array $variables) {
            return $variables['token'] && !$variables['trust_resolver']->isAnonymous($variables['token']);
        });

        $this->register('is_fully_authenticated', function () {
            return '$trust_resolver->isFullFledged($token)';
        }, function (array $variables) {
            return $variables['trust_resolver']->isFullFledged($variables['token']);
        });

        $this->register('is_remember_me', function () {
            return '$trust_resolver->isRememberMe($token)';
        }, function (array $variables) {
            return $variables['trust_resolver']->isRememberMe($variables['token']);
        });

        $this->register('has_role', function ($role) {
            return sprintf('in_array(%s, $roles)', $role);
        }, function (array $variables, $role) {
            return in_array($role, $variables['roles']);
        });

        $this->register('is_granted', function ($attributes, $object = 'null') {
            return sprintf('$security_context->isGranted(%s, %s)', $attributes, $object);
        }, function (array $variables, $attributes, $object = null) {
            return $variables['security_context']->isGranted($attributes, $object);
        });

        $this->register('is_sudoer', function () {
            $securityConf = $security_context->getApplication()->getConfig()->getSecurityConfig();
            return sprintf('array_key_exists(%s, %s)', $token->getUser()->getLogin(), $securityConf);
        }, function (array $variables) {
            $securityConf = $variables['security_context']->getApplication()->getConfig()->getSecurityConfig();
            return array_key_exists($variables['token']->getUser()->getLogin(), $securityConf['sudoers']);
        });
    }
}
