<?php declare(strict_types=1);

namespace Ruudk\Absurd\Serialization;

use JsonException;
use Ruudk\Absurd\Exception\SerializationException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Symfony Serializer implementation with support for typed deserialization.
 */
final readonly class SymfonySerializer implements Serializer
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {}

    /**
     * @throws SerializationException If serialization fails
     */
    public function encode(mixed $value): string
    {
        try {
            return $this->serializer->serialize($value, 'json');
        } catch (SerializerException $e) {
            throw new SerializationException('Failed to encode value: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws SerializationException If deserialization fails
     */
    public function decode(string $data, ?string $type = null): mixed
    {
        try {
            if ($type === null) {
                return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            }

            return $this->serializer->deserialize($data, $type, 'json');
        } catch (JsonException $e) {
            throw new SerializationException('Failed to decode JSON: ' . $e->getMessage(), 0, $e);
        } catch (SerializerException $e) {
            throw new SerializationException('Failed to deserialize value: ' . $e->getMessage(), 0, $e);
        }
    }
}
