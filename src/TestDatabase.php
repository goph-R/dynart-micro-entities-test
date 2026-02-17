<?php

namespace Dynart\Micro\Entities\Test;

use Dynart\Micro\Entities\Database;
use Dynart\Micro\Entities\PdoBuilder;

class TestDatabase extends Database {

    public string $tablePrefix = '';

    public function __construct() {
        parent::__construct(new StubConfig(), new StubLogger(), new PdoBuilder());
    }

    protected function connect(): void {}

    public function escapeName(string $name): string {
        $parts = explode('.', $name);
        return '`' . join('`.`', $parts) . '`';
    }

    public function escapeLike(string $string): string {
        return str_replace('%', '\\%', $string);
    }

    public function configValue(string $name): mixed {
        return match($name) {
            'table_prefix' => $this->tablePrefix,
            default => '',
        };
    }
}
