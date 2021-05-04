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

namespace BackBee\Bundle;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Nicolas Dufreche <nicolas.dufreche@lp-digital.fr>
 */
abstract class AbstractAdminBundleController extends AbstractBundleController
{
    const NOTIFY_SUCCESS = 'success';
    const NOTIFY_WARNING = 'warning';
    const NOTIFY_ERROR = 'error';

    /**
     * @var array
     */
    protected $notifications = [];

    protected $error;

    /**
     * @param $method
     * @param $arguments
     * @return mixed|Response
     */
    public function __call($method, $arguments)
    {
        if (!$this->isGranted('VIEW', $this->bundle)) {
            return $this->createResponse('You must be authenticated to access', 401);
        }

        try {
            return parent::__call($method, $arguments);
        } catch (\Exception $error) {
            $this->notifyUser(self::NOTIFY_ERROR, $error->getMessage());

            $completeResponse = [
                'content' => '',
                'notification' => $this->notifications,
                'error' => [
                    'name' => get_class($error),
                    'message' => $error->getMessage(),
                    'code' => $error->getCode(),
                    'php_stack' => $error->getTraceAsString(),
                ]
            ];
            return new JsonResponse($completeResponse, 500);
        }
    }

    /**
     * Renders provided template with parameters and returns the generated string.
     *
     * @param  string     $template   the template relative path
     * @param  array|null $parameters
     * @return string
     */
    public function render($template, array $parameters = null, Response $response = null)
    {
        return parent::render($this->bundle->getBaseDirectory().DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.$template, $parameters, $response);
    }

    /**
     * Notify the user with a message
     * @param  String $type    values availble self::NOTIFY_SUCCESS, self::NOTIFY_WARNING and self::NOTIFY_ERROR
     * @param  String $message Message to the user
     */
    public function notifyUser($type, $message)
    {
        $this->notifications[] = ['type' => $type, 'message' => $message];
    }

    /**
     * @inherited
     */
    protected function decorateResponse($response, $method)
    {
        if (is_string($response)) {
            $completeResponse = [
                'content' => $response,
                'notification' => $this->notifications,
                'error' => '',
            ];
            $response =  new JsonResponse($completeResponse, 200);
        }

        if (!($response instanceof Response)) {
            throw new \InvalidArgumentException(sprintf(
                '%s must returns a string or an object instance of %s, %s given.',
                get_class($this).'::'.$method,
                'Symfony\Component\HttpFoundation\Response',
                gettype($response)
            ));
        }
        return $response;
    }
}
