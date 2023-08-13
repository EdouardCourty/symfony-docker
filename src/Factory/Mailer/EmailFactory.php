<?php

namespace App\Factory\Mailer;

use App\Entity\Email as EmailEntity;
use Symfony\Component\Mime\Email;

class EmailFactory
{
    public function createFromEmailEntity(EmailEntity $emailEntity): Email
    {
        $email = new Email();

        $email
            ->to(...$emailEntity->getRecipients())
            ->from($emailEntity->getSender())
            ->html($emailEntity->getContent())
            ->subject($emailEntity->getSubject());

        return $email;
    }
}
