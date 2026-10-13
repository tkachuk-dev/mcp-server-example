<?php

declare(strict_types=1);

namespace App\Dto\Ai\Response;

use App\Enums\Role;

class RoleUsersCount
{
    private ?int $role = null;
    private ?string $roleName = null;
    private ?int $usersCount = null;

    public static function fromRole(Role $role, int $usersCount): self
    {
        $dto = new self();
        $dto->setRole($role->value);
        $dto->setRoleName($role->securityRole());
        $dto->setUsersCount($usersCount);

        return $dto;
    }

    public function setRole(?int $role): void
    {
        $this->role = $role;
    }

    public function getRole(): int
    {
        return $this->role;
    }

    public function setRoleName(?string $roleName): void
    {
        $this->roleName = $roleName;
    }

    public function getRoleName(): string
    {
        return $this->roleName;
    }

    public function setUsersCount(?int $usersCount): void
    {
        $this->usersCount = $usersCount;
    }

    public function getUsersCount(): int
    {
        return $this->usersCount;
    }
}
