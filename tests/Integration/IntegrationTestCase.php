<?php declare(strict_types=1);

namespace Ruudk\Absurd\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Serialization\SymfonySerializer;
use Ruudk\Absurd\Task\ClaimOptions;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

abstract class IntegrationTestCase extends TestCase
{
    protected static ?PDO $pdo = null;
    protected Absurd $absurd;
    protected string $queueName;

    public static function setUpBeforeClass(): void
    {
        $databaseUrl = $_ENV['ABSURD_DATABASE_URL'] ?? getenv('ABSURD_DATABASE_URL');

        if ($databaseUrl === false || $databaseUrl === '') {
            static::markTestSkipped('ABSURD_DATABASE_URL not set');
        }

        self::$pdo = new PDO($databaseUrl);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            static::markTestSkipped('Database not available');
        }

        $this->queueName = 'test-' . bin2hex(random_bytes(4));
        $this->absurd = new Absurd(self::$pdo, $this->createSerializer(), $this->queueName);
        $this->absurd->createQueue();
    }

    private function createSerializer(): SymfonySerializer
    {
        return new SymfonySerializer(new Serializer(normalizers: [
            new BackedEnumNormalizer(),
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(),
        ], encoders: [new JsonEncoder()]));
    }

    protected function tearDown(): void
    {
        if (self::$pdo !== null) {
            $stmt = self::$pdo->prepare('SELECT absurd.drop_queue(?)');
            $stmt->execute([$this->queueName]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo = null;
    }

    protected function processAllTasks(int $claimTimeout = 120): void
    {
        $tasks = $this->absurd->claimTasks(new ClaimOptions(batchSize: 10, claimTimeout: $claimTimeout));

        foreach ($tasks as $task) {
            $this->absurd->executeTask($task, $claimTimeout, fatalOnLeaseTimeout: false);
        }
    }
}
