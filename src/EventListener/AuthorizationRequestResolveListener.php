<?php

namespace App\EventListener;

use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

#[AsEventListener(
    event: OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE,
    method: 'onAuthorizationRequestResolve'
)]
final readonly class AuthorizationRequestResolveListener
{
    public function __construct(
        private Security $security,
        private RouterInterface $router
    ) {
    }

    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $event->setUser($user);
            $event->resolveAuthorization(true);

            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('security.login_oauth.auth_login', [
            'redirect_uri' => $this->router->generate('oauth2_authorize', [
                'client_id' => $event->getClient()->getIdentifier(),
                'response_type' => 'code',
                'state' => $event->getState(),
                'redirect_uri' => $event->getRedirectUri()
            ])
        ])));
    }
}
