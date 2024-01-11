<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\UserType;
use App\Security\Authenticator\FormLoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route(path: '/', name: 'security.')]
class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly Security $security
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

    #[Route(path: '/register', name: 'register')]
    public function register(Request $request): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('homepage.index');
        }

        $user = new User();

        $registrationForm = $this->createForm(UserType::class, $user);
        $registrationForm->handleRequest($request);

        if ($registrationForm->isSubmitted() && $registrationForm->isValid()) {
            $encodedPassword = $this->userPasswordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($encodedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->security->login($user, FormLoginAuthenticator::class);
            return $this->redirectToRoute('homepage.index');
        }

        return $this->render('security/register_form.html.twig', [
            'registration_form' => $registrationForm->createView()
        ]);
    }

    #[Route(path: 'logout', name: 'logout')]
    public function logout(): void
    {
        # Symfony will take care of the logout on its own.
    }
}
