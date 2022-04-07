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

namespace BackBee\Security\Context;

use BackBee\Security\SecurityContext;

/**
 * Description of AbstractContext.
 *
 * @category    BackBee
 *
 * 
 * @author      nicolas.dufreche <nicolas.dufreche@lp-digital.fr>
 */
abstract class AbstractContext
{
    /**
     * @var SecurityContext
     */
    protected $_context;

    public function __construct(SecurityContext $context)
    {
        $this->_context = $context;
    }

    public function getDefaultProvider($config)
    {
        $user_provider = $this->_context->getUserProviders();
        $default_provider = reset($user_provider);
        if (array_key_exists('provider', $config) && array_key_exists($config['provider'], $this->_context->getUserProviders())) {
            $user_provider = $this->_context->getUserProviders();
            $default_provider = $user_provider[$config['provider']];
        }

        return $default_provider;
    }
}
