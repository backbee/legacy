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

namespace BackBee\ClassContent;

use BackBee\ApplicationInterface;
use BackBee\ClassContent\Iconizer\IconizerInterface;
use BackBee\Exception\InvalidArgumentException;
use BackBee\NestedNode\Page;
use BackBee\Security\Token\BBUserToken;

use Doctrine\ORM\Tools\Pagination\Paginator;

use Exception;
use Symfony\Component\Security\Core\Util\ClassUtils;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ClassContentManager
{
    /**
     * @var ApplicationInterface
     */
    private $app;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * @var BBUserToken
     */
    private $token;

    /**
     * @var IconizerInterface
     */
    private $iconizer;

    /**
     * @var string[]
     */
    private $contentClassnames;

    /**
     * @var \BackBee\Cache\AbstractCache
     */
    private $cache;

    /**
     * Instantiate a ClassContentManager.
     *
     * @param ApplicationInterface   $app      The current application.
     * @param IconizerInterface|null $iconizer Optional, an content iconizer.
     */
    public function __construct(ApplicationInterface $app, IconizerInterface $iconizer = null)
    {
        $this->app = $app;
        $this->entityManager = $app->getEntityManager();
        $this->iconizer = $iconizer;
        $this->cache = $app->getContainer()->get('cache.control');
    }

    /**
     * Sets a content iconizer to manager.
     *
     * @param  IconizerInterface $iconizer The content iconizer to use.
     *
     * @return ClassContentManager
     */
    public function setIconizer(IconizerInterface $iconizer)
    {
        $this->iconizer = $iconizer;

        return $this;
    }

    /**
     * Setter of BBUserToken.
     *
     * @param  BBUserToken|null $token
     *
     * @return ClassContentManager
     */
    public function setBBUserToken(BBUserToken $token = null)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Updates the content whith provided data.
     *
     * @param  AbstractClassContent $content The content to be updated.
     * @param  array                $data    Array of data that must contains parameters and/or elements key.
     *
     * @return ClassContentManager
     *
     * @throws \InvalidArgumentException     Raises if provided data doesn't have parameters and elements key.
     */
    public function update(AbstractClassContent $content, array $data)
    {
        if (!isset($data['parameters']) && !isset($data['elements'])) {
            throw new \InvalidArgumentException('Provided data are not valids for ClassContentManager::update.');
        }

        if (isset($data['parameters'])) {
            $this->updateParameters($content, $data['parameters']);
        }

        if (isset($data['elements'])) {
            $this->updateElements($content, $data['elements']);
        }

        return $this;
    }

    /**
     * Check if a contentset is a main zone on a page
     *
     * @param  AbstractClassContent $content The content to be updated.
     *
     */
    public function isMainZone($content)
    {
        if ($content instanceof ContentSet) {
            $pages = $this->entityManager->getRepository('BackBee\ClassContent\ContentSet')->findPagesByContent($content);

            if (count($pages) > 0) {
                foreach ($pages as $page) {
                    if ($page instanceof Page) {
                        if (false !== $index = $page->getContentSet()->indexOf($content, true)) {
                            $zone = $page->getLayout()->getZone($index);
                            if (is_a($zone, '\stdClass') && 1 === $zone->mainZone) {
                                return $page;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Calls ::jsonSerialize of the content and build valid url for image.
     *
     * @param  AbstractClassContent $content
     * @param  integer              $format
     *
     * @return array
     */
    public function jsonEncode(AbstractClassContent $content, $format = AbstractClassContent::JSON_DEFAULT_FORMAT)
    {
        if (AbstractClassContent::JSON_DEFINITION_FORMAT === $format) {
            $classname = get_class($content);
            $content = new $classname;
        }

        $result = $content->jsonSerialize($format);

        if (isset($result['image']) && null !== $this->iconizer) {
            $result['image'] = $this->iconizer->getIcon($content);
        }

        return $result;
    }

    /**
     * Calls ::jsonSerialize on all contents and build valid url for image.
     *
     * It can manage collection type of array or object that implements \IteratorAggregate and/or \Traversable.
     *
     * @param mixed $collection The collection to encode
     * @param int   $format     The format to use
     *
     * @return array
     * @throws \InvalidArgumentException if provided collection is not supported type
     */
    public function jsonEncodeCollection($collection, $format = AbstractClassContent::JSON_DEFAULT_FORMAT)
    {
        if (
            !is_array($collection)
            && !($collection instanceof \IteratorAggregate)
            && !($collection instanceof \Traversable)
        ) {
            throw new \InvalidArgumentException(
                'Collection must be type of array or an object that implements \IteratorAggregate and/or \Traversable.'
            );
        }

        $contents = [];
        if (AbstractClassContent::JSON_DEFINITION_FORMAT === $format) {
            if (is_object($collection) && $collection instanceof Paginator) {
                $contents[] = $this->jsonEncode($collection->getIterator()->current(), $format);
            } elseif (is_array($collection)) {
                $contents[] = array_pop($collection);
            }
        } else {
            foreach ($collection as $content) {
                $contents[] = $this->jsonEncode($content, $format);
            }
        }

        return $contents;
    }

    /**
     * Returns all classcontents classnames
     *
     * @return string[] An array that contains all classcontents classnames
     */
    public function getAllClassContentClassnames(): array
    {
        if ($this->contentClassnames === null) {
            $cacheId = md5('all_classcontents_classnames_'.$this->app->getContext().'_'.$this->app->getEnvironment());
            if (!$this->app->isDebugMode() && ($value = $this->cache->load($cacheId)) !== false) {
                try {
                    $this->contentClassnames = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (Exception $exception) {
                    $this->app->getLogging()->error(
                        sprintf(
                            '%s : %s :%s',
                            __CLASS__,
                            __FUNCTION__,
                            $exception->getMessage()
                        )
                    );
                }
            } else {
                $this->contentClassnames = array_merge(
                    [AbstractClassContent::CLASSCONTENT_BASE_NAMESPACE . 'ContentSet'],
                    ...array_map(static function ($directory) {
                        return CategoryManager::getClassContentClassnamesFromDir($directory);
                    }, $this->app->getClassContentDir())
                );
                try {
                    $this->cache->save($cacheId, json_encode($this->contentClassnames, JSON_THROW_ON_ERROR));
                } catch (Exception $exception) {
                    $this->app->getLogging()->error(
                        sprintf(
                            '%s : %s :%s',
                            __CLASS__,
                            __FUNCTION__,
                            $exception->getMessage()
                        )
                    );
                }
            }
        }

        $this->contentClassnames = array_reverse($this->contentClassnames);

        return $this->contentClassnames;
    }

    /**
     * Returns classnames of all classcontents element
     *
     * @return string[] Contains every BackBee's element classcontent classnames
     */
    public function getAllElementClassContentClassnames(): array
    {
        $classnames = array_filter(
            $this->getAllClassContentClassnames(),
            static function ($classname) {
                return strpos(
                    $classname,
                    AbstractContent::CLASSCONTENT_BASE_NAMESPACE.'Element\\'
                ) !== false;
            }
        );

        $classnames[] = AbstractContent::CLASSCONTENT_BASE_NAMESPACE.'ContentSet';

        return $classnames;
    }

    /**
     * Returns current revision for the given $content
     *
     * @param AbstractClassContent $content           The content we want to get the latest revision
     * @param boolean              $checkoutOnMissing If TRUE, checkout a new revision for $content
     *
     * @return null|Revision
     */
    public function getDraft(AbstractClassContent $content, $checkoutOnMissing = false)
    {
        return $this->entityManager->getRepository('BackBee\ClassContent\Revision')->getDraft(
            $content,
            $this->token ?: $this->app->getBBUserToken(),
            $checkoutOnMissing
        );
    }

    /**
     * Computes provided data to define what to commit from given content.
     *
     * @param  AbstractClassContent $content
     * @param  array                $data
     *
     * @return ClassContentManager
     */
    public function commit(AbstractClassContent $content, array $data = [])
    {
        foreach ($this->getAllClassContentClassnames() as $classname) {
            class_exists($classname);
        }

        if (null === $draft = $this->getDraft($content)) {
            throw new InvalidArgumentException(sprintf(
                '%s with identifier "%s" has not draft, nothing to commit.',
                $content->getContentType(),
                $content->getUid()
            ));
        }

        if (isset($data['parameters']) || isset($data['elements'])) {
            $cleanDraft = clone $draft;
            $this->prepareDraftForCommit($content, $draft, $data);
            $this->executeCommit($content, $draft);
            $this->commitPostProcess($content, $cleanDraft);
        } else {
            $this->executeCommit($content, $draft);
        }

        return $this;
    }

    /**
     * Computes provided data to define what to revert from given content.
     *
     * @param  AbstractClassContent $content
     * @param  array                $data
     *
     * @return ClassContentManager
     */
    public function revert(AbstractClassContent $content, array $data = [])
    {
        if (0 === count($data)) {
            $data['parameters'] = array_keys($content->getAllParams());
            if ($content instanceof ContentSet) {
                $data['elements'] = true;
            } else {
                $data['elements'] = array_keys($content->getData());
            }
        }

        if (!isset($data['parameters']) && !isset($data['elements'])) {
            throw new \InvalidArgumentException('Provided data are not valids for ClassContentManager::revert.');
        }

        if (null === $draft = $this->getDraft($content)) {
            throw new InvalidArgumentException(sprintf(
                '%s with identifier "%s" has not draft, nothing to revert.',
                $content->getContentType(),
                $content->getUid()
            ));
        }

        $this->executeRevert($content, $draft, $data);
        $this->revertPostProcess($content, $draft);

        return $this;
    }

    /**
     * Reverts a content to a specific revision.
     *
     * @param  AbstractClassContent $content  The content to revert.
     * @param  integer              $revision The revision number.
     *
     * @return ClassContentManager            The current class content manager.
     */
    public function revertToRevision(AbstractClassContent $content, $revision)
    {
        // First remove existing draft for user
        if (null !== $draft = $this->getDraft($content)) {
            $this->entityManager->remove($draft);
        }

        // If asked $revision is the last one, all is done.
        if ($content->getRevision() === (int) $revision) {
            return $this;
        }

        $source = $this->entityManager
                ->getRepository('BackBee\ClassContent\Revision')
                ->getRevision($content, (int) $revision);

        if (null === $source) {
            throw new \InvalidArgumentException(sprintf('Unknown revision %d for content %s.', $revision, $content->getObjectIdentifier()));
        }

        $token = $this->token ? : $this->app->getBBUserToken();

        $draft = clone $source;
        $draft->setOwner($token->getUser());
        $draft->setRevision($content->getRevision());
        $draft->setState(Revision::STATE_MODIFIED);
        $draft->setComment(sprintf('Revert to revision %d.', $revision));
        $draft->setCreated();
        $draft->setModified();

        $this->entityManager->persist($draft);
        $content->setDraft($draft);

        return $this;
    }

    /**
     * Alias to ClassContentRepository::findOneByTypeAndUid. In addition, it can also manage content's revision.
     *
     * @see BackBee\ClassContent\Repository\ClassContentRepository
     * @param  string  $type
     * @param  string  $uid
     * @param  boolean $hydrateDraft      if true and BBUserToken is setted, will try to get and set draft to content
     * @param  boolean $checkoutOnMissing this parameter is used only if hydrateDraft is true
     *
     * @return AbstractClassContent|null
     */
    public function findOneByTypeAndUid($type, $uid, $hydrateDraft = false, $checkoutOnMissing = false)
    {
        $classname = AbstractClassContent::getClassnameByContentType($type);
        $content = $this->entityManager->getRepository($classname)->findOneBy(['_uid' => $uid]);

        if (null !== $content && true === $hydrateDraft && null !== $this->token) {
            $content->setDraft($this->getDraft($content, $checkoutOnMissing));
        }

        return $content;
    }

    /**
     * Updates provided content's elements with data
     *
     * @param  AbstractClassContent $content
     * @param  array                $elementsData
     *
     * @return ClassContentManager
     */
    private function updateElements(AbstractClassContent $content, array $elementsData)
    {
        if ($content instanceof ContentSet) {
            $elements = $this->prepareElements($elementsData, false);
            $page = $this->isMainZone($content);
            $this->updateContentSetElements($content, $elements, $page);
        } else {
            $elements = $this->prepareElements($elementsData);
            $this->updateContentElements($content, $elements);
        }

        return $this;
    }

    /**
     * Updates provided contentset's elements with data.
     *
     * @param  ContentSet $content
     * @param  array      $elementsData
     *
     * @return ClassContentManager
     */
    private function updateContentSetElements(ContentSet $content, array $elementsData, Page $page = null)
    {
        $content->clear();
        foreach ($elementsData as $data) {
            if ($data instanceof AbstractClassContent) {
                if (null !== $page && null === $data->getMainNode()) {
                    $data->setMainNode($page);
                }
                $content->push($data);
            }
        }

        return $this;
    }

    /**
     * Updates provided content's elements with data.
     *
     * @param  AbstractClassContent $content
     * @param  array                $elementsData
     *
     * @return ClassContentManager
     */
    private function updateContentElements(AbstractClassContent $content, array $elementsData)
    {
        foreach ($elementsData as $key => $data) {
            if (in_array('Element\Keyword', $content->getAccept()[$key])) {
                $this->entityManager
                        ->getRepository('BackBee\ClassContent\Element\Keyword')
                        ->updateKeywordLinks($content, $data, $this->token);
            }
            $content->$key = $data;
        }

        return $this;
    }

    /**
     * Prepare elements to be setted.
     *
     * @param  array   $elementsData The elements to be setted.
     * @param  boolean $acceptScalar If true, scalar value will be accepted.
     *
     * @return array
     */
    private function prepareElements(array $elementsData, $acceptScalar = true)
    {
        $elements = [];
        foreach ($elementsData as $key => $data) {
            if (true === $acceptScalar && is_scalar($data)) {
                $elements[$key] = $data;
                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            if (isset($data['type']) && isset($data['uid'])) {
                if (null !== $element = $this->findOneByTypeAndUid($data['type'], $data['uid'])) {
                    $elements[$key] = $element;
                }

                continue;
            }

            $elements[$key] = [];
            foreach ($data as $row) {
                if (
                    isset($row['type'])
                    && isset($row['uid'])
                    && null !== $element = $this->findOneByTypeAndUid($row['type'], $row['uid'])
                ) {
                    $elements[$key][] = $element;
                }
            }
        }

        return $elements;
    }

    /**
     * Updates provided content's parameters.
     *
     * @param  AbstractClassContent $content
     * @param  array                $paramsData
     *
     * @return ClassContentManager
     */
    private function updateParameters(AbstractClassContent $content, array $paramsData)
    {
        foreach ($paramsData as $key => $param) {
            $content->setParam($key, $param);
        }

        return $this;
    }

    /**
     * Prepares draft for commit.
     *
     * @param  AbstractClassContent $content
     * @param  Revision             $draft
     * @param  array                $data
     *
     * @return ClassContentManager
     */
    private function prepareDraftForCommit(AbstractClassContent $content, Revision $draft, array $data)
    {
        if ($content instanceof ContentSet) {
            if (!isset($data['elements']) || false === $data['elements']) {
                $draft->clear();
                foreach ($content->getData() as $element) {
                    $draft->push($element);
                }
            }
        } elseif (AbstractClassContent::STATE_NORMAL === $content->getState()) {
            foreach ($content->getData() as $key => $element) {
                if (!isset($data['elements']) || !in_array($key, $data['elements'])) {
                    $draft->$key = $content->$key;
                }
            }
        }

        if (isset($data['parameters'])) {
            foreach ($content->getAllParams() as $key => $params) {
                if (!in_array($key, $data['parameters'])) {
                    $draft->setParam($key, $content->getParamValue($key));
                }
            }
        }

        if (isset($data['message'])) {
            $draft->setComment($data['message']);
        }

        return $this;
    }

    /**
     * Executes commit action on content and its draft.
     *
     * @param  AbstractClassContent $content
     * @param  Revision             $draft
     *
     * @return ClassContentManager
     */
    private function executeCommit(AbstractClassContent $content, Revision $draft)
    {
        $content->setDraft(null);

        if ($content instanceof ContentSet) {
            $content->clear();
            while ($subcontent = $draft->next()) {
                if ($subcontent instanceof AbstractClassContent) {
                    $subcontent = $this->entityManager->getRepository(ClassUtils::getRealClass($subcontent))->load($subcontent);
                    if (null !== $subcontent) {
                        $content->push($subcontent);
                    }
                }
            }
        } else {
            foreach ($draft->getData() as $key => $values) {
                $values = is_array($values) ? $values : [$values];
                foreach ($values as &$subcontent) {
                    if ($subcontent instanceof AbstractClassContent) {
                        $subcontent = $this->entityManager->getRepository(ClassUtils::getRealClass($subcontent))
                            ->load($subcontent)
                        ;
                    }
                }

                unset($subcontent);
                if (in_array('Element\Keyword', $content->getAccept()[$key])) {
                    $this->entityManager
                        ->getRepository('BackBee\ClassContent\Element\Keyword')
                        ->cleanKeywordLinks($content, $values);
                }

                $content->$key = $values;
            }
        }

        $draft->commit();
        $content->setLabel($draft->getLabel());

        foreach ($draft->getAllParams() as $key => $params) {
            $content->setParam($key, $params['value']);
        }

        $content->setRevision($draft->getRevision())
            ->setState(AbstractClassContent::STATE_NORMAL)
            ->addRevision($draft)
        ;

        return $this;
    }

    /**
     * Runs process of post commit.
     *
     * @param  AbstractClassContent $content
     * @param  Revision             $draft
     *
     * @return ClassContentManager
     */
    private function commitPostProcess(AbstractClassContent $content, Revision $draft)
    {
        $data = $draft->jsonSerialize();
        if (0 !== count($data['parameters']) && 0 !== count($data['elements'])) {
            $draft->setRevision($content->getRevision());
            $draft->setState(Revision::STATE_MODIFIED);
            $this->entityManager->persist($draft);
        }

        return $this;
    }

    /**
     * Executes revert action on content and its draft.
     *
     * @param  AbstractClassContent $content
     * @param  Revision             $draft
     * @param  array                $data
     *
     * @return ClassContentManager
     */
    private function executeRevert(AbstractClassContent $content, Revision $draft, array $data)
    {
        $content->setDraft(null);
        if ($content instanceof ContentSet) {
            if (isset($data['elements']) && true === $data['elements']) {
                $draft->clear();
                foreach ($content->getData() as $element) {
                    if ($element instanceof AbstractClassContent) {
                        $draft->push($element);
                    }
                }
            }
        } else {
            foreach ($content->getData() as $key => $element) {
                if (isset($data['elements']) && in_array($key, $data['elements'])) {
                    $draft->$key = $content->$key;
                }

                if (in_array('Element\Keyword', $content->getAccept()[$key])) {
                    $this->entityManager
                        ->getRepository('BackBee\ClassContent\Element\Keyword')
                        ->cleanKeywordLinks($content, $element);
                }
            }
        }

        if (isset($data['parameters'])) {
            foreach ($content->getDefaultParams() as $key => $params) {
                if (in_array($key, $data['parameters'])) {
                    $draft->setParam($key, $content->getParamValue($key));
                }
            }
        }

        return $this;
    }

    /**
     * Runs revert post action on content and its draft.
     *
     * @param  AbstractClassContent $content
     * @param  Revision             $draft
     *
     * @return ClassContentManager
     */
    private function revertPostProcess(AbstractClassContent $content, Revision $draft)
    {
        $data = $draft->jsonSerialize();

        if (
            0 === count($data['parameters'])
            && (
                0 === count($data['elements'])
                || (
                    $content instanceof ContentSet
                    && $data['elements']['current'] === $data['elements']['draft']
                )
            )
        ) {
            if ($content instanceof ContentSet) {
                $draft->clear();
            }

            if (AbstractClassContent::STATE_NEW === $content->getState()) {
                $classname = AbstractClassContent::getClassnameByContentType($content->getContentType());
                $this->entityManager->getRepository($classname)->deleteContent($content);
            } else {
                $this->entityManager->remove($draft);
            }
        }

        return $this;
    }
}
