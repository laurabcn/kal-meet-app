<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\UlidValue;

interface DomainEventInterface
{
    public function id(): UlidValue;

    public function aggregateId(): UlidValue;

    public function name(): string;

    public function occurredOn(): DateTime;

    public function version(): string;
}
