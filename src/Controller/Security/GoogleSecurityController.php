<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Factory\Entity\UserFactory;
use App\Security\Voter\GoogleLinkVoter;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/security/google', name: 'security.google_')]
class GoogleSecurityController extends AbstractController
{
    private const GOOGLE_FLAGS = ['profile', 'email'];

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UserFactory $userFactory,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route(path: '/connect', name: 'connect')]
    public function googleRegister(): Response
    {
        return $this->clientRegistry->getClient('google_main')->redirect(self::GOOGLE_FLAGS, []);
    }

    #[IsGranted(attribute: GoogleLinkVoter::NO_GOOGLE_LINK)]
    #[Route(path: '/link', name: 'link')]
    public function googleLink(): Response
    {
        return $this->clientRegistry->getClient('google_link')->redirect(self::GOOGLE_FLAGS);
    }

    #[IsGranted(attribute: GoogleLinkVoter::NO_GOOGLE_LINK)]
    #[Route(path: '/link_check', name: 'link_check')]
    public function googleLinkCheck(): Response
    {
        $client = $this->clientRegistry->getClient('google_link');

        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUser();

        $alreadyLinkedUser = $this->entityManager->getRepository(User::class)->findOneBy([
            'googleId' => $googleUser->getId()
        ]);

        if ($alreadyLinkedUser !== null) {
            $this->addFlash('warning', 'This Google account is already linked to another user.');

            return $this->redirectToRoute('user.profile');
        }

        /** @var User $user */
        $user = $this->getUser();

        $this->userFactory->enrichUserWithGoogleId($user, $googleUser->getId());
        $this->entityManager->flush();

        $this->addFlash('success', 'Your Google account has been linked.');

        return $this->redirectToRoute('user.profile');
    }

    #[IsGranted(attribute: GoogleLinkVoter::HAS_GOOGLE_LINK)]
    #[Route(path: '/unlink', name: 'unlink')]
    public function unlink(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $user->setGoogleId(null);
        $this->entityManager->flush();

        $this->addFlash('success', 'Your Google account has been unlinked.');

        return $this->redirectToRoute('user.profile');
    }

    #[Route(path: '/check', name: 'check')]
    public function checkRegister(): void
    {
        // Placeholder for the GoogleAuthenticator
    }
}
