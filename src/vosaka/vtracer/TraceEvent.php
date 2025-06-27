<?php

declare(strict_types=1);

namespace vosaka\vtracer;

final class TraceEvent
{
    public readonly float $timestamp;
    public readonly string $traceId;
    public readonly string $taskId;

    public function __construct(
        public readonly TraceLevel $level,
        public readonly TraceEventType $type,
        public readonly string $message,
        public readonly array $context = [],
        ?string $traceId = null,
        ?string $taskId = null
    ) {
        $this->timestamp = microtime(true);
        $this->traceId = $traceId ?? uniqid("trace_", true);
        $this->taskId = $taskId ?? uniqid("task_", true);
    }

    public function toArray(): array
    {
        return [
            "timestamp" => $this->timestamp,
            "datetime" => date("Y-m-d H:i:s.v", (int) $this->timestamp),
            "trace_id" => $this->traceId,
            "task_id" => $this->taskId,
            "level" => $this->level->value,
            "type" => $this->type->value,
            "message" => $this->message,
            "context" => $this->context,
        ];
    }

    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }
}
