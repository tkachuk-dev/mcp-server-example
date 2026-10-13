<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enums\SecurityEventType;
use App\Repository\SecurityEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecurityEventRepository::class)]
#[ORM\Table(name: 'security_events')]
#[ORM\Index(name: 'idx_security_events_tenant_time', columns: ['tenant_id', 'occurred_at'])]
class SecurityEvent
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

    #[ORM\Column(length: 32, enumType: SecurityEventType::class)]
    private SecurityEventType $type;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    public function __construct(Tenant $tenant, ?User $user, SecurityEventType $type, \DateTimeImmutable $occurredAt)
    {
        $this->tenant = $tenant;
        $this->user = $user;
        $this->type = $type;
        $this->occurredAt = $occurredAt;
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

    public function getType(): SecurityEventType
    {
        return $this->type;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
