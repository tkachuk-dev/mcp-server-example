<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PasswordResetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasswordResetRepository::class)]
#[ORM\Table(name: 'password_resets')]
#[ORM\Index(name: 'idx_password_resets_tenant_day', columns: ['tenant_id', 'created_on'])]
class PasswordReset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $createdOn;

    public function __construct(User $user, \DateTimeImmutable $createdAt)
    {
        $this->tenant = $user->getTenant();
        $this->user = $user;
        $this->createdAt = $createdAt;
        $this->createdOn = $createdAt->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedOn(): \DateTimeImmutable
    {
        return $this->createdOn;
    }
}
