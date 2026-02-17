<?php

namespace Dynart\Micro\Entities\Test\Entities;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\Entity;

class TestPost extends Entity {

    #[Column(type: Column::TYPE_INT, primaryKey: true, autoIncrement: true, notNull: true)]
    public int $id = 0;

    #[Column(type: Column::TYPE_INT, notNull: true, foreignKey: [TestUser::class, 'id'], onDelete: Column::ACTION_CASCADE)]
    public int $user_id = 0;

    #[Column(type: Column::TYPE_STRING, size: 200, notNull: true)]
    public string $title = '';

    #[Column(type: Column::TYPE_STRING)]
    public string $body = '';

    #[Column(type: Column::TYPE_DATE)]
    public ?string $published_at = null;
}
