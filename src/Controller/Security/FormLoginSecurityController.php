<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Factory\Entity\UserFactory;
use App\Security\Authenticator\FormAuthenticator;
use App\Type\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

#[Route(path: '/', name: 'security.')]
class FormLoginSecurityController extends AbstractController
{
    public function __construct(
        private readonly UserFactory $userFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly FormAuthenticator $formAuthenticator
    ) {
    }

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

    #[Route(path: 'register', name: 'register')]
    public function register(Request $request): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('homepage.index');
        }

        $form = $this->createForm(UserType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $userData */
            $userData = $form->getData();

            $user = $this->userFactory->create(
                $userData->getUsername(),
                $userData->getEmail(),
                $userData->getPassword()
            );

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->userAuthenticator->authenticateUser($user, $this->formAuthenticator, $request);

            return $this->redirectToRoute('homepage.index');
        }

        return $this->render('security/registration_form.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route(path: 'logout', name: 'logout')]
    public function logout(): void
    {
        # Symfony will take care of the logout on its own.
    }
}
