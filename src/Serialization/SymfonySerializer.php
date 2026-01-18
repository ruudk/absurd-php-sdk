<?php declare(strict_types=1);

namespace Ruudk\Absurd\Serialization;

use Symfony\Component\Serializer\SerializerInterface;

/**
 * Symfony Serializer implementation with support for typed deserialization.
 */
final readonly class SymfonySerializer implements Serializer
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {}

    public function encode(mixed $value): string
    {
        return $this->serializer->serialize($value, 'json');
    }

    public function decode(string $data, ?string $type = null): mixed
    {
        if ($type === null) {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }

        return $this->serializer->deserialize($data, $type, 'json');
    }
}
