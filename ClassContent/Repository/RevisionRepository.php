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

namespace BackBee\ClassContent\Repository;

use BackBee\AutoLoader\Exception\ClassNotFoundException;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\ContentSet;
use BackBee\ClassContent\Exception\ClassContentException;
use BackBee\ClassContent\Exception\UnknownPropertyException;
use BackBee\ClassContent\Revision;
use BackBee\Security\Token\BBUserToken;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Class RevisionRepository
 *
 * @package BackBee\ClassContent\Repository
 *
 * @author  c.rouillon <charles.rouillon@lp-digital.fr>
 * @author Djoudi Bensid <d.bensid@team-one.fr>
 */
class RevisionRepository extends EntityRepository
{
    /**
     * @var BBUserToken
     */
    private $uniqToken;

    /**
     * RevisionRepository constructor.
     *
     * @param                 $em
     * @param ClassMetadata   $class
     */
    public function __construct($em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->uniqToken = (new BBUserToken())->getUniqToken();
    }

    /**
     * Returns the unique token used to unify all users drafts.
     *
     * @return BBUserToken
     */
    public function getUniqToken(): BBUserToken
    {
        return $this->uniqToken;
    }

    /**
     * Checkouts a new revision for $content
     *
     * @param AbstractClassContent $content
     * @param BBUserToken          $token
     *
     * @return Revision
     */
    public function checkout(AbstractClassContent $content, BBUserToken $token): Revision
    {
        $revision = new Revision();
        $revision->setAccept($content->getAccept());
        $revision->setContent($content);
        $revision->setData($content->getDataToObject());
        $revision->setLabel($content->getLabel());

        $maxEntry = $content->getMaxEntry() ?? [];
        $minEntry = $content->getMinEntry() ?? [];
        $revision->setMaxEntry($maxEntry);
        $revision->setMinEntry($minEntry);

        $revision->setOwner($this->uniqToken->getUser());
        $defaultParams = $content->getDefaultParams();

        foreach ($content->getAllParams() as $key => $param) {
            if ($defaultParams[$key]['value'] !== $param['value']) {
                $revision->setParam($key, $content->getParamValue($key));
            }
        }

        $revision->setRevision($content->getRevision() ?: 0);
        $revision->setState($content->getRevision() ? Revision::STATE_MODIFIED : Revision::STATE_ADDED);

        return $revision;
    }

    /**
     * Update user revision.
     *
     * @param Revision $revision
     *
     * @return Revision
     * @throws ClassContentException Occurs on illegal revision state
     * @throws ClassNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws UnknownPropertyException
     */
    public function update(Revision $revision): Revision
    {
        switch ($revision->getState()) {
            case Revision::STATE_ADDED:
                throw new ClassContentException('Content is not versioned yet', ClassContentException::REVISION_ADDED);

            case Revision::STATE_MODIFIED:
                try {
                    $this->checkContent($revision);
                    throw new ClassContentException(
                        'Content is already up-to-date',
                        ClassContentException::REVISION_UPTODATE
                    );
                } catch (ClassContentException $e) {
                    if (ClassContentException::REVISION_OUTOFDATE === $e->getCode()) {
                        return $this->loadSubcontents($revision);
                    }

                    throw $e;
                }

            case Revision::STATE_CONFLICTED:
                throw new ClassContentException(
                    'Content is in conflict, resolve or revert it',
                    ClassContentException::REVISION_CONFLICTED
                );
        }

        throw new ClassContentException('Content is already up-to-date', ClassContentException::REVISION_UPTODATE);
    }

    /**
     * Loads sub contents
     *
     * @param Revision $revision
     *
     * @return Revision
     * @throws ClassContentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws ClassNotFoundException
     * @throws UnknownPropertyException
     */
    public function loadSubcontents(Revision $revision): Revision
    {
        $content = $revision->getContent();
        if ($content instanceof ContentSet) {
            while ($subcontent = $revision->next()) {
                if (!($subcontent instanceof AbstractClassContent)) {
                    continue;
                }

                if ($this->_em->contains($subcontent)) {
                    continue;
                }

                $subcontent = $this->_em->find(get_class($subcontent), $subcontent->getUid());
            }
        } else {
            foreach ($revision->getData() as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as &$val) {
                        if ($val instanceof AbstractClassContent) {
                            if (null !== $entity = $this->_em->find(get_class($val), $val->getUid())) {
                                $val = $entity;
                            }
                        }
                    }

                    unset($val);
                } elseif ($value instanceof AbstractClassContent) {
                    if (null !== $entity = $this->_em->find(get_class($value), $value->getUid())) {
                        $value = $entity;
                    }
                }

                $revision->$key = $value;
            }
        }

        return $revision;
    }

    /**
     * Return the user's draft of a content, optionally checks out a new one if not exists.
     *
     * @param AbstractClassContent $content
     * @param BBUserToken          $token
     * @param boolean              $checkoutOnMissing If true, checks out a new revision if none was found
     *
     * @return Revision|void|null
     */
    public function getDraft(
        AbstractClassContent $content,
        BBUserToken $token,
        bool $checkoutOnMissing = false
    ) {
        if (null === ($revision = $content->getDraft())) {
            try {
                if (false === $this->_em->contains($content)) {
                    $content = $this->_em->find(get_class($content), $content->getUid());
                    if (null === $content) {
                        return;
                    }
                }

                $q = $this->createQueryBuilder('r')
                    ->andWhere('r._content = :content')
                    ->andWhere('r._owner = :owner')
                    ->andWhere('r._state IN (:states)')
                    ->orderBy('r._revision', 'desc')
                    ->orderBy('r._modified', 'desc')
                    ->setParameters(
                        [
                            'content' => $content,
                            'owner' => '' . UserSecurityIdentity::fromToken($this->uniqToken),
                            'states' => [Revision::STATE_ADDED, Revision::STATE_MODIFIED, Revision::STATE_CONFLICTED],
                        ]
                    )
                    ->getQuery();

                $revision = $q->getSingleResult();
            } catch (NoResultException $e) {
                if ($checkoutOnMissing) {
                    $revision = $this->checkout($content, $this->uniqToken);
                    $this->_em->persist($revision);
                } else {
                    $revision = null;
                }
            } catch (NonUniqueResultException $e) {
                $drafts = $q->getResult();
                $revision = array_shift($drafts);
                foreach ($drafts as $draft) {
                    $this->_em->remove($draft);
                    $this->_em->flush($draft);
                }
            } catch (Exception $e) {
                $this->removeDraft($content);
                $revision = null;
            }
        }

        return $revision;
    }

    /**
     * Returns all current drafts for authenticated user.
     *
     * @param TokenInterface $token
     *
     * @return array
     * @throws DBALException
     */
    public function getAllDrafts(TokenInterface $token): array
    {
        $owner = UserSecurityIdentity::fromToken($this->uniqToken);
        $states = [Revision::STATE_ADDED, Revision::STATE_MODIFIED];

        try {
            $result = $this->findBy(
                [
                    '_owner' => $owner,
                    '_state' => $states,
                ]
            );
        } catch (ConversionException $e) {
            $result = [];
            foreach ($this->getAllDraftsUids($owner, $states) as $data) {
                try {
                    $result[] = $this->find($data['_uid']);
                } catch (ConversionException $e) {
                    $this->_em->getConnection()->executeUpdate(
                        'DELETE FROM revision WHERE uid = :uid',
                        ['uid' => $data['_uid']]
                    );
                }
            }
        } catch (MappingException $e) {
            $result = [];
            foreach ($this->getAllDraftsUids($owner, $states) as $data) {
                $draft = $this->find($data['_uid']);
                if (null !== $draft->getContent()) {
                    $result[] = $draft;
                } else {
                    $this->_em->getConnection()->executeUpdate(
                        'DELETE FROM revision WHERE uid = :uid',
                        ['uid' => $data['_uid']]
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Returns revisions for $content
     *
     * @param AbstractClassContent $content  The content of the revisions to look for.
     * @param array                $states   Optional, an array of states of revision
     *                                       (empty by default).
     * @param integer              $start    Optional, the first result number (0 by default).
     * @param integer              $limit    Optional, the max number of results
     *
     * @return Paginator|Revision[]          If $limit is provided returns a Doctrine Paginator
     *                                       elsewhere an array of maching revisions.
     */
    public function getRevisions(AbstractClassContent $content, array $states = [], int $start = 0, $limit = null)
    {
        $query = $this->createQueryBuilder('r')
            ->setFirstResult($start)
            ->andWhere('r._content = :content')
            ->orderBy('r._revision', 'desc')
            ->setParameter('content', $content);

        if (!empty($states)) {
            $query->andWhere($query->expr()->in('r._state', $states));
        }

        if (null !== $limit) {
            $query->setMaxResults($limit);

            return new Paginator($query);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Returns a revision of $content.
     *
     * @param AbstractClassContent $content  The content.
     * @param integer              $revision Optional, the revision number (the last one by default).
     *
     * @return Revision|null                  The matching revision if found, null elsewhere.
     */
    public function getRevision(AbstractClassContent $content, $revision = null): ?Revision
    {
        $criteria = ['_content' => $content];
        if ($revision !== null) {
            $criteria['_revision'] = $revision;
        }

        return $this->findOneBy($criteria, ['_revision' => 'desc']);
    }

    protected function getAllDraftsUids($owner, array $states)
    {
        $qb = $this->createQueryBuilder('r');

        return $qb
            ->select('r._uid')
            ->where('r._owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere($qb->expr()->in('r._state', $states))
            ->getQuery()
            ->getResult();
    }

    /**
     * Checks the content state of a revision.
     *
     * @param Revision $revision
     *
     * @return void  the valid content according to revision state
     * @throws ClassContentException Occurs when the revision is orphan
     */
    private function checkContent(Revision $revision): void
    {
        $content = $revision->getContent();

        if (null === $content || !($content instanceof AbstractClassContent)) {
            $this->_em->remove($revision);
            throw new ClassContentException('Orphan revision, deleted', ClassContentException::REVISION_ORPHAN);
        }

        if ($revision->getRevision() !== $content->getRevision()) {
            throw new ClassContentException('Content is out of date', ClassContentException::REVISION_OUTOFDATE);
        }

    }

    /**
     * Remove draft in particular if it is incorrect.
     *
     * @param \BackBee\ClassContent\AbstractClassContent $content
     *
     * @return void
     */
    private function removeDraft(AbstractClassContent $content): void
    {
        try {
            $this->createQueryBuilder('r')->delete()
                ->andWhere('r._content = :content')
                ->andWhere('r._owner = :owner')
                ->andWhere('r._state IN (:states)')
                ->setParameters(
                    [
                        'content' => $content,
                        'owner' => '' . UserSecurityIdentity::fromToken($this->uniqToken),
                        'states' => [Revision::STATE_ADDED, Revision::STATE_MODIFIED, Revision::STATE_CONFLICTED],
                    ]
                )
                ->getQuery()
                ->execute();
        } catch (Exception $exception) {

        }
    }
}
