<?php

declare(strict_types=1);

namespace Tools\Castor\Commands;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Tools\Castor\Builder\DockerCommandBuilder;
use Tools\Castor\Support\WorktreeRegistry;

use function Castor\io;
use function Castor\run;

#[AsTask(namespace: 'worktree', name: 'create', description: 'Create a new git worktree with its own isolated Docker stack')]
function worktree_create(
    #[AsArgument(description: 'Worktree name (also used as the git branch, Docker project suffix, and registry key)')]
    string $name,
    #[AsOption(description: 'Path where the new worktree will be created (required)')]
    string $path = '',
    #[AsOption(description: 'Base branch to create the new branch from, if it does not already exist')]
    string $base = 'main',
    #[AsOption(description: 'Start the full Docker stack once the worktree is scaffolded')]
    bool $start = false,
): void {
    if ($path === '') {
        throw new \InvalidArgumentException('The --path option is required.');
    }

    if (\file_exists('infrastructure/dev/network.yml') === false) {
        throw new \RuntimeException('This checkout has not been initialized yet. Run "castor project:init" first.');
    }

    $registry = new WorktreeRegistry();

    if ($registry->find($name) !== null) {
        throw new \InvalidArgumentException(\sprintf('A worktree named "%s" already exists in the registry.', $name));
    }

    $baseConfig = readCurrentProjectConfig();

    io()->title(\sprintf('🌱 Creating worktree "%s"', $name));

    io()->section('1/5 - Creating the git worktree');

    if (worktreeBranchExists($name)) {
        run(['git', 'worktree', 'add', $path, $name]);
    } else {
        run(['git', 'worktree', 'add', $path, '-b', $name, $base]);
    }

    $absolutePath = \realpath($path);

    if ($absolutePath === false) {
        throw new \RuntimeException(\sprintf('Could not resolve the absolute path of the new worktree at "%s".', $path));
    }

    io()->section('2/5 - Allocating ports');
    $portOffset = $registry->allocatePortOffset();
    $ports = [
        'nginx' => $baseConfig['nginxPort'] + $portOffset,
        'postgres' => $baseConfig['postgresPort'] + $portOffset,
    ];
    $projectName = \sprintf('%s_%s', $baseConfig['projectName'], $name);

    io()->section('3/5 - Generating the isolated Docker Compose configuration');
    $replacements = [
        '{{ project_name }}' => $projectName,
        '{{ nginx_port }}' => (string) $ports['nginx'],
        '{{ postgres_port }}' => (string) $ports['postgres'],
        '{{ hostname }}' => $baseConfig['hostname'],
        '{{ database_name }}' => $baseConfig['databaseName'],
        '{{ database_user }}' => $baseConfig['databaseUser'],
        '{{ database_password }}' => $baseConfig['databasePassword'],
    ];

    $previousWorkingDirectory = \getcwd();

    if ($previousWorkingDirectory === false || \chdir($absolutePath) === false) {
        throw new \RuntimeException('Could not switch to the new worktree directory to generate its configuration.');
    }

    try {
        generateProjectFiles($replacements);
    } finally {
        \chdir($previousWorkingDirectory);
    }

    io()->section('4/5 - Sharing the SSL certificates from the current checkout');
    $sslSource = 'infrastructure/dev/configurations/nginx/ssl';
    $certificates = \glob($sslSource . '/*.crt');

    if ($certificates !== false && $certificates !== []) {
        // Trailing "/." copies the directory's *contents*, since the destination
        // ssl/ directory already exists (it tracks a .gitignore file).
        run(['cp', '-R', $sslSource . '/.', $absolutePath . '/' . $sslSource]);
    } else {
        io()->warning('No SSL certificate found in the current checkout — run "castor ssl:generate" first, then re-run this command.');
    }

    $registry->add($name, $absolutePath, $name, $projectName, $portOffset, $ports);

    io()->section('5/5 - Summary');
    printWorktreeSummary($baseConfig['hostname'], $ports);

    if ($start) {
        $previousWorkingDirectory = \getcwd();

        if ($previousWorkingDirectory === false || \chdir($absolutePath) === false) {
            throw new \RuntimeException('Could not switch to the new worktree directory to start the stack.');
        }

        try {
            io()->section('Starting the Docker stack');
            start();

            io()->section('Installing dependencies & initializing the project');
            install('all');
        } finally {
            \chdir($previousWorkingDirectory);
        }

        io()->success(\sprintf(
            '✅ Worktree "%s" is up and ready! Access: https://%s:%d',
            $name,
            $baseConfig['hostname'],
            $ports['nginx'],
        ));
    } else {
        io()->note(\sprintf(
            'Run "cd %s && castor docker:start && castor install" to start the stack.',
            $path,
        ));
    }
}

#[AsTask(namespace: 'worktree', name: 'destroy', description: 'Destroy a worktree and tear down its isolated Docker stack')]
function worktree_destroy(
    #[AsArgument(description: 'Worktree name, as given to worktree:create')]
    string $name,
    #[AsOption(description: 'Also delete the associated git branch')]
    bool $deleteBranch = false,
): void {
    $registry = new WorktreeRegistry();
    $entry = $registry->find($name);

    if ($entry === null) {
        throw new \InvalidArgumentException(\sprintf('No worktree named "%s" found in the registry.', $name));
    }

    $path = $entry['path'];

    io()->title(\sprintf('🔥 Destroying worktree "%s"', $name));

    if (\is_dir($path)) {
        io()->section('1/4 - Stopping the Docker stack');
        $previousWorkingDirectory = \getcwd();

        if ($previousWorkingDirectory === false || \chdir($path) === false) {
            throw new \RuntimeException(\sprintf('Could not switch to the worktree directory at "%s".', $path));
        }

        try {
            (new DockerCommandBuilder())
                ->withAllServices()
                ->withVolumes(true)
                ->noTty()
                ->down();
        } finally {
            \chdir($previousWorkingDirectory);
        }
    } else {
        io()->note('Worktree directory not found on disk — skipping Docker teardown.');
    }

    io()->section('2/4 - Removing the git worktree');
    $forceRemove = false;

    if (\is_dir($path) && worktreeHasUncommittedChanges($path)) {
        $forceRemove = io()->confirm(
            \sprintf('Worktree "%s" has uncommitted changes. Force removal anyway?', $name),
            false,
        );

        if ($forceRemove === false) {
            io()->warning('Aborted — commit or discard the changes, then retry.');
            return;
        }
    }

    if (worktreeIsRegistered($path)) {
        $removeCommand = ['git', 'worktree', 'remove', $path];

        if ($forceRemove) {
            $removeCommand[] = '--force';
        }

        run($removeCommand);
    } else {
        io()->note('Worktree already unregistered from git — skipping removal.');
    }

    io()->section('3/4 - Pruning stale worktree metadata');
    run(['git', 'worktree', 'prune']);

    if ($deleteBranch) {
        io()->section('4/4 - Deleting the git branch');
        run(['git', 'branch', '-D', $entry['branch']]);
    }

    $registry->remove($name);

    io()->success(\sprintf('✅ Worktree "%s" fully removed.', $name));
}

/**
 * @return array{projectName: string, nginxPort: int, postgresPort: int, hostname: string, databaseName: string, databaseUser: string, databasePassword: string}
 */
function readCurrentProjectConfig(): array
{
    $networkYml = \file_get_contents('infrastructure/dev/network.yml');
    $databaseYml = \file_get_contents('infrastructure/dev/services/database/database.yml');
    $proxyYml = \file_get_contents('infrastructure/dev/services/proxy/proxy.yml');
    $nginxConf = \file_get_contents('infrastructure/dev/configurations/nginx/project_local.conf');
    $databaseEnv = \file_get_contents('infrastructure/dev/services/database/.env');

    return [
        'projectName' => extractYamlScalar($networkYml, 'name'),
        'nginxPort' => (int) extractYamlScalar($proxyYml, 'published'),
        'postgresPort' => (int) extractYamlScalar($databaseYml, 'published'),
        'hostname' => extractNginxServerName($nginxConf),
        'databaseName' => extractEnvValue($databaseEnv, 'POSTGRES_DB'),
        'databaseUser' => extractEnvValue($databaseEnv, 'POSTGRES_USER'),
        'databasePassword' => extractEnvValue($databaseEnv, 'POSTGRES_PASSWORD'),
    ];
}

function extractYamlScalar(string $content, string $key): string
{
    if (\preg_match('/^\s*' . \preg_quote($key, '/') . ':\s*(.+)$/m', $content, $matches) !== 1) {
        throw new \RuntimeException(\sprintf('Could not find "%s:" in the current project configuration.', $key));
    }

    return \trim($matches[1]);
}

function extractNginxServerName(string $content): string
{
    if (\preg_match('/server_name\s+([^;]+);/', $content, $matches) !== 1) {
        throw new \RuntimeException('Could not find "server_name" in the current nginx configuration.');
    }

    return \trim($matches[1]);
}

function extractEnvValue(string $content, string $key): string
{
    if (\preg_match('/^' . \preg_quote($key, '/') . '=(.*)$/m', $content, $matches) !== 1) {
        throw new \RuntimeException(\sprintf('Could not find "%s=" in the current database environment file.', $key));
    }

    return \trim($matches[1]);
}

function worktreeBranchExists(string $branch): bool
{
    \exec(\sprintf('git show-ref --verify --quiet %s', \escapeshellarg("refs/heads/{$branch}")), result_code: $exitCode);

    return $exitCode === 0;
}

function worktreeHasUncommittedChanges(string $path): bool
{
    \exec(\sprintf('git -C %s status --porcelain', \escapeshellarg($path)), $output);

    return $output !== [];
}

function worktreeIsRegistered(string $path): bool
{
    \exec('git worktree list --porcelain', $output);

    $realPath = \realpath($path);

    foreach ($output as $line) {
        if (\str_starts_with($line, 'worktree ') && \substr($line, \strlen('worktree ')) === $realPath) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, int> $ports
 */
function printWorktreeSummary(string $hostname, array $ports): void
{
    io()->table(
        ['Service', 'Port'],
        [
            ['Nginx (HTTPS)', $ports['nginx']],
            ['PostgreSQL', $ports['postgres']],
        ],
    );

    io()->listing([
        \sprintf('Application: https://%s:%d', $hostname, $ports['nginx']),
        \sprintf('Database: localhost:%d', $ports['postgres']),
    ]);
}
