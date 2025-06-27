<?php

declare(strict_types=1);

namespace vosaka\vtracer;

use Generator;
use Throwable;

final class VOsakaTracer
{
    private static ?self $instance = null;
    private array $handlers = [];
    private array $activeTraces = [];
    private bool $enabled = true;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function addHandler(TracerHandler $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function trace(
        TraceLevel $level,
        TraceEventType $type,
        string $message,
        array $context = [],
        ?string $traceId = null,
        ?string $taskId = null
    ): void {
        if (!$this->enabled) {
            return;
        }

        $event = new TraceEvent(
            $level,
            $type,
            $message,
            $context,
            $traceId,
            $taskId
        );

        foreach ($this->handlers as $handler) {
            try {
                $handler->handle($event);
            } catch (Throwable $e) {
                error_log("Tracer handler error: " . $e->getMessage());
            }
        }
    }

    public function startTrace(string $operation, array $context = []): string
    {
        $traceId = uniqid("trace_", true);
        $this->activeTraces[$traceId] = [
            "operation" => $operation,
            "start_time" => microtime(true),
            "context" => $context,
        ];

        $this->trace(
            TraceLevel::DEBUG,
            TraceEventType::TASK_SPAWN,
            "Starting trace for: {$operation}",
            $context,
            $traceId
        );

        return $traceId;
    }

    public function endTrace(
        string $traceId,
        array $additionalContext = []
    ): void {
        if (!isset($this->activeTraces[$traceId])) {
            return;
        }

        $trace = $this->activeTraces[$traceId];
        $duration = microtime(true) - $trace["start_time"];

        $context = array_merge($trace["context"], $additionalContext, [
            "duration_ms" => round($duration * 1000, 2),
        ]);

        $this->trace(
            TraceLevel::DEBUG,
            TraceEventType::TASK_COMPLETE,
            "Completed trace for: {$trace["operation"]}",
            $context,
            $traceId
        );

        unset($this->activeTraces[$traceId]);
    }

    public function traceGenerator(
        Generator $generator,
        string $operationName,
        array $context = []
    ): Generator {
        $traceId = $this->startTrace($operationName, $context);
        $stepCount = 0;

        try {
            while ($generator->valid()) {
                $stepCount++;

                $this->trace(
                    TraceLevel::DEBUG,
                    TraceEventType::TASK_YIELD,
                    "Generator step {$stepCount} for: {$operationName}",
                    ["step" => $stepCount, "current" => $generator->current()],
                    $traceId
                );

                $value = $generator->current();
                $generator->next();

                yield $value;
            }

            $this->endTrace($traceId, ["total_steps" => $stepCount]);
            return $generator->getReturn();
        } catch (Throwable $e) {
            $this->trace(
                TraceLevel::ERROR,
                TraceEventType::TASK_ERROR,
                "Generator error in: {$operationName}",
                [
                    "error" => $e->getMessage(),
                    "file" => $e->getFile(),
                    "line" => $e->getLine(),
                    "step" => $stepCount,
                ],
                $traceId
            );

            $this->endTrace($traceId, [
                "error" => true,
                "total_steps" => $stepCount,
            ]);
            throw $e;
        }
    }

    public function getActiveTraces(): array
    {
        return $this->activeTraces;
    }

    public function clearActiveTraces(): void
    {
        $this->activeTraces = [];
    }
}
