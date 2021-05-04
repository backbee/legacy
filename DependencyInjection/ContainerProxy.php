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

namespace BackBee\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use BackBee\Exception\InvalidArgumentException;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class ContainerProxy extends Container
{
    /**
     * raw definitions provided by container dump array (key: services).
     *
     * @var array
     */
    private $rawDefinitions;

    /**
     * raw definitions id provided by container dump array (keys of container dump array services).
     *
     * @var array
     */
    private $rawDefinitionsId;

    /**
     * shows if the container has been compiled before being dump or not.
     *
     * @var boolean
     */
    private $alreadyCompiled;

    /**
     * ContainerProxy's constructor;.
     *
     * @param array $containerDump the container dump from where we can restore entirely the container
     */
    public function init(array $containerDump = [])
    {
        if (0 < count($containerDump)) {
            $this->rawDefinitions = $containerDump['services'];

            if (isset($containerDump['parameters'])) {
                $this->getParameterBag()->add($containerDump['parameters']);
            }

            if (isset($containerDump['aliases'])) {
                $this->addAliases($containerDump['aliases']);
            }

            $this->alreadyCompiled = $containerDump['is_compiled'];
        } elseif ($this->hasParameter('services_dump') && $this->hasParameter('is_compiled')) {
            $this->rawDefinitions = unserialize($this->getParameter('services_dump'));
            $this->getParameterBag()->remove('services_dump');
            $this->alreadyCompiled = $this->getParameter('is_compiled');
            $this->getParameterBag()->remove('is_compiled');
        } else {
            throw new \InvalidArgumentException(
                'Unable to find services definitions in provided parameters or in current container'
            );
        }

        $this->rawDefinitionsId = array_keys($this->rawDefinitions);
        $this->is_restored = true;
    }

    /**
     * Returns boolean that determine if container has been compiled before the dump or not.
     *
     * @return boolean true if the container has been compiled before the dump, otherwise false
     */
    public function isCompiled()
    {
        return $this->alreadyCompiled;
    }

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::set
     */
    public function set($id, $service, $scope = self::SCOPE_CONTAINER)
    {
        $definition = $this->hasDefinition($id) ? $this->getDefinition($id) : null;
        if (null !== $definition && $definition->isSynthetic()) {
            foreach ($definition->getMethodCalls() as $method) {
                $arguments = [];
                foreach ($method[1] as $argument) {
                    if ($argument instanceof Reference) {
                        if (!$this->has($argument->__toString())) {
                            continue;
                        }

                        $argument = $this->get($argument->__toString());
                    }

                    $arguments[] = $argument;
                }

                call_user_func_array([$service, $method[0]], $arguments);
            }
        }

        parent::set($id, $service, $scope);
    }

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::has
     */
    public function has($id)
    {
        $this->tryLoadDefinitionFromRaw($id);

        return parent::has($id);
    }

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::getDefinition
     */
    public function getDefinition($id)
    {
        $this->tryLoadDefinitionFromRaw($id);

        return parent::getDefinition($id);
    }

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::hasDefinition
     */
    public function hasDefinition($id)
    {
        $this->tryLoadDefinitionFromRaw($id);

        return parent::hasDefinition($id);
    }

    /**
     * @see Symfony\Component\DependencyInjection\ContainerBuilder::getDefinitions
     */
    public function getDefinitions()
    {
        $this->loadRawDefinitions();

        return parent::getDefinitions();
    }

    /**
     * Try to load definition by looking for its raw definition with the provided $id.
     *
     * @param string $id the id of the service we try to load its definition
     *
     * @return null|Symfony\Component\DependencyInjection\Definition return null if no definition has been found
     *                                                               in raw definitions, else the Definition object newly build
     */
    private function tryLoadDefinitionFromRaw($id)
    {
        $definition = null;
        if (is_string($id) && is_array($this->rawDefinitionsId) && in_array($id, $this->rawDefinitionsId)) {
            $this->setDefinition($id, $definition = $this->buildDefinition($this->rawDefinitions[$id]));
            $this->rawDefinitionsId = array_flip($this->rawDefinitionsId);
            unset($this->rawDefinitionsId[$id]);
            $this->rawDefinitionsId = array_flip($this->rawDefinitionsId);
        }

        return $definition;
    }

    /**
     * Load every raw definitions and convert them into definition object.
     */
    private function loadRawDefinitions()
    {
        foreach ($this->rawDefinitionsId as $id) {
            $this->tryLoadDefinitionFromRaw($id);
        }
    }

    /**
     * Build a definition from the definition's array provided as current method parameter.
     *
     * @param array $array the raw definition's array
     *
     * @return Symfony\Component\DependencyInjection\Definition the definition object
     */
    private function buildDefinition(array $array)
    {
        $definition = null;
        if (isset($array['parent'])) {
            $definition = new DefinitionDecorator($array['parent']);
        } else {
            $definition = new Definition();
        }

        if (isset($array['synthetic'])) {
            $definition->setSynthetic($array['synthetic']);
        }

        $this->setDefinitionClass($definition, $array);
        $this->setDefinitionArguments($definition, $array);
        $this->setDefinitionFactory($definition, $array);
        $this->setDefinitionTags($definition, $array);
        $this->setDefinitionMethodCalls($definition, $array);
        $this->setDefinitionProperties($definition, $array);
        $this->setDefinitionConfigurator($definition, $array);

        return $definition;
    }

    /**
     * Set the definition class into definition object if it exists.
     *
     * @param Definition $definition definition object to hydrate
     * @param array      $array      raw definition datas
     */
    private function setDefinitionClass(Definition $definition, array $array)
    {
        if (true === array_key_exists('class', $array)) {
            $definition->setClass($array['class']);
        }
    }

    /**
     * Set the definition arguments into definition object if it exists.
     *
     * @param Definition $definition definition object to hydrate
     * @param array      $array      raw definition datas
     */
    private function setDefinitionArguments(Definition $definition, array $array)
    {
        if (true === array_key_exists('arguments', $array)) {
            foreach ($array['arguments'] as $arg) {
                $definition->addArgument($this->convertArgument($arg));
            }
        }
    }

    /**
     * Set the definition factory into definition object if it exists.
     *
     * @param Definition $definition definition object to hydrate
     * @param array      $array      raw definition datas
     */
    private function setDefinitionFactory(Definition $definition, array $array)
    {
        if (isset($array['factory'])) {
            $factory = [];
            foreach ($array['factory'] as $argument) {
                $factory[] = $this->convertArgument($argument);
            }

            $definition->setFactory($factory);
        }
    }

    /**
     * Converts a service string id into Reference if needed.
     *
     * @param mixed $argument the argument we may convert
     *
     * @return mixed the argument which may be converted
     */
    private function convertArgument($argument)
    {
        if (is_string($argument) && 0 < strlen($argument) && '@' === $argument[0]) {
            $argument = new Reference(substr($argument, 1));
        }

        return $argument;
    }

    /**
     * Set the definition tags into definition object if it exists.
     *
     * @param Definition $definition definition object to hydrate
     * @param array      $array      raw definition datas
     */
    private function setDefinitionTags(Definition $definition, array $array)
    {
        if (isset($array['tags'])) {
            if (!is_array($array['tags'])) {
                throw new InvalidArgumentException(sprintf('Parameter "tags" must be an array for service "%s".', $id));
            }

            foreach ($array['tags'] as $tag) {
                if (!isset($tag['name'])) {
                    throw new InvalidArgumentException(sprintf(
                        'A "tags" entry is missing a "name" key for service "%s".',
                        $id
                    ));
                }

                $name = $tag['name'];
                unset($tag['name']);

                foreach ($tag as $attribute => $value) {
                    if (!is_scalar($value)) {
                        throw new InvalidArgumentException(sprintf(
                            'A "tags" attribute must be of a scalar-type for service "%s", tag "%s".',
                            $id,
                            $name
                        ));
                    }
                }

                $definition->addTag($name, $tag);
            }
        }
    }

    /**
     * Set the definition method calls into definition object if it exists.
     *
     * @param Definition $definition definition object to hydrate
     * @param array      $array      raw definition datas
     */
    private function setDefinitionMethodCalls(Definition $definition, array $array)
    {
        if (isset($array['calls'])) {
            foreach ($array['calls'] as $call) {
                $args = [];
                if (isset($call[1])) {
                    foreach ($call[1] as $arg) {
                        $args[] = $this->convertArgument($arg);
                    }
                }

                $definition->addMethodCall($call[0], $args);
            }
        }
    }

    /**
     * Set the definition configurator into definition object if it exists.
     *
     * @param Definition $definition definition object to hydrate
     * @param array      $array      raw definition datas
     */
    private function setDefinitionConfigurator(Definition $definition, array $array)
    {
        if (isset($array['configurator'])) {
            $configurator = $array['configurator'];
            if (is_array($configurator)) {
                $configurator[0] = $this->convertArgument($configurator[0]);
            }

            $definition->setConfigurator($configurator);
        }
    }

    /**
     * Set the definition property (public/abstract/scope/file) into definition object if it exists.
     *
     * @param Definition $definition definition object to hydrate
     * @param array      $array      raw definition datas
     */
    private function setDefinitionProperties(Definition $definition, array $array)
    {
        if (isset($array['public'])) {
            $definition->setPublic($array['public']);
        }

        if (isset($array['abstract'])) {
            $definition->setAbstract($array['abstract']);
        }

        if (isset($array['scope'])) {
            $definition->setScope($array['scope']);
        }

        if (isset($array['file'])) {
            $definition->setFile($array['file']);
        }
    }
}
