<?php

namespace App\Factory\Entity;

use App\Entity\Email;
use Symfony\Component\Mime\Email as SymfonyEmail;

class EmailFactory
{
    public function create(string $from, string $subject, string $content, array $recipients): Email
    {
        $email = new Email();

        $email
            ->setSender($from)
            ->setContent($content)
            ->setRecipients($recipients)
            ->setSubject($subject)
            ->setStatus(Email::STATUS_PENDING);

        return $email;
    }
}
