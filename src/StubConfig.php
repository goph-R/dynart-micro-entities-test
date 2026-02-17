<?php

namespace Dynart\Micro\Entities\Test;

use Dynart\Micro\ConfigInterface;

class StubConfig implements ConfigInterface {
    public function load(string $path): void {}
    public function clearCache(): void {}
    public function get(string $name, mixed $default = null, bool $useCache = true): mixed { return $default; }
    public function getCommaSeparatedValues(string $name, bool $useCache = true): array { return []; }
    public function getArray(string $prefix, array $default = [], bool $useCache = true): array { return $default; }
    public function isCached(string $name): bool { return false; }
    public function getFullPath(string $path): string { return $path; }
    public function rootPath(): string { return ''; }
}
