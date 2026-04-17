<?php

declare(strict_types=1);

namespace Ruudk\Absurd\Connection;

use Ruudk\Absurd\Exception\QueryException;

interface Connection
{
    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     * @throws QueryException
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * @param array<string, mixed> $params
     * @throws QueryException
     */
    public function fetch(string $sql, array $params = []): array|false;

    public function execute(string $sql, array $params = []): void;

    public function scalar(string $sql, array $params = []): int|string|bool|float|null;
}
