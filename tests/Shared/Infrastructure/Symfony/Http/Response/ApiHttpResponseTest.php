<?php

declare(strict_types=1);

use App\Shared\Application\Query\ResponseInterface;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpCreatedResponse;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpNoContentResponse;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpOkResponse;
use Symfony\Component\HttpFoundation\Response;

it('wraps a query response in a data envelope', function (): void {
    $queryResponse = new class implements ResponseInterface {
        public function result(): array
        {
            return ['id' => '01J5M6XQBR4GTYHN8KZXP0F1W3', 'name' => 'KAL'];
        }
    };

    $response = new ApiHttpOkResponse($queryResponse);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->getContent())->toBe('{"data":{"id":"01J5M6XQBR4GTYHN8KZXP0F1W3","name":"KAL"}}');
});

it('answers 201 with an empty json object for creates', function (): void {
    $response = new ApiHttpCreatedResponse();

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED)
        ->and($response->getContent())->toBe('{}');
});

it('answers 204 with an empty body', function (): void {
    $response = new ApiHttpNoContentResponse();

    expect($response->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($response->getContent())->toBe('');
});
