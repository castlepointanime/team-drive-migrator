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

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * Controller actions related to the OAuth flow that are not already handled
 * natively by the HWIOAuthBundle.
 *
 * These controllers are usually only used by other controllers, such as the
 * {@link JobsController}, to obtain permissions before performing an action.
 */
class OauthController extends AbstractController
{
    /**
     * Parses an OAuth response from the {@link Request}, stores the new token in the
     * user, and finally redirects the user back from whence they came.
     *
     * A precondition of this function is that the session contains a random XSRF token.
     * In addition, the OAuth state is expected to be a base64-encoded JSON array,
     * containing the same XSRF token, and information regarding which Symfony route to
     * redirect the user to.
     *
     * @Route("/oauth/callback", name="oauth_callback")
     */
    public function oauthCallback(
        Request $request,
        Security $security,
        EntityManagerInterface $entityManager,
        \Google_Client $googleClient,
        SessionInterface $session
    ): Response {
        if (!$request->query->get('code') || !$request->query->get('state')) {
            // TODO: Show error message to user.
            return $this->redirectToRoute('home');
        }

        $state = json_decode(
            base64_decode(strtr($request->query->get('state'), '-_,', '+/='), true),
            true
        );

        if ($state['xsrf'] !== $session->get('oauth_xsrf')) {
            // TODO: Show error message to user.
            return $this->redirectToRoute('home');
        }

        // Redirect URI must match exactly what was passed in when starting the auth flow.
        $googleClient->setRedirectUri(strtok($request->getUri(), '?'));
        $accessToken = $googleClient->fetchAccessTokenWithAuthCode(
            $request->query->get('code')
        );

        if (!$accessToken || !\array_key_exists('access_token', $accessToken)) {
            // TODO: Show error message to user.
            throw new \RuntimeException();

            return $this->redirectToRoute('home');
        }

        $user = $security->getUser();
        $user->setGoogleAccessToken($accessToken);
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute($state['routeName'], $state['routeParams']);
    }
}
