<?php

declare(strict_types=1);

namespace vosaka\vtracer;

final class VOsakaTracerExtensions
{
    private VOsakaTracer $tracer;

    public function __construct(VOsakaTracer $tracer)
    {
        $this->tracer = $tracer;
    }

    public function traceTCPConnection(
        string $address,
        string $port,
        ?string $traceId = null
    ): void {
        $this->tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TCP_CONNECT,
            "TCP connection attempt",
            ["address" => $address, "port" => $port],
            $traceId
        );
    }

    public function traceTCPAccept(
        string $clientInfo,
        ?string $traceId = null
    ): void {
        $this->tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TCP_ACCEPT,
            "TCP client accepted",
            ["client" => $clientInfo],
            $traceId
        );
    }

    public function traceIOOperation(
        string $operation,
        int $bytes,
        float $duration,
        ?string $traceId = null
    ): void {
        $this->tracer->trace(
            TraceLevel::DEBUG,
            $operation === "read"
                ? TraceEventType::IO_READ
                : TraceEventType::IO_WRITE,
            "I/O {$operation} operation",
            [
                "bytes" => $bytes,
                "duration_ms" => round($duration * 1000, 2),
                "throughput_mbps" => round(
                    $bytes / $duration / (1024 * 1024),
                    2
                ),
            ],
            $traceId
        );
    }

    public function traceVosakaSpawn(
        callable $task,
        ?string $traceId = null
    ): void {
        $taskName = is_string($task) ? $task : "anonymous";
        $this->tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TASK_SPAWN,
            "VOsaka::spawn called",
            ["task_name" => $taskName],
            $traceId
        );
    }

    public function traceFileOperation(
        string $operation,
        string $filename,
        ?int $bytes = null,
        ?string $traceId = null
    ): void {
        $context = ["filename" => $filename];
        if ($bytes !== null) {
            $context["bytes"] = $bytes;
        }

        $this->tracer->trace(
            TraceLevel::DEBUG,
            $operation === "read"
                ? TraceEventType::FILE_READ
                : TraceEventType::FILE_WRITE,
            "File {$operation} operation",
            $context,
            $traceId
        );
    }
}
