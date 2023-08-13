<?php

namespace App\Service\SMTP;

use App\Entity\Email;
use App\Factory\Mailer\EmailFactory;
use App\Messenger\Message\SendEmailMessage;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MailSender
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MessageBusInterface $messageBus,
        private readonly EmailFactory $emailFactory
    ) {
    }

    /**
     * @param Email $email
     *
     * @throws TransportExceptionInterface
     */
    public function sendEmailSync(Email $email): void
    {
        $symfonyEmail = $this->emailFactory->createFromEmailEntity($email);
        $this->mailer->send($symfonyEmail);
    }

    public function sendEmailAsync(Email $email): void
    {
        $message = new SendEmailMessage();
        $message->emailId = $email->getId();

        $this->messageBus->dispatch($message);
    }
}
