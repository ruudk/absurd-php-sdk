<?php declare(strict_types=1);

namespace Ruudk\Absurd\Serialization;

use JsonException;
use Ruudk\Absurd\Exception\SerializationException;

final readonly class JsonSerializer implements SerializerInterface
{
    /**
     * @throws SerializationException If serialization fails
     */
    public function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializationException('Failed to encode value: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws SerializationException If deserialization fails
     */
    public function decode(string $data, ?string $type = null): mixed
    {
        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializationException('Failed to decode JSON: ' . $e->getMessage(), 0, $e);
        }
    }
}
