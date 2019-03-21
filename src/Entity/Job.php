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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\JobRepository")
 */
class Job
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createTime;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $confirmTime;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $executeTime;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $finishTime;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $creator;

    /**
     * @ORM\Column(type="text")
     */
    private $sourceFolder;

    /**
     * @ORM\Column(type="text")
     */
    private $destinationFolder;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $fileOwners = [];

    /**
     * @ORM\OneToMany(
     *     targetEntity="App\Entity\JobGrant",
     *     mappedBy="job",
     *     cascade={"persist"},
     *     orphanRemoval=true
     * )
     */
    private $grants;

    public function __construct()
    {
        $this->grants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreateTime(): ?\DateTimeInterface
    {
        return $this->createTime;
    }

    public function setCreateTime(\DateTimeInterface $createTime): self
    {
        $this->createTime = $createTime;

        return $this;
    }

    public function getConfirmTime(): ?\DateTimeInterface
    {
        return $this->confirmTime;
    }

    public function setConfirmTime(?\DateTimeInterface $confirmTime): self
    {
        $this->confirmTime = $confirmTime;

        return $this;
    }

    public function getExecuteTime(): ?\DateTimeInterface
    {
        return $this->executeTime;
    }

    public function setExecuteTime(?\DateTimeInterface $executeTime): self
    {
        $this->executeTime = $executeTime;

        return $this;
    }

    public function getFinishTime(): ?\DateTimeInterface
    {
        return $this->finishTime;
    }

    public function setFinishTime(?\DateTimeInterface $finishTime): self
    {
        $this->finishTime = $finishTime;

        return $this;
    }

    public function getCreator(): User
    {
        return $this->creator;
    }

    public function setCreator(User $creator): self
    {
        $this->creator = $creator;

        return $this;
    }

    public function getSourceFolder(): ?string
    {
        return $this->sourceFolder;
    }

    public function setSourceFolder(string $sourceFolder): self
    {
        $this->sourceFolder = $sourceFolder;

        return $this;
    }

    public function getDestinationFolder(): ?string
    {
        return $this->destinationFolder;
    }

    public function setDestinationFolder(string $destinationFolder): self
    {
        $this->destinationFolder = $destinationFolder;

        return $this;
    }

    public function getFileOwners(): ?array
    {
        return $this->fileOwners;
    }

    public function setFileOwners(?array $fileOwners): self
    {
        $this->fileOwners = $fileOwners;

        return $this;
    }

    public function addFileOwner(string $fileOwner): self
    {
        if (!\in_array($fileOwner, $this->fileOwners, true)) {
            $this->fileOwners[] = $fileOwner;
        }

        return $this;
    }

    /**
     * @return Collection|JobGrant[]
     */
    public function getGrants(): Collection
    {
        return $this->grants;
    }

    public function findGrantByUser(User $user): ?JobGrant
    {
        return $this->grants->matching(
            Criteria::create()
                ->where(Criteria::expr()->eq('user', $user))
                ->setMaxResults(1)
        )
            ->first() ?: null
        ;
    }

    public function findGrantByEmail(string $email): ?JobGrant
    {
        return $this->grants->matching(
            Criteria::create()
                ->where(Criteria::expr()->eq('email', $email))
                ->setMaxResults(1)
        )
            ->first() ?: null
        ;
    }

    public function addGrant(JobGrant $grant): self
    {
        if (!$this->grants->contains($grant) &&
            null === $this->findGrantByEmail($grant->getEmail())
        ) {
            $this->grants[] = $grant;
            $grant->setJob($this);
        }

        return $this;
    }

    public function removeGrant(JobGrant $grant): self
    {
        if ($this->grants->contains($grant)) {
            $this->grants->removeElement($grant);
            // set the owning side to null (unless already changed)
            if ($grant->getJob() === $this) {
                $grant->setJob(null);
            }
        }

        return $this;
    }

    public function isReadyToExecute(): bool
    {
        return $this->grants->matching(
            Criteria::create()
                ->where(Criteria::expr()->eq('grant_time', null))
        )
            ->isEmpty()
        ;
    }
}
