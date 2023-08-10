<?php

namespace App\Validation\ConstraintValidator;

use App\Service\Customer\UserPasswordDirector;
use App\Service\Security\UserSecurityDirector;
use App\Validation\Constraint\CurrentPassword;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CurrentPasswordValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserPasswordDirector $userPasswordDirector,
        private readonly UserSecurityDirector $userSecurityDirector
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CurrentPassword) {
            throw new UnexpectedTypeException($constraint, CurrentPassword::class);
        }

        $currentUser = $this->userSecurityDirector->getCurrentUser();

        if ($currentUser == null) {
            return;
        }

        if (false === $this->userPasswordDirector->isPasswordValid($currentUser, $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
