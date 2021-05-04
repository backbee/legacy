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

namespace BackBee\Cache\IdentifierAppender;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Util\ClassUtils;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\Renderer\RendererInterface;

/**
 * NodeAppender will looking for class content cache node parameter and define if it should append node uid
 * to cache identifier. The node parameter can take 3 differents values:
 *     - SELF_NODE (='self'): it will append the current page uid to the cache identifier
 *     - PARENT_NODE (='parent'): it will append the current parent page uid to the cache identifier
 *     - ROOT_NODE (='root'): it will append the current root page uid to the cache identifier.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class NodeAppender implements IdentifierAppenderInterface
{
    const SELF_NODE = 'self';
    const PARENT_NODE = 'parent';
    const ROOT_NODE = 'root';

    /**
     * Application main entity manager.
     *
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * list of group name this validator belong to.
     *
     * @var array
     */
    private $groups;

    /**
     * constructor.
     *
     * @param EntityManager $em    application main entity manager
     * @param array         $group list of groups this appender belongs to
     */
    public function __construct(EntityManager $em, $groups = array())
    {
        $this->em = $em;
        $this->groups = (array) $groups;
    }

    /**
     * @see BackBee\Cache\IdentifierAppender\IdentifierAppenderInterface::computeIdentifier
     */
    public function computeIdentifier($identifier, RendererInterface $renderer = null)
    {
        if (
            null !== $renderer
            && true === ($renderer->getObject() instanceof AbstractClassContent)
            && null !== $renderer->getCurrentPage()
        ) {
            switch ((string) $this->getClassContentCacheNodeParameter($renderer->getObject())) {
                case self::SELF_NODE:
                    $identifier .= '-'.$renderer->getCurrentPage()->getUid();

                    break;
                case self::PARENT_NODE:
                    if (null !== $renderer->getCurrentPage()->getParent()) {
                        $identifier .= '-'.$renderer->getCurrentPage()->getParent()->getUid();
                    }

                    break;
                case self::ROOT_NODE:
                    $identifier .= '-'.$renderer->getCurrentRoot()->getUid();

                default:
                    break;
            }
        }

        return $identifier;
    }

    /**
     * @see BackBee\Cache\IdentifierAppender\IdentifierAppenderInterface::getGroups
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * Try to extract cache node parameter from class content yaml files.
     *
     * @param AbstractClassContent $content the content which we want to extract cache node parameter
     *
     * @return null|string return the cache node parameter if it exists, else null
     */
    private function getClassContentCacheNodeParameter(AbstractClassContent $content)
    {
        $classnames = array(ClassUtils::getRealClass($content));

        $content_uids = $this->em->getRepository('\BackBee\ClassContent\Indexes\IdxContentContent')
            ->getDescendantsContentUids($content)
        ;

        if (0 < count($content_uids)) {
            $classnames = array_merge(
                $classnames,
                $this->em->getRepository('\BackBee\ClassContent\AbstractClassContent')->getClassnames($content_uids)
            );
        }

        $node_parameter = null;
        foreach ($classnames as $classname) {
            if (false === class_exists($classname)) {
                continue;
            }

            $object = new $classname();
            if (null !== $parameters = $object->getProperty('cache-param')) {
                if (true === isset($parameters['node'])) {
                    $node_parameter = $parameters['node'];
                    break;
                }
            }
        }

        return $node_parameter;
    }
}
