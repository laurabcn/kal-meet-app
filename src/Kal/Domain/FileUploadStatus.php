<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalFileException;

enum FileUploadStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function isInProgress(): bool
    {
        return self::IN_PROGRESS === $this;
    }

    public function isCompleted(): bool
    {
        return self::COMPLETED === $this;
    }

    public function isFailed(): bool
    {
        return self::FAILED === $this;
    }

    public function getStatusMessage(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'In progress',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Error',
        };
    }

    /** @throws KalFileException */
    public static function tryFromStatus(string $status): self
    {
        return self::tryFrom($status) ?? throw KalFileException::invalidFileStatus();
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(FileUploadStatus $other): bool
    {
        return $this->value() === $other->value();
    }
}
