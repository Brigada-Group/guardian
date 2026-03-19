<?php

namespace Brigada\Guardian\Results;

use Brigada\Guardian\Enums\Status;

class CheckResult
{
    public function __construct(
        public readonly Status $status,
        public readonly string $message,
        public readonly array $metadata = [],
    ) {}

    public function isOk(): bool
    {
        return $this->status === Status::Ok;
    }

    public function isWarning(): bool
    {
        return $this->status === Status::Warning;
    }

    public function isCritical(): bool
    {
        return $this->status === Status::Critical;
    }

    public function isError(): bool
    {
        return $this->status === Status::Error;
    }
}
