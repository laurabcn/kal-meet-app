<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class KalFileException extends DomainException
{
    public static function invalidFileType(string $fileType): self
    {
        return new self(sprintf('Invalid file type: %s', $fileType));
    }

    public static function fileAlreadyDeleted(): self
    {
        return new self('The file has already been deleted.');
    }

    public static function fileUploadInProgress(): self
    {
        return new self('The file upload is currently in progress. No changes can be made until it is completed.');
    }

    public static function invalidKalFile(): self
    {
        return new self('The property file is invalid.');
    }

    public static function invalidFileStatus(): self
    {
        return new self('The file status is invalid or does not exist.');
    }

    public static function fileUploadAlreadyFinished(): self
    {
        return new self('The file upload has already finished.');
    }

    public static function fileUploadAlreadyCompleted(): self
    {
        return new self('The file upload has already been completed.');
    }
}
