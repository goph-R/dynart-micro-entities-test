<?php

namespace Dynart\Micro\Entities\Test;

use Dynart\Micro\EventServiceInterface;

class StubEvents implements EventServiceInterface {
    public array $emitted = [];

    public function subscribeWithRef(string $event, &$callable): void {}
    public function subscribe(string $event, mixed $callable): void {}
    public function unsubscribe(string $event, &$callable): bool { return true; }
    public function emit(string $event, array $args = []): void {
        $this->emitted[] = $event;
    }
}
