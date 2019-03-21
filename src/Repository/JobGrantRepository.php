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

namespace App\Repository;

use App\Entity\JobGrant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method null|JobGrant find($id, $lockMode = null, $lockVersion = null)
 * @method null|JobGrant findOneBy(array $criteria, array $orderBy = null)
 * @method JobGrant[]    findAll()
 * @method JobGrant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JobGrantRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, JobGrant::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.email = ?1')
            ->setParameter(1, $user->getEmail())
            ->getQuery()
            ->getResult()
        ;
    }
}
