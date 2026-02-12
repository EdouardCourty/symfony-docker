<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthcheckController extends AbstractController
{
    #[Route(path: '/healthcheck', name: 'healthcheck', methods: Request::METHOD_GET)]
    public function __invoke(): Response
    {
        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}
