<?php

namespace App\DataFixture;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $userPasswordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();

        $plainPassword = 'password';

        $user
            ->setPassword($this->userPasswordHasher->hashPassword($user, $plainPassword))
            ->setUsername('admin')
            ->addRole(User::ROLE_ADMIN);

        $manager->persist($user);
        $manager->flush();
    }
}
