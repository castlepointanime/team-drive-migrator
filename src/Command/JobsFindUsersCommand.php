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

namespace App\Command;

use App\Entity\JobGrant;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class JobsFindUsersCommand extends Command
{
    protected static $defaultName = 'app:jobs:find-users';

    private $googleClient;
    private $googleDriveClient;
    private $jobRepository;
    private $entityManager;

    public function __construct(
        JobRepository $jobRepository,
        \Google_Client $googleClient,
        \Google_Service_Drive $googleDriveClient,
        EntityManagerInterface $entityManager
    ) {
        $this->jobRepository = $jobRepository;
        $this->googleClient = $googleClient;
        $this->googleDriveClient = $googleDriveClient;
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Create and email the list of users needed for all jobs that need it'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $jobs = $this->jobRepository->findUnconfirmed();

        foreach ($jobs as $job) {
            $this->googleClient->setAccessToken(
                $job->getCreator()->getGoogleAccessToken()
            );

            $owners = $this->findOwners($job->getSourceFolder());
            foreach ($owners as $owner) {
                $job->addGrant((new JobGrant())->setEmail($owner));
            }
            $job->setConfirmTime(new \DateTime());

            $this->entityManager->persist($job);
        }
        $this->entityManager->flush();

        $io->success('Successfully processed '.\count($jobs).' jobs');
    }

    private function findOwners(string $sourceFolder): array
    {
        $query = [
            'q' => "'${sourceFolder}' in parents and ".
                'mimeType != \'application/vnd.google-apps.folder\' and '.
                'trashed != true',
            'fields' => 'nextPageToken,files/owners/emailAddress',
            'supportsTeamDrives' => true,
        ];

        $owners = [];
        $files = null;
        do {
            if ($files) {
                $query['pageToken'] = $files->getNextPageToken();
            }
            $files = $this->googleDriveClient->files->listFiles($query);

            foreach ($files->getFiles() as $file) {
                foreach ($file->getOwners() as $owner) {
                    if (!\in_array($owner->getEmailAddress(), $owners, true)) {
                        $owners[] = $owner->getEmailAddress();
                    }
                }
            }
        } while ($files->getNextPageToken());

        return $owners;
    }
}
