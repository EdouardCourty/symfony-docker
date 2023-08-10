<?php

namespace App\Validation\Constraint;

use App\Validation\ConstraintValidator\CurrentPasswordValidator;
use Symfony\Component\Validator\Constraint;

class CurrentPassword extends Constraint
{
    public string $message = 'Incorrect password.';

    public function validatedBy(): string
    {
        return CurrentPasswordValidator::class;
    }
}
