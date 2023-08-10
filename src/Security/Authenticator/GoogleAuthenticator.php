<?php

namespace App\Security\Authenticator;

use App\Entity\User;
use App\Factory\Entity\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class GoogleAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly UserFactory $userFactory
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->get('_route') === 'security.google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google_main');
        $accessToken = $this->fetchAccessToken($client);

        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);
        $factoryUser = $this->userFactory->createFromGoogleUser($googleUser);

        $existingGoogleUser = $this->entityManager->getRepository(User::class)->findOneBy([
            'googleId' => $googleUser->getId()
        ]);

        if ($existingGoogleUser) {
            return new SelfValidatingPassport(new UserBadge(
                $accessToken->getToken(),
                fn() => $existingGoogleUser
            ));
        }

        $userWithSameLogin = $this->entityManager->getRepository(User::class)->findUserByUsernameOrEmail(
            $factoryUser->getUsername(),
            $factoryUser->getEmail()
        );

        if ($userWithSameLogin !== null) {
            $this->requestStack->getSession()->getFlashBag()->add(
                'error',
                'A user already exists with this email or username. Login into your account to link your Google profile.'
            );
            throw new AccessDeniedException();
        }

        $this->entityManager->persist($factoryUser);
        $this->entityManager->flush();

        return new SelfValidatingPassport(new UserBadge(
            $accessToken->getToken(),
            fn() => $factoryUser
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->router->generate('homepage.index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->requestStack->getSession()->getFlashBag()->add('error', $exception->getMessage());

        return new RedirectResponse($this->router->generate('security.login'));
    }
}