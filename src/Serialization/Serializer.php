<?php declare(strict_types=1);

namespace Ruudk\Absurd\Serialization;

/**
 * Interface for serializing and deserializing data for storage.
 */
interface Serializer
{
    /**
     * Encode a value for storage.
     */
    public function encode(mixed $value): string;

    /**
     * Decode a stored value.
     *
     * @template T of object
     * @param class-string<T>|null $type The target class to deserialize into
     * @return ($type is null ? mixed : T)
     */
    public function decode(string $data, ?string $type = null): mixed;
}
