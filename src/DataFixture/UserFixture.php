<?php

namespace App\DataFixture;

use App\Entity\User;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixture extends Fixture
{
    public function __construct(
        private UserFactory $userFactory
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->userFactory->create('admin', 'password', [User::ROLE_ADMIN]);
        $user = $this->userFactory->create('user', 'password');

        $manager->persist($admin);
        $manager->persist($user);
        $manager->flush();
    }
}
