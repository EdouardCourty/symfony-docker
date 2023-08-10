<?php

namespace App\Register;

use App\Entity\User;

class PasswordTokenRegister extends AbstractRegister
{
    private const TAG = 'password_token';

    protected function getTag(): string
    {
        return self::TAG;
    }

    public function getTokenKey(User $user): string
    {
        return $user->getId()->toRfc4122();
    }
}
