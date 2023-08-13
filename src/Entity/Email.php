<?php

namespace App\Entity;

use App\Entity\Utils\HasIdTrait;
use App\Entity\Utils\HasTimestampTrait;
use App\Repository\Doctrine\EmailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailRepository::class)]
class Email
{
    use HasIdTrait;
    use HasTimestampTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_SENT];

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Email subject cannot be blank.')]
    private string $subject;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Sender cannot be blank.')]
    private string $sender;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Content cannot be blank.')]
    private string $content;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Length(min: 1, minMessage: 'Email needs to have at least one recipient.')]
    private array $recipients;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    public function setSender(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): self
    {
        $this->recipients = $recipients;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }
}