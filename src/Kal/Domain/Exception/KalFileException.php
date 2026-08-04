<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class KalFileException extends DomainException
{
    public static function invalidFileType(string $fileType): self
    {
        return new self(
            'kal_file_invalid_type',
            sprintf('Invalid file type: %s', $fileType),
        );
    }

    public static function fileAlreadyDeleted(): self
    {
        return new self('kal_file_already_deleted', 'The file has already been deleted.');
    }

    public static function fileUploadInProgress(): self
    {
        return new self(
            'kal_file_upload_in_progress',
            'The file upload is currently in progress. No changes can be made until it is completed.',
        );
    }

    public static function invalidKalFile(): self
    {
        return new self('kal_file_invalid', 'The property file is invalid.');
    }

    public static function invalidFileStatus(): self
    {
        return new self('kal_file_invalid_status', 'The file status is invalid or does not exist.');
    }

    public static function fileUploadAlreadyFinished(): self
    {
        return new self('kal_file_upload_already_finished', 'The file upload has already finished.');
    }

    public static function fileUploadAlreadyCompleted(): self
    {
        return new self('kal_file_upload_already_completed', 'The file upload has already been completed.');
    }
}
