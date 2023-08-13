<?php

namespace App\Command\Support;

use App\Service\Cache\CacheSingleton;
use RedisException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:redis:flush-all',
    description: 'Flushes all the keys from Redis.'
)]
class RedisFlushAllCommand extends Command
{
    public function __construct(
        private readonly CacheSingleton $cacheSingleton
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->cacheSingleton->getInstance()->flushAll(false);
        } catch (RedisException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $io->success('Done.');

        return self::SUCCESS;
    }
}
