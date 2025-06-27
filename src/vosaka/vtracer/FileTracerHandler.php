<?php

declare(strict_types=1);

namespace vosaka\vtracer;

final class FileTracerHandler implements TracerHandler
{
    private $fileHandle;

    public function __construct(
        private readonly string $filePath,
        private readonly TraceLevel $minLevel = TraceLevel::DEBUG
    ) {
        $this->fileHandle = fopen($this->filePath, "a");
        if (!$this->fileHandle) {
            throw new \RuntimeException(
                "Cannot open trace file: {$this->filePath}"
            );
        }
    }

    public function handle(TraceEvent $event): void
    {
        if (!$this->shouldHandle($event->level)) {
            return;
        }

        $line = $event->toJson() . "\n";
        fwrite($this->fileHandle, $line);
        fflush($this->fileHandle);
    }

    private function shouldHandle(TraceLevel $level): bool
    {
        $levels = [
            TraceLevel::DEBUG->value => 0,
            TraceLevel::INFO->value => 1,
            TraceLevel::WARN->value => 2,
            TraceLevel::ERROR->value => 3,
            TraceLevel::CRITICAL->value => 4,
        ];

        return $levels[$level->value] >= $levels[$this->minLevel->value];
    }

    public function __destruct()
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }
}
