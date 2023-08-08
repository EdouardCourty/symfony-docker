<?php

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route(path: '/', name: 'security.')]
class SecurityController extends AbstractController
{
    #[Route(path: 'login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('homepage.index');
        }

        return $this->render('security/login_form.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'remember_me_enabled' => true
        ]);
    }

    #[Route(path: 'logout', name: 'logout')]
    public function logout(): void
    {
        # Symfony will take care of the logout on its own.
    }
}
