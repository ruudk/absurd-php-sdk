<?php declare(strict_types=1);

namespace Ruudk\Absurd\Serialization;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SymfonySerializerTest extends TestCase
{
    private SymfonySerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new SymfonySerializer(new Serializer(normalizers: [
            new BackedEnumNormalizer(),
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(),
        ], encoders: [new JsonEncoder()]));
    }

    #[Test]
    public function encodesScalarValues(): void
    {
        static::assertSame('"hello"', $this->serializer->encode('hello'));
        static::assertSame('42', $this->serializer->encode(42));
        static::assertSame('3.14', $this->serializer->encode(3.14));
        static::assertSame('true', $this->serializer->encode(true));
        static::assertSame('null', $this->serializer->encode(null));
    }

    #[Test]
    public function encodesArrays(): void
    {
        $data = ['foo' => 'bar', 'count' => 5];

        static::assertSame('{"foo":"bar","count":5}', $this->serializer->encode($data));
    }

    #[Test]
    public function encodesObjects(): void
    {
        $data = new TestPayload('test-id', 42);

        $encoded = $this->serializer->encode($data);

        static::assertSame('{"id":"test-id","value":42}', $encoded);
    }

    #[Test]
    public function encodesDateTime(): void
    {
        $date = new DateTimeImmutable('2024-01-15T10:30:00+00:00');

        $encoded = $this->serializer->encode($date);

        static::assertSame('"2024-01-15T10:30:00+00:00"', $encoded);
    }

    #[Test]
    public function decodesWithoutTypeReturnsArray(): void
    {
        $json = '{"foo":"bar","count":5}';

        $decoded = $this->serializer->decode($json);

        static::assertSame(['foo' => 'bar', 'count' => 5], $decoded);
    }

    #[Test]
    public function decodesWithTypeReturnsObject(): void
    {
        $json = '{"id":"test-id","value":42}';

        $decoded = $this->serializer->decode($json, TestPayload::class);

        static::assertInstanceOf(TestPayload::class, $decoded);
        static::assertSame('test-id', $decoded->id);
        static::assertSame(42, $decoded->value);
    }

    #[Test]
    public function roundTripWithObject(): void
    {
        $original = new TestPayload('round-trip', 100);

        $encoded = $this->serializer->encode($original);
        $decoded = $this->serializer->decode($encoded, TestPayload::class);

        static::assertInstanceOf(TestPayload::class, $decoded);
        static::assertSame($original->id, $decoded->id);
        static::assertSame($original->value, $decoded->value);
    }

    #[Test]
    public function decodesNestedArrays(): void
    {
        $json = '{"items":[{"name":"a"},{"name":"b"}]}';

        $decoded = $this->serializer->decode($json);

        static::assertSame(
            [
                'items' => [
                    ['name' => 'a'],
                    ['name' => 'b'],
                ],
            ],
            $decoded,
        );
    }
}

final readonly class TestPayload
{
    public function __construct(
        public string $id,
        public int $value,
    ) {}
}
