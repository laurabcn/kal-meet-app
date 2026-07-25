<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalFileException;

enum FileExtension: string
{
    case PDF = 'pdf';
    case CSV = 'csv';
    case TXT = 'txt';

    /** @throws KalFileException */
    public static function tryFromStatus(string $extension): self
    {
        return self::tryFrom($extension) ?? throw KalFileException::invalidFileStatus();
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(FileExtension $other): bool
    {
        return $this->value() === $other->value();
    }
}
