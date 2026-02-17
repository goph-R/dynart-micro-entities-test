<?php

namespace Dynart\Micro\Entities\Test;

use Dynart\Micro\LoggerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class StubLogger extends AbstractLogger implements LoggerInterface {
    public function log($level, $message, array $context = []): void {}
    public function level(): string { return LogLevel::ERROR; }
}
