<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api', name: 'api.')]
class ApiController extends AbstractController
{
    #[Route(path: '/profile', name: 'profile')]
    public function profile(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'enabled' => $user->isEnabled(),
            'created_at' => $user->getCreatedAt()->format(DATE_ATOM),
            'updated_at' => $user->getUpdatedAt()->format(DATE_ATOM)
        ]);
    }
}
