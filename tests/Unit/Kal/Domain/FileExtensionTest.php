<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalFileException;
use App\Kal\Domain\FileExtension;

it('reads an allowed extension', function (): void {
    expect(FileExtension::tryFromStatus('pdf'))->toBe(FileExtension::PDF);
});

it('rejects an extension that is not allowed as an invalid type, not an invalid status', function (): void {
    // Llançava l'excepció de `FileUploadStatus` (kal_file_invalid_status),
    // copiada d'allà; el client rebia «The file status is invalid».
    expect(fn () => FileExtension::tryFromStatus('docx'))
        ->toThrow(KalFileException::class, 'Invalid file type: docx');

    try {
        FileExtension::tryFromStatus('docx');
    } catch (KalFileException $exception) {
        expect($exception->errorCode())->toBe('kal_file_invalid_type');
    }
});
