<?php

require "../vendor/autoload.php";

use venndev\vosaka\net\tcp\TCPListener;
use venndev\vosaka\net\tcp\TCPStream;
use venndev\vosaka\VOsaka;
use vosaka\vtracer\ConsoleTracerHandler;
use vosaka\vtracer\FileTracerHandler;
use vosaka\vtracer\TraceEventType;
use vosaka\vtracer\TraceLevel;
use vosaka\vtracer\VOsakaTracer;
use vosaka\vtracer\VOsakaTracerExtensions;

function vosaka_trace(): VOsakaTracer
{
    return VOsakaTracer::getInstance();
}

function vosaka_trace_extensions(): VOsakaTracerExtensions
{
    return new VOsakaTracerExtensions(VOsakaTracer::getInstance());
}

/**
 * Example usage and setup
 */
class TracerSetup
{
    public static function setupDefault(): VOsakaTracer
    {
        // Get the singleton tracer instance
        $tracer = VOsakaTracer::getInstance();

        // Add console handler for development logging
        $consoleHandler = new ConsoleTracerHandler(
            colorEnabled: true,
            minLevel: TraceLevel::DEBUG
        );
        $tracer->addHandler($consoleHandler);

        // Add file handler for persistent logging
        $logFile = __DIR__ . "/vosaka_trace_" . date("Y-m-d") . ".log";
        $fileHandler = new FileTracerHandler($logFile, TraceLevel::INFO);
        $tracer->addHandler($fileHandler);

        // Return the configured tracer
        return $tracer;
    }

    public static function setupProduction(): VOsakaTracer
    {
        // Get the singleton tracer instance
        $tracer = VOsakaTracer::getInstance();

        // Add file handler for production logging with warning level
        $logFile = __DIR__ . "/trace.log";
        $fileHandler = new FileTracerHandler($logFile, TraceLevel::WARN);
        $tracer->addHandler($fileHandler);

        // Return the configured tracer
        return $tracer;
    }
}

// Example integration with VOsaka for traced task spawning
function traced_vosaka_spawn(callable $task): void
{
    // Get tracer and extensions instances
    $tracer = vosaka_trace();
    $extensions = vosaka_trace_extensions();

    // Trace the task spawning operation
    $extensions->traceVosakaSpawn($task);

    // Check if the task is callable and handle generator results
    if (is_callable($task)) {
        $result = $task();
        if ($result instanceof Generator) {
            // Trace and spawn generator-based tasks
            $tracedGenerator = $tracer->traceGenerator($result, "vosaka_task");
            VOsaka::spawn($tracedGenerator);
        } else {
            // Spawn non-generator tasks directly
            VOsaka::spawn($task);
        }
    }
}

/**
 * Example 1: Setup and configure Tracer
 */
function setupTracerExample(): void
{
    echo "=== SETUP TRACER ===\n";

    // Method 1: Default setup for development
    $tracer = TracerSetup::setupDefault();
    echo "✓ Default tracer setup with console and file logging\n";

    // Method 2: Production setup (commented out)
    // $tracer = TracerSetup::setupProduction();

    // Method 3: Custom setup with console handler
    $customTracer = VOsakaTracer::getInstance();
    $customTracer->addHandler(
        new ConsoleTracerHandler(colorEnabled: true, minLevel: TraceLevel::INFO)
    );

    // Test the tracer with a sample trace
    vosaka_trace()->trace(
        TraceLevel::INFO,
        TraceEventType::TASK_SPAWN,
        "Tracer setup completed successfully",
        ["timestamp" => time(), "memory_usage" => memory_get_usage()]
    );

    echo "✓ Custom tracer setup completed\n\n";
}

/**
 * Example 2: TCP Server with detailed tracing
 */
function tracedHandleClient(TCPStream $client): Generator
{
    // Initialize tracer and extensions
    $tracer = vosaka_trace();
    $extensions = vosaka_trace_extensions();

    // Start tracing for this client connection
    $traceId = $tracer->startTrace("handle_client", [
        "client_id" => spl_object_hash($client),
        "remote_address" => "unknown",
    ]);

    $messageCount = 0;
    $totalBytes = 0;

    try {
        // Trace new client connection
        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TCP_ACCEPT,
            "New client connection established",
            ["trace_id" => $traceId],
            $traceId
        );

        while (!$client->isClosed()) {
            $startTime = microtime(true);

            // Trace read operation start
            $tracer->trace(
                TraceLevel::DEBUG,
                TraceEventType::IO_READ,
                "Starting read operation",
                ["buffer_size" => 1024],
                $traceId
            );

            // Read data from client
            $data = yield from $client->read(1024)->unwrap();
            $readDuration = microtime(true) - $startTime;

            // Handle client disconnection
            if ($data === null || $data === "") {
                $tracer->trace(
                    TraceLevel::INFO,
                    TraceEventType::TCP_CLOSE,
                    "Client disconnected gracefully",
                    [
                        "messages_processed" => $messageCount,
                        "total_bytes" => $totalBytes,
                    ],
                    $traceId
                );
                break;
            }

            $messageCount++;
            $totalBytes += strlen($data);

            // Trace successful read operation
            $extensions->traceIOOperation(
                "read",
                strlen($data),
                $readDuration,
                $traceId
            );

            // Trace message processing
            $tracer->trace(
                TraceLevel::DEBUG,
                TraceEventType::TASK_RESUME,
                "Processing received message",
                [
                    "message_length" => strlen($data),
                    "message_preview" =>
                        substr($data, 0, 50) .
                        (strlen($data) > 50 ? "..." : ""),
                    "message_count" => $messageCount,
                ],
                $traceId
            );

            // Process data with simulated work
            yield from simulateProcessing($data, $traceId);

            // Trace write operation
            $response = "Echo #{$messageCount}: " . trim($data) . "\n";
            $writeStartTime = microtime(true);

            $bytesWritten = yield from $client->writeAll($response)->unwrap();
            $writeDuration = microtime(true) - $writeStartTime;

            // Trace write operation details
            $extensions->traceIOOperation(
                "write",
                $bytesWritten,
                $writeDuration,
                $traceId
            );

            $tracer->trace(
                TraceLevel::DEBUG,
                TraceEventType::IO_WRITE,
                "Response sent to client",
                [
                    "bytes_written" => $bytesWritten,
                    "response_preview" => substr($response, 0, 50),
                ],
                $traceId
            );
        }
    } catch (\Throwable $e) {
        // Trace errors during client handling
        $tracer->trace(
            TraceLevel::ERROR,
            TraceEventType::TASK_ERROR,
            "Error handling client",
            [
                "error" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "messages_processed" => $messageCount,
            ],
            $traceId
        );
        throw $e;
    } finally {
        // Ensure client connection is closed and traced
        if (!$client->isClosed()) {
            $client->close();
            $tracer->trace(
                TraceLevel::INFO,
                TraceEventType::TCP_CLOSE,
                "Client connection closed",
                [],
                $traceId
            );
        }

        // End the trace with final metrics
        $tracer->endTrace($traceId, [
            "final_message_count" => $messageCount,
            "final_total_bytes" => $totalBytes,
        ]);
    }
}

/**
 * Simulate processing with tracing with tracing
 */
function simulateProcessing(string $data, string $traceId): Generator
{
    // Get tracer instance
    $tracer = vosaka_trace();

    // Start tracing for message processing
    $processingTraceId = $tracer->startTrace("process_message", [
        "parent_trace" => $traceId,
        "data_length" => strlen($data),
    ]);

    // Define processing steps
    $steps = ["validate", "transform", "store"];

    foreach ($steps as $step) {
        // Trace each processing step
        $tracer->trace(
            TraceLevel::DEBUG,
            TraceEventType::TASK_RESUME,
            "Processing step: {$step}",
            ["step" => $step],
            $processingTraceId
        );

        // Simulate async work
        yield;

        // Simulate processing time
        usleep(rand(1000, 5000)); // 1-5ms
    }

    // End the processing trace
    $tracer->endTrace($processingTraceId);
}

/**
 * Example 3: TCP Server main function with tracing
 */
function tracedServerMain(): Generator
{
    // Initialize tracer and extensions
    $tracer = vosaka_trace();
    $extensions = vosaka_trace_extensions();

    // Start tracing for the TCP server
    $serverTraceId = $tracer->startTrace("tcp_server", [
        "bind_address" => "127.0.0.1:8099",
        "server_start_time" => date("Y-m-d H:i:s"),
    ]);

    try {
        // Trace server startup
        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TCP_CONNECT,
            "Starting TCP server",
            ["address" => "127.0.0.1", "port" => 8099],
            $serverTraceId
        );

        // Bind the TCP listener
        $listener = yield from TCPListener::bind("127.0.0.1:8099")->unwrap();

        // Trace successful server binding
        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TASK_SPAWN,
            "TCP server listening successfully",
            ["socket_info" => "TCPListener bound"],
            $serverTraceId
        );

        $clientCount = 0;

        while (!$listener->isClosed()) {
            // Trace waiting for client connections
            $tracer->trace(
                TraceLevel::DEBUG,
                TraceEventType::TCP_ACCEPT,
                "Waiting for client connections...",
                ["active_clients" => $clientCount],
                $serverTraceId
            );

            // Accept client connection
            $client = yield from $listener->accept()->unwrap();

            // Handle null or closed clients
            if ($client === null || $client->isClosed()) {
                $tracer->trace(
                    TraceLevel::WARN,
                    TraceEventType::TCP_ACCEPT,
                    "Received null or closed client",
                    [],
                    $serverTraceId
                );
                continue;
            }

            $clientCount++;

            // Trace client acceptance
            $extensions->traceTCPAccept(
                "Client #{$clientCount}",
                $serverTraceId
            );

            // Trace spawning of client handler
            $tracer->trace(
                TraceLevel::INFO,
                TraceEventType::TASK_SPAWN,
                "Spawning handler for new client",
                [
                    "client_number" => $clientCount,
                    "total_clients_served" => $clientCount,
                ],
                $serverTraceId
            );

            // Spawn traced client handler
            VOsaka::spawn(
                $tracer->traceGenerator(
                    tracedHandleClient($client),
                    "client_handler_{$clientCount}",
                    [
                        "client_id" => $clientCount,
                        "parent_trace" => $serverTraceId,
                    ]
                )
            );
        }
    } catch (\Throwable $e) {
        // Trace server errors
        $tracer->trace(
            TraceLevel::CRITICAL,
            TraceEventType::TASK_ERROR,
            "Server fatal error",
            [
                "error" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
            ],
            $serverTraceId
        );
        throw $e;
    } finally {
        // End the server trace
        $tracer->endTrace($serverTraceId);
    }
}

/**
 * Example 4: File operations with tracing
 */
function tracedFileOperations(): Generator
{
    // Initialize tracer and extensions
    $tracer = vosaka_trace();
    $extensions = vosaka_trace_extensions();

    // Start tracing for file operations
    $fileTraceId = $tracer->startTrace("file_operations");

    try {
        $filename = __DIR__ . "/vosaka_test.txt";
        $testData = "Test data from Vosaka: " . date("Y-m-d H:i:s") . "\n";

        // Trace file write operation
        $extensions->traceFileOperation(
            "write",
            $filename,
            strlen($testData),
            $fileTraceId
        );
        file_put_contents($filename, $testData);

        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::FILE_WRITE,
            "File written successfully",
            ["filename" => $filename, "bytes" => strlen($testData)],
            $fileTraceId
        );

        // Simulate async operation
        yield;

        // Trace file read operation
        $extensions->traceFileOperation("read", $filename, null, $fileTraceId);
        $readData = file_get_contents($filename);

        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::FILE_READ,
            "File read successfully",
            [
                "filename" => $filename,
                "bytes_read" => strlen($readData),
                "content_preview" => substr($readData, 0, 50),
            ],
            $fileTraceId
        );

        // Clean up by deleting the file
        unlink($filename);
        $tracer->trace(
            TraceLevel::DEBUG,
            TraceEventType::FILE_WRITE,
            "Cleanup: File deleted",
            ["filename" => $filename],
            $fileTraceId
        );
    } catch (\Throwable $e) {
        // Trace file operation errors
        $tracer->trace(
            TraceLevel::ERROR,
            TraceEventType::TASK_ERROR,
            "File operation error",
            [
                "error" => $e->getMessage(),
                "operation" => "file_ops",
            ],
            $fileTraceId
        );
    } finally {
        // End the file operation trace
        $tracer->endTrace($fileTraceId);
    }
}

/**
 * Example 5: Timer operations with tracing
 */
function tracedTimerOperations(): Generator
{
    // Get tracer instance
    $tracer = vosaka_trace();

    // Start tracing for timer operations
    $timerTraceId = $tracer->startTrace("timer_operations");

    // Simulate multiple timers
    for ($i = 1; $i <= 3; $i++) {
        // Start tracing for individual timer
        $singleTimerTrace = $tracer->startTrace("timer_{$i}", [
            "timer_number" => $i,
            "parent_trace" => $timerTraceId,
        ]);

        // Trace timer start
        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TIMER_START,
            "Starting timer #{$i}",
            ["delay_ms" => $i * 100],
            $singleTimerTrace
        );

        // Simulate timer delay
        for ($j = 0; $j < $i; $j++) {
            yield;
            usleep(100000); // 100ms
        }

        // Trace timer completion
        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TIMER_FIRE,
            "Timer #{$i} fired",
            ["actual_delay_ms" => $i * 100],
            $singleTimerTrace
        );

        // End individual timer trace
        $tracer->endTrace($singleTimerTrace);
    }

    // End the timer operation trace
    $tracer->endTrace($timerTraceId);
}

/**
 * Example 6: Error handling and recovery with tracing
 */
function tracedErrorHandling(): Generator
{
    // Get tracer instance
    $tracer = vosaka_trace();

    // Start tracing for error handling demo
    $errorTraceId = $tracer->startTrace("error_handling_demo");

    try {
        // Trace start of error handling demo
        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TASK_SPAWN,
            "Starting error handling demonstration",
            [],
            $errorTraceId
        );

        // Simulate successful operations
        for ($i = 1; $i <= 3; $i++) {
            $tracer->trace(
                TraceLevel::DEBUG,
                TraceEventType::TASK_RESUME,
                "Processing step {$i}",
                ["step" => $i],
                $errorTraceId
            );
            yield;
        }

        // Simulate an error with 30% probability
        if (rand(1, 10) > 7) {
            throw new \RuntimeException("Simulated error occurred");
        }

        // Trace successful completion
        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TASK_COMPLETE,
            "Error handling demo completed successfully",
            [],
            $errorTraceId
        );
    } catch (\Throwable $e) {
        // Trace caught error
        $tracer->trace(
            TraceLevel::ERROR,
            TraceEventType::TASK_ERROR,
            "Caught expected error",
            [
                "error_type" => get_class($e),
                "error_message" => $e->getMessage(),
                "recovery_attempted" => true,
            ],
            $errorTraceId
        );

        // Simulate recovery
        yield;

        // Trace recovery success
        $tracer->trace(
            TraceLevel::INFO,
            TraceEventType::TASK_COMPLETE,
            "Error recovered successfully",
            [],
            $errorTraceId
        );
    } finally {
        // End the error handling trace
        $tracer->endTrace($errorTraceId);
    }
}

/**
 * Example 7: Performance monitoring
 */
function tracedPerformanceMonitoring(): Generator
{
    // Get tracer instance
    $tracer = vosaka_trace();

    // Start tracing for performance monitoring
    $perfTraceId = $tracer->startTrace("performance_monitoring");

    // Record initial memory usage
    $startMemory = memory_get_usage();

    // Trace start of performance monitoring
    $tracer->trace(
        TraceLevel::INFO,
        TraceEventType::TASK_SPAWN,
        "Starting performance monitoring",
        [
            "start_memory_bytes" => $startMemory,
            "start_memory_mb" => round($startMemory / 1024 / 1024, 2),
        ],
        $perfTraceId
    );

    // Simulate memory-intensive operations
    $data = [];
    for ($i = 0; $i < 1000; $i++) {
        $data[] = str_repeat("x", 1000);

        // Trace memory checkpoints periodically
        if ($i % 100 === 0) {
            $currentMemory = memory_get_usage();
            $tracer->trace(
                TraceLevel::DEBUG,
                TraceEventType::TASK_RESUME,
                "Memory checkpoint",
                [
                    "iteration" => $i,
                    "current_memory_bytes" => $currentMemory,
                    "memory_increase_mb" => round(
                        ($currentMemory - $startMemory) / 1024 / 1024,
                        2
                    ),
                ],
                $perfTraceId
            );
            yield;
        }
    }

    // Record final memory metrics
    $endMemory = memory_get_usage();
    $peakMemory = memory_get_peak_usage();

    // End trace with final memory metrics
    $tracer->endTrace($perfTraceId, [
        "end_memory_bytes" => $endMemory,
        "peak_memory_bytes" => $peakMemory,
        "memory_increase_mb" => round(
            ($endMemory - $startMemory) / 1024 / 1024,
            2
        ),
        "peak_memory_mb" => round($peakMemory / 1024 / 1024, 2),
    ]);
}

/**
 * Main function to run all examples
 */
function main(): void
{
    echo "🚀 VOSAKA TRACER - DETAILED USAGE EXAMPLES\n";
    echo str_repeat("=", 60) . "\n\n";

    // Setup tracer
    setupTracerExample();

    // Spawn traced tasks
    VOsaka::spawn(tracedFileOperations());
    VOsaka::spawn(tracedTimerOperations());
    VOsaka::spawn(tracedErrorHandling());
    VOsaka::spawn(tracedPerformanceMonitoring());

    // Server spawning (commented out by default)
    // VOsaka::spawn(tracedServerMain());

    echo "📊 All traced tasks spawned. Check console output and log files.\n";
    echo "📁 Log file location: " .
        __DIR__ .
        "/vosaka_trace_" .
        date("Y-m-d") .
        ".log\n\n";

    // Monitor active traces
    $tracer = vosaka_trace();

    echo "🔍 ACTIVE TRACES:\n";
    $activeTraces = $tracer->getActiveTraces();

    if (empty($activeTraces)) {
        echo "   No active traces\n";
    } else {
        foreach ($activeTraces as $traceId => $info) {
            $duration = microtime(true) - $info["start_time"];
            echo "   • {$info["operation"]} (ID: " .
                substr($traceId, -8) .
                ") - Running for " .
                round($duration * 1000, 2) .
                "ms\n";
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎯 To test TCP server, uncomment VOsaka::spawn(tracedServerMain());\n";
    echo "   and run: telnet 127.0.0.1 8099\n";
    echo str_repeat("=", 60) . "\n";
}

/**
 * CLI usage example
 */
if (php_sapi_name() === "cli") {
    // Run the main function and start VOsaka runtime
    main();
    VOsaka::run();
}
