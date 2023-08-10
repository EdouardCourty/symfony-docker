<?php

namespace App\Validation\Constraint;

use App\Validation\ConstraintValidator\PasswordValidator;
use Symfony\Component\Validator\Constraint;

class Password extends Constraint
{
    public string $message = 'Your password must contain at least one number.';

    /**
     * @return string
     */
    public function validatedBy(): string
    {
        return PasswordValidator::class;
    }
}