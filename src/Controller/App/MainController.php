<?php

namespace App\Controller\App;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route(path: '/', name: 'homepage')]
    public function homepageAction(): JsonResponse
    {
        $user = new User();
        $user
            ->setUsername('username')
            ->setPassword('plainPassword')
            ->addRole(User::ROLE_ADMIN);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Hello, world!',
            'timestamp' => (new DateTime('now'))->getTimestamp()
        ]);
    }
}
