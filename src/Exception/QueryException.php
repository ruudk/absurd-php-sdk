<?php

declare(strict_types=1);

namespace Ruudk\Absurd\Exception;

class QueryException extends \RuntimeException
{
    public function __construct(
        public readonly ?string $sqlState,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isConnectionError(): bool
    {
        if (str_starts_with($this->sqlState ?? '', '08')) {
            return true;
        }

        // SQLSTATE HY000 with driver code 7 = "no connection to the server"
        // SQLSTATE 08xxx = connection exceptions in SQL standard
        $message = strtolower($this->getMessage());
        return (
            str_contains($message, 'no connection')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection timed out')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
        );
    }

    public function isAbsurdCancelled(): bool
    {
        return $this->sqlState === 'AB001';
    }

    public function isAbsurdFailed(): bool
    {
        return $this->sqlState === 'AB002';
    }
}
