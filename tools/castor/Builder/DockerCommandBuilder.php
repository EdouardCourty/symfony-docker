<?php

declare(strict_types=1);

namespace Tools\Castor\Builder;

use Tools\Castor\Enum\Service;

use function Castor\context;
use function Castor\run;

final class DockerCommandBuilder
{
    private const string DOCKER_COMPOSE_DIR = 'infrastructure/dev';
    private const string NETWORK_FILE = 'network.yml';

    /** @var string[] */
    private array $composeFiles = [];

    private bool $detached = false;
    private bool $removeOrphans = false;
    private bool $volumes = false;
    private ?string $service = null;
    private ?string $workdir = null;
    private bool $removeContainer = false;
    private bool $servicePorts = false;
    private bool $tty = true; // TTY enabled by default
    private bool $forwardStdin = false;
    /** @var Service[] */
    private array $services = [];
    /** @var array<string, string> */
    private array $env = [];

    public function __construct()
    {
        // Always include network file first
        $this->composeFiles[] = self::DOCKER_COMPOSE_DIR . '/' . self::NETWORK_FILE;
    }

    /**
     * @param Service[] $services
     */
    public function withServices(array $services): self
    {
        $this->services = $services;
        
        // Always load all compose files for proper dependencies
        // Only the service names will be filtered during execution
        foreach (Service::all() as $service) {
            $this->composeFiles[] = $service->getComposeFilePath();
        }

        return $this;
    }

    public function withAllServices(): self
    {
        return $this->withServices(Service::all());
    }

    public function addComposeFile(string $file): self
    {
        $this->composeFiles[] = $file;

        return $this;
    }

    public function detached(bool $detached = true): self
    {
        $this->detached = $detached;

        return $this;
    }

    public function removeOrphans(bool $removeOrphans = true): self
    {
        $this->removeOrphans = $removeOrphans;

        return $this;
    }

    public function withVolumes(bool $volumes = true): self
    {
        $this->volumes = $volumes;

        return $this;
    }

    public function service(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function workdir(string $workdir): self
    {
        $this->workdir = $workdir;

        return $this;
    }

    public function removeContainer(bool $remove = true): self
    {
        $this->removeContainer = $remove;

        return $this;
    }

    public function servicePorts(bool $servicePorts = true): self
    {
        $this->servicePorts = $servicePorts;

        return $this;
    }

    public function withTty(bool $tty = true): self
    {
        $this->tty = $tty;

        return $this;
    }

    public function noTty(): self
    {
        $this->tty = false;

        return $this;
    }

    /**
     * Pipes the parent process's real STDIN into the child process without allocating a
     * PTY (unlike tty/pty mode, this doesn't echo the input back onto stdout). Needed for
     * long-lived processes that speak a line-delimited protocol over stdio, like an MCP server.
     */
    public function forwardStdin(bool $forward = true): self
    {
        $this->forwardStdin = $forward;

        return $this;
    }

    /**
     * @param array<string, string> $env
     */
    public function env(array $env): self
    {
        $this->env = array_merge($this->env, $env);

        return $this;
    }

    private function buildBaseCommand(): string
    {
        $files = array_map(fn (string $file) => "-f {$file}", $this->composeFiles);

        return \sprintf('docker compose %s', implode(' ', $files));
    }

    public function up(): void
    {
        $command = $this->buildBaseCommand() . ' up';

        if ($this->detached) {
            $command .= ' --detach';
        }

        if ($this->removeOrphans) {
            $command .= ' --remove-orphans';
        }

        // Add specific services if any
        if (!empty($this->services)) {
            $serviceNames = array_map(fn (Service $s) => $s->getServiceName(), $this->services);
            $command .= ' ' . implode(' ', $serviceNames);
        }

        $context = $this->tty ? context()->withTty(true) : context()->withPty(false);
        run($command, context: $context);
    }

    public function down(): void
    {
        $command = $this->buildBaseCommand() . ' down';

        if ($this->volumes) {
            $command .= ' --volumes';
        }

        $context = $this->tty ? context()->withTty(true) : context()->withPty(false);
        run($command, context: $context);
    }

    public function stop(): void
    {
        $command = $this->buildBaseCommand() . ' stop';

        // Add specific services if any
        if (!empty($this->services)) {
            $serviceNames = array_map(fn (Service $s) => $s->getServiceName(), $this->services);
            $command .= ' ' . implode(' ', $serviceNames);
        }

        $context = $this->tty ? context()->withTty(true) : context()->withPty(false);
        run($command, context: $context);
    }

    public function build(): void
    {
        $context = $this->tty ? context()->withTty(true) : context()->withPty(false);
        run($this->buildBaseCommand() . ' build', context: $context);
    }

    public function restart(bool $forceRecreate = false): void
    {
        if ($forceRecreate) {
            $command = $this->buildBaseCommand() . ' up --detach --force-recreate';

            if (!empty($this->services)) {
                $serviceNames = array_map(fn (Service $s) => $s->getServiceName(), $this->services);
                $command .= ' ' . implode(' ', $serviceNames);
            }
        } else {
            $command = $this->buildBaseCommand() . ' restart';

            if (!empty($this->services)) {
                $serviceNames = array_map(fn (Service $s) => $s->getServiceName(), $this->services);
                $command .= ' ' . implode(' ', $serviceNames);
            }
        }

        $context = $this->tty ? context()->withTty(true) : context()->withPty(false);
        run($command, context: $context);
    }

    public function exec(string $command): void
    {
        if ($this->service === null) {
            throw new \LogicException('Service must be set before calling exec()');
        }

        $options = [];

        if ($this->tty) {
            $options[] = '-it';
        } else {
            $options[] = '-T';
        }

        foreach ($this->env as $key => $value) {
            $options[] = \sprintf('--env %s=%s', escapeshellarg($key), escapeshellarg($value));
        }

        if ($this->workdir !== null) {
            $options[] = \sprintf('--workdir=%s', escapeshellarg($this->workdir));
        }

        $execCommand = $this->buildBaseCommand() . \sprintf(
            ' exec %s %s %s',
            implode(' ', $options),
            $this->service,
            $command,
        );

        $context = match (true) {
            $this->tty => context()->withTty(true),
            $this->forwardStdin => context()->withInput(\STDIN),
            default => context()->withPty(false),
        };

        run($execCommand, context: $context);
    }

    public function runCommand(string $command, bool $detached = false): void
    {
        if ($this->service === null) {
            throw new \LogicException('Service must be set before calling runCommand()');
        }

        $options = [];

        if ($detached) {
            $options[] = '--detach';
        }

        if ($this->removeContainer) {
            $options[] = '--rm';
        }

        if ($this->servicePorts) {
            $options[] = '--service-ports';
        }

        if ($this->workdir !== null) {
            $options[] = \sprintf('--workdir=%s', escapeshellarg($this->workdir));
        }

        $runCommand = $this->buildBaseCommand() . \sprintf(
            ' run %s %s %s',
            implode(' ', $options),
            $this->service,
            $command,
        );

        $context = ($this->tty && !$detached) ? context()->withTty(true) : context()->withPty(false);
        run($runCommand, context: $context);
    }

    public function ps(): void
    {
        $context = $this->tty ? context()->withTty(true) : context()->withPty(false);
        run($this->buildBaseCommand() . ' ps', context: $context);
    }

    public function logs(?string $service = null, bool $follow = false): void
    {
        $command = $this->buildBaseCommand() . ' logs';

        if ($follow) {
            $command .= ' -f';
        }

        if ($service !== null) {
            $command .= ' ' . $service;
        }

        $context = $this->tty ? context()->withTty(true) : context()->withPty(false);
        run($command, context: $context);
    }
}
