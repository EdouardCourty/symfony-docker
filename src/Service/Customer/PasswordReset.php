<?php

namespace App\Service\Customer;

use App\Entity\User;
use App\Factory\Entity\EmailFactory;
use App\Factory\Entity\UserFactory;
use App\Messenger\Message\SendEmailMessage;
use App\Repository\Doctrine\UserRepository;
use App\Repository\Redis\PasswordTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use RedisException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\VarDumper\Exception\ThrowingCasterException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PasswordReset
{
    private const TOKEN_TTL = 300; // 5 minutes

    public function __construct(
        private readonly PasswordTokenRepository $passwordTokenRepository,
        private readonly UserRepository $userRepository,
        private readonly UserFactory $userFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly Environment $twig,
        private readonly RouterInterface $router,
        private readonly EmailFactory $emailFactory
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

        $sendEmailMessage = new SendEmailMessage();
        $sendEmailMessage->emailId = $email->getId();

        $this->messageBus->dispatch($sendEmailMessage);
    }

    public function updatePassword(User $user, string $password): void
    {
        $this->userFactory->updatePassword($user, $password);
        $this->entityManager->flush();
    }

    /**
     * @throws RedisException
     */
    public function invalidateResetToken(User $user): void
    {
        $this->passwordTokenRepository->deleteToken($user);
    }

    /**
     * @throws RedisException
     */
    public function retrieveUser(string $token): ?User
    {
        $userUuid = $this->passwordTokenRepository->getUserUuid($token);

        return $userUuid
            ? $this->userRepository->find($userUuid)
            : null;
    }

    private function generateToken(): string
    {
        return md5(uniqid() . '_' . microtime());
    }
}
