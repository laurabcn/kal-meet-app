<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus\Factory;

use App\Shared\Application\Message\ExternalMessageInterface;
use App\Shared\Application\Message\Factory\DefaultMessageFactoryInterface;
use App\Shared\Application\Message\UnknownExternalMessage;
use App\Shared\Domain\Exception\InvalidArgumentException;

final class DefaultMessageFactory implements DefaultMessageFactoryInterface
{
    /**
     * @var array<string, class-string<ExternalMessageInterface>>
     */
    private array $keyToClassNameMap;

    public function __construct()
    {
        $this->keyToClassNameMap = [];
    }

    /** @throws InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    public function create(
        string $messageName,
        array $payloadData,
        array $messageData,
        array $metadata,
    ): ExternalMessageInterface {
        if (!$this->messageNameExistsInMap($messageName)) {
            return UnknownExternalMessage::createFromMessagePayloads(
                $payloadData,
                $messageData,
                $metadata
            );
        }

        /** @var ExternalMessageInterface $messageClass */
        $messageClass = $this->keyToClassNameMap[$messageName];

        return $messageClass::createFromMessagePayloads(
            $payloadData,
            $messageData,
            $metadata
        );
    }

    private function messageNameExistsInMap(string $messageName): bool
    {
        return array_key_exists($messageName, $this->keyToClassNameMap);
    }

    /**
     * @param array<class-string<ExternalMessageInterface>> $messageClassNames
     *
     * @throws \InvalidArgumentException
     */
    public function addMessagesToMap(array $messageClassNames): void
    {
        foreach ($messageClassNames as $messageClassName) {
            $this->keyToClassNameMap[$messageClassName::name()->value()] = $messageClassName;
        }
    }
}
