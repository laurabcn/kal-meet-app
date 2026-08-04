<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final readonly class UndecodifiableMessage implements EventMessageInterface
{
    private const string STAMPS = '__stamps';

    /**
     * @param array<string, mixed> $envelope
     */
    private function __construct(
        private array $envelope,
    ) {
    }

    /**
     * @param array<string, mixed> $envelope
     */
    public static function create(array $envelope): self
    {
        $envelope = self::removeStamps($envelope);

        return new self($envelope);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $message
     * @param array<string, mixed> $metadata
     *
     * @throws \BadMethodCallException
     */
    public static function createFromMessagePayloads(array $data, array $message, array $metadata): self
    {
        throw new \BadMethodCallException('This method is not implemented.');
    }

    /**
     * @throws \BadMethodCallException
     */
    public function id(): UlidValue
    {
        throw new \BadMethodCallException('This method is not implemented.');
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function name(): NonEmptyStringValue
    {
        return NonEmptyStringValue::create('undecodifiable_message');
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function version(): NonEmptyStringValue
    {
        return NonEmptyStringValue::create('1.0.0');
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function boundedContext(): NonEmptyStringValue
    {
        return NonEmptyStringValue::create('internal');
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->envelope;
    }

    /**
     * @return array<string, mixed>
     */
    public function event(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $envelope
     *
     * @return array<string, mixed>
     */
    private static function removeStamps(array $envelope): array
    {
        if (!isset($envelope['body'])) {
            return $envelope;
        }

        if (!is_string($envelope['body'])) {
            return $envelope;
        }

        try {
            /** @var array<string, mixed> $body */
            $body = json_decode($envelope['body'], true, 512, JSON_THROW_ON_ERROR);
            unset($body[self::STAMPS]);
            $envelope['body'] = json_encode($body, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
        }

        return $envelope;
    }
}
