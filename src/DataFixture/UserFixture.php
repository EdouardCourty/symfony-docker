<?php

namespace App\DataFixture;

use App\Entity\User;
use App\Factory\Entity\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixture extends Fixture
{
    public function __construct(
        private readonly UserFactory $userFactory
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->userFactory->create('admin', 'admin@gmail.com', 'password', [User::ROLE_ADMIN]);
        $user = $this->userFactory->create('user', 'user@gmail.com', 'password');
        $disabledUser = $this->userFactory
            ->create('disabled_user', 'disabled_user@gmail.com', 'password')
            ->setEnabled(false);

        $manager->persist($admin);
        $manager->persist($user);
        $manager->persist($disabledUser);

        $manager->flush();
    }
}
