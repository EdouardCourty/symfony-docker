<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\Security\User\UserSecurity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

#[AsEventListener(event: SecurityEvents::INTERACTIVE_LOGIN, method: 'onInteractiveLogin')]
class LoginEventListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserSecurity $userSecurityDirector
    ) {
    }

    /**
     * @param InteractiveLoginEvent $event
     *
     * @return void
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        /** @var User $user */
        $user = $event->getAuthenticationToken()->getUser();

        if ($user->isEnabled() === false) {
            $this->userSecurityDirector->emptyTokenStorage();

            throw new AccessDeniedException('Your account is disabled.');
        }

        $user->setLastLogin(new DateTimeImmutable());
        $this->entityManager->flush();
    }
}
