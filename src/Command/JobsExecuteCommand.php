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

use App\Entity\Job;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class JobsExecuteCommand extends Command
{
    protected static $defaultName = 'app:jobs:execute';

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
        $this->setDescription('Execute all jobs that are ready to be executed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $jobs = $this->jobRepository->findReadyToExecute();

        foreach ($jobs as $job) {
            $this->googleClient->setAccessToken(
                $job->getCreator()->getGoogleAccessToken()
            );
            $this->addUsersAsOrganizer($job->getDestinationFolder(), $job->getGrants);

            foreach ($job->getGrants() as $grant) {
                $this->googleClient->setAccessToken(
                    $grant->getUser()->getGoogleAccessToken()
                );
                $this->migrateFiles($job, $grant);
            }

            $job->setFinishTime(new \DateTime());
            $this->entityManager->persist($job);
        }

        $io->success('');
    }

    private function addUsersAsOrganizer(string $destinationFolder, array $jobGrants): void
    {
        $results = [];
        foreach (array_chunk($grants, 100) as $grantsChunk) {
            $batch = $this->googleDriveClient->createBatch();
            foreach ($grantsChunk as $grant) {
                $request = $this->googleDriveClient->permissions->create(
                    $destinationFolder,
                    (new \Google_Service_Drive_Permission())
                        ->setRole('organizer')
                        ->setType('user')
                        ->setEmailAddress(),
                    [
                        'supportsTeamDrives' => true,
                        'sendNotificationEmail' => false,
                    ]
                );
                $batch->add($request, 'user');
            }
            $results += $batch->execute();
        }

        foreach ($results as $result) {
            if ($result instanceof Google_Service_Exception) {
                throw $result;
            }
        }
    }

    private function migrateFiles(Job $job, JobGrant $grant): void
    {
        $sourceFolder = $job->getSourceFolder();
        $destinationFolder = $job->getDestinationFolder();

        $emailAddress = $grant->getEmail();
        $query = [
            'q' => "'${sourceFolder}' in parents and ".
                "'${emailAddress}' in owners and ".
                'trashed != true',
            'fields' => 'nextPageToken,files(id,ownedByMe,parents)',
            'supportsTeamDrives' => true,
        ];

        $results = [];
        $files = null;
        do {
            if ($files) {
                $query['pageToken'] = $files->getNextPageToken();
            }
            $files = $this->googleDriveClient->files->listFiles($query)->execute();
            $results += $this->migrateFilesBatch($destinationFolder, $files);
        } while ($files->getNextPageToken());

        foreach ($results as $result) {
            if ($result instanceof Google_Service_Exception) {
                throw $result;
            }
        }
    }

    private function migrateFilesBatch($destinationFolder, $files)
    {
        $this->googleClient->setDefer(true);
        $batch = $this->googleDriveClient->createBatch();

        foreach ($files->getFiles() as $file) {
            if (!$file->getOwnedByMe()) {
                continue;
            }
            $request = $this->googleDriveClient->files->update(
                $file->getId(),
                null,
                [
                    'addParents' => $destinationFolder,
                    'removeParents' => implode(',', $file->getParents()),
                    'supportsTeamDrives' => true,
                ]
            );
            $batch->add($request);
        }

        $result = $batch->execute();
        $this->googleClient->setDefer(false);

        return $result;
    }
}
