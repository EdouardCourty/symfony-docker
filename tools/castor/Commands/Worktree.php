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
    assertValidWorktreeName($name);

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
    #[AsOption(description: 'Remove the worktree even if it has uncommitted changes (no confirmation)')]
    bool $force = false,
): void {
    assertValidWorktreeName($name);

    $registry = new WorktreeRegistry();
    $target = resolveWorktreeTarget($name, $registry);
    $path = $target['path'];

    io()->title(\sprintf('🔥 Destroying worktree "%s"', $name));

    $forceRemove = $force;

    if ($path !== null && \is_dir($path) && worktreeHasUncommittedChanges($path) && $forceRemove === false) {
        $forceRemove = io()->confirm(
            \sprintf('Worktree "%s" has uncommitted changes. Force removal anyway?', $name),
            false,
        );

        if ($forceRemove === false) {
            io()->warning('Aborted — commit or discard the changes, then retry.');

            return;
        }
    }

    io()->section('1/4 - Tearing down the Docker stack');
    // Stateless: compose resolves the stack from its project label, so no compose file
    // (nor the worktree directory) is needed. A project that does not exist is a no-op.
    run([
        'docker', 'compose', '-p', composeProjectName($target['projectName']),
        'down', '--volumes', '--remove-orphans', '--rmi', 'local',
    ]);

    io()->section('2/4 - Removing the git worktree');

    if ($path !== null && worktreeIsRegistered($path)) {
        $removeCommand = ['git', 'worktree', 'remove', $path];

        if ($forceRemove) {
            $removeCommand[] = '--force';
        }

        run($removeCommand);
    } else {
        io()->note('Worktree not known to git — skipping removal.');
    }

    io()->section('3/4 - Pruning stale worktree metadata');
    run(['git', 'worktree', 'prune']);

    if ($deleteBranch) {
        io()->section('4/4 - Deleting the git branch');

        if (worktreeBranchExists($target['branch'])) {
            run(['git', 'branch', '-D', $target['branch']]);
        } else {
            io()->note(\sprintf('Branch "%s" not found — skipping.', $target['branch']));
        }
    }

    $registry->remove($name);

    io()->success(\sprintf('✅ Worktree "%s" fully removed.', $name));
}

#[AsTask(namespace: 'worktree', name: 'list', description: 'List registered worktrees with their ports and Docker status')]
function worktree_list(): void
{
    $entries = (new WorktreeRegistry())->all();

    if ($entries === []) {
        io()->note('No worktree registered.');

        return;
    }

    $rows = [];

    foreach ($entries as $entry) {
        $running = countRunningContainers(composeProjectName($entry['projectName']));

        $rows[] = [
            $entry['name'],
            $entry['branch'],
            $entry['path'],
            \sprintf('nginx %d / postgres %d', $entry['ports']['nginx'], $entry['ports']['postgres']),
            \is_dir($entry['path']) ? \sprintf('%d container(s) running', $running) : 'directory missing',
        ];
    }

    io()->table(['Name', 'Branch', 'Path', 'Ports', 'Status'], $rows);
}

function assertValidWorktreeName(string $name): void
{
    // The name ends up in the Docker Compose project name, which only accepts these characters.
    if (\preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name) !== 1) {
        throw new \InvalidArgumentException(\sprintf(
            'Invalid worktree name "%s": use lowercase letters, digits, "-" and "_" only.',
            $name,
        ));
    }
}

function composeProjectName(string $projectName): string
{
    return \strtolower($projectName);
}

function countRunningContainers(string $composeProject): int
{
    \exec(\sprintf(
        'docker ps --quiet --filter %s 2>/dev/null',
        \escapeshellarg('label=com.docker.compose.project=' . $composeProject),
    ), $output);

    return \count($output);
}

/**
 * Resolves what to tear down from the registry, falling back to git and to the naming
 * convention so a worktree can still be destroyed when its registry entry or directory is gone.
 *
 * @return array{path: ?string, branch: string, projectName: string}
 */
function resolveWorktreeTarget(string $name, WorktreeRegistry $registry): array
{
    $entry = $registry->find($name);

    if ($entry !== null) {
        return ['path' => $entry['path'], 'branch' => $entry['branch'], 'projectName' => $entry['projectName']];
    }

    io()->note(\sprintf('No registry entry for "%s" — falling back to git and naming conventions.', $name));

    $worktrees = listGitWorktrees();
    $path = null;
    $branch = $name;

    foreach (\array_slice($worktrees, 1) as $worktree) {
        if ($worktree['branch'] === $name || \basename($worktree['path']) === $name) {
            $path = $worktree['path'];
            $branch = $worktree['branch'] ?? $name;
            break;
        }
    }

    $networkFile = $path !== null ? $path . '/infrastructure/dev/network.yml' : null;

    if ($networkFile !== null && \file_exists($networkFile)) {
        $projectName = extractYamlScalar((string) \file_get_contents($networkFile), 'name');
    } else {
        // The first listed worktree is always the main checkout, whose project name has no suffix.
        $mainNetworkFile = $worktrees[0]['path'] . '/infrastructure/dev/network.yml';

        if (\file_exists($mainNetworkFile) === false) {
            throw new \RuntimeException('Cannot derive the Docker project name: the main checkout has not been initialized.');
        }

        $projectName = \sprintf('%s_%s', extractYamlScalar((string) \file_get_contents($mainNetworkFile), 'name'), $name);
    }

    return ['path' => $path, 'branch' => $branch, 'projectName' => $projectName];
}

/**
 * @return array<int, array{path: string, branch: ?string}>
 */
function listGitWorktrees(): array
{
    \exec('git worktree list --porcelain', $output);

    $worktrees = [];

    foreach ($output as $line) {
        if (\str_starts_with($line, 'worktree ')) {
            $worktrees[] = ['path' => \substr($line, \strlen('worktree ')), 'branch' => null];
        } elseif (\str_starts_with($line, 'branch refs/heads/') && $worktrees !== []) {
            $worktrees[\array_key_last($worktrees)]['branch'] = \substr($line, \strlen('branch refs/heads/'));
        }
    }

    return $worktrees;
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
