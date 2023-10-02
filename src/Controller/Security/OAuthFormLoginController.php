<?php

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route(path: '/oauth/v2', name: 'security.login_oauth.')]
class OAuthFormLoginController extends AbstractController
{
    #[Route(path: '/auth_login', name: 'auth_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $lastError = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $redirectUri = $request->get('redirect_uri');

        return $this->render('security/oauth/login.html.twig', [
            'last_error' => $lastError,
            'last_username' => $lastUsername,
            'redirect_uri' => $redirectUri
        ]);
    }
}
