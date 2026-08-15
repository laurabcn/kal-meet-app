<?php

declare(strict_types=1);

use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

/** El KAL de l'organitzadora autenticada, prou ample per encabir-hi pistes. */
function organizerKal(InMemoryKalRepository $repository): App\Kal\Domain\Kal
{
    $kal = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        endsOn: DateTime::create('2026-12-01 00:00:00'),
    );
    $repository->create($kal);

    return $kal;
}

it('creates a clue and answers 201 with its id', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);

    $client->request(
        'POST',
        '/kal/'.$kal->id->value().'/clue',
        server: apiJsonHeaders(),
        content: (string) json_encode(cluePayload()),
    );

    $payload = json_decode((string) $client->getResponse()->getContent(), true);

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED)
        ->and($payload['data']['id'])->toBeString()
        ->and($kal->clues->all())->toHaveCount(1)
        ->and($kal->clues->all()[0]->id->value())->toBe($payload['data']['id']);
});

it('answers 400 when the clue payload has no meeting', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);

    $payload = cluePayload();
    unset($payload['meeting']);

    $client->request(
        'POST',
        '/kal/'.$kal->id->value().'/clue',
        server: apiJsonHeaders(),
        content: (string) json_encode($payload),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($kal->clues->all())->toBeEmpty();
});

it('answers 400 when the clue falls outside the kal range', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);

    $payload = cluePayload();
    $payload['startsOn'] = '2026-01-01 00:00:00';
    $payload['endsOn'] = '2026-01-10 00:00:00';
    $payload['meeting']['scheduledAt'] = '2026-01-02 18:00:00';

    $client->request(
        'POST',
        '/kal/'.$kal->id->value().'/clue',
        server: apiJsonHeaders(),
        content: (string) json_encode($payload),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())
        ->toBe('{"error":"A clue date range falls outside the kal date range.","code":"kal_clue_outside_range"}');
});

it('answers 404 when creating a clue on a kal that is not yours', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create();
    $repository->create($kal);

    $client->request(
        'POST',
        '/kal/'.$kal->id->value().'/clue',
        server: apiJsonHeaders(),
        content: (string) json_encode(cluePayload()),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('patches a clue and answers 204', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);
    $clue = ClueMother::create(name: 'Abans');
    $kal->addClue($clue);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value().'/clue/'.$clue->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'Després']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($client->getResponse()->getContent())->toBe('')
        ->and($kal->clues->all()[0]->name->value())->toBe('Després');
});

it('answers 400 when the patch carries the meeting', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);
    $clue = ClueMother::create();
    $kal->addClue($clue);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value().'/clue/'.$clue->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['meeting' => ['url' => 'https://example.com/nova']]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())
        ->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 400 when shortening a clue below its own meeting', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);
    $clue = ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-08-20 00:00:00'),
    );
    $kal->addClue($clue);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value().'/clue/'.$clue->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['startsOn' => '2026-08-10 00:00:00']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())
        ->toBe('{"error":"A meeting is scheduled outside its clue date range.","code":"kal_meeting_outside_clue_range"}');
});

it('answers 404 when patching a clue that is not in the kal', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value().'/clue/01J5M6XQBR4GTYHN8KZXP0F1C9',
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'Nope']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Clue not found.","code":"clue_not_found"}');
});

it('deletes a clue and answers 204', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);
    $clue = ClueMother::create();
    $kal->addClue($clue);

    $client->request(
        'DELETE',
        '/kal/'.$kal->id->value().'/clue/'.$clue->id->value(),
        server: apiJsonHeaders(),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($client->getResponse()->getContent())->toBe('')
        ->and($kal->clues->all())->toBeEmpty()
        ->and($repository->deletedClueIds())->toBe([$clue->id->value()]);
});

it('answers 404 when deleting the same clue twice', function (): void {
    $client = static::createClient();
    // Sense això el kernel es reinicia entre peticions, el doble en memòria es
    // buida i la segona crida donaria kal_not_found: amagaria el que es prova.
    $client->disableReboot();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);
    $clue = ClueMother::create();
    $kal->addClue($clue);
    $path = '/kal/'.$kal->id->value().'/clue/'.$clue->id->value();

    $client->request('DELETE', $path, server: apiJsonHeaders());
    $client->request('DELETE', $path, server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Clue not found.","code":"clue_not_found"}');
});

it('answers 400 when the clue id in the path is not a ulid', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    $kal = organizerKal($repository);

    $client->request('DELETE', '/kal/'.$kal->id->value().'/clue/not-a-ulid', server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())
        ->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 401 on every clue endpoint without a token', function (): void {
    $client = static::createClient();
    $path = '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9/clue';
    $headers = ['CONTENT_TYPE' => 'application/json'];

    $client->request('POST', $path, server: $headers, content: '{}');
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);

    $client->request('PATCH', $path.'/01J5M6XQBR4GTYHN8KZXP0F1C1', server: $headers, content: '{}');
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);

    $client->request('DELETE', $path.'/01J5M6XQBR4GTYHN8KZXP0F1C1', server: $headers);
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});
