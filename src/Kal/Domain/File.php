<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final readonly class File
{
    public function __construct(
        public private(set) NonEmptyStringValue $fileName,
        public private(set) NonEmptyStringValue $filePath,
        public private(set) FileSize $fileSize,
        public private(set) FileExtension $fileExtension,
        public private(set) Locale $locale,
        public private(set) UlidValue $uploadId,
        public private(set) DateTime $uploadedAt,
    ) {
    }
}
