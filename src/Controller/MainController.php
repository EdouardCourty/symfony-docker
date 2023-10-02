<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '', name: 'main.')]
class MainController extends AbstractController
{
    #[Route(path: '/redirect', name: 'redirect')]
    public function handleRedirect(Request $request): Response
    {
        $code = $request->get('code');
        $state = $request->get('state');

        dd($code, $state);
    }
}
