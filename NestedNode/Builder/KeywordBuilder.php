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

namespace BackBee\NestedNode\Builder;

/**
 * @author e.chau <eric.chau@lp-digital.fr>
 */
class KeywordBuilder
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * KeywordBuilder's constructor.
     *
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct(\Doctrine\ORM\EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Create new entity BackBee\NestedNode\KeyWord with $keyword if not exists.
     *
     * @param string $keyword
     *
     * @return BackBee\NestedNode\KeyWord
     */
    public function createKeywordIfNotExists($keyword, $do_persist = true)
    {
        if (null === $keyword_object = $this->em->getRepository('BackBee\NestedNode\KeyWord')->exists($keyword)) {
            $keyword_object = new \BackBee\NestedNode\KeyWord();
            $keyword_object->setRoot($this->em->find('BackBee\NestedNode\KeyWord', md5('root')));
            $keyword_object->setKeyWord(preg_replace('#[/\"]#', '', trim($keyword)));

            if (true === $do_persist) {
                $this->em->persist($keyword_object);
                $this->em->flush($keyword_object);
            }
        }

        return $keyword_object;
    }
}
