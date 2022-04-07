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

namespace BackBee\Rest\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use BackBee\Controller\Controller;
use BackBee\Security\Encoder\RequestSignatureEncoder;

/**
 * Test api key controller.
 *
 * @category    BackBee
 *
 *
 * @author      k.golovin
 */
class TestController extends Controller
{
    /**
     * @access public
     */
    public function generateKeyAction(Request $request)
    {
        $values = $request->request->get('generator');

        $signature = null;

        if ('POST' === $request->getMethod()) {
            $encoder = new RequestSignatureEncoder();
            $requestToBeSigned = Request::create($values['url'], $values['method']);
            $signature = $encoder->createSignature($requestToBeSigned, $values['private_key']);
        }

        return $this->render('Rest/test.html.twig', array(
            'form' => $values,
            'signature' => $signature,
        ));
    }
}
