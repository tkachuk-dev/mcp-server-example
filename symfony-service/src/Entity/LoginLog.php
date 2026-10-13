<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enums\LoginResult;
use App\Repository\LoginLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// IP and user agent are deliberately not stored: the log only feeds aggregates.
#[ORM\Entity(repositoryClass: LoginLogRepository::class)]
#[ORM\Table(name: 'login_logs')]
#[ORM\Index(name: 'idx_login_logs_tenant_day', columns: ['tenant_id', 'created_on'])]
class LoginLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(type: Types::SMALLINT, enumType: LoginResult::class)]
    private LoginResult $result;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // Denormalized UTC day: lets repositories GROUP BY day without platform-specific SQL functions.
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $createdOn;

    public function __construct(Tenant $tenant, ?User $user, LoginResult $result, \DateTimeImmutable $createdAt)
    {
        $this->tenant = $tenant;
        $this->user = $user;
        $this->result = $result;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getResult(): LoginResult
    {
        return $this->result;
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
