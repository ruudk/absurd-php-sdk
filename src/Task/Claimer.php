<?php declare(strict_types=1);

namespace Ruudk\Absurd\Task;

use PDO;
use Ruudk\Absurd\Serialization\Serializer;

/**
 * Handles claiming tasks from the queue.
 *
 * @internal
 */
final readonly class Claimer
{
    public function __construct(
        private PDO $pdo,
        private string $queueName,
        private Serializer $serializer,
    ) {}

    /**
     * Claim tasks from the queue.
     *
     * @return list<ClaimedTask>
     */
    public function claim(string $workerId, int $claimTimeout, int $batchSize): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT run_id, task_id, attempt, task_name, params, retry_strategy, max_attempts,
                    headers, wake_event, event_payload
             FROM absurd.claim_task(:queue, :worker_id, :claim_timeout, :batch_size)',
        );

        if ($stmt === false) {
            return [];
        }

        $stmt->execute([
            'queue' => $this->queueName,
            'worker_id' => $workerId,
            'claim_timeout' => $claimTimeout,
            'batch_size' => $batchSize,
        ]);

        $tasks = [];
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            /** @var array<string, mixed>|null $headers */
            $headers = $row['headers'] !== null ? $this->serializer->decode((string) $row['headers']) : null;

            $tasks[] = new ClaimedTask(
                runId: (string) $row['run_id'],
                taskId: (string) $row['task_id'],
                attempt: (int) $row['attempt'],
                taskName: (string) $row['task_name'],
                rawParams: (string) $row['params'],
                retryStrategy: $row['retry_strategy'] !== null ? (string) $row['retry_strategy'] : null,
                maxAttempts: (int) $row['max_attempts'],
                headers: $headers,
                wakeEvent: $row['wake_event'] !== null ? (string) $row['wake_event'] : null,
                eventPayload: $row['event_payload'] !== null
                    ? $this->serializer->decode((string) $row['event_payload'])
                    : null,
            );
        }

        return $tasks;
    }
}
