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

namespace BackBee\ClassContent\Repository\Element;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Element\Keyword;
use BackBee\ClassContent\Exception\ClassContentException;
use BackBee\ClassContent\Repository\ClassContentRepository;
use BackBee\Security\Token\BBUserToken;

/**
 * keyword repository.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class KeywordRepository extends ClassContentRepository
{
    /**
     * Do update by post of the content editing form.
     *
     * @param  \BackBee\ClassContent\AbstractClassContent $content
     * @param  stdClass                            $value
     * @param  \BackBee\ClassContent\AbstractClassContent $parent
     *
     * @return \BackBee\ClassContent\Element\File
     * @throws ClassContentException Occures on invalid content type provided
     * @deprecated since version v1.1
     */
    public function getValueFromPost(AbstractClassContent $content, $value, AbstractClassContent $parent = null)
    {
        if (false === ($content instanceof Keyword)) {
            throw new ClassContentException('Invalid content type');
        }

        if (true === property_exists($value, 'value')) {
            $content->value = $value->value;

            if (null !== $realkeyword = $this->_em->find('BackBee\NestedNode\KeyWord', $value->value)) {
                if (null === $parent) {
                    throw new ClassContentException('Invalid parent content');
                }

                if (null === $realkeyword->getContent() || false === $realkeyword->getContent()->contains($parent)) {
                    $realkeyword->addContent($parent);
                }
            }
        }

        return $content;
    }

    /**
     * Do removing content from the content editing form.
     *
     * @param  \BackBee\ClassContent\AbstractClassContent $content
     * @param  type                                $value
     * @param  \BackBee\ClassContent\AbstractClassContent $parent
     *
     * @return type
     *
     * @throws ClassContentException
     * @deprecated since version v1.1
     */
    public function removeFromPost(AbstractClassContent $content, $value = null, AbstractClassContent $parent = null)
    {
        if (false === ($content instanceof Keyword)) {
            throw new ClassContentException('Invalid content type');
        }

        $content = parent::removeFromPost($content);

        if (true === property_exists($value, 'value')) {
            if (null === $parent) {
                throw new ClassContentException('Invalid parent content');
            }

            if (null !== $realkeyword = $this->_em->find('BackBee\NestedNode\KeyWord', $value->value)) {
                if (true === $realkeyword->getContent()->contains($parent)) {
                    $realkeyword->removeContent($parent);
                }
            }
        }

        return $content;
    }

    /**
     * Updates keywords_contents join.
     *
     * @param AbstractClassContent $content
     * @param mixed                $keywords
     * @param BBUserToken          $token
     */
    public function updateKeywordLinks(AbstractClassContent $content, $keywords, BBUserToken $token = null)
    {
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }

        foreach ($keywords as $keyword) {
            if (!($keyword instanceof Keyword)) {
                continue;
            }

            if (
                null !== $token &&
                null !== $draft = $this->_em->getRepository('BackBee\ClassContent\Revision')->getDraft($keyword, $token)
            ) {
                $keyword->setDraft($draft);
            }

            if (
                empty($keyword->value)
                || (null === $realKeyword = $this->_em->find('BackBee\NestedNode\KeyWord', $keyword->value))
            ) {
                continue;
            }

            if (!$realKeyword->getContent()->contains($content)) {
                $realKeyword->getContent()->add($content);
            }
        }
    }

    /**
     * Deletes outdated keyword content joins.
     *
     * @param AbstractClassContent $content
     * @param mixed                $keywords
     */
    public function cleanKeywordLinks(AbstractClassContent $content, $keywords)
    {
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }

        $keywordUids = [];
        foreach ($keywords as $keyword) {
            if (
                $keyword instanceof Keyword
                && !empty($keyword->value)
                && (null !== $realKeyword = $this->_em->find('BackBee\NestedNode\KeyWord', $keyword->value))
            ) {
                $keywordUids[] = $realKeyword->getUid();
            }
        }

        $query = $this->_em
            ->getConnection()
            ->createQueryBuilder()
            ->select('c.keyword_uid')
            ->from('keywords_contents', 'c')
        ;
        $query->where($query->expr()->eq('c.content_uid', $query->expr()->literal($content->getUid())));
        $savedKeywords = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $linksToBeRemoved = array_diff($savedKeywords, $keywordUids);
        if (count($linksToBeRemoved)) {
            $query = $this->_em
                ->getConnection()
                ->createQueryBuilder()
                ->delete('keywords_contents')
            ;

            array_walk(
                $linksToBeRemoved,
                function(&$value, $key, $query) {
                    $value = $query->expr()->literal($value);
                },
                $query
            );

            $query
                ->where($query->expr()->eq('content_uid', $query->expr()->literal($content->getUid())))
                ->andWhere($query->expr()->in('keyword_uid', $linksToBeRemoved))
                ->execute()
            ;
        }
    }
}
