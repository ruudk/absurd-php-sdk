# Absurd SDK for PHP

PHP SDK for [Absurd](https://github.com/earendil-works/absurd): a PostgreSQL-based durable task execution system.

Absurd is the simplest durable execution workflow system you can think of. It's entirely based on Postgres and nothing else. It's almost as easy to use as a queue, but it handles scheduling and retries, and it does all of that without needing any other services to run in addition to Postgres.

**Warning:** _This is an early experiment and should not be used in production._

## What is Durable Execution?

Durable execution (or durable workflows) is a way to run long-lived, reliable functions that can survive crashes, restarts, and network failures without losing state or duplicating work. Instead of running your logic in memory, a durable execution system decomposes a task into smaller pieces (step functions) and records every step and decision.

## How It Works

This SDK uses [PHP Fibers](https://www.php.net/manual/en/language.fibers.php) to provide a clean, synchronous-looking API for durable workflows. When you call methods like `$ctx->step()`, `$ctx->awaitEvent()`, or `$ctx->sleepFor()`, the Fiber suspends execution, allowing the SDK to checkpoint progress to the database. When the task resumes (after a crash, timeout, or event), execution continues from exactly where it left off.

This means you can write workflow code that looks like normal sequential PHP code, while the SDK handles all the complexity of persistence, retries, and resumption behind the scenes.

## Requirements

- PHP 8.4+
- PDO extension with PostgreSQL driver
- PCNTL extension (for signal handling in workers)

## Installation

```bash
composer require ruudk/absurd-php-sdk
```

## Quick Start

```php
<?php

use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Serialization\SymfonySerializer;
use Ruudk\Absurd\Task\Context as TaskContext;

// Create PDO connection
$pdo = new PDO('pgsql:host=localhost;dbname=absurd');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create Absurd instance
$absurd = new Absurd($pdo, new SymfonySerializer());

// Register a task handler
$absurd->registerTask('order-fulfillment', function (array $params, TaskContext $ctx): array {
    // Each step is checkpointed - if the process crashes, we resume from the last completed step
    $payment = $ctx->step('process-payment', fn() => processPayment($params['amount']));

    $inventory = $ctx->step('reserve-inventory', fn() => reserveItems($params['items']));

    // Wait for an event - the task suspends until the event arrives
    $shipment = $ctx->awaitEvent("shipment.packed:{$params['orderId']}");

    $ctx->step('send-notification', fn() => sendEmail($params['email'], $shipment));

    return [
        'orderId' => $payment['id'],
        'trackingNumber' => $shipment['trackingNumber'],
    ];
});

// Start a worker that pulls tasks from Postgres
$worker = $absurd->startWorker();
$worker->start();
```

## Client Configuration

```php
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Serialization\SymfonySerializer;
use Symfony\Component\EventDispatcher\EventDispatcher;

$pdo = new PDO('pgsql:host=localhost;dbname=absurd');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$absurd = new Absurd(
    pdo: $pdo,
    serializer: new SymfonySerializer(),
    defaultQueueName: 'default',      // Default queue for tasks
    defaultMaxAttempts: 5,            // Default retry attempts
    eventDispatcher: new EventDispatcher(), // Optional: for hooks and error handling
);
```

## Queue Management

```php
// Create a queue (required before spawning tasks)
$absurd->createQueue('my-queue');

// List all queues
$queues = $absurd->listQueues();
// Returns: ['default', 'my-queue', ...]

// Drop a queue and all its data
$absurd->dropQueue('my-queue');
```

## Task Registration

```php
use Ruudk\Absurd\Task\RegisterOptions;
use Ruudk\Absurd\Task\CancellationPolicy;

// Simple registration
$absurd->registerTask('my-task', function (array $params, TaskContext $ctx): array {
    // Task logic here
    return ['result' => 'done'];
});

// Registration with options
$absurd->registerTask(
    'order-processor',
    function (array $params, TaskContext $ctx): array {
        // Task logic
        return [];
    },
    new RegisterOptions(
        queue: 'orders',                // Override default queue
        defaultMaxAttempts: 3,          // Override default max attempts
        defaultCancellation: new CancellationPolicy(
            maxDuration: 3600,          // Cancel after 1 hour total
            maxDelay: 300,              // Cancel if delayed more than 5 minutes
        ),
    ),
);
```

## Spawning Tasks

```php
use Ruudk\Absurd\Task\SpawnOptions;
use Ruudk\Absurd\Task\RetryStrategy;
use Ruudk\Absurd\Task\CancellationPolicy;

// Simple spawn
$result = $absurd->spawn('order-fulfillment', [
    'orderId' => '42',
    'amount' => 9999,
    'items' => ['widget-1', 'gadget-2'],
    'email' => 'customer@example.com',
]);

echo "Task ID: {$result->taskId}\n";
echo "Run ID: {$result->runId}\n";
echo "Attempt: {$result->attempt}\n";
echo "Created: " . ($result->created ? 'yes' : 'no (from cache)') . "\n";

// Spawn with options
$result = $absurd->spawn(
    'order-fulfillment',
    $params,
    new SpawnOptions(
        maxAttempts: 5,
        retryStrategy: RetryStrategy::exponential(baseSeconds: 10, factor: 2.0, maxSeconds: 300),
        cancellation: new CancellationPolicy(maxDuration: 3600),
        headers: ['priority' => 'high', 'trace_id' => 'abc123'],
        idempotencyKey: 'order-42',     // Prevents duplicate task creation
    ),
    queue: 'orders',                    // Override queue for unregistered tasks
);

// Check if task was newly created or returned from idempotency cache
if ($result->created) {
    echo "New task created\n";
} else {
    echo "Existing task returned (idempotency key matched)\n";
}
```

## Retry Strategies

```php
use Ruudk\Absurd\Task\RetryStrategy;

// Exponential backoff: 10s, 20s, 40s, 80s... up to 300s
RetryStrategy::exponential(baseSeconds: 10, factor: 2.0, maxSeconds: 300);

// Linear backoff: 10s, 20s, 30s, 40s... up to 300s
RetryStrategy::linear(baseSeconds: 10, maxSeconds: 300);

// Fixed delay: always 30s between retries
RetryStrategy::fixed(seconds: 30);

// No delay: immediate retry
RetryStrategy::none();
```

## Task Context Methods

Inside a task handler, you have access to `TaskContext` with these methods:

```php
$absurd->registerTask('workflow', function (array $params, TaskContext $ctx): array {
    // Access task metadata
    $taskId = $ctx->taskId;
    $runId = $ctx->runId;
    $attempt = $ctx->attempt;
    $headers = $ctx->headers;    // Custom headers from spawn options

    // Checkpoint a step - cached on retry
    $result = $ctx->step('step-name', fn() => expensiveOperation());

    // Wait for an external event
    $eventPayload = $ctx->awaitEvent('payment-confirmed');

    // Wait for event with timeout (throws TimeoutError if not received)
    use Ruudk\Absurd\Execution\AwaitEventOptions;
    $payload = $ctx->awaitEvent('webhook-received', new AwaitEventOptions(
        stepName: 'wait-webhook',   // Custom checkpoint name
        timeout: 300,               // 5 minute timeout
    ));

    // Sleep for a duration
    $ctx->sleepFor('delay', 60);  // Sleep for 60 seconds

    // Sleep until a specific time
    $ctx->sleepUntil('scheduled', new DateTimeImmutable('tomorrow 9am'));

    // Emit an event (can wake other waiting tasks)
    $ctx->emitEvent('order-processed', ['orderId' => '123']);

    // Extend the lease for long-running operations
    $ctx->heartbeat(120);     // Extend by 120 seconds
    $ctx->heartbeat();        // Extend by original claim timeout

    return ['done' => true];
});
```

## Emitting Events

```php
// Emit an event that a suspended task might be waiting for
$absurd->emitEvent('shipment.packed:42', [
    'trackingNumber' => 'TRACK123',
]);

// Emit to a specific queue
$absurd->emitEvent('payment-confirmed', $payload, 'orders');
```

## Cancelling Tasks

```php
// Cancel a task by ID
$absurd->cancelTask($taskId);

// Cancel in a specific queue
$absurd->cancelTask($taskId, 'orders');
```

Running tasks will stop at their next checkpoint, heartbeat, or await event call.

## Worker Configuration

```php
use Ruudk\Absurd\Worker\WorkerOptions;
use Psr\Log\NullLogger;

$worker = $absurd->startWorker(new WorkerOptions(
    workerId: 'my-worker-1',        // Unique identifier (default: hostname:pid)
    claimTimeout: 120,              // Seconds before task lease expires (default: 120)
    batchSize: 5,                   // Number of tasks to claim at once (default: 1)
    pollInterval: 0.25,             // Seconds between polls (default: 0.25)
    fatalOnLeaseTimeout: true,      // Exit process if lease times out (default: true)
    logger: new NullLogger(),       // PSR-3 logger for worker output
));

// Handle graceful shutdown
pcntl_async_signals(true);
pcntl_signal(SIGTERM, fn() => $worker->stop());
pcntl_signal(SIGINT, fn() => $worker->stop());

$worker->start();
```

## Error Handling with Events

Use the PSR-14 EventDispatcher for error handling and lifecycle hooks:

```php
use Ruudk\Absurd\Absurd;
use Ruudk\Absurd\Event\TaskErrorEvent;
use Ruudk\Absurd\Event\BeforeSpawnEvent;
use Ruudk\Absurd\Event\TaskExecutionEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();

// Handle task errors
$dispatcher->addListener(TaskErrorEvent::class, function (TaskErrorEvent $event) {
    $exception = $event->exception;
    $task = $event->task;  // May be null for non-task errors

    error_log(sprintf(
        'Task error: %s (task: %s)',
        $exception->getMessage(),
        $task?->taskId ?? 'unknown',
    ));
});

// Modify spawn options before task creation (e.g., inject trace IDs)
$dispatcher->addListener(BeforeSpawnEvent::class, function (BeforeSpawnEvent $event) {
    $event->options = $event->options->with(
        headers: array_merge($event->options->headers ?? [], [
            'trace_id' => getCurrentTraceId(),
        ]),
    );
});

// Wrap task execution (e.g., restore trace context)
$dispatcher->addListener(TaskExecutionEvent::class, function (TaskExecutionEvent $event) {
    $event->wrapExecution(function (Closure $execute) use ($event) {
        $traceId = $event->context->headers['trace_id'] ?? null;
        $scope = TraceContext::restore($traceId);
        try {
            return $execute();
        } finally {
            $scope->detach();
        }
    });
});

$absurd = new Absurd($pdo, $serializer, eventDispatcher: $dispatcher);
```

## Typed Payloads

You can use typed objects as task parameters:

```php
readonly class OrderPayload
{
    public function __construct(
        public string $orderId,
        public int $amount,
        public array $items,
    ) {}
}

$absurd->registerTask('process-order', function (OrderPayload $order, TaskContext $ctx): array {
    // $order is automatically deserialized to OrderPayload
    echo "Processing order: {$order->orderId}\n";

    return ['processed' => true];
});

// Spawn with typed payload
$absurd->spawn('process-order', new OrderPayload(
    orderId: 'ord-123',
    amount: 9999,
    items: ['widget', 'gadget'],
));
```

## Idempotency Keys

Use idempotency keys to prevent duplicate task creation:

```php
// First call creates the task
$result1 = $absurd->spawn('daily-report', $params, new SpawnOptions(
    idempotencyKey: 'daily-report-2024-01-15',
));
echo $result1->created;  // true

// Second call with same key returns existing task
$result2 = $absurd->spawn('daily-report', $differentParams, new SpawnOptions(
    idempotencyKey: 'daily-report-2024-01-15',
));
echo $result2->created;  // false
echo $result2->taskId === $result1->taskId;  // true
```

Use task ID for deriving idempotency keys for external APIs:

```php
$absurd->registerTask('payment-task', function (array $params, TaskContext $ctx): array {
    $payment = $ctx->step('charge-card', function () use ($params, $ctx) {
        // Use taskId to create idempotency key for Stripe
        $idempotencyKey = "{$ctx->taskId}:payment";
        return $stripe->charges->create([
            'amount' => $params['amount'],
            'idempotency_key' => $idempotencyKey,
        ]);
    });

    return $payment;
});
```

## Local Development

### Prerequisites

- Docker and Docker Compose
- GitHub CLI (`gh`) - for downloading Absurd binaries

### Quick Start

```bash
# Start PostgreSQL, initialize Absurd schema, and launch Habitat UI
make up

# Stop everything
make down

# Clean up (removes binaries and database volumes)
make clean
```

After running `make up`:
- **PostgreSQL** is available at `localhost:54329`
- **Habitat UI** (task dashboard) is available at http://localhost:7890

### Running the Example

The SDK includes a comprehensive e-commerce order fulfillment example demonstrating checkpoints, events, sub-tasks, and trace propagation.

```bash
# Terminal 1: Start the worker
php examples/ecommerce.php consume

# Terminal 2: Create an order and interact with the workflow
php examples/ecommerce.php produce
```

The example will guide you through confirming payment, showing how tasks checkpoint their progress, spawn sub-tasks for inventory and fraud checks, and emit events.

### Make Commands

| Command | Description |
|---------|-------------|
| `make help` | Show available commands |
| `make setup` | Download `absurdctl` binary |
| `make up` | Start PostgreSQL, initialize Absurd, and run Habitat |
| `make down` | Stop containers |
| `make clean` | Remove binaries and database volumes |

## Production Setup

For production, initialize Absurd in your PostgreSQL database:

```bash
# Install absurdctl from https://github.com/earendil-works/absurd/releases
absurdctl init -d your-database-name
absurdctl create-queue -d your-database-name default
```

## API Reference

### Absurd Class

| Method | Description |
|--------|-------------|
| `registerTask(name, handler, options?)` | Register a task handler |
| `spawn(taskName, params, options?, queue?)` | Spawn a new task |
| `emitEvent(eventName, payload?, queueName?)` | Emit an event |
| `cancelTask(taskId, queueName?)` | Cancel a running task |
| `claimTasks(options?)` | Claim tasks for processing |
| `startWorker(options?)` | Start a worker |
| `createQueue(queueName?)` | Create a queue |
| `dropQueue(queueName?)` | Drop a queue |
| `listQueues()` | List all queues |
| `executeTask(task, claimTimeout, ...)` | Execute a claimed task |

### TaskContext Class

| Method | Description |
|--------|-------------|
| `step(name, value)` | Execute a checkpointed step |
| `awaitEvent(eventName, options?)` | Wait for an event |
| `sleepFor(stepName, duration)` | Sleep for a duration (seconds) |
| `sleepUntil(stepName, wakeAt)` | Sleep until a specific time |
| `emitEvent(eventName, payload?)` | Emit an event from within a task |
| `heartbeat(seconds?)` | Extend the task lease |

### SpawnResult Class

| Property | Type | Description |
|----------|------|-------------|
| `taskId` | string | Unique task identifier |
| `runId` | string | Current run identifier |
| `attempt` | int | Current attempt number |
| `created` | bool | True if newly created, false if from idempotency cache |
