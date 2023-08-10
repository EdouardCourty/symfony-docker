<?php

namespace App\Factory\Entity;

use App\Entity\User;
use Gedmo\Sluggable\Util\Urlizer;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFactory
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function create(string $username, string $email, string $password, array $roles = [User::ROLE_DEFAULT]): User
    {
        $user = new User();

        $roles = [...$roles, User::ROLE_DEFAULT];

        $password = $this->passwordHasher->hashPassword($user, $password);

        $user
            ->setUsername($username)
            ->setEmail($email)
            ->setPassword($password)
            ->setRoles(array_unique($roles));

        return $user;
    }

    public function createFromGoogleUser(GoogleUser $googleUser): User
    {
        $user = $this->create(Urlizer::urlize($googleUser->getName()) . uniqid(), $googleUser->getEmail(), uniqid());

        return $this->enrichUserWithGoogleId($user, $googleUser->getId());
    }

    public function enrichUserWithGoogleId(User $user, string $googleId): User
    {
        return $user->setGoogleId($googleId);
    }

    public function updatePassword(User $user, string $password): User
    {
        $password = $this->passwordHasher->hashPassword($user, $password);

        $user->setPassword($password);

        return $user;
    }
}
