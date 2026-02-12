<?php

declare(strict_types=1);

namespace Tools\Castor\Commands;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Tools\Castor\Enum\ProjectFolder;
use Tools\Castor\Enum\Service;
use Tools\Castor\Builder\DockerCommandBuilder;

#[AsTask(namespace: 'docker', description: 'Start Docker containers', aliases: ['start'])]
function start(
    #[AsArgument(description: 'Services to start (database, server, proxy). Leave empty for all')]
    array $services = [],
): void {
    $builder = (new DockerCommandBuilder())
        ->detached()
        ->removeOrphans();

    if (empty($services)) {
        $builder->withAllServices();
    } else {
        $builder->withServices(Service::fromNames($services));
    }

    $builder->up();
}

#[AsTask(namespace: 'docker', description: 'Stop Docker containers', aliases: ['stop'])]
function stop(
    #[AsArgument(description: 'Services to stop (database, server, proxy). Leave empty for all')]
    ?array $services = null,
): void {
    $builder = new DockerCommandBuilder();

    if (empty($services)) {
        $builder->withAllServices();
    } else {
        $builder->withServices(Service::fromNames($services));
    }

    $builder->stop();
}

#[AsTask(namespace: 'docker', description: 'Stop and remove Docker containers', aliases: ['down'])]
function down(
    #[AsOption(shortcut: 'v', description: 'Remove volumes')]
    bool $volumes = false,
): void {
    (new DockerCommandBuilder())
        ->withAllServices()
        ->withVolumes($volumes)
        ->down();
}

#[AsTask(namespace: 'docker', description: 'Build Docker containers')]
function build(
    #[AsArgument(description: 'Services to build (database, server, proxy). Leave empty for all')]
    ?array $services = null,
): void {
    $builder = new DockerCommandBuilder();

    if (empty($services)) {
        $builder->withAllServices();
    } else {
        $builder->withServices(Service::fromNames($services));
    }

    $builder->build();
}

#[AsTask(namespace: 'docker', description: 'Restart Docker containers', aliases: ['restart'])]
function restart(
    #[AsArgument(description: 'Services to restart (database, server, proxy). Leave empty for all')]
    ?array $services = null,
): void {
    $builder = new DockerCommandBuilder();

    if (empty($services)) {
        $builder->withAllServices();
    } else {
        $builder->withServices(Service::fromNames($services));
    }

    $builder->restart();
}

#[AsTask(description: 'Open bash on server container', namespace: '', aliases: ['bash'])]
function bash(
    #[AsOption(shortcut: 'p', description: 'Project folder: app or tools')]
    string $project = 'app',
): void {
    $projectFolder = ProjectFolder::from($project);

    if ($projectFolder->isAll()) {
        throw new \InvalidArgumentException('Cannot open bash in "all". Choose "app" or "tools".');
    }

    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->workdir($projectFolder->getPath())
        ->exec('ash');
}

#[AsTask(namespace: 'docker', description: 'List Docker containers status', aliases: ['ps'])]
function ps(): void
{
    (new DockerCommandBuilder())->withAllServices()->ps();
}

#[AsTask(namespace: 'docker', description: 'Show Docker container logs', aliases: ['logs'])]
function logs(
    #[AsArgument(description: 'Service name (server, proxy, database)')]
    ?string $service = null,
    #[AsOption(shortcut: 'f', description: 'Follow log output')]
    bool $follow = false,
): void {
    (new DockerCommandBuilder())->withAllServices()->logs($service, $follow);
}
