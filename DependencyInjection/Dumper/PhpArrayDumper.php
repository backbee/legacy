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

namespace BackBee\DependencyInjection\Dumper;

use BackBee\DependencyInjection\Exception\InvalidServiceProxyException;
use BackBee\DependencyInjection\Exception\ServiceNotDumpableException;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Dumper\DumperInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * PhpArrayDumper allow us to dump any container which implements ContainerInterface into
 * php array format.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class PhpArrayDumper implements DumperInterface
{
    const RESTORABLE_SERVICE_INTERFACE = 'BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface';

    /**
     * container we want to dump to php array format.
     *
     * @var BackBee\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * PhpArrayDumper's constructor;.
     *
     * @param ContainerInterface $container the container we want to dump
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Dumps the service container.
     *
     * @param array $options An array of options
     *
     * @return string The representation of the service container
     */
    public function dump(array $options = [])
    {
        $compiled = false;
        if (isset($options['do_compile']) && true === $options['do_compile']) {
            $this->container->compile();
            $compiled = true;
        }

        $dumper = [
            'parameters'    => $this->dumpContainerParameters($options),
            'services'      => $this->dumpContainerDefinitions($options),
            'aliases'       => $this->dumpContainerAliases($options),
            'is_compiled'   => $compiled,
        ];

        return serialize($dumper);
    }

    /**
     * Dumps every parameters of current container into an array and returns it.
     *
     * @param array $options
     *
     * @return array contains all parameters of current container
     */
    private function dumpContainerParameters(array $options)
    {
        return $this->container->getParameterBag()->all();
    }

    /**
     * Dumps every container definitions into array.
     *
     * @param array $options
     *
     * @return array contains every container definitions converted to array
     */
    private function dumpContainerDefinitions(array $options)
    {
        $definitions = [];
        foreach ($this->container->getDefinitions() as $key => $definition) {
            $definitions[$key] = $this->convertDefinitionToPhpArray($definition);
            $this->tryHydrateDefinitionForRestoration($key, $definition, $definitions[$key]);
        }

        return $definitions;
    }

    /**
     * Dumps every container aliases into array.
     *
     * @param array $options
     *
     * @return array contains container aliases
     */
    private function dumpContainerAliases(array $options)
    {
        $aliases = [];
        foreach ($this->container->getAliases() as $id => $alias) {
            $aliases[$id] = $alias->__toString();
        }

        return $aliases;
    }

    /**
     * Convert a single definition entity into array.
     *
     * @param Definition $definition the definition to convert
     *
     * @return array the definition converted into array
     */
    private function convertDefinitionToPhpArray(Definition $definition)
    {
        $definitionArray = [];
        if ($definition->isSynthetic()) {
            $definitionArray = $this->convertSyntheticDefinitionToPhpArray($definition);
        }

        $this->hydrateDefinitionClass($definition, $definitionArray);
        $this->hydrateDefinitionArguments($definition, $definitionArray);
        $this->hydrateDefinitionFactory($definition, $definitionArray);
        $this->hydrateDefinitionTags($definition, $definitionArray);
        $this->hydrateDefinitionMethodCalls($definition, $definitionArray);
        $this->hydrateDefinitionConfigurator($definition, $definitionArray);
        $this->hydrateDefinitionParent($definition, $definitionArray);
        $this->hydrateDefinitionScopeProperty($definition, $definitionArray);
        $this->hydrateDefinitionPublicProperty($definition, $definitionArray);
        $this->hydrateDefinitionAbstractProperty($definition, $definitionArray);
        $this->hydrateDefinitionFileProperty($definition, $definitionArray);

        return $definitionArray;
    }

    /**
     * Convert a synthetic definition entity into a synthetic definition array.
     *
     * @param Definition $definition the definition to convert
     *
     * @return array the synthetic definition array
     */
    private function convertSyntheticDefinitionToPhpArray(Definition $definition)
    {
        return ['synthetic' => true];
    }

    /**
     * Try to hydrate definition class from entity into definition array.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionClass(Definition $definition, array &$definitionArray)
    {
        if (null !== $definition->getClass()) {
            $definitionArray['class'] = $definition->getClass();
        }
    }

    /**
     * Try to hydrate definition arguments from entity into definition array.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionArguments(Definition $definition, array &$definitionArray)
    {
        foreach ($definition->getArguments() as $arg) {
            $definitionArray['arguments'][] = $this->convertArgument($arg);
        }
    }

    /**
     * Try to hydrate definition factory from entity into definition array.
     *
     * @param  Definition $definition
     * @param  array      &$definitionArray
     * @return [type]
     */
    private function hydrateDefinitionFactory(Definition $definition, array &$definitionArray)
    {
        if (null !== $definition->getFactory()) {
            foreach ($definition->getFactory() as $argument) {
                $definitionArray['factory'][] = $this->convertArgument($argument);
            }
        }
    }

    /**
     * Converts object into string and returns it; if it's a string or a boolean, this method
     * won't do anything; it only converts Symfony\Component\DependencyInjection\Reference into
     * string.
     *
     * @param mixed $argument the argument we may do conversion
     *
     * @return boolean|string the argument in acceptable type
     */
    private function convertArgument($argument)
    {
        return $argument instanceof Reference ? '@'.$argument->__toString() : $argument;
    }

    /**
     * Hydrate definition tags from entity into definition array.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionTags(Definition $definition, array &$definitionArray)
    {
        foreach ($definition->getTags() as $key => $tag) {
            $definitionTag = [
                'name' => $key,
            ];

            foreach (array_shift($tag) as $key => $option) {
                $definitionTag[$key] = $option;
            }

            $definitionArray['tags'][] = $definitionTag;
        }
    }

    /**
     * Hydrate definition array method calls with definition entity.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionMethodCalls(Definition $definition, array &$definitionArray)
    {
        foreach ($definition->getMethodCalls() as $methodToCall) {
            $method_call_array = [];

            // retrieving method to call name
            $method_name = array_shift($methodToCall);
            $method_call_array[] = $method_name;

            // retrieving method to call arguments
            $methodArgs = [];
            foreach (array_shift($methodToCall) as $arg) {
                $methodArgs[] = $this->convertArgument($arg);
            }

            $method_call_array[] = $methodArgs;

            // finally add method call to definition array
            $definitionArray['calls'][] = $method_call_array;
        }
    }

    /**
     * Try to hydrate definition array method calls with definition entity.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionConfigurator(Definition $definition, array &$definitionArray)
    {
        if (null !== $configurator = $definition->getConfigurator()) {
            if (is_string($configurator)) {
                $definitionArray['configurator'] = $definition->getConfigurator();
            } else {
                $definitionArray['configurator'] = [$this->convertArgument($configurator[0]), $configurator[1]];
            }
        }
    }

    /**
     * Try to hydrate definition array method calls with definition entity.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionParent(Definition $definition, array &$definitionArray)
    {
        if ($definition instanceof DefinitionDecorator) {
            $definitionArray['parent'] = $definition->getParent();
        }
    }

    /**
     * Try to hydrate definition scope property from entity into definition array.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionScopeProperty(Definition $definition, array &$definitionArray)
    {
        if (ContainerInterface::SCOPE_CONTAINER !== $definition->getScope()) {
            $definitionArray['scope'] = $definition->getScope();
        }
    }

    /**
     * Try to hydrate definition public property from entity into definition array.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionPublicProperty(Definition $definition, array &$definitionArray)
    {
        if (!$definition->isPublic()) {
            $definitionArray['public'] = false;
        }
    }

    /**
     * Try to hydrate definition abstract property from entity into definition array.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionAbstractProperty(Definition $definition, array &$definitionArray)
    {
        if ($definition->isAbstract()) {
            $definitionArray['abstract'] = true;
        }
    }

    /**
     * Try to hydrate definition file property from entity into definition array.
     *
     * @param Definition $definition       the definition to convert
     * @param array      $definitionArray the definition array (passed by reference)
     */
    private function hydrateDefinitionFileProperty(Definition $definition, array &$definitionArray)
    {
        if (null !== $definition->getFile()) {
            $definitionArray['file'] = $definition->getFile();
        }
    }

    /**
     * @param string     $id
     * @param Definition $definition
     * @param array      $definitionArray
     */
    private function tryHydrateDefinitionForRestoration($id, Definition $definition, array &$definitionArray)
    {
        if ($this->container->isLoaded($id) && $definition->hasTag('dumpable')) {
            $service = $this->container->get($id);
            if (!($service instanceof DumpableServiceInterface)) {
                throw new ServiceNotDumpableException(
                    $id,
                    get_class($service)
                );
            }

            $classProxy = $service->getClassProxy() ?: get_class($service);
            if (!in_array(self::RESTORABLE_SERVICE_INTERFACE, class_implements($classProxy))) {
                throw new InvalidServiceProxyException($classProxy);
            }

            if (isset($definitionArray['class'])) {
                if ($classProxy !== $definitionArray['class']) {
                    unset($definitionArray['arguments']);
                }

                $definitionArray['class'] = $classProxy;
            }

            unset($definitionArray['configurator']);
            $definitionArray['calls'] = [];
            $definitionArray['calls'][] = [
                'restore',
                [
                    '@service_container',
                    $service->dump(),
                ]
            ];
        }
    }
}
