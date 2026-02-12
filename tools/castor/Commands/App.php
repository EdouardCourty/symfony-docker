<?php

declare(strict_types=1);

namespace Tools\Castor\Commands;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Tools\Castor\Enum\ProjectFolder;
use Tools\Castor\Builder\DockerCommandBuilder;

use function Castor\io;

// Cache commands
#[AsTask(description: 'Clear Symfony cache', namespace: 'cache', aliases: ['cc'])]
function clear(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console cache:clear');
}

// Database commands
#[AsTask(description: 'Reload database with fixtures', namespace: 'database')]
function reload(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:database:drop --force --if-exists');
    
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:database:create');
    
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:migrations:migrate --no-interaction');
    
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console hautelook:fixtures:load --no-interaction');
}

#[AsTask(description: 'Reload test database with fixtures', namespace: 'database')]
function reload_tests(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:database:drop --env=test --force --if-exists');
    
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:database:create --env=test');
    
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:migrations:migrate --env=test --no-interaction');
    
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console hautelook:fixtures:load --env=test --no-interaction');
}

#[AsTask(description: 'Run database migrations', namespace: 'database')]
function migrate(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:migrations:migrate --no-interaction');
}

#[AsTask(description: 'Create a new migration', namespace: 'database')]
function make_migration(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console make:migration');
}

#[AsTask(description: 'Drop database', namespace: 'database')]
function drop(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:database:drop --force');
}

#[AsTask(description: 'Create database', namespace: 'database')]
function create(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:database:create');
}

#[AsTask(description: 'Load fixtures', namespace: 'database')]
function load_fixtures(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console hautelook:fixtures:load --no-interaction');
}

// Quality commands
#[AsTask(description: 'Run PHP CS Fixer', namespace: 'app', aliases: ['app:phpcs'])]
function php_cs_fixer(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->workdir('/var/www/tools')
        ->exec('vendor/bin/php-cs-fixer fix');
}

#[AsTask(description: 'Run PHPStan static analysis', namespace: 'app')]
function phpstan(
    #[AsArgument(description: 'Additional arguments for PHPStan')]
    ?string $args = null
): void
{
    $command = 'vendor/bin/phpstan --memory-limit=-1';
    
    if ($args !== null) {
        $command .= ' ' . $args;
    }
    
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->workdir('/var/www/tools')
        ->exec($command);
}

// Test commands
#[AsTask(description: 'Run PHPUnit tests', namespace: 'app')]
function phpunit(
    #[AsArgument(description: 'Test file path (e.g., tests/Unit/SomeTest.php)')]
    ?string $path = null,
    #[AsOption(description: 'Filter tests by name')]
    ?string $filter = null
): void
{
    $command = 'vendor/bin/phpunit';
    
    if ($path !== null) {
        $command .= ' ' . $path;
    }
    
    if ($filter !== null) {
        $command .= ' --filter=' . $filter;
    }
    
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec($command);
}

// QA command
#[AsTask(description: 'Run all quality checks (phpcs, phpstan, phpunit)', namespace: 'app', aliases: ['qa'])]
function qa(): void
{
    io()->title('Running Quality Assurance checks');
    
    io()->section('1/3 - PHP CS Fixer');
    php_cs_fixer();
    
    io()->section('2/3 - PHPStan');
    phpstan(null);
    
    io()->section('3/3 - PHPUnit');
    phpunit(null, null);
    
    io()->success('All QA checks completed!');
}

// Project setup
#[AsTask(namespace: '', description: 'Install project from scratch')]
function install(
    #[AsArgument(description: 'Folder to install: app, tools, or all')]
    string $folder = 'all'
): void
{
    $projectFolder = ProjectFolder::from($folder);

    $folders = ProjectFolder::getInstallableFolders($projectFolder);

    foreach ($folders as $targetFolder) {
        io()->section(sprintf('Installing dependencies for %s', $targetFolder->value));
        
        (new DockerCommandBuilder())
            ->withAllServices()
            ->service('server')
            ->workdir($targetFolder->getPath())
            ->exec('composer install');
    }

    if ($projectFolder === ProjectFolder::APP || $projectFolder->isAll()) {
        io()->section('Setting up database');
        reload();
    }
}
