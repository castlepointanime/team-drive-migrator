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

use App\Entity\Job;
use App\Repository\JobGrantRepository;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * Main set of controllers for the application. Here lies everything related
 * to starting, approving, and executing jobs. The purpose of a job is to migrate
 * a folder and its hierarchy into a Team Drive, even if the source folder is
 * owned by a number of accounts.
 *
 * Here's the general process of a job:
 *
 * 1. A user grants OAuth permission for Google Drive (handled in OauthController)
 * 2. A user creates a job (handled here)
 * 3. The application queries Drive to find all users that are owners in the selected
 *    source directory (handled asynchronously in JobsFindUsersCommand)
 * 4. The user executes the job (handled here).
 * 5. The application performs the actual migration (handled asynchronously in
 *    JobsExecuteCommand).
 */
class JobsController extends AbstractController
{
    private $security;
    private $entityManager;
    private $session;
    private $googleClient;

    public function __construct(
        Security $security,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        \Google_Client $googleClient
    ) {
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->session = $session;
        $this->googleClient = $googleClient;
    }

    /**
     * Shows the user a list of current jobs associated with them, and gives them
     * a form to create a new job.
     *
     * @Route("/jobs", name="job_new")
     * @IsGranted("ROLE_RUNNER")
     */
    public function newJob(
        Request $request,
        JobRepository $jobRepository,
        JobGrantRepository $JobGrantRepository
    ): Response {
        $job = new Job();
        $form = $this->createFormBuilder($job)
            ->add('name', TextType::class)
            ->add('description', TextareaType::class)
            ->add(
                'source_picker',
                ButtonType::class,
                ['label' => 'Pick Source Folder', 'disabled' => true]
            )
            ->add(
                'destination_picker',
                ButtonType::class,
                ['label' => 'Pick Destination Team Drive', 'disabled' => true]
            )
            ->add('source_folder', HiddenType::class)
            ->add('destination_folder', HiddenType::class)
            ->add('submit', SubmitType::class)
            ->getForm()
        ;

        // User must have these OAuth scopes granted before creating a job.
        // DRIVE_FILE is needed for the Drive Picker API to work on this page.
        // DRIVE is needed later as a part of the JobsFindUsersCommand.
        $redirectUrl = $this->getOauthUrlIfNeeded(
            $request,
            \Google_Service_Drive::DRIVE,
            \Google_Service_Drive::DRIVE_FILE
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($redirectUrl) {
                // Shouldn't happen, because the page won't let the user submit the form
                // until they grant OAuth, but just in case, redirect them here.
                return $this->redirect($redirectUrl);
            }

            $job = $form->getData();
            $job->setCreateTime(new \DateTime());
            $job->setCreator($this->security->getUser());

            $this->entityManager->persist($job);
            $this->entityManager->flush();

            return $this->redirectToRoute('job_status', ['id' => $job->getId()]);
        }

        return $this->render('jobs/new.html.twig', [
            'jobs' => $jobRepository->findByCreator($this->security->getUser()),
            'grants' => $JobGrantRepository->findByUser($this->security->getUser()),
            'form' => $form->createView(),
            'newJobRedirectUrl' => $redirectUrl,
            'clientId' => $this->googleClient->getClientId(),
            'oauthToken' => $this->googleClient->getAccessToken()['access_token'],
            'developerKey' => $this->getParameter('google_key'),
        ]);
    }

    /**
     * Gives the user a form to approve a {@link Job} for their account. Usually
     * this page is accessed from an email sent to the user.
     *
     * @Route("/jobs/{id}/grant", name="job_grant")
     */
    public function grantJob(Job $job, Request $request): Response
    {
        $grant = $job->findGrantByUser($this->security->getUser());
        if (!$grant) {
            $grant = $job->findGrantByEmail($this->security->getUser()->getEmail());
            if (!$grant) {
                throw new BadRequestHttpException('User is not a part of this job.');
            }
            $grant->setUser($this->security->getUser());
            $this->entityManager->persist($grant);
            $this->entityManager->flush();
        }

        $redirectUrl = $this->getOauthUrlIfNeeded($request, \Google_Service_Drive::DRIVE);

        $form = $this->createFormBuilder($grant)
            ->add('submit', SubmitType::class, [
                // Disable is not authorized or if already granted.
                'disabled' => (bool) $redirectUrl || $grant->getGrantTime(),
            ])
            ->getForm()
        ;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Shouldn't happen, because the page won't let the user submit the form
            // until they grant OAuth, but just in case, redirect them here.
            if ($redirectUrl) {
                return $this->redirect($redirectUrl);
            }

            $grant = $form->getData();
            $grant->setGrantTime(new \DateTime());

            $this->entityManager->persist($grant);
            $this->entityManager->flush();
        }

        return $this->render('jobs/grant.html.twig', [
            'form' => $form->createView(),
            'job' => $job,
            'redirectUrl' => $redirectUrl,
        ]);
    }

    /**
     * Lets the user mark a job as ready to execute. This can only be accessed once
     * all users associated with the job have approved it. The job is not actually
     * executed in this controller; it's processed asynchronously in JobsExecuteCommand.
     *
     * @Route("/jobs/{id}/execute", name="job_execute")
     * @IsGranted("JOB_RUNNER", subject="job")
     */
    public function executeJob(Job $job): Response
    {
        $form = $this->createFormBuilder($job)
            ->add('submit', SubmitType::class)
            ->getForm()
        ;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$job->isReadyToExecute()) {
                throw new BadRequestHttpException('Not all users have approved the job yet.');
            }

            $job = $form->getData();
            $job->setExecuteTime(new \DateTime());

            $this->entityManager->persist($job);
            $this->entityManager->flush();

            return $this->redirectToRoute('job_status');
        }

        return $this->render('jobs/execute.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/jobs/{id}", name="job_status")
     * @IsGranted("JOB_RUNNER", subject="job")
     */
    public function statusJob(Job $job): Response
    {
        return $this->render('jobs/status.html.twig', ['job' => $job]);
    }

    /**
     * Checks the given requested scopes against the current granted OAuth scopes for
     * the current user, and returns null if all the scopes are already granted, and otherwise
     * returns the URL the user should be redirected to to get those grants.
     */
    private function getOauthUrlIfNeeded(Request $request, string ...$requestedScopes): ?string
    {
        $existingScopes = explode(' ', $this->googleClient->getAccessToken()['scope']);
        if (!array_diff($requestedScopes, $existingScopes)) {
            return null;
        }

        $state = sha1(openssl_random_pseudo_bytes(1024));
        $this->session->set('oauth_xsrf', $state);
        foreach ($requestedScopes as $requestedScope) {
            $this->googleClient->addScope($requestedScope);
        }
        $this->googleClient->setApprovalPrompt('force');
        $this->googleClient->setState(
            strtr(base64_encode(json_encode([
                'routeName' => $request->get('_route'),
                'routeParams' => $request->attributes->all(),
                'xsrf' => $state,
            ])), '+/=', '-_,')
        );

        return $this->googleClient->createAuthUrl();
    }
}
