<?php

namespace App\Validation\Constraint;

use App\Validation\ConstraintValidator\SecurePasswordConstraintValidator;
use Symfony\Component\Validator\Constraint;

class SecurePassword extends Constraint
{
    public string $message = 'Your password is too weak. Please use a combination of numbers and lowercase and uppercase letters.';

    public function validatedBy(): string
    {
        return SecurePasswordConstraintValidator::class;
    }
}
