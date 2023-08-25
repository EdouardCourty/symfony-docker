<?php

namespace App\Service\Security\User;

use App\Entity\User;
use App\Factory\Entity\EmailFactory;
use App\Factory\Entity\UserFactory;
use App\Repository\Doctrine\UserRepository;
use App\Repository\Redis\PasswordTokenRepository;
use App\Service\SMTP\EmailSender;
use App\Service\Trait\RandomGeneratorTrait;
use Doctrine\ORM\EntityManagerInterface;
use RedisException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PasswordReset
{
    use RandomGeneratorTrait;

    private const TOKEN_TTL = 300; // 5 minutes

    public function __construct(
        private readonly PasswordTokenRepository $passwordTokenRepository,
        private readonly UserFactory $userFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly Environment $twig,
        private readonly RouterInterface $router,
        private readonly EmailFactory $emailFactory,
        private readonly EmailSender $emailSender
    ) {
    }

    /**
     * @throws RedisException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function resetPassword(User $user): void
    {
        $token = $this->generateToken();
        $this->passwordTokenRepository->setToken($user, $token, self::TOKEN_TTL);

        $emailContent = $this->twig->render('email/password_reset.html.twig', [
            'resetLink' => $this->router->generate('password_reset.new_password', ['token' => $token])
        ]);

        $email = $this->emailFactory->create(
            'password_reset_sender@admin.com',
            'Password reset',
            $emailContent,
            [$user->getEmail()]
        );

        $this->entityManager->persist($email);
        $this->entityManager->flush();

        $this->emailSender->sendEmailAsync($email);
    }

    public function updatePassword(User $user, string $password): void
    {
        $this->userFactory->updatePassword($user, $password);
        $this->entityManager->flush();
    }

    /**
     * @throws RedisException
     */
    public function invalidateResetToken(string $token): void
    {
        $this->passwordTokenRepository->deleteToken($token);
    }

    /**
     * @throws RedisException
     */
    public function retrieveUser(string $token): ?User
    {
        $userUuid = $this->passwordTokenRepository->getUserUuid($token);

        return $userUuid
            ? $this->entityManager->find(User::class, $userUuid)
            : null;
    }
}
