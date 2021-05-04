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

namespace BackBee\Security\Access;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;

/**
 * Class for all access decision managers that use decision voters.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class DecisionManager extends AccessDecisionManager
{
    /**
     * The current BackBee application.
     *
     * @var \BackBee\BBApplication
     */
    private $_application;

    /**
     * Allow to try BBToken in voters if access is not granted.
     *
     * @var Boolean
     */
    private $_tryBBTokenOnDenied;

    /**
     * Class constructor.
     *
     * @param VoterInterface[] $voters                             An array of VoterInterface instances
     * @param string           $strategy                           The vote strategy
     * @param Boolean          $allowIfAllAbstainDecisions         Whether to grant access if all voters abstained or not
     * @param Boolean          $allowIfEqualGrantedDeniedDecisions Whether to grant access if result are equals
     * @param Boolean          $tryBBTokenOnDenied                 Allow to try BBToken in voters if access is not granted
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $voters, $strategy = 'affirmative', $allowIfAllAbstainDecisions = false, $allowIfEqualGrantedDeniedDecisions = true, $tryBBTokenOnDenied = true)
    {
        parent::__construct($voters, $strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);

        $this->_tryBBTokenOnDenied = $tryBBTokenOnDenied;
    }

    /**
     * Sets the current application.
     *
     * @param \BackBee\BBApplication $application
     *
     * @return \BackBee\Security\Access\DecisionManager
     */
    public function setApplication(\BackBee\BBApplication $application)
    {
        $this->_application = $application;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $result = parent::decide($token, $attributes, $object);

        if (
            false === $result
            && true === $this->_tryBBTokenOnDenied
            && null !== $this->_application
            && null !== $this->_application->getBBUserToken()
        ) {
            $result = parent::decide($this->_application->getBBUserToken(), $attributes, $object);
        }

        return $result;
    }
}
