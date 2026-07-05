<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Repository;

final readonly class MessageStoreRepositoryMapper
{
    /** @param array<string, MessageStoreRepositoryInterface> $repositories keyed by bounded context name */
    public function __construct(private array $repositories = [])
    {
    }

    public function find(string $key): ?MessageStoreRepositoryInterface
    {
        if (isset($this->repositories[$key])) {
            return $this->repositories[$key];
        }

        // Transport names follow the convention {bc-kebab-case}-{suffix}, e.g.
        // ai-visibility-property-management-consumer → ai_visibility
        return array_find(
            $this->repositories,
            fn ($_, $bcName) => str_starts_with($key, str_replace('_', '-', $bcName).'-'),
        );
    }
}
