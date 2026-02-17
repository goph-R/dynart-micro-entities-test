<?php

namespace Dynart\Micro\Entities\Test\Entities;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\Entity;

class TestUser extends Entity {

    #[Column(type: Column::TYPE_INT, primaryKey: true, autoIncrement: true, notNull: true)]
    public int $id = 0;

    #[Column(type: Column::TYPE_STRING, size: 100, notNull: true)]
    public string $name = '';

    #[Column(type: Column::TYPE_STRING, size: 150)]
    public string $email = '';

    #[Column(type: Column::TYPE_BOOL, default: false)]
    public bool $active = false;

    #[Column(type: Column::TYPE_DATETIME, default: Column::NOW)]
    public ?string $created_at = null;
}
