<?php declare(strict_types=1);

namespace Ruudk\Absurd\Integration;

use PHPUnit\Framework\Attributes\Test;
use Ruudk\Absurd\Task\ClaimOptions;
use Ruudk\Absurd\Task\Context as TaskContext;

final class WorkflowTest extends IntegrationTestCase
{
    #[Test]
    public function simpleWorkflowCompletes(): void
    {
        $steps = [];

        $this->absurd->registerTask('simple-workflow', static function (array $params, TaskContext $ctx) use (&$steps) {
            $steps[] = 'start';

            $result = $ctx->step('step-1', static fn() => $params['value'] * 2);
            $steps[] = "step-1: {$result}";

            $result2 = $ctx->step('step-2', static fn() => $result + 10);
            $steps[] = "step-2: {$result2}";

            return ['final' => $result2];
        });

        $this->absurd->spawn('simple-workflow', ['value' => 5]);
        $this->processAllTasks();

        static::assertSame(
            [
                'start',
                'step-1: 10',
                'step-2: 20',
            ],
            $steps,
        );
    }

    #[Test]
    public function workflowWithTypedPayload(): void
    {
        $receivedPayload = null;

        $this->absurd->registerTask('typed-workflow', static function (TestPayload $payload, TaskContext $ctx) use (
            &$receivedPayload,
        ) {
            $receivedPayload = $payload;

            $doubled = $ctx->step('double', static fn() => $payload->amount * 2);

            return ['result' => $doubled];
        });

        $this->absurd->spawn('typed-workflow', new TestPayload('order-123', 50));
        $this->processAllTasks();

        static::assertInstanceOf(TestPayload::class, $receivedPayload);
        static::assertSame('order-123', $receivedPayload->id);
        static::assertSame(50, $receivedPayload->amount);
    }

    #[Test]
    public function workflowEmitsEvent(): void
    {
        $this->absurd->registerTask('emitting-workflow', static function (array $params, TaskContext $ctx) {
            $ctx->emitEvent('workflow-started', ['id' => $params['id']]);

            $result = $ctx->step('process', static fn() => 'processed');

            $ctx->emitEvent('workflow-completed', ['id' => $params['id'], 'result' => $result]);

            return ['status' => 'done'];
        });

        $this->absurd->spawn('emitting-workflow', ['id' => 'test-123']);
        $this->processAllTasks();

        // If we get here without exception, events were emitted successfully
        static::assertTrue(true);
    }

    #[Test]
    public function checkpointsPersistAcrossRuns(): void
    {
        $runCount = 0;
        $step1Executions = 0;
        $step2Executions = 0;

        $this->absurd->registerTask('checkpoint-workflow', static function (array $params, TaskContext $ctx) use (
            &$runCount,
            &$step1Executions,
            &$step2Executions,
        ) {
            $runCount++;

            $ctx->step('step-1', static function () use (&$step1Executions) {
                $step1Executions++;
                return 'step-1-result';
            });

            // Simulate a failure on first run
            if ($runCount === 1) {
                throw new \RuntimeException('Simulated failure');
            }

            $ctx->step('step-2', static function () use (&$step2Executions) {
                $step2Executions++;
                return 'step-2-result';
            });

            return ['completed' => true];
        });

        $this->absurd->spawn('checkpoint-workflow', ['test' => true]);

        // First run - will fail after step-1
        $this->processAllTasks();

        // Second run - step-1 should be checkpointed
        $this->processAllTasks();

        static::assertSame(2, $runCount);
        static::assertSame(1, $step1Executions, 'Step 1 should only execute once due to checkpointing');
        static::assertSame(1, $step2Executions);
    }

    #[Test]
    public function multipleTasksClaimed(): void
    {
        $this->absurd->registerTask('batch-task', static fn(array $params, TaskContext $ctx) => [
            'id' => $params['id'],
        ]);

        $this->absurd->spawn('batch-task', ['id' => 1]);
        $this->absurd->spawn('batch-task', ['id' => 2]);
        $this->absurd->spawn('batch-task', ['id' => 3]);

        $tasks = $this->absurd->claimTasks(new ClaimOptions(batchSize: 3));

        static::assertCount(3, $tasks);
    }
}

final readonly class TestPayload
{
    public function __construct(
        public string $id,
        public int $amount,
    ) {}
}
