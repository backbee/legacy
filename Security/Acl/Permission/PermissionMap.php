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

namespace BackBee\Security\Acl\Permission;

use Symfony\Component\Security\Acl\Permission\BasicPermissionMap;

/**
 * This is basic permission map complements the masks which have been defined
 * on the standard implementation of the MaskBuilder.
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class PermissionMap extends BasicPermissionMap
{
    const PERMISSION_COMMIT = 'COMMIT';
    const PERMISSION_PUBLISH = 'PUBLISH';

    public function __construct()
    {
        $this->map = array(
            self::PERMISSION_VIEW => array(
                MaskBuilder::MASK_VIEW,
                MaskBuilder::MASK_EDIT,
                MaskBuilder::MASK_OPERATOR,
                MaskBuilder::MASK_MASTER,
                MaskBuilder::MASK_OWNER,
            ),
            self::PERMISSION_EDIT => array(
                MaskBuilder::MASK_EDIT,
                MaskBuilder::MASK_OPERATOR,
                MaskBuilder::MASK_MASTER,
                MaskBuilder::MASK_OWNER,
            ),
            self::PERMISSION_CREATE => array(
                MaskBuilder::MASK_CREATE,
                MaskBuilder::MASK_OPERATOR,
                MaskBuilder::MASK_MASTER,
                MaskBuilder::MASK_OWNER,
            ),
            self::PERMISSION_DELETE => array(
                MaskBuilder::MASK_DELETE,
                MaskBuilder::MASK_OPERATOR,
                MaskBuilder::MASK_MASTER,
                MaskBuilder::MASK_OWNER,
            ),
            self::PERMISSION_UNDELETE => array(
                MaskBuilder::MASK_UNDELETE,
                MaskBuilder::MASK_OPERATOR,
                MaskBuilder::MASK_MASTER,
                MaskBuilder::MASK_OWNER,
            ),
            self::PERMISSION_COMMIT => array(
                MaskBuilder::MASK_COMMIT,
                MaskBuilder::MASK_OPERATOR,
                MaskBuilder::MASK_MASTER,
                MaskBuilder::MASK_OWNER,
            ),
            self::PERMISSION_PUBLISH => array(
                MaskBuilder::MASK_PUBLISH,
                MaskBuilder::MASK_MASTER,
            ),
            self::PERMISSION_OPERATOR => array(
                MaskBuilder::MASK_OPERATOR,
                MaskBuilder::MASK_MASTER,
                MaskBuilder::MASK_OWNER,
            ),
            self::PERMISSION_MASTER => array(
                MaskBuilder::MASK_MASTER,
                MaskBuilder::MASK_OWNER,
            ),
            self::PERMISSION_OWNER => array(
                MaskBuilder::MASK_OWNER,
            ),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getMasks($permission, $object)
    {
        if (!isset($this->map[$permission])) {
            return;
        }

        return $this->map[$permission];
    }

    /**
     * {@inheritDoc}
     */
    public function contains($permission)
    {
        return isset($this->map[$permission]);
    }
}
