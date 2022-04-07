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

namespace BackBee\NestedNode\Repository;

use Doctrine\ORM\NoResultException;

use BackBee\NestedNode\Section;
use BackBee\Site\Site;
use BackBee\Util\Collection\Collection;

/**
 * Section repository
 *
 * @category    BackBee
 *
 * 
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class SectionRepository extends NestedNodeRepository
{
    /**
     * Returns the root section for $site tree.
     *
     * @param  Site                 $site               The site to test.
     *
     * @return Section|null
     */
    public function getRoot(Site $site)
    {
        try {
            $query = $this->createQueryBuilder('s')
                    ->andWhere('s._site = :site')
                    ->andWhere('s._parent is null')
                    ->setMaxResults(1)
                    ->setParameters([
                        'site' => $site,
                    ]);

            return $query->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            return;
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * Updates nodes information of a tree.
     *
     * @param  string               $nodeUid            The starting point in the tree.
     * @param  integer              $leftNode           Optional, the first value of left node, 1 by default.
     * @param  integer              $level              Optional, the first value of level, 0 by default.
     *
     * @return \StdClass
     */
    public function updateTreeNatively($nodeUid, $leftNode = 1, $level = 0)
    {
        $node = new \StdClass();
        $node->uid = $nodeUid;
        $node->leftnode = $leftNode;
        $node->rightnode = $leftNode + 1;
        $node->level = $level;

        foreach ($this->getNativelyNodeChildren($nodeUid) as $child_uid) {
            $child = $this->updateTreeNatively($child_uid, $leftNode + 1, $level + 1);
            $node->rightnode = $child->rightnode + 1;
            $leftNode = $child->rightnode;
        }

        $this->updateSectionNodes($node->uid, $node->leftnode, $node->rightnode, $node->level)
                ->updatePageLevel($node->uid, $node->level);

        return $node;
    }

    /**
     * Returns an array of uid of the children of $nodeUid.
     *
     * @param  string               $nodeUid            The node uid to look for children.
     *
     * @return string[]
     */
    public function getNativelyNodeChildren($nodeUid)
    {
        $query = $this->createQueryBuilder('s')
                ->select('s._uid', 's._leftnode')
                ->where('s._parent = :node')
                ->addOrderBy('s._leftnode', 'asc')
                ->addOrderBy('s._modified', 'desc')
                ->getQuery()
                ->getSQL();

        $result = $this->getEntityManager()
                ->getConnection()
                ->executeQuery((string) $query, [$nodeUid], [\Doctrine\DBAL\Types\Type::STRING])
                ->fetchAll();

        return Collection::array_column($result, 'uid0');
    }

    /**
     * Updates nodes information for Section $sectionUid.
     *
     * @param  string               $sectionUid
     * @param  integer              $leftNode
     * @param  integer              $rightNode
     * @param  integer              $level
     *
     * @return SectionRepository
     * @codeCoverageIgnore
     */
    private function updateSectionNodes($sectionUid, $leftNode, $rightNode, $level)
    {
        $this->createQueryBuilder('s')
                ->update()
                ->set('s._leftnode', $leftNode)
                ->set('s._rightnode', $rightNode)
                ->set('s._level', $level)
                ->where('s._uid = :uid')
                ->setParameter('uid', $sectionUid)
                ->getQuery()
                ->execute();

        return $this;
    }

    public function deleteSection(Section $section)
    {
        $page_repo = $this->getEntityManager()
                ->getRepository('BackBee\NestedNode\Page');

        $pages = $page_repo->createQueryBuilder('p')
            ->andParentIs($section->getPage())
            ->andIsNotSection()
            ->getQuery()
            ->execute();

        foreach ($pages as $page) {
            $page_repo->deletePage($page);
        }

        $sections = $page_repo->createQueryBuilder('p')
            ->andParentIs($section->getPage())
            ->andIsSection()
            ->getQuery()
            ->execute();

        $this->getEntityManager()
                ->createQueryBuilder()
                ->update('BackBee\NestedNode\Page', 'p')
                ->set('p._section', ':null')
                ->where('p._section = :uid')
                ->setParameter('uid', $section->getUid())
                ->setParameter('null', null)
                ->getQuery()
                ->execute();

        foreach ($sections as $subsection) {
            $page_repo->deletePage($subsection);
        }

        $this->getEntityManager()->remove($section);
    }

    /**
     * Updates level of page attach to section $sectionUid.
     *
     * @param  string               $sectionUid
     * @param  integer              $level
     *
     * @return SectionRepository
     * @codeCoverageIgnore
     */
    private function updatePageLevel($sectionUid, $level)
    {
        $page_repo = $this->getEntityManager()
                ->getRepository('BackBee\NestedNode\Page');

        $page_repo->createQueryBuilder('p')
                ->update()
                ->set('p._level', $level)
                ->where('p._uid = :uid')
                ->setParameter('uid', $sectionUid)
                ->getQuery()
                ->execute();

        $page_repo->createQueryBuilder('p')
                ->update()
                ->set('p._level', $level + 1)
                ->where('p._section = :uid')
                ->andWhere('p._uid <> :uid')
                ->setParameter('uid', $sectionUid)
                ->getQuery()
                ->execute();

        return $this;
    }

    /**
     * Move node regarding $dest.
     *
     * @param  Section $node     The section to be moved.
     * @param  Section $dest     The targetted section in tree.
     * @param  string  $position Either 'after', 'before', 'firstin', 'lastin'.
     *
     * @return Section
     */
    public function moveNode(Section $node, Section $dest, $position)
    {
        return $this->_moveNode($node, $dest, $position);
    }
}
