<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Kal\Domain\Mother\FileMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function replacedFileBody(array $overrides = []): array
{
    return [
        'fileName' => 'patro.pdf',
        'filePath' => '01J5M6XQBR4GTYHN8KZXP0F1W3/ca.pdf',
        'fileSize' => 184320,
        'fileExtension' => 'pdf',
        'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1B1',
        'uploadedAt' => '2026-10-03 12:00:00',
        ...$overrides,
    ];
}

it('sets the pattern file of a kal that had none and answers 204 with an empty body', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($client->getResponse()->getContent())->toBe('')
        ->and($repository->all()[0]->file?->uploadId->value())->toBe('01J5M6XQBR4GTYHN8KZXP0F1B1')
        ->and($repository->all()[0]->updatedAt)->not->toBeNull();
});

it('replaces the pattern file a kal already had', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        file: FileMother::create('old.pdf'),
    );
    $repository->create($kal);

    $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($repository->all()[0]->file?->fileName->value())->toBe('patro.pdf')
        ->and($repository->fileReplacements())->toBe(1);
});

it('answers 204 to a retry and writes nothing the second time', function (): void {
    $client = static::createClient();
    // Sense això el segon request arrenca un kernel nou, amb un repositori buit.
    $client->disableReboot();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);
    $send = fn () => $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody()),
    );
    $send();
    $updatedAt = $repository->all()[0]->updatedAt?->value();

    $send();

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($repository->fileReplacements())->toBe(1)
        ->and($repository->all()[0]->updatedAt?->value())->toBe($updatedAt);
});

it('answers 404 when the authenticated user is not the organizer', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = KalMother::create();
    $repository->create($kal);

    $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}')
        ->and($repository->all()[0]->file)->toBeNull();
});

it('answers 404 when the kal does not exist', function (): void {
    $client = static::createClient();

    $client->request(
        'PUT',
        '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 404 when the kal is soft-deleted', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);
    $repository->softDelete($kal->id->value());

    $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 400 with the code of the error and keeps the live file', function (array $body, string $expected): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $live = FileMother::create('live.pdf');
    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID), file: $live);
    $repository->create($kal);

    $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode($body),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe($expected)
        ->and($repository->fileReplacements())->toBe(0)
        ->and($repository->liveFile($kal->id->value())?->uploadId->equals($live->uploadId))->toBeTrue();
})->with([
    'missing fileName' => [
        array_diff_key(replacedFileBody(), ['fileName' => true]),
        '{"error":"The request payload is invalid.","code":"invalid_payload"}',
    ],
    'fileSize of the wrong type' => [
        replacedFileBody(['fileSize' => '184320']),
        '{"error":"The request payload is invalid.","code":"invalid_payload"}',
    ],
    'a locale of its own' => [
        replacedFileBody(['locale' => 'ca']),
        '{"error":"The request payload is invalid.","code":"invalid_payload"}',
    ],
    'fileSize of zero' => [
        replacedFileBody(['fileSize' => 0]),
        '{"error":"The file size must be greater than zero.","code":"file_size_not_positive"}',
    ],
    'fileExtension not allowed' => [
        replacedFileBody(['fileExtension' => 'docx']),
        '{"error":"Invalid file type: docx","code":"kal_file_invalid_type"}',
    ],
]);

it('answers 400 file_size_exceeded for a file above the limit', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody(['fileSize' => 50 * 1024 * 1024])),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe(
            '{"error":"The file size 52428800 exceeds the maximum allowed size of 5242880 bytes","code":"file_size_exceeded"}',
        )
        ->and($repository->liveFile($kal->id->value()))->toBeNull();
});

it('answers 400 invalid_payload when the upload id was already used by another file', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $theirs = FileMother::create('theirs.pdf');
    $repository->create(KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID), file: $theirs));
    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody(['uploadId' => $theirs->uploadId->value()])),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())
        ->toBe('{"error":"The upload id has already been used by another file.","code":"invalid_payload"}')
        ->and($repository->liveFile($kal->id->value()))->toBeNull();
});

it('answers 500 kal_persistence_failed and keeps the live file when the write fails', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $live = FileMother::create('live.pdf');
    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID), file: $live);
    $repository->create($kal);
    $repository->failWith(KalStateException::persistenceFailed(new RuntimeException('connection lost')));

    $client->request(
        'PUT',
        '/kal/'.$kal->id->value().'/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Failed to persist the kal.","code":"kal_persistence_failed"}')
        ->and($repository->liveFile($kal->id->value())?->uploadId->equals($live->uploadId))->toBeTrue();
});

it('answers 400 invalid_payload when the kal id is not a ULID', function (): void {
    $client = static::createClient();

    $client->request(
        'PUT',
        '/kal/not-a-ulid/file',
        server: apiJsonHeaders(),
        content: (string) json_encode(replacedFileBody()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 400 invalid_json when the body is not JSON', function (): void {
    $client = static::createClient();

    $client->request(
        'PUT',
        '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9/file',
        server: apiJsonHeaders(),
        content: '{not json',
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request body is not valid JSON.","code":"invalid_json"}');
});

it('answers 401 without an authorization header', function (): void {
    $client = static::createClient();

    $client->request(
        'PUT',
        '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9/file',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: (string) json_encode(replacedFileBody()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});
