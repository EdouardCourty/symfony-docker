<?php

namespace App\Service\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

abstract class Consumer
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     * @param Uuid|string|int $identifier
     * @param bool $abortIfNotFound
     *
     * @return T|null
     */
    protected function getById(string $className, Uuid|string|int $identifier, bool $abortIfNotFound = false)
    {
        $result = $this->entityManager->getRepository($className)->find($identifier);

        if ($abortIfNotFound) {
            $this->abortIfNotFound($result);
        }

        return $result;
    }

    /**
     * @param ?object $object
     * @param string $message
     *
     * @return void
     */
    protected function abortIfNotFound(?object $object, string $message = 'Entity not found.'): void
    {
        if (empty($object)) {
            throw new UnrecoverableMessageHandlingException($message);
        }
    }
}
