<?php

namespace App\Register;

use App\Entity\User;

class EmailVerificationTokenRegister extends AbstractRegister
{
    private const TAG = 'email_verification';

    protected function getTag(): string
    {
        return self::TAG;
    }
}
