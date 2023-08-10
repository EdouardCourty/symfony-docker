<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GoogleLinkVoter extends Voter
{
    public const NO_GOOGLE_LINK = 'no_google_link';
    public const HAS_GOOGLE_LINK = 'has_google_link';

    public const ATTRIBUTES = [
        self::NO_GOOGLE_LINK,
        self::HAS_GOOGLE_LINK
    ];

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $token->getUser();

        if ($user === null) {
            return false;
        }

        return match($attribute) {
            self::NO_GOOGLE_LINK => $this->handleNoLink($user),
            self::HAS_GOOGLE_LINK => $this->handleLink($user)
        };
    }

    private function handleLink(User $user): bool
    {
        if ($user->getGoogleId() === null) {
            $this->addFlash('warning', 'Your account is not linked to Google.');

            return false;
        }

        return true;
    }

    private function handleNoLink(User $user): bool
    {
        if ($user->getGoogleId() !== null) {
            $this->addFlash('warning', 'Your account is already linked to Google.');
            return false;
        }

        return true;
    }

    private function addFlash(string $type, string $message): void
    {
        $this->requestStack->getSession()->getFlashBag()->add($type, $message);
    }
}
