<?php

declare(strict_types=1);

namespace App\Dto\Ai\Response;

use App\Entity\SecurityEvent;

class SecurityEventItem
{
    private ?int $id = null;
    private ?string $type = null;
    private ?int $userId = null;
    private ?\DateTimeImmutable $occurredAt = null;

    public static function fromEntity(SecurityEvent $event): self
    {
        $dto = new self();
        $dto->setId($event->getId());
        $dto->setType($event->getType()->value);
        // getId() on an uninitialized proxy does not trigger a lazy load.
        $dto->setUserId($event->getUser()?->getId());
        $dto->setOccurredAt($event->getOccurredAt());

        return $dto;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    // Nullable by contract: system-level events have no user.
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setOccurredAt(?\DateTimeImmutable $occurredAt): void
    {
        $this->occurredAt = $occurredAt;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
