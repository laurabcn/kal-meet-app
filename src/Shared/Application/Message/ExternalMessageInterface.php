<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

interface ExternalMessageInterface
{
    public const string KITT_API_DEFAULT_BOUNDED_CONTEXT = 'kitt_api';
    /** @var string */
    public const string MESSAGE_VERSION = '1.0.0';
    public const string BUNDLE_CONTEXT_METADATA_FIELD = 'bundle_context';
    public const string MESSAGE_ID_FIELD = 'id';
    public const string MESSAGE_NAME_FIELD = 'name';
    public const string MESSAGE_VERSION_FIELD = 'version';

    public function id(): UlidValue;

    public static function name(): NonEmptyStringValue;

    public static function version(): NonEmptyStringValue;

    public static function boundedContext(): ?NonEmptyStringValue;

    /**
     * @return array<string, mixed>|null
     */
    public function data(): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $message
     * @param array<string, mixed> $metadata
     */
    public static function createFromMessagePayloads(
        array $data,
        array $message,
        array $metadata,
    ): self;
}
