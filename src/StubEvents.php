<?php

namespace Dynart\Micro\Entities\Test;

use Dynart\Micro\EventServiceInterface;

class StubEvents implements EventServiceInterface {

    /** The names of the emitted events, in order */
    public array $emitted = [];

    /** The arguments of the last emit per event name, in [event => args] format */
    public array $args = [];

    public function subscribeWithRef(string $event, &$callable): void {}
    public function subscribe(string $event, mixed $callable): void {}
    public function unsubscribe(string $event, &$callable): bool { return true; }

    public function emit(string $event, array $args = []): void {
        $this->emitted[] = $event;
        $this->args[$event] = $args;
    }
}
