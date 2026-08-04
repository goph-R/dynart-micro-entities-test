<?php

namespace Dynart\Micro\Entities\Test\Entities;

use Dynart\Micro\Entities\Attribute\Auditable;
use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\Entity;

#[Auditable]
class TestAudited extends Entity {

    protected static string $eventName = 'test_audited';

    #[Column(type: Column::TYPE_INT, primaryKey: true, autoIncrement: true, notNull: true)]
    public int $id = 0;

    #[Column(type: Column::TYPE_STRING, size: 200, notNull: true, unique: true)]
    public string $slug = '';

    #[Column(type: Column::TYPE_STRING, size: 100)]
    public string $title = '';

    #[Column(type: Column::TYPE_INT, foreignKey: [TestUser::class, 'id'], onDelete: Column::ACTION_CASCADE)]
    public ?int $user_id = null;
}
