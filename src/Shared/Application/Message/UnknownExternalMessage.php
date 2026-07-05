<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final readonly class UnknownExternalMessage implements ExternalMessageInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $event
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private UlidValue $id,
        private array $data,
        private array $event,
        private array $metadata,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $message
     * @param array<string, mixed> $metadata
     *
     * @throws InvalidArgumentException
     */
    public static function createFromMessagePayloads(
        array $data,
        array $message,
        array $metadata,
    ): self {
        return new self(
            UlidValue::generate(),
            $data,
            $message,
            $metadata
        );
    }

    public function id(): UlidValue
    {
        return $this->id;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function name(): NonEmptyStringValue
    {
        return new NonEmptyStringValue('unknown_external_message');
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function version(): NonEmptyStringValue
    {
        return new NonEmptyStringValue('1.0.0');
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function boundedContext(): NonEmptyStringValue
    {
        return new NonEmptyStringValue('external');
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function event(): array
    {
        return $this->event;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
