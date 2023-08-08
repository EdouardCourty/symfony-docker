<?php

namespace App\Command;

use App\Entity\User;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create',
    description: 'Creates a new user.'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserFactory $userFactory,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $io->ask('Please enter a username:');
        $password = $io->askHidden('Please enter a password:');
        $passwordConfirmation = $io->askHidden('Confirm your password:');

        if ($password !== $passwordConfirmation) {
            $io->error("Password don't match.");

            return self::INVALID;
        }

        $shouldBeAdmin = $io->ask('Should this user be an Administrator?', 'Y');

        $roles = in_array($shouldBeAdmin, ['Y', 'y'])
            ? [User::ROLE_DEFAULT, User::ROLE_ADMIN]
            : [User::ROLE_DEFAULT];

        $user = $this->userFactory->create($username, $password, $roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('User created. ID: %s', $user->getId()->toRfc4122()));

        return self::SUCCESS;
    }
}
