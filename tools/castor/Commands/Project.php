<?php

declare(strict_types=1);

namespace Tools\Castor\Commands;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(description: 'Initialize a new project from templates')]
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

    io()->section('Next steps');
    io()->listing([
        'Run "castor install" to build and start the project',
        'Access the application at http://localhost:' . $nginxPort,
        'Connect to database at localhost:' . $postgresPort,
    ]);

    io()->success('🎉 Project initialized successfully!');
}

