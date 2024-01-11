<?php

namespace App\Validation\ConstraintValidator;

use App\Validation\Constraint\SecurePassword;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class SecurePasswordConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SecurePassword) {
            throw new UnexpectedTypeException($constraint, SecurePassword::class);
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (strtolower($value) === $value || strtoupper($value) === $value || !preg_match('/\d/', $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
