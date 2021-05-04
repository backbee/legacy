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

namespace BackBee\Profiler\DataCollector;

use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Yaml\Dumper;

/**
 * Doctrine data collector.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin
 */
class DoctrineDataCollector extends DataCollector implements ContainerAwareInterface
{
    private $container;
    private $invalidEntityCount;
    private $connections;
    private $managers;
    private $loggers = array();

    public function __construct()
    {
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Adds the stack logger for a connection.
     *
     * @param string     $name
     * @param DebugStack $logger
     */
    public function addLogger($name, DebugStack $logger)
    {
        $this->loggers[$name] = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $queries = array();
        foreach ($this->loggers as $name => $logger) {
            $queries[$name] = $this->sanitizeQueries($name, $logger->queries);
        }

        $this->data = array(
            'queries'     => $queries,
            'connections' => $this->connections,
            'managers'    => $this->managers,
        );

        $errors = array();
        $entities = array();

        $entities['default'] = array();
        /** @var $factory \Doctrine\ORM\Mapping\ClassMetadataFactory */
        $factory = $this->container->get('em')->getMetadataFactory();
        $validator = new SchemaValidator($this->container->get('em'));

        /** @var $class \Doctrine\ORM\Mapping\ClassMetadataInfo */
        foreach ($factory->getLoadedMetadata() as $class) {
            $entities['default'][] = $class->getName();
            $classErrors = $validator->validateClass($class);

            if (!empty($classErrors)) {
                $errors['default'][$class->getName()] = $classErrors;
            }
        }

        $this->data['entities'] = $entities;
        $this->data['errors'] = $errors;
    }

    public function getManagers()
    {
        return $this->data['managers'];
    }

    public function getConnections()
    {
        return $this->data['connections'];
    }

    public function getQueryCount()
    {
        return array_sum(array_map('count', $this->data['queries']));
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $queries) {
            foreach ($queries as $query) {
                $time += $query['executionMS'];
            }
        }

        return $time;
    }

    public function getEntities()
    {
        return $this->data['entities'];
    }

    public function getMappingErrors()
    {
        return $this->data['errors'];
    }

    public function getInvalidEntityCount()
    {
        if (null === $this->invalidEntityCount) {
            $this->invalidEntityCount = array_sum(array_map('count', $this->data['errors']));
        }

        return $this->invalidEntityCount;
    }

    private function sanitizeQueries($connectionName, $queries)
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitizeQuery($connectionName, $query);
        }

        return $queries;
    }

    /**
     * @inheritDoc
     */
    private function sanitizeQuery($connectionName, $query)
    {
        $query['explainable'] = true;
        $query['params'] = (array) $query['params'];
        foreach ($query['params'] as $j => &$param) {
            if (isset($query['types'][$j])) {
                // Transform the param according to the type
                $type = $query['types'][$j];
                if (is_string($type)) {
                    $type = Type::getType($type);
                }
                if ($type instanceof Type) {
                    $query['types'][$j] = $type->getBindingType();
                    $param = $type->convertToDatabaseValue($param, $this->container->get('em')->getConnection()->getDatabasePlatform());
                }
            }

            list($param, $explainable) = $this->sanitizeParam($param);
            if (!$explainable) {
                $query['explainable'] = false;
            }
        }

        $dumper = new Dumper();

        if (count($query['params']) > 0) {
            $query['paramsString'] = $dumper->dump($query['params']);
        } else {
            $query['paramsString'] = null;
        }

        $query['sqlInterpolated'] = $this->interpolateQuery($query['sql'], $query['params']);

        return $query;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     *
     * @param mixed $var
     *
     * @return array
     */
    private function sanitizeParam($var)
    {
        if (is_object($var)) {
            return array(sprintf('Object(%s)', get_class($var)), false);
        }

        if (is_array($var)) {
            $a = array();
            $original = true;
            foreach ($var as $k => $v) {
                list($value, $orig) = $this->sanitizeParam($v);
                $original = $original && $orig;
                $a[$k] = $value;
            }

            return array($a, $original);
        }

        if (is_resource($var)) {
            return array(sprintf('Resource(%s)', get_resource_type($var)), false);
        }

        return array($var, true);
    }

    /**
     * Interpolate sql query tokens.
     *
     * @param string $query
     * @param array  $params
     *
     * @return string
     */
    private function interpolateQuery($query, array $params)
    {
        $keys = array();
        $values = $params;

        # build a regular expression for each parameter
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/:'.$key.'/';
            } else {
                $keys[] = '/[?]/';
            }

            if (is_array($value)) {
                $values[$key] = implode(',', $value);
            }

            if (is_null($value)) {
                $values[$key] = 'NULL';
            }
        }
        // Walk the array to see if we can add single-quotes to strings
        array_walk($values, create_function('&$v, $k', 'if (!is_numeric($v) && $v!="NULL") $v = "\'".$v."\'";'));

        $query = preg_replace($keys, $values, $query, 1);

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'db';
    }
}
