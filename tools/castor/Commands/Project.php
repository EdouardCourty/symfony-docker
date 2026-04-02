<?php

declare(strict_types=1);

namespace Tools\Castor\Commands;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;

#[AsTask(namespace: 'project', description: 'Initialize a new project from templates')]
function init(): void
{
    io()->title('🚀 Project Initialization');

    // Check if already initialized
    if (file_exists('infrastructure/dev/docker-compose.yml')) {
        $overwrite = io()->confirm('Project already initialized. Overwrite existing configuration?', false);
        if (!$overwrite) {
            io()->warning('Initialization cancelled');
            return;
        }
    }

    // Collect project information
    $projectName = io()->ask('Project name (lowercase, no spaces, e.g., "my-app")', 'symfony-docker', function ($answer) {
        if (!preg_match('/^[a-z0-9-]+$/', $answer)) {
            throw new \RuntimeException('Project name must be lowercase with hyphens only');
        }
        return $answer;
    });

    $nginxPort = io()->ask('Nginx port', '8811', function ($answer) {
        if (!is_numeric($answer) || $answer < 1024 || $answer > 65535) {
            throw new \RuntimeException('Port must be between 1024 and 65535');
        }
        return (string) $answer;
    });

    $postgresPort = io()->ask('PostgreSQL port', '5422', function ($answer) {
        if (!is_numeric($answer) || $answer < 1024 || $answer > 65535) {
            throw new \RuntimeException('Port must be between 1024 and 65535');
        }
        return (string) $answer;
    });

    $hostname = io()->ask('Local hostname (will be added to /etc/hosts)', 'app.local', function ($answer) {
        if (!preg_match('/^[a-z0-9.-]+$/', $answer)) {
            throw new \RuntimeException('Hostname must be lowercase with dots and hyphens only');
        }
        return $answer;
    });

    $databaseName = io()->ask('Database name', 'main_dev');
    $databaseUser = io()->ask('Database user', 'app');
    $databasePassword = io()->ask('Database password', 'app');

    // Display summary
    io()->section('Configuration Summary');
    io()->table(
        ['Setting', 'Value'],
        [
            ['Project Name', $projectName],
            ['Nginx Port', $nginxPort],
            ['PostgreSQL Port', $postgresPort],
            ['Hostname', $hostname],
            ['Database Name', $databaseName],
            ['Database User', $databaseUser],
            ['Database Password', $databasePassword],
        ]
    );

    if (!io()->confirm('Proceed with this configuration?', true)) {
        io()->warning('Initialization cancelled');
        return;
    }

    // Replacements map
    $replacements = [
        '{{ project_name }}' => $projectName,
        '{{ nginx_port }}' => $nginxPort,
        '{{ postgres_port }}' => $postgresPort,
        '{{ hostname }}' => $hostname,
        '{{ database_name }}' => $databaseName,
        '{{ database_user }}' => $databaseUser,
        '{{ database_password }}' => $databasePassword,
    ];

    // Generate files from templates
    io()->section('Generating configuration files');

    $templates = [
        'infrastructure/dev/network.yml.dist' => 'infrastructure/dev/network.yml',
        'infrastructure/dev/services/database/.env.dist' => 'infrastructure/dev/services/database/.env',
        'infrastructure/dev/services/database/database.yml.dist' => 'infrastructure/dev/services/database/database.yml',
        'infrastructure/dev/services/server/server.yml.dist' => 'infrastructure/dev/services/server/server.yml',
        'infrastructure/dev/services/proxy/proxy.yml.dist' => 'infrastructure/dev/services/proxy/proxy.yml',
        'infrastructure/dev/configurations/nginx/project_local.conf.dist' => 'infrastructure/dev/configurations/nginx/project_local.conf',
    ];

    foreach ($templates as $source => $destination) {
        if (!file_exists($source)) {
            io()->error("Template file not found: {$source}");
            continue;
        }

        $content = file_get_contents($source);
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        file_put_contents($destination, $content);

        io()->success("✓ Generated {$destination}");
    }

    io()->section('Generating SSL certificates');
    generate(hostname: $hostname, trust: true);

    io()->section('Configuring /etc/hosts');
    hosts($hostname);

    io()->section('Next steps');
    io()->listing([
        'Run "castor install" to build and start the project',
        "Access the application at https://{$hostname}:{$nginxPort}",
        'Connect to database at localhost:' . $postgresPort,
    ]);

    io()->success('🎉 Project initialized successfully!');
}

#[AsTask(namespace: 'project', description: 'Start the full stack and initialize the database')]
function reset(): void
{
    io()->title('🔄 Starting full stack');

    io()->section('1/3 - Starting Docker services');
    start();

    io()->section('2/3 - Installing dependencies');
    install('all');

    io()->section('3/3 - Initializing database');
    reload();

    io()->success('✅ Stack is up and ready!');
}

#[AsTask(namespace: 'project', description: 'Add a hostname to /etc/hosts (requires sudo)')]
function hosts(
    #[AsOption(description: 'Hostname to add')]
    ?string $hostname = null,
): void {
    if ($hostname === null) {
        $hostname = io()->ask('Hostname to add to /etc/hosts', 'app.local');
    }

    $hostsFile = '/etc/hosts';
    $entry = "127.0.0.1 {$hostname}";

    $contents = \file_get_contents($hostsFile);

    if (\str_contains($contents, $hostname)) {
        io()->success("{$hostname} is already in {$hostsFile} — nothing to do.");
        return;
    }

    io()->writeln("Adding <info>{$entry}</info> to {$hostsFile}...");

    run(['sudo', 'sh', '-c', "echo '{$entry}' >> {$hostsFile}"]);

    io()->success("{$hostname} added to {$hostsFile}.");
}

