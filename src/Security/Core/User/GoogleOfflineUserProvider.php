<?php

declare(strict_types=1);

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.en.html AGPL-3.0+
 *
 * This file is part of Team Drive Migrator, an application for migrating
 * files into Team Drives.
 * Copyright (C) 2019  Anime Critics United, Inc. <webmaster@castlepointanime.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Security\Core\User;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;

class GoogleOfflineUserProvider extends FOSUBUserProvider
{
    /** {@inheritdoc} */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        $this->updateUser($user, $response);

        return parent::connect($user, $response);
    }

    /** {@inheritdoc} */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response): UserInterface
    {
        $user = parent::loadUserByOAuthUserResponse($response);
        $this->updateUser($user, $response);

        return $user;
    }

    private function updateUser(
        UserInterface $user,
        UserResponseInterface $response
    ): void {
        if (!$this->accessor->isWritable($user, 'google_access_token') ||
                !$this->accessor->isWritable($user, 'username') ||
                !$this->accessor->isWritable($user, 'fullname') ||
                !$this->accessor->isWritable($user, 'email')) {
            throw new \RuntimeException('Object does not have the necessary fields');
        }
        if (empty($response->getOAuthToken()->getRefreshToken())) {
            throw new \RuntimeException('Token does not have refresh token');
        }

        $this->accessor->setValue(
            $user,
            'google_access_token',
            $response->getOAuthToken()->getRawToken()
        );
        $this->accessor->setValue($user, 'username', $response->getUsername());
        $this->accessor->setValue($user, 'fullname', $response->getRealName());
        $this->accessor->setValue($user, 'email', $response->getEmail());
    }
}
