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
#[AsTask(namespace: 'cache', description: 'Clear Symfony cache', aliases: ['cc'])]
function clear(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console cache:clear');
}

// Database commands
#[AsTask(namespace: 'database', description: 'Reload database with fixtures')]
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

#[AsTask(namespace: 'database', description: 'Reload test database with fixtures')]
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

#[AsTask(namespace: 'database', description: 'Run database migrations')]
function migrate(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:migrations:migrate --no-interaction');
}

#[AsTask(namespace: 'database', description: 'Create a new migration')]
function make_migration(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console make:migration');
}

#[AsTask(namespace: 'database', description: 'Drop database')]
function drop(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:database:drop --force');
}

#[AsTask(namespace: 'database', description: 'Create database')]
function create(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console doctrine:database:create');
}

#[AsTask(namespace: 'database', description: 'Load fixtures')]
function load_fixtures(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console hautelook:fixtures:load --no-interaction');
}

// Quality commands
#[AsTask(namespace: 'app', description: 'Run PHP CS Fixer', aliases: ['app:phpcs'])]
function php_cs_fixer(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->workdir('/var/www/tools')
        ->exec('vendor/bin/php-cs-fixer fix');
}

#[AsTask(namespace: 'app', description: 'Run PHPStan static analysis')]
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
#[AsTask(namespace: 'app', description: 'Run PHPUnit tests')]
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
#[AsTask(namespace: 'app', description: 'Run all quality checks (phpcs, phpstan, phpunit)', aliases: ['qa'])]
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

// AI Mate commands
#[AsTask(namespace: 'mate', description: 'Setup MCP configuration file with absolute project path')]
function setup(): void
{
    $projectRoot = getcwd();
    $exampleFile = $projectRoot . '/mcp.json.example';
    $targetFile = $projectRoot . '/mcp.json';
    
    if (!file_exists($exampleFile)) {
        io()->error('mcp.json.example not found at project root');
        return;
    }
    
    if (file_exists($targetFile)) {
        $overwrite = io()->confirm('mcp.json already exists. Overwrite?', false);
        if (!$overwrite) {
            io()->note('Setup cancelled. Existing mcp.json was not modified.');
            return;
        }
    }
    
    $content = file_get_contents($exampleFile);
    $content = str_replace('{{ absolute_path }}', $projectRoot, $content);
    
    file_put_contents($targetFile, $content);
    
    // Create .mcp.json symlink for auto-discovery
    $symlinkFile = $projectRoot . '/.mcp.json';
    if (file_exists($symlinkFile) || is_link($symlinkFile)) {
        unlink($symlinkFile);
    }
    symlink('mcp.json', $symlinkFile);
    
    io()->success('MCP configuration created successfully!');
    io()->table(
        ['File', 'Path'],
        [
            ['mcp.json', $targetFile],
            ['.mcp.json', $symlinkFile . ' → mcp.json'],
            ['Project root', $projectRoot],
        ]
    );
    
    io()->note([
        'Next steps:',
        '1. Configure your AI assistant to use this MCP server',
        '2. Run "castor mate:call php-version \'{}\'" to test',
        '3. See AI_MATE_SETUP.md for AI assistant configuration',
    ]);
}

#[AsTask(namespace: 'mate', description: 'Start AI Mate MCP server (for AI assistants on host)')]
function serve(
    #[AsOption(description: 'Enable debug mode with verbose logging')]
    bool $debug = false
): void
{
    $builder = (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->workdir('/var/www/project');
    
    if ($debug) {
        $builder->env([
            'MATE_DEBUG' => '1',
            'MATE_DEBUG_FILE' => '1',
            'MATE_DEBUG_LOG_FILE' => '/var/www/project/mate_inner.log',
        ]);
    }
    
    // Redirect stderr to /dev/null to keep stdout clean for MCP JSON-RPC protocol
    // unless debug mode is enabled (for logging to file)
    $command = $debug 
        ? 'vendor/bin/mate serve --force-keep-alive'
        : 'vendor/bin/mate serve --force-keep-alive 2>/dev/null';
    
    $builder->noTty()->exec($command);
}

#[AsTask(namespace: 'mate', description: 'List all available MCP tools')]
function tools(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->noTty()
        ->exec('vendor/bin/mate mcp:tools:list');
}

#[AsTask(namespace: 'mate', description: 'Show all MCP capabilities (tools, resources, prompts)')]
function capabilities(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->noTty()
        ->exec('vendor/bin/mate debug:capabilities');
}

#[AsTask(namespace: 'mate', description: 'Show detailed extension information')]
function extensions(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->noTty()
        ->exec('vendor/bin/mate debug:extensions');
}

#[AsTask(namespace: 'mate', description: 'Discover and update MCP extensions')]
function discover(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->noTty()
        ->exec('vendor/bin/mate discover');
}

#[AsTask(namespace: 'mate', description: 'Call a specific MCP tool')]
function call(
    #[AsArgument(description: 'Tool name to call')]
    string $tool,
    #[AsArgument(description: 'JSON parameters for the tool')]
    string $params = '{}'
): void
{
    $escapedParams = addslashes($params);
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->noTty()
        ->exec(sprintf("vendor/bin/mate mcp:tools:call %s '%s'", $tool, $escapedParams));
}
