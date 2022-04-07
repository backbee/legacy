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

namespace BackBee\Security\Acl;

use InvalidArgumentException;
use Symfony\Component\Security\Acl\Domain\Acl;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\AclAlreadyExistsException;
use Symfony\Component\Security\Acl\Exception\AclNotFoundException;
use Symfony\Component\Security\Acl\Model\DomainObjectInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityInterface;
use Symfony\Component\Security\Acl\Permission\PermissionMapInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Util\ClassUtils;

use BackBee\NestedNode\Page;
use BackBee\Security\Acl\Domain\AbstractObjectIdentifiable;
use BackBee\Security\Acl\Domain\ObjectIdentifiableInterface;
use BackBee\Security\Acl\Permission\InvalidPermissionException;
use BackBee\Security\Acl\Permission\MaskBuilder;
use BackBee\Standard\Application;

/**
 * Class AclManager
 *
 * @package BackBee\Security\Acl
 */
class AclManager
{
    /**
     * @var SecurityContextInterface
     */
    protected $securityContext;

    /**
     * @var PermissionMapInterface
     */
    protected $permissionMap;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @param SecurityContextInterface $securityContext
     * @param PermissionMapInterface $permissionMap
     */
    public function __construct(SecurityContextInterface $securityContext, PermissionMapInterface $permissionMap)
    {
        $this->securityContext = $securityContext;
        $this->permissionMap = $permissionMap;
        $this->em = $this->securityContext->getApplication()->getEntityManager();
    }

    /**
     * Get ACL for the given domain object.
     *
     * @param  ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @return Acl
     */
    public function getAcl($objectIdentity)
    {
        $this->enforceObjectIdentity($objectIdentity);

        try {
            $acl = $this->securityContext->getACLProvider()->createAcl($objectIdentity);
        } catch (AclAlreadyExistsException $e) {
            $acl = $this->securityContext->getACLProvider()->findAcl($objectIdentity);
        }

        return $acl;
    }

    /**
     * Updates an existing object ACE.
     *
     * @param ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @param SecurityIdentityInterface|UserSecurityIdentity $sid
     * @param int  $mask
     * @param string|null $strategy
     */
    public function updateObjectAce($objectIdentity, $sid, $mask, $strategy = null)
    {
        $this->enforceObjectIdentity($objectIdentity);
        $this->enforceSecurityIdentity($sid);
        $mask = $this->resolveMask($mask, $objectIdentity);

        $acl = $this->getAcl($objectIdentity);

        $found = false;

        foreach ($acl->getObjectAces() as $index => $ace) {
            if ($ace->getSecurityIdentity()->equals($sid)) {
                $acl->updateObjectAce($index, $mask, $strategy);
                break;
            }
        }

        if (false === $found) {
            throw new InvalidArgumentException('ACE not found for the supplied combination of ObjectIdentity and SecurityIdentity');
        }

        $this->securityContext->getACLProvider()->updateAcl($acl);
    }

    /**
     * Updates an existing object ACE.
     *
     * @param ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @param SecurityIdentityInterface|UserSecurityIdentity     $sid
     * @param int $mask
     * @param string|null $strategy
     */
    public function updateClassAce($objectIdentity, $sid, $mask, $strategy = null)
    {
        $this->enforceObjectIdentity($objectIdentity);
        $this->enforceSecurityIdentity($sid);
        $mask = $this->resolveMask($mask, $objectIdentity);

        $acl = $this->getAcl($objectIdentity);

        $found = false;
        foreach ($acl->getClassAces() as $index => $ace) {
            if ($ace->getSecurityIdentity()->equals($sid)) {
                $acl->updateClassAce($index, $mask, $strategy);
                $found = true;
                break;
            }
        }

        if (false === $found) {
            throw new InvalidArgumentException('ACE not found for the supplied combination of ObjectIdentity and SecurityIdentity');
        }

        $this->securityContext->getACLProvider()->updateAcl($acl);
    }

    /**
     * Updates an existing Object ACE, Inserts if it doesnt exist.
     *
     * @param ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @param SecurityIdentityInterface|UserSecurityIdentity $sid
     * @param int $mask
     * @param string|null $strategy
     * @return $this
     */
    public function insertOrUpdateObjectAce($objectIdentity, $sid, $mask, $strategy = null)
    {
        $this->enforceObjectIdentity($objectIdentity);
        $this->enforceSecurityIdentity($sid);
        $mask = $this->resolveMask($mask, $objectIdentity);

        $acl = $this->getAcl($objectIdentity);

        $found = false;

        foreach ($acl->getObjectAces() as $index => $ace) {
            if ($ace->getSecurityIdentity()->equals($sid)) {
                $acl->updateObjectAce($index, $mask, $strategy);
                $found = true;
                break;
            }
        }

        if (false === $found) {
            $acl->insertObjectAce($sid, $mask, 0, true, $strategy);
        }

        $this->securityContext->getACLProvider()->updateAcl($acl);

        return $this;
    }

    /**
     * Updates an existing Class ACE, Inserts if it doesn't exist.
     *
     * @param ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @param SecurityIdentityInterface|UserSecurityIdentity $sid
     * @param int $mask
     * @param string|null $strategy
     * @return $this
     */
    public function insertOrUpdateClassAce($objectIdentity, $sid, $mask, $strategy = null)
    {
        $this->enforceObjectIdentity($objectIdentity);
        $this->enforceSecurityIdentity($sid);
        $mask = $this->resolveMask($mask, $objectIdentity);

        $acl = $this->getAcl($objectIdentity);

        $found = false;

        foreach ($acl->getClassAces() as $index => $ace) {
            if ($ace->getSecurityIdentity()->equals($sid)) {
                $acl->updateClassAce($index, $mask, $strategy);
                $found = true;
                break;
            }
        }

        if (false === $found) {
            $acl->insertClassAce($sid, $mask, 0, true, $strategy);
        }

        $this->securityContext->getACLProvider()->updateAcl($acl);

        return $this;
    }

    /**
     * Deletes a class-scope ACE.
     *
     * @param ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @param SecurityIdentityInterface|UserSecurityIdentity     $sid
     */
    public function deleteClassAce($objectIdentity, $sid)
    {
        $this->enforceObjectIdentity($objectIdentity);
        $this->enforceSecurityIdentity($sid);

        $acl = $this->getAcl($objectIdentity);

        $found = false;

        foreach ($acl->getClassAces() as $index => $ace) {
            if ($ace->getSecurityIdentity()->equals($sid)) {
                $acl->deleteClassAce($index);
                $found = true;
                break;
            }
        }

        if (false === $found) {
            throw new InvalidArgumentException('ACE not found for the supplied combination of ObjectIdentity and SecurityIdentity');
        }

        $this->securityContext->getACLProvider()->updateAcl($acl);
    }

    /**
     * Deletes an object-scope ACE.
     *
     * @param ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @param SecurityIdentityInterface|UserSecurityIdentity     $sid
     */
    public function deleteObjectAce($objectIdentity, $sid)
    {
        $this->enforceObjectIdentity($objectIdentity);
        $this->enforceSecurityIdentity($sid);

        $acl = $this->getAcl($objectIdentity);

        $found = false;

        foreach ($acl->getObjectAces() as $index => $ace) {
            if ($ace->getSecurityIdentity()->equals($sid)) {
                $acl->deleteObjectAce($index);
                $found = true;
                break;
            }
        }

        if (false === $found) {
            throw new InvalidArgumentException('ACE not found for the supplied combination of ObjectIdentity and SecurityIdentity');
        }

        $this->securityContext->getACLProvider()->updateAcl($acl);
    }

    /**
     * Get a class-scope ACE.
     *
     * @param ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @param SecurityIdentityInterface|UserSecurityIdentity     $sid
     */
    public function getClassAce($objectIdentity, $sid)
    {
        $this->enforceObjectIdentity($objectIdentity);
        $this->enforceSecurityIdentity($sid);

        $acl = $this->securityContext->getACLProvider()->findAcl($objectIdentity);

        foreach ($acl->getClassAces() as $index => $ace) {
            if ($ace->getSecurityIdentity()->equals($sid)) {
                return $ace;
            }
        }

        throw new InvalidArgumentException('ACE not found for the supplied combination of ObjectIdentity and SecurityIdentity');
    }

    /**
     * Get an object-scope ACE.
     *
     * @param ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @param SecurityIdentityInterface|UserSecurityIdentity     $sid
     */
    public function getObjectAce($objectIdentity, $sid)
    {
        $this->enforceObjectIdentity($objectIdentity);
        $this->enforceSecurityIdentity($sid);

        $acl = $this->securityContext->getACLProvider()->findAcl($objectIdentity);

        foreach ($acl->getObjectAces() as $index => $ace) {
            if ($ace->getSecurityIdentity()->equals($sid)) {
                return $ace;
            }
        }
        throw new InvalidArgumentException('ACE not found for the supplied combination of ObjectIdentity and SecurityIdentity');
    }

    /**
     * Calculate mask for a list of permissions.
     *
     * ['view', 'edit'] => (int) 5
     *
     * @param array $permissions
     *
     * @return int
     */
    public function getMask(array $permissions)
    {
        $maskBuilder = new MaskBuilder();

        foreach ($permissions as $permission) {
            try {
                $maskBuilder->add($permission);
            } catch (InvalidArgumentException $e) {
                throw new InvalidPermissionException('Invalid permission mask: '.$permission, $permission, $e);
            }
        }

        return $maskBuilder->get();
    }

    /**
     * Get a list of all available permission codes.
     *
     * @return array
     */
    public function getPermissionCodes()
    {
        $permissions = [
            'view' => MaskBuilder::MASK_VIEW,
            'create' => MaskBuilder::MASK_CREATE,
            'edit' => MaskBuilder::MASK_EDIT,
            'delete' => MaskBuilder::MASK_DELETE,
            'undelete' => MaskBuilder::MASK_UNDELETE,
            'operator' => MaskBuilder::MASK_OPERATOR,
            'master' => MaskBuilder::MASK_MASTER,
            'owner' => MaskBuilder::MASK_OWNER,
            'iddqd' => MaskBuilder::MASK_IDDQD,
            'commit' => MaskBuilder::MASK_COMMIT,
            'publish' => MaskBuilder::MASK_PUBLISH,
            'none' => MaskBuilder::CODE_NONE
        ];

        return $permissions;
    }

    /**
     * @param  ObjectIdentityInterface|AbstractObjectIdentifiable $objectIdentity
     * @throws InvalidArgumentException
     */
    private function enforceObjectIdentity(&$objectIdentity)
    {
        if (
            ($objectIdentity instanceof ObjectIdentifiableInterface)
        ) {
            $objectIdentity = new ObjectIdentity($objectIdentity->getObjectIdentifier(), get_class($objectIdentity));
        } elseif (! ($objectIdentity instanceof ObjectIdentityInterface)) {
            throw new InvalidArgumentException('Object must implement ObjectIdentifiableInterface');
        }
    }

    /**
     * @param SecurityIdentityInterface|UserSecurityIdentity $sid
     *
     * @throws InvalidArgumentException
     */
    private function enforceSecurityIdentity(&$sid)
    {
        if (
            ($sid instanceof DomainObjectInterface)
        ) {
            $sid = new UserSecurityIdentity($sid->getObjectIdentifier(), get_class($sid));
        } elseif (! ($sid instanceof SecurityIdentityInterface)) {
            throw new InvalidArgumentException('Object must implement ObjectIdentifiableInterface');
        }
    }

    /**
     * Resolves any variation of masks/permissions to an integer.
     *
     * @param string|int|array $masks
     *
     * @param $object
     * @return int
     */
    private function resolveMask($masks, $object)
    {
        $integerMask = 0;

        if (is_integer($masks)) {
            $integerMask = $masks;
        } elseif (is_string($masks)) {
            $permission = $this->permissionMap->getMasks($masks, $object);
            $integerMask = $this->resolveMask($permission, $object);
        } elseif (is_array($masks)) {
            foreach ($masks as $mask) {
                $integerMask += $this->resolveMask($mask, $object);
            }
        } else {
            throw new \RuntimeException('Not a valid mask type');
        }

        return $integerMask;
    }

    /**
     * Get permissions
     *
     * @param $objectIdentity
     * @param $sid
     * @return array
     */
    public function getPermissions($objectIdentity, $sid)
    {
        $objectIdentity = $this->getClassScopeObjectIdentity($objectIdentity);
        $exceptedClass = [
            'BackBee\NestedNode\MediaFolder'
        ];

        try{

            $ace = $this->getObjectAce($objectIdentity, $sid);
        }
        catch (\Exception $e){

            if(in_array($objectIdentity->getType(), $exceptedClass)) return [];

            try {

                $ace = $this->getClassAce($this->getClassScopeObjectIdentity($objectIdentity->getType()), $sid);
            }
            catch (\Exception $e){

                $parentClass = get_parent_class($objectIdentity->getType());

                if (false !== $parentClass) {

                    return $this->getPermissions($parentClass, $sid);
                }
                else{
                    return [];
                }
            }
        }

        return $this->getAccessGranted($ace);
    }

    /**
     * Get permissions by page
     *
     * @param $page Page
     * @param $sid
     * @return array
     */
    public function getPermissionsByPage($page, $sid)
    {
        $objectIdentity = $this->getClassScopeObjectIdentity($page);

        try{

            $ace = $this->getObjectAce($objectIdentity, $sid);
        }
        catch (\Exception $e){

            if (null !== $page->getParent()) {

                return $this->getPermissionsByPage($page->getParent(), $sid);
            }
            elseif ($page->isRoot()){

                try{
                    $ace = $this->getClassAce($this->getClassScopeObjectIdentity(ClassUtils::getRealClass($page)), $sid);
                }
                catch (\Exception $e){
                    return [];
                }
            }
            else{
                return [];
            }
        }

        return $this->getAccessGranted($ace);
    }

    /**
     * Returns the class-scope object identity for $object.
     *
     * @param $object
     * @return ObjectIdentity
     */
    public function getClassScopeObjectIdentity($object)
    {
        $className = ClassUtils::getRealClass($object);
        $identifier = 'all';

        if($object instanceof ObjectIdentifiableInterface) {
            $identifier = $object->getObjectIdentifier();
            $className = $object->getType();
        }

        return new ObjectIdentity($identifier, $className);
    }

    /**
     * Determines whether access is granted.
     *
     * @param $ace
     * @return array
     */
    private function getAccessGranted($ace)
    {
        $access = [
            'total' => $ace->getMask()
        ];

        foreach ($this->getPermissionCodes() as $permission => $code){
            $access[$permission] = (0 !== ($ace->getMask() & $code)) ? 1 : 0;
        }

        $access['none'] = (0 === $ace->getMask()) ? 1 : 0;
        $access['view'] = (1 === $access['edit'] && 0 === $access['view']) ? 1 : $access['view'];

        return $access;
    }
}
