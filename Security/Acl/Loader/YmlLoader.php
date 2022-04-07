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

namespace BackBee\Security\Acl\Loader;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Yaml\Yaml;

use BackBee\Security\Acl\Permission\MaskBuilder;
use BackBee\Security\Acl\Permission\PermissionMap;

/**
 * Yml Loader.
 *
 * Loads yml acl data into the DB
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class YmlLoader extends ContainerAware
{
    protected $em;
    protected $bbapp;

    public function load($aclData)
    {
        $aclProvider = $this->container->get('bbapp')->getSecurityContext()->getACLProvider();

        if (null === $aclProvider) {
            throw new \RuntimeException('ACL configuration missing');
        }

        $this->em = $this->container->get('bbapp')->getEntityManager();
        $this->bbapp = $this->container->get('bbapp');

        $grid = Yaml::parse($aclData, true);

        if (false === array_key_exists('groups', $grid) || false === is_array($grid['groups'])) {
            throw new \Exception('Invalid yml: '.$ymlFile);
        }

        foreach ($grid['groups'] as $group_name => $rights) {
            if (null === $group = $this->em->getRepository('BackBee\Security\Group')->findOneBy(array('_name' => $group_name))) {
                // ensure group exists
                $group = new \BackBee\Security\Group();
                $group->setName($group_name);
                $this->em->persist($group);
                $this->em->flush($group);
            }

            $securityIdentity = new UserSecurityIdentity($group->getObjectIdentifier(), get_class($group));

            if (true === array_key_exists('sites', $rights)) {
                $sites = $this->addSiteRights($rights['sites'], $aclProvider, $securityIdentity);
                foreach ($sites as $site) {
                    if (true === array_key_exists('layouts', $rights)) {
                        $this->addLayoutRights($rights['layouts'], $sites, $aclProvider, $securityIdentity);
                    }
                }

                if (true === array_key_exists('pages', $rights)) {
                    $this->addPageRights($rights['pages'], $aclProvider, $securityIdentity);
                }

                if (true === array_key_exists('mediafolders', $rights)) {
                    $this->addFolderRights($rights['mediafolders'], $aclProvider, $securityIdentity);
                }

                if (true === array_key_exists('contents', $rights)) {
                    $this->addContentRights($rights['contents'], $aclProvider, $securityIdentity);
                }

                if (true === array_key_exists('bundles', $rights)) {
                    $this->addBundleRights($rights['bundles'], $aclProvider, $securityIdentity);
                }
            }
        }
    }

    private function addSiteRights($sites_def, $aclProvider, $securityIdentity)
    {
        if (false === array_key_exists('resources', $sites_def) || false === array_key_exists('actions', $sites_def)) {
            return array();
        }

        $actions = $this->getActions($sites_def['actions']);
        if (0 === count($actions)) {
            return array();
        }

        $sites = array();
        if (true === is_array($sites_def['resources'])) {
            foreach ($sites_def['resources'] as $site_label) {
                if (null === $site = $this->em->getRepository('BackBee\Site\Site')->findOneBy(array('_label' => $site_label))) {
                    continue;
                }

                $sites[] = $site;
                $this->addObjectAcl($site, $aclProvider, $securityIdentity, $actions);
            }
        } elseif ('all' === $sites_def['resources']) {
            $sites = $this->em->getRepository('BackBee\Site\Site')->findAll();
            $this->addClassAcl(new \BackBee\Site\Site('*'), $aclProvider, $securityIdentity, $actions);
        }

        return $sites;
    }

    private function addLayoutRights($layout_def, $sites, $aclProvider, $securityIdentity)
    {
        if (false === array_key_exists('resources', $layout_def) || false === array_key_exists('actions', $layout_def)) {
            return;
        }

        $actions = $this->getActions($layout_def['actions']);
        if (0 === count($actions)) {
            return array();
        }

        foreach ($sites as $site) {
            if (true === is_array($layout_def['resources'])) {
                foreach ($layout_def['resources'] as $layout_label) {
                    if (null === $layout = $this->em->getRepository('BackBee\Site\Layout')->findOneBy(array('_site' => $site, '_label' => $layout_label))) {
                        continue;
                    }

                    $this->addObjectAcl($layout, $aclProvider, $securityIdentity, $actions);
                }
            } elseif ('all' === $layout_def['resources']) {
                $this->addClassAcl(new \BackBee\Site\Layout('*'), $aclProvider, $securityIdentity, $actions);
            }
        }
    }

    private function addPageRights($page_def, $aclProvider, $securityIdentity)
    {
        if (false === array_key_exists('resources', $page_def) || false === array_key_exists('actions', $page_def)) {
            return;
        }

        $actions = $this->getActions($page_def['actions']);
        if (0 === count($actions)) {
            return array();
        }

        if (true === is_array($page_def['resources'])) {
            foreach ($page_def['resources'] as $page_url) {
                $pages = $this->em->getRepository('BackBee\Site\Layout')->findBy(array('_url' => $page_url));
                foreach ($pages as $page) {
                    $this->addObjectAcl($page, $aclProvider, $securityIdentity, $actions);
                }
            }
        } elseif ('all' === $page_def['resources']) {
            $this->addClassAcl(new \BackBee\NestedNode\Page('*'), $aclProvider, $securityIdentity, $actions);
        }
    }

    private function addFolderRights($folder_def, $aclProvider, $securityIdentity)
    {
        if (false === array_key_exists('resources', $folder_def) || false === array_key_exists('actions', $folder_def)) {
            return;
        }

        $actions = $this->getActions($folder_def['actions']);
        if (0 === count($actions)) {
            return array();
        }

        if ('all' === $folder_def['resources']) {
            $this->addClassAcl(new \BackBee\NestedNode\MediaFolder('*'), $aclProvider, $securityIdentity, $actions);
        }
    }

    private function addContentRights($content_def, $aclProvider, $securityIdentity)
    {
        if (false === array_key_exists('resources', $content_def) || false === array_key_exists('actions', $content_def)) {
            return;
        }

        $service = new \BackBee\Services\Local\ContentBlocks();
        $service->initService($this->bbapp);
        $all_classes = $service->getContentsByCategory();

        if ('all' === $content_def['resources']) {
            $actions = $this->getActions($content_def['actions']);
            if (0 === count($actions)) {
                return array();
            }

            $service = new \BackBee\Services\Local\ContentBlocks();
            $service->initService($this->bbapp);
            foreach ($all_classes as $content) {
                $classname = '\BackBee\ClassContent\\'.$content->name;
                $this->addClassAcl(new $classname('*'), $aclProvider, $securityIdentity, $actions);
            }
        } elseif (true === is_array($content_def['resources']) && 0 < count($content_def['resources'])) {
            if (true === is_array($content_def['resources'][0])) {
                $used_classes = array();
                foreach ($content_def['resources'] as $index => $resources_def) {
                    if (false === isset($content_def['actions'][$index])) {
                        continue;
                    }

                    $actions = $this->getActions($content_def['actions'][$index]);

                    if ('remains' === $resources_def) {
                        foreach ($all_classes as $content) {
                            $classname = '\BackBee\ClassContent\\'.$content->name;
                            if (false === in_array($classname, $used_classes)) {
                                $used_classes[] = $classname;
                                if (0 < count($actions)) {
                                    $this->addClassAcl(new $classname('*'), $aclProvider, $securityIdentity, $actions);
                                }
                            }
                        }
                    } elseif (true === is_array($resources_def)) {
                        foreach ($resources_def as $content) {
                            $classname = '\BackBee\ClassContent\\'.$content;
                            if (substr($classname, -1) === '*') {
                                $classname = substr($classname, 0 - 1);
                                foreach ($all_classes as $content) {
                                    $fullclass = '\BackBee\ClassContent\\'.$content->name;
                                    if (0 === strpos($fullclass, $classname)) {
                                        $used_classes[] = $fullclass;
                                        if (0 < count($actions)) {
                                            $this->addClassAcl(new $fullclass('*'), $aclProvider, $securityIdentity, $actions);
                                        }
                                    }
                                }
                            } elseif (true === class_exists($classname)) {
                                $used_classes[] = $classname;
                                if (0 < count($actions)) {
                                    $this->addClassAcl(new $classname('*'), $aclProvider, $securityIdentity, $actions);
                                }
                            }
                        }
                    }
                }
            } else {
                $actions = $this->getActions($content_def['actions']);
                if (0 === count($actions)) {
                    return array();
                }

                foreach ($content_def['resources'] as $content) {
                    $classname = '\BackBee\ClassContent\\'.$content;
                    if (substr($classname, -1) === '*') {
                        $classname = substr($classname, 0 -1);
                        foreach ($all_classes as $content) {
                            $fullclass = '\BackBee\ClassContent\\'.$content->name;
                            if (0 === strpos($fullclass, $classname)) {
                                $this->addClassAcl(new $fullclass('*'), $aclProvider, $securityIdentity, $actions);
                            }
                        }
                    } elseif (true === class_exists($classname)) {
                        $this->addClassAcl(new $classname('*'), $aclProvider, $securityIdentity, $actions);
                    }
                }
            }
        }
    }

    private function addBundleRights($bundle_def, $aclProvider, $securityIdentity)
    {
        if (false === array_key_exists('resources', $bundle_def) || false === array_key_exists('actions', $bundle_def)) {
            return;
        }

        $actions = $this->getActions($bundle_def['actions']);
        if (0 === count($actions)) {
            echo 'Notice: none actions defined on bundle'.PHP_EOL;

            return array();
        }

        if (true === is_array($bundle_def['resources'])) {
            foreach ($bundle_def['resources'] as $bundle_name) {
                if (null !== $bundle = $this->bbapp->getBundle($bundle_name)) {
                    $this->addObjectAcl($bundle, $aclProvider, $securityIdentity, $actions);
                }
            }
        } elseif ('all' === $bundle_def['resources']) {
            foreach ($this->bbapp->getBundles() as $bundle) {
                $this->addObjectAcl($bundle, $aclProvider, $securityIdentity, $actions);
            }
        }
    }

    private function getActions($def)
    {
        $actions = array();
        if (true === is_array($def)) {
            $actions = array_intersect(array('view', 'create', 'edit', 'delete', 'publish'), $def);
        } elseif ('all' === $def) {
            $actions = array('view', 'create', 'edit', 'delete', 'publish');
        }

        return $actions;
    }

    private function addObjectAcl($object, $aclProvider, $securityIdentity, $rights)
    {
        $objectIdentity = ObjectIdentity::fromDomainObject($object);

        try {
            $acl = $aclProvider->findAcl($objectIdentity, array($securityIdentity));
        } catch (\Exception $e) {
            $acl = $aclProvider->createAcl($objectIdentity);
        }

        foreach ($rights as $right) {
            try {
                $map = new PermissionMap();
                $acl->isGranted($map->getMasks(strtoupper($right), $object), array($securityIdentity));
            } catch (\Exception $e) {
                $builder = new MaskBuilder();
                foreach ($rights as $right) {
                    $builder->add($right);
                }
                $mask = $builder->get();

                $acl->insertObjectAce($securityIdentity, $mask);
                $aclProvider->updateAcl($acl);
            }
        }
    }

    private function addClassAcl($object, $aclProvider, $securityIdentity, $rights)
    {
        $objectIdentity = ObjectIdentity::fromDomainObject($object);

        try {
            $acl = $aclProvider->findAcl($objectIdentity, array($securityIdentity));
        } catch (\Exception $e) {
            $acl = $aclProvider->createAcl($objectIdentity);
        }

        foreach ($rights as $right) {
            try {
                $map = new PermissionMap();
                $acl->isGranted($map->getMasks(strtoupper($right), $object), array($securityIdentity));
            } catch (\Exception $e) {
                $builder = new MaskBuilder();
                foreach ($rights as $right) {
                    $builder->add($right);
                }
                $mask = $builder->get();

                $acl->insertClassAce($securityIdentity, $mask);
                $aclProvider->updateAcl($acl);
            }
        }
    }
}
