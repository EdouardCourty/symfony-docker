<?php

namespace App\Controller\Security;

use App\Repository\Doctrine\UserRepository;
use App\Service\Customer\PasswordReset;
use App\Type\EmailAddressType;
use App\Type\RepeatedPasswordType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/password-reset', name: 'password_reset.')]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordReset  $userPasswordDirector,
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route(path: '', name: 'show_form')]
    public function trigger(Request $request): Response
    {
        $emailForm = $this->createForm(EmailAddressType::class);
        $emailForm->handleRequest($request);

        $emailSent = false;

        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $email = $emailForm->getData()['email'];

            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user === null) {
                $this->addFlash('error', 'User not found.');
                return $this->redirectToRoute('password_reset.show_form');
            }

            $this->userPasswordDirector->dispatchPasswordReset($user);

            $this->addFlash('success', 'A password reset email has been sent!');
            $emailSent = true;
        }

        return $this->render('security/password_reset/show_reset_form.html.twig', [
            'form' => $emailForm->createView(),
            'email_sent' => $emailSent
        ]);
    }

    #[Route(path: '/new-password', name: 'new_password')]
    public function showResetForm(Request $request): Response
    {
        $token = $request->get('token');
        $user = $token ? $this->userPasswordDirector->retrieveUser($token) : null;

        $passwordResetForm = $this->createForm(RepeatedPasswordType::class);

        $passwordResetForm->handleRequest($request);

        if ($passwordResetForm->isSubmitted() && $passwordResetForm->isValid() && $user !== null) {
            $password = $passwordResetForm->getData()['password'];

            $this->userPasswordDirector->updatePassword($user, $password);
            $this->userPasswordDirector->invalidateToken($user);
        }

        return $this->render('security/password_reset/new_password.html.twig', [
            'user' => $user,
            'form' => $passwordResetForm->createView()
        ]);
    }
}
