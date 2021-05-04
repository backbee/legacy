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

namespace BackBee\Rest\Controller;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BackBee\ClassContent\Exception\InvalidContentTypeException;
use BackBee\ClassContent\Revision;
use BackBee\Rest\Controller\Annotations as Rest;

/**
 * Revision API Controller.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class RevisionController extends AbstractRestController
{

    /**
     * Returns a revision.
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\ParamConverter(name="revision", class="BackBee\ClassContent\Revision")
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getAction(Revision $revision)
    {
        $this->granted('VIEW', $revision->getContent());

        $response = $this->createJsonResponse();
        $response->setData($revision->jsonSerialize(Revision::JSON_REVISION_FORMAT));

        return $response;
    }

    /**
     * Returns collection of revisions for a content.
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     *
     * @Rest\Pagination(default_count=25, max_count=100)
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function getCollectionByContentAction($type, $uid, $start, $count)
    {
        $content = $this->findOneByTypeAndUid($type, $uid);
        $this->granted('VIEW', $content);

        $revisions = $this->getEntityManager()
                ->getRepository('BackBee\ClassContent\Revision')
                ->getRevisions($content, [Revision::STATE_COMMITTED], $start, $count);

        $result = [];
        foreach ($revisions as $revision) {
            $result[] = $revision->jsonSerialize(Revision::JSON_REVISION_FORMAT);
        }

        $response = $this->createJsonResponse();
        $response->setData($result);

        if ($revisions instanceof Paginator) {
            $response->headers->set('Content-Range', sprintf('%d-%d/%d', $start, $start +
                            count($result) - 1, count($revisions)));
        }

        return $response;
    }

    /**
     * Returns classcontent according to provided $type and $uid.
     *
     * @param  string               $type Either short or full namespace of a classcontent.
     *                                    (full: BackBee\ClassContent\Block\paragraph => short: Block/paragraph)
     * @param  string               $uid  A unique identifier of a content.
     *
     * @return AbstractClassContent       The matching content.
     *
     * @throws NotFoundHttpException      Thrown if no matching content found.
     */
    private function findOneByTypeAndUid($type, $uid)
    {
        try {
            $content = $this->getApplication()
                    ->getContainer()
                    ->get('classcontent.manager')
                    ->findOneByTypeAndUid($type, $uid, true);
        } catch (InvalidContentTypeException $e) {
            throw new NotFoundHttpException(sprintf('Provided content type (:%s) is invalid.', $type), $e);
        }

        if (null === $content) {
            throw new NotFoundHttpException(sprintf('Cannot find `%s` with uid `%s`.', $type, $uid));
        }

        return $content;
    }
}
