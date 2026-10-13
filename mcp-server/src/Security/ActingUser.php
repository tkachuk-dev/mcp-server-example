<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Email of the Google-authenticated team member, forwarded to the backends as X-Acting-User.
 */
final readonly class ActingUser
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function email(): string
    {
        $user = $this->security->getUser();
        if (null === $user) {
            throw new \LogicException('No authenticated user: MCP tools are only reachable behind the firewall.');
        }

        return $user->getUserIdentifier();
    }
}
