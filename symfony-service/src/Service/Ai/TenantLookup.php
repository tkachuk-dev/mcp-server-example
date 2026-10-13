<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\Tenant;
use App\Exception\TenantNotFoundException;
use App\Repository\TenantRepository;

class TenantLookup
{
    public function __construct(private readonly TenantRepository $tenants)
    {
    }

    public function getOrFail(int $tenantId): Tenant
    {
        return $this->tenants->find($tenantId) ?? throw new TenantNotFoundException('Tenant not found');
    }
}
