<?php

declare(strict_types=1);

namespace Ruudk\Absurd\Connection;

use Ruudk\Absurd\Exception\QueryException;

final readonly class PdoConnection implements Connection
{
    public function __construct(
        private \PDO $pdo,
    ) {}

    private function prepare(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        if ($stmt === false) {
            throw new QueryException(null, 'Preparing statement failed');
        }

        return $stmt;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);

            /**
             * @var list<array<string, mixed>> $result
             * @mago-expect lint:inline-variable-return for satisfying the analysier
             */
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch (\PDOException $e) {
            throw new QueryException((string) $e->getCode(), $e->getMessage(), $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            /** @var array<string, mixed>|false $result */
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result === false ? null : $result;
        } catch (\PDOException $e) {
            throw new QueryException((string) $e->getCode(), $e->getMessage(), $e);
        }
    }

    public function execute(string $sql, array $params = []): void
    {
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            throw new QueryException((string) $e->getCode(), $e->getMessage(), $e);
        }
    }

    public function scalar(string $sql, array $params = []): int|string|bool|float|null
    {
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            /**
             * @var int|string|bool|float|null $value
             * @mago-expect lint:inline-variable-return for satisfying the analysier
             */
            $value = $stmt->fetchColumn();
            return $value;
        } catch (\PDOException $e) {
            throw new QueryException((string) $e->getCode(), $e->getMessage(), $e);
        }
    }
}
