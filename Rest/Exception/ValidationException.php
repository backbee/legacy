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

namespace BackBee\Rest\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Body listener/encoder.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class ValidationException extends BadRequestHttpException
{
    /**
     * @var ConstraintViolationList
     */
    protected $violations;

    /**
     * @param ConstraintViolationList $violations
     */
    public function __construct(ConstraintViolationList $violations)
    {
        parent::__construct("Supplied data is invalid", $this);

        $this->violations = $violations;
    }

    /**
     * @return array
     */
    public function getErrorsArray()
    {
        $errors = array();

        foreach ($this->violations as $violation) {
            if (0 < strpos($violation->getPropertyPath(), '[')) {
                //parse into proper php array
                $parsedPath = [];
                parse_str($violation->getPropertyPath().'[]='.urlencode($violation->getMessage()), $parsedPath);
                $errors = array_merge_recursive($errors, $parsedPath);
            } else {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }
        }

        return $errors;
    }
}
