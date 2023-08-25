<?php

namespace App\Service\Security\User;

use App\Entity\User;
use App\Factory\Entity\EmailFactory;
use App\Repository\Doctrine\UserRepository;
use App\Repository\Redis\EmailVerificationTokenRepository;
use App\Service\SMTP\EmailSender;
use App\Service\Trait\RandomGeneratorTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class EmailVerifier
{
    private const TOKEN_TTL = 600; // 10 minutes

    use RandomGeneratorTrait;

    public function __construct(
        private readonly EmailSender $emailSender,
        private readonly RouterInterface $router,
        private readonly EmailVerificationTokenRepository $tokenRepository,
        private readonly Environment $twig,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailFactory $emailFactory
    ) {
    }

    public function startEmailVerification(User $user): void
    {
        $token = $this->generateToken();
        $this->tokenRepository->setToken($user, $token, self::TOKEN_TTL);

        $emailContent = $this->twig->render('email/email_verification.html.twig', [
            'verificationLink' => $this->router->generate('email_verification.verify', ['token' => $token])
        ]);

        $email = $this->emailFactory->create(
            'user_verify@admin.com',
            'Email address verification',
            $emailContent,
            [$user->getEmail()]
        );

        $this->entityManager->persist($email);
        $this->entityManager->flush();

        $this->emailSender->sendEmailAsync($email);
    }

    public function retrieveUser(string $token): ?User
    {
        $userUuid = $this->tokenRepository->getUserUuid($token);

        return $userUuid
            ? $this->userRepository->find($userUuid)
            : null;
    }

    public function verifyUser(User $user, string $token): void
    {
        $user->addRole(User::ROLE_EMAIL_VERIFIED);
        $this->entityManager->flush();

        $this->tokenRepository->deleteToken($token);
    }
}
