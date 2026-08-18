<?php

declare(strict_types=1);

namespace Tools\Castor\Support;

final class WorktreeRegistry
{
    private const string REGISTRY_FILENAME = 'castor-worktrees.json';
    private const int PORT_OFFSET_STEP = 10;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $name): ?array
    {
        foreach ($this->all() as $entry) {
            if ($entry['name'] === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $path = $this->registryPath();

        if (\file_exists($path) === false) {
            return [];
        }

        $contents = \file_get_contents($path);

        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded['worktrees'] ?? [];
    }

    /**
     * @param array<string, int> $ports
     */
    public function add(string $name, string $path, string $branch, string $projectName, int $portOffset, array $ports): void
    {
        $entries = $this->all();

        $entries[] = [
            'name' => $name,
            'path' => $path,
            'branch' => $branch,
            'projectName' => $projectName,
            'portOffset' => $portOffset,
            'ports' => $ports,
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        $this->write($entries);
    }

    public function remove(string $name): void
    {
        $entries = \array_values(\array_filter(
            $this->all(),
            static fn (array $entry): bool => $entry['name'] !== $name,
        ));

        $this->write($entries);
    }

    public function allocatePortOffset(): int
    {
        $usedOffsets = \array_column($this->all(), 'portOffset');

        // Offset 0 is reserved for the main checkout (its ports are the compose files' defaults).
        $offset = self::PORT_OFFSET_STEP;

        while (\in_array($offset, $usedOffsets, true)) {
            $offset += self::PORT_OFFSET_STEP;
        }

        return $offset;
    }

    private function registryPath(): string
    {
        return $this->gitCommonDir() . '/' . self::REGISTRY_FILENAME;
    }

    private function gitCommonDir(): string
    {
        $output = \shell_exec('git rev-parse --git-common-dir 2>/dev/null');

        if ($output === null || \trim($output) === '') {
            throw new \RuntimeException('Unable to resolve the git common directory. Are you inside a Git repository?');
        }

        $gitCommonDir = \trim($output);

        if (\str_starts_with($gitCommonDir, '/') === false) {
            $gitCommonDir = \getcwd() . '/' . $gitCommonDir;
        }

        return $gitCommonDir;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function write(array $entries): void
    {
        \file_put_contents(
            $this->registryPath(),
            \json_encode(['worktrees' => $entries], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT) . "\n",
        );
    }
}
