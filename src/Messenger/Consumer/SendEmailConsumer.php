<?php

namespace App\Messenger\Consumer;

use App\Entity\Email;
use App\Messenger\Message\SendEmailMessage;
use App\Service\Messenger\Consumer;
use App\Service\SMTP\EmailSender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEmailConsumer extends Consumer
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly EmailSender $mailSender
    ) {
        parent::__construct($entityManager);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function __invoke(SendEmailMessage $sendEmailMessage): void
    {
        $email = $this->entityManager->find(Email::class, $sendEmailMessage->emailId);
        $this->abortIfNotFound($email, 'Email not found.');

        $this->mailSender->sendEmailSync($email);
        $email->setStatus(Email::STATUS_SENT);

        $this->entityManager->flush();
    }
}
