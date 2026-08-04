<?php

declare(strict_types=1);

namespace App\Kal\Application\Query\GetKal;

use App\Kal\Domain\Clue;
use App\Kal\Domain\File;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Meeting;
use App\Shared\Application\Query\ResponseInterface;
use App\Shared\Domain\ValueObject\Locale;

final readonly class GetKalResponse implements ResponseInterface
{
    public function __construct(private Kal $kal)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function result(): array
    {
        return [
            'id' => $this->kal->id->value(),
            'name' => $this->kal->name->value(),
            'description' => $this->kal->description?->value(),
            'startsOn' => $this->kal->startsOn->value(),
            'endsOn' => $this->kal->endsOn?->value(),
            'coverPath' => $this->kal->coverPath,
            'locales' => array_map(
                static fn (Locale $locale): string => $locale->value(),
                $this->kal->locales->all(),
            ),
            'inviteToken' => $this->kal->inviteToken->value(),
            'meetings' => array_map(self::meeting(...), $this->kal->meetings->all()),
            'clues' => array_map(self::clue(...), $this->kal->clues->all()),
            'files' => array_map(self::file(...), $this->kal->files->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function meeting(Meeting $meeting): array
    {
        return [
            'id' => $meeting->id->value(),
            'scheduledAt' => $meeting->scheduledAt->value(),
            'url' => $meeting->url->value(),
            'title' => $meeting->title->value(),
            'timezone' => $meeting->timezone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function clue(Clue $clue): array
    {
        return [
            'id' => $clue->id->value(),
            'name' => $clue->name->value(),
            'description' => $clue->description?->value(),
            'startsOn' => $clue->startsOn->value(),
            'endsOn' => $clue->endsOn->value(),
            'updatedAt' => $clue->updatedAt->value(),
            'locale' => $clue->locale->value(),
            'file' => self::file($clue->file),
            'meeting' => self::meeting($clue->meeting),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function file(File $file): array
    {
        return [
            'fileName' => $file->fileName->value(),
            'filePath' => $file->filePath->value(),
            'fileSize' => $file->fileSize->value(),
            'fileExtension' => $file->fileExtension->value(),
            'locale' => $file->locale->value(),
            'uploadId' => $file->uploadId->value(),
            'uploadedAt' => $file->uploadedAt->value(),
        ];
    }
}
