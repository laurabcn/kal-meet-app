<?php

declare(strict_types=1);

namespace Tests\Unit\Kal\Domain\Mother;

use App\Kal\Domain\File;
use App\Kal\Domain\FileExtension;
use App\Kal\Domain\FileSize;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final class FileMother
{
    public static function create(?string $name = null): File
    {
        $name ??= 'pattern.pdf';

        return new File(
            NonEmptyStringValue::create($name),
            NonEmptyStringValue::create(sprintf('kals/kal-id/round-1/%s', $name)),
            FileSize::create(1000),
            FileExtension::PDF,
            UlidValue::generate(),
            DateTime::now(),
        );
    }
}
