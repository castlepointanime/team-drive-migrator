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

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Represents the requested (and possibly fulfilled) approval for a {@link Job} by
 * a {@link User}.
 *
 * @ORM\Entity(repositoryClass="App\Repository\JobGrantRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"job_id", "email"})})
 */
class JobGrant
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Job", inversedBy="grants")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $job;

    /**
     * The {@link User} approving the job. Note that this might be null if, at the time
     * this {@link JobGrant} was created, a {@link User} did not yet exist with the
     * given email. The user's account will be created only once they log in via OAuth
     * after clicking on the link in the approval email.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="jobGrants")
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     */
    private $user;

    /**
     * When the user was emailed requesting approval, or null if the email has yet
     * to be sent.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $emailTime;

    /**
     * When the user approved the job, or null if the user has yet to actually
     * approve.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $grantTime;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): self
    {
        $this->job = $job;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEmailTime(): ?\DateTimeInterface
    {
        return $this->emailTime;
    }

    public function setEmailTime(?\DateTimeInterface $emailTime): self
    {
        $this->emailTime = $emailTime;

        return $this;
    }

    public function getGrantTime(): ?\DateTimeInterface
    {
        return $this->grantTime;
    }

    public function setGrantTime(?\DateTimeInterface $grantTime): self
    {
        $this->grantTime = $grantTime;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
}
