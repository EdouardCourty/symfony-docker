<?php

namespace App\Validation\ConstraintValidator;

use App\Validation\Constraint\Password;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class PasswordValidator extends ConstraintValidator
{
    /**
     * @param mixed $value
     * @param Constraint $constraint
     *
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Password) {
            throw new UnexpectedTypeException($constraint, Password::class);
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (0 === preg_match('~[0-9]+~', $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
