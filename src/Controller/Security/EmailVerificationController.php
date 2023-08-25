<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Service\Security\User\EmailVerifier;
use App\Type\EmailVerificationType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/email-verification', name: 'email_verification.')]
class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier
    ) {
    }

    #[Route(path: '/send-email', name: 'show_form')]
    public function sendEmail(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->emailVerifier->startEmailVerification($user);

        return new Response();
    }

    #[Route(path: '/verify', name: 'verify')]
    public function verify(Request $request): Response
    {
        $token = $request->get('token');
        $user = $token ? $this->emailVerifier->retrieveUser($token) : null;

        $emailVerificationForm = $this->createForm(EmailVerificationType::class);

        $emailVerificationForm->handleRequest($request);

        if ($emailVerificationForm->isSubmitted() && $emailVerificationForm->isValid()) {
            $this->emailVerifier->verifyUser($user, $token);

            return $this->redirectToRoute('user.profile');
        }

        return $this->render('security/email_verification/email_verification.html.twig', [
            'form' => $emailVerificationForm->createView()
        ]);
    }
}
