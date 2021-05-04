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

namespace BackBee\Security\Context;

use BackBee\Security\Authentication\Provider\UserAuthenticationProvider;
use BackBee\Security\Listeners\UsernamePasswordAuthenticationListener;

/**
 * Description of AnonymousContext.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      nicolas.dufreche <nicolas.dufreche@lp-digital.fr>
 */
class UsernamePasswordContext extends AbstractContext implements ContextInterface
{
    /**
     * {@inheritdoc}
     */
    public function loadListeners($config)
    {
        $listeners = array();
        if (array_key_exists('form_login', $config)) {
            if (false !== ($default_provider = $this->getDefaultProvider($config))) {
                $login_path = array_key_exists('login_path', $config['form_login']) ? $config['form_login']['login_path'] : null;
                $check_path = array_key_exists('check_path', $config['form_login']) ? $config['form_login']['check_path'] : null;
                $this->_context->getAuthenticationManager()->addProvider(new UserAuthenticationProvider($default_provider, $this->_context->getEncoderFactory()));
                $listeners[] = new UsernamePasswordAuthenticationListener($this->_context, $this->_context->getAuthenticationManager(), $login_path, $check_path, $this->_context->getLogger());

                if (array_key_exists('rememberme', $config) && class_exists($config['rememberme'])) {
                    $classname = $config['rememberme'];
                    $listeners[] = new $classname($this->_context, $this->_context->getAuthenticationManager(), $this->_context->getLogger());
                }
            }
        }

        return $listeners;
    }
}
