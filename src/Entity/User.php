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
use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A user (should be obvious). Users can exist solely for the purpose of approving
 * jobs they are a part of, or they can be granted powers to create and run jobs
 * as well.
 *
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User extends BaseUser implements EquatableInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @Groups({"user"})
     */
    protected $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"user"})
     */
    protected $fullname;

    /**
     * @Groups({"user:write"})
     */
    protected $plainPassword;

    /**
     * @Groups({"user"})
     */
    protected $username;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $googleId;

    /**
     * OAuth access token exactly as it comes from Google, in a format that
     * {@link \Google_Client} will accept.
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private $googleAccessToken = [];

    /**
     * List of job grants the user is a part of. Note that a grant being here
     * does not mean the user has approved the job, it merely means they are
     * associated with the job and approval has been requested. The JobGrant
     * itself stores information about whether approval was granted.
     *
     * @ORM\OneToMany(targetEntity="App\Entity\JobGrant", mappedBy="user")
     */
    private $jobGrants;

    public function __construct()
    {
        $this->jobGrants = new ArrayCollection();
    }

    public function isUser(?UserInterface $user = null): bool
    {
        return $user instanceof self && $user->id === $this->id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullname(): ?string
    {
        return $this->fullname;
    }

    public function setFullname(?string $fullname): self
    {
        $this->fullname = $fullname;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getGoogleAccessToken(): ?array
    {
        return $this->googleAccessToken;
    }

    public function setGoogleAccessToken(?array $googleAccessToken): self
    {
        $this->googleAccessToken = $googleAccessToken;

        return $this;
    }

    /**
     * @return Collection|JobGrant[]
     */
    public function getJobGrants(): Collection
    {
        return $this->jobGrants;
    }

    public function addJobGrant(JobGrant $jobGrant): self
    {
        if (!$this->jobGrants->contains($jobGrant)) {
            $this->jobGrants[] = $jobGrant;
            $jobGrant->setUser($this);
        }

        return $this;
    }

    public function removeJobGrant(JobGrant $jobGrant): self
    {
        if ($this->jobGrants->contains($jobGrant)) {
            $this->jobGrants->removeElement($jobGrant);
            // set the owning side to null (unless already changed)
            if ($jobGrant->getUser() === $this) {
                $jobGrant->setUser(null);
            }
        }

        return $this;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return
            $this->getUsername() === $user->getUsername() &&
            $this->getPassword() === $user->getPassword() &&
            $this->getSalt() === $user->getSalt() &&
            $this->getId() === $user->getId();
    }
}
