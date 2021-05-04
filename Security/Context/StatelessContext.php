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

use BackBee\Security\Listeners\ContextListener;

/**
 * Description of AnonymousContext.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      nicolas.dufreche <nicolas.dufreche@lp-digital.fr>
 */
class StatelessContext extends AbstractContext implements ContextInterface
{
    /**
     * {@inheritdoc}
     */
    public function loadListeners($config)
    {
        $listeners = array();
        if (!array_key_exists('stateless', $config) || false === $config['stateless']) {
            $contextKey = array_key_exists('context', $config) ? $config['context'] : $config['firewall_name'];
            $listeners[] = new ContextListener($this->_context, $this->_context->getUserProviders(), $contextKey, $this->_context->getLogger(), $this->_context->getDispatcher());
        }

        return $listeners;
    }
}
