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

use Symfony\Component\Security\Core\Authentication\Provider\AnonymousAuthenticationProvider;
use BackBee\Security\Listeners\AnonymousAuthenticationListener;

/**
 * Description of AnonymousContext.
 *
 * @category    BackBee
 *
 *
 * @author      nicolas.dufreche <nicolas.dufreche@lp-digital.fr>
 */
class AnonymousContext extends AbstractContext implements ContextInterface
{
    /**
     * {@inheritdoc}
     */
    public function loadListeners($config)
    {
        $listeners = array();
        if (array_key_exists('anonymous', $config)) {
            $key = array_key_exists('key', (array) $config['anonymous']) ? $config['anonymous']['key'] : 'anom';
            $this->_context->addAuthProvider(new AnonymousAuthenticationProvider($key));
            $listeners[] = new AnonymousAuthenticationListener($this->_context, $key, $this->_context->getLogger());
        }

        return $listeners;
    }
}
