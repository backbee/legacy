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

namespace BackBee\Security\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use BackBee\Security\ApiUserInterface;
use BackBee\Security\Encoder\RequestSignatureEncoder;
use BackBee\Security\Exception\SecurityException;
use BackBee\Security\Token\PublicKeyToken;

/**
 * Authentication provider for username/password firewall.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class PublicKeyAuthenticationProvider extends BBAuthenticationProvider
{

    /**
     * Role for API user
     * @var string
     */
    private $apiUserRole;

    /**
     * Class constructor.
     *
     * @param \Symfony\Component\Security\Core\User\UserProviderInterface $userProvider
     * @param string                                                      $nonceDir
     * @param int                                                         $lifetime
     * @param \BackBuillder\Bundle\Registry\Repository                    $registryRepository
     */
    public function __construct(UserProviderInterface $userProvider, $nonceDir, $lifetime = 300, $registryRepository = null, EncoderFactoryInterface $encoderFactory = null, $apiUserRole = 'ROLE_API_USER')
    {
        parent::__construct($userProvider, $nonceDir, $lifetime, $registryRepository, $encoderFactory);
        $this->apiUserRole = $apiUserRole;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        if (false === $this->supports($token)) {
            return;
        }

        $publicKey = $token->getUsername();

        if (null === $nonce = $this->readNonceValue($token->getNonce())) {
            $this->onInvalidAuthentication();
        }


        $user = $this->userProvider->loadUserByPublicKey($publicKey);

        if (null === $user) {
            $this->onInvalidAuthentication();
        }

        $token->setUser($user);
        $signature_encoder = new RequestSignatureEncoder();
        if (false === $signature_encoder->isApiSignatureValid($token, $nonce[1])) {
            $this->onInvalidAuthentication();
        }

        if (time() > $nonce[0] + $this->lifetime) {
            $this->removeNonce($token->getNonce());
            throw new SecurityException('Prior authentication expired', SecurityException::EXPIRED_AUTH);
        }

        $authenticatedToken = new PublicKeyToken($this->getRoles($user));
        $authenticatedToken
            ->setUser($user)
            ->setNonce($token->getNonce())
            ->setCreated(new \DateTime())
            ->setLifetime($this->lifetime)
        ;

        $this->writeNonceValue($authenticatedToken);

        return $authenticatedToken;
    }

    /**
     * Add API user role to authenticated token
     * @param ApiUserInterface $user
     * @return array
     */
    private function getRoles(ApiUserInterface $user)
    {
        $roles = $user->getRoles();
        if ($user->getApiKeyEnabled()) {
            $roles[] = $this->apiUserRole;
        }

        return $roles;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof PublicKeyToken;
    }

    /**
     * Throw a SecurityException with Invalid authentication informations and 401 as status code.
     */
    private function onInvalidAuthentication()
    {
        throw new SecurityException('Invalid authentication informations', SecurityException::INVALID_CREDENTIALS);
    }
}
