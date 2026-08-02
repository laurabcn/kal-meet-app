<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use App\Shared\Infrastructure\Symfony\Security\Exception\JwksFetchFailedException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpJwksFetcher implements JwksFetcherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $jwksUrl,
        private int $timeoutSeconds = 5,
    ) {
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JwksFetchFailedException
     */
    public function fetch(): array
    {
        try {
            return $this->httpClient
                ->request('GET', $this->jwksUrl, ['timeout' => $this->timeoutSeconds])
                ->toArray();
        } catch (HttpClientExceptionInterface $exception) {
            throw new JwksFetchFailedException($exception->getMessage(), previous: $exception);
        }
    }
}
