<?php

namespace App\Controller\Administration;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(path: '', name: 'admin.')]
class AdminDashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    #[Route(path: '/admin' , name: 'index')]
    public function index(): Response
    {
        return $this->render('administration/index.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Administration dashboard')
            ->renderContentMaximized()
            ->renderSidebarMinimized(false)
            ->setTextDirection('ltr');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Shortcuts', 'fa fa-star');
        yield MenuItem::linkToUrl('Home', 'fa fa-home', $this->urlGenerator->generate('homepage.index'));

        yield MenuItem::section('Platform management');
        yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class);
    }
}
