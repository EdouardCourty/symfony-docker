<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Security\User\PasswordReset;
use App\Type\UserPasswordResetType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/user', name: 'user.')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly PasswordReset $userPasswordDirector
    ) {
    }

    #[Route(path: '/profile', name: 'profile')]
    public function showProfile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $passwordChangeForm = $this->createForm(UserPasswordResetType::class);
        $passwordChangeForm->handleRequest($request);

        if ($passwordChangeForm->isSubmitted() && $passwordChangeForm->isValid()) {
            $newPassword = $passwordChangeForm->getData()['new_password']['password'];

            $this->userPasswordDirector->updatePassword($user, $newPassword);

            $this->addFlash('success', 'Your password has been changed.');
        }

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'password_reset_form' => $passwordChangeForm->createView()
        ]);
    }
}
