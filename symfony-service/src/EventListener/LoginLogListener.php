<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\LoginLog;
use App\Entity\User;
use App\Enums\LoginResult;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

// Only the user-facing firewall writes the login journal: service-to-service
// requests (internal_ai firewall) must never touch it or last_login_at.
class LoginLogListener
{
    public const string USER_FIREWALL = 'main';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly ClockInterface $clock,
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (self::USER_FIREWALL !== $event->getFirewallName() || !$user instanceof User) {
            return;
        }

        $now = $this->clock->now();
        $user->setLastLoginAt($now);
        $this->entityManager->persist(new LoginLog($user->getTenant(), $user, LoginResult::Success, $now));
        $this->entityManager->flush();
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (self::USER_FIREWALL !== $event->getFirewallName()) {
            return;
        }

        $identifier = $event->getPassport()?->getBadge(UserBadge::class)?->getUserIdentifier();
        $user = null !== $identifier ? $this->users->findOneByEmail($identifier) : null;
        if (null === $user) {
            return;
        }

        $this->entityManager->persist(new LoginLog($user->getTenant(), $user, LoginResult::Failure, $this->clock->now()));
        $this->entityManager->flush();
    }
}
