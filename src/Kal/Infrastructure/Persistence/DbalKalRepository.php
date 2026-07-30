<?php

declare(strict_types=1);

namespace App\Kal\Infrastructure\Persistence;

use App\Kal\Domain\Clue;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\File;
use App\Kal\Domain\Kal;
use App\Kal\Domain\KalRepositoryInterface;
use App\Kal\Domain\Meeting;
use App\Shared\Domain\ValueObject\UlidValue;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final readonly class DbalKalRepository implements KalRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws KalException
     */
    public function create(Kal $kal): void
    {
        try {
            $this->connection->beginTransaction();

            $this->insertKal($kal);
            $this->insertLocales($kal);
            $this->insertFiles($kal);
            $this->insertClues($kal);
            $this->insertMeetings($kal);
            $this->insertDebateRoom($kal);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->safeRollBack();

            throw KalException::persistenceFailed($e);
        }
    }

    private function safeRollBack(): void
    {
        try {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        } catch (\Throwable $rollBackException) {
            $this->logger->error('Failed to roll back Kal transaction', [
                'exception' => $rollBackException,
            ]);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function insertKal(Kal $kal): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO kals (id, organizer_id, name, description, starts_on, ends_on, cover_path, invite_token, created_at, updated_at)
                VALUES (:id, :organizer_id, :name, :description, :starts_on, :ends_on, :cover_path, :invite_token, :created_at, :updated_at)
                SQL,
            [
                'id' => $kal->id->value(),
                'organizer_id' => $kal->organizerId->value(),
                'name' => $kal->name->value(),
                'description' => $kal->description?->value(),
                'starts_on' => $kal->startsOn->value(),
                'ends_on' => $kal->endsOn?->value(),
                'cover_path' => $kal->coverPath,
                'invite_token' => $kal->inviteToken->value(),
                'created_at' => $kal->createdAt->value(),
                'updated_at' => $kal->updatedAt->value(),
            ],
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function insertLocales(Kal $kal): void
    {
        foreach ($kal->locales->all() as $locale) {
            $this->connection->executeStatement(
                'INSERT INTO kal_locales (kal_id, locale) VALUES (:kal_id, :locale)',
                [
                    'kal_id' => $kal->id->value(),
                    'locale' => $locale->value(),
                ],
            );
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function insertFiles(Kal $kal): void
    {
        foreach ($kal->files->all() as $file) {
            $this->insertFile($kal->id->value(), $file);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function insertFile(string $kalId, File $file): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO kal_files (upload_id, kal_id, file_name, file_path, file_size, file_extension, locale, uploaded_at)
                VALUES (:upload_id, :kal_id, :file_name, :file_path, :file_size, :file_extension, :locale, :uploaded_at)
                SQL,
            [
                'upload_id' => $file->uploadId->value(),
                'kal_id' => $kalId,
                'file_name' => $file->fileName->value(),
                'file_path' => $file->filePath->value(),
                'file_size' => $file->fileSize->value(),
                'file_extension' => $file->fileExtension->value(),
                'locale' => $file->locale->value(),
                'uploaded_at' => $file->uploadedAt->value(),
            ],
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function insertClues(Kal $kal): void
    {
        foreach ($kal->clues->all() as $clue) {
            $this->insertClue($kal->id->value(), $clue);
            // La reunió va després de la pista: meetings.clue_id té FK cap a clues.
            $this->insertMeeting($kal->id->value(), $clue->meeting, $clue->id->value());
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function insertClue(string $kalId, Clue $clue): void
    {
        $file = $clue->file;

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO clues (id, kal_id, name, description, starts_on, ends_on, updated_at, locale, file_name, file_path, file_size, file_extension, file_locale, file_upload_id, file_uploaded_at)
                VALUES (:id, :kal_id, :name, :description, :starts_on, :ends_on, :updated_at, :locale, :file_name, :file_path, :file_size, :file_extension, :file_locale, :file_upload_id, :file_uploaded_at)
                SQL,
            [
                'id' => $clue->id->value(),
                'kal_id' => $kalId,
                'name' => $clue->name->value(),
                'description' => $clue->description?->value(),
                'starts_on' => $clue->startsOn->value(),
                'ends_on' => $clue->endsOn->value(),
                'updated_at' => $clue->updatedAt->value(),
                'locale' => $clue->locale->value(),
                'file_name' => $file->fileName->value(),
                'file_path' => $file->filePath->value(),
                'file_size' => $file->fileSize->value(),
                'file_extension' => $file->fileExtension->value(),
                'file_locale' => $file->locale->value(),
                'file_upload_id' => $file->uploadId->value(),
                'file_uploaded_at' => $file->uploadedAt->value(),
            ],
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function insertMeetings(Kal $kal): void
    {
        foreach ($kal->meetings->all() as $meeting) {
            $this->insertMeeting($kal->id->value(), $meeting);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function insertMeeting(string $kalId, Meeting $meeting, ?string $clueId = null): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO meetings (id, kal_id, clue_id, title, url, scheduled_at, timezone)
                VALUES (:id, :kal_id, :clue_id, :title, :url, :scheduled_at, :timezone)
                SQL,
            [
                'id' => $meeting->id->value(),
                'kal_id' => $kalId,
                'clue_id' => $clueId,
                'title' => $meeting->title->value(),
                'url' => $meeting->url->value(),
                'scheduled_at' => $meeting->scheduledAt->value(),
                'timezone' => $meeting->timezone,
            ],
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \App\Shared\Domain\Exception\InvalidArgumentException
     */
    private function insertDebateRoom(Kal $kal): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO debate_rooms (id, kal_id, created_at)
                VALUES (:id, :kal_id, :created_at)
                SQL,
            [
                'id' => UlidValue::generate()->value(),
                'kal_id' => $kal->id->value(),
                'created_at' => $kal->createdAt->value(),
            ],
        );
    }
}
