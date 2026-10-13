<?php

declare(strict_types=1);

namespace App\Dto\Ai\Response;

class SecurityEventsResponse
{
    private ?int $tenantId = null;
    private ?int $limit = null;
    private ?\DateTimeImmutable $generatedAt = null;

    /** @var list<SecurityEventItem>|null */
    private ?array $events = null;

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function setLimit(?int $limit): void
    {
        $this->limit = $limit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setGeneratedAt(?\DateTimeImmutable $generatedAt): void
    {
        $this->generatedAt = $generatedAt;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /**
     * @param list<SecurityEventItem>|null $events
     */
    public function setEvents(?array $events): void
    {
        $this->events = $events;
    }

    /**
     * @return list<SecurityEventItem>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
