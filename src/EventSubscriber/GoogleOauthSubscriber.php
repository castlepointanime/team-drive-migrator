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

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class GoogleOauthSubscriber implements EventSubscriberInterface
{
    private $urlGenerator;
    private $security;
    private $googleClient;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        Security $security,
        \Google_Client $googleClient
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->security = $security;
        $this->googleClient = $googleClient;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['onKernelController', 0],
            ],
        ];
    }

    public function onKernelController(FilterControllerEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $this->googleClient->setRedirectUri(
            $this->urlGenerator->generate(
                'oauth_callback',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        );

        if ($this->security->getUser()) {
            $this->googleClient->setAccessToken(
                $this->security->getUser()->getGoogleAccessToken()
            );
            if ($this->googleClient->isAccessTokenExpired()) {
                $this->googleClient->fetchAccessTokenWithRefreshToken();
            }
        }
    }
}
