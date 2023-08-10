<?php

namespace App\EventListener;

use App\Service\Security\UserSecurityDirector;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onRequest')]
class RequestEventListener
{
    public function __construct(
        private readonly UserSecurityDirector $userSecurityDirector
    ) {
    }

    public function onRequest(RequestEvent $requestEvent): void
    {
        if ($requestEvent->isMainRequest() === false) {
            return;
        }

        $user = $this->userSecurityDirector->getCurrentUser();

        if ($user === null) {
            return;
        }

        if ($user->isEnabled() === false) {
            throw new AccessDeniedException('Your account is disabled.');
        }
    }
}
