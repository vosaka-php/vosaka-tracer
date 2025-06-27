<?php

declare(strict_types=1);

namespace vosaka\vtracer;

final class ConsoleTracerHandler implements TracerHandler
{
    public function __construct(
        private readonly bool $colorEnabled = true,
        private readonly TraceLevel $minLevel = TraceLevel::DEBUG
    ) {}

    public function handle(TraceEvent $event): void
    {
        if (!$this->shouldHandle($event->level)) {
            return;
        }

        $color = $this->getColor($event->level);
        $reset = $this->colorEnabled ? "\033[0m" : "";

        $timestamp = date("H:i:s.v", (int) $event->timestamp);
        $levelStr = str_pad($event->level->value, 8);
        $typeStr = str_pad($event->type->value, 15);

        echo "{$color}[{$timestamp}] {$levelStr} {$typeStr} {$event->message}{$reset}\n";

        if (!empty($event->context)) {
            $contextStr = json_encode($event->context, JSON_UNESCAPED_UNICODE);
            echo "{$color}    Context: {$contextStr}{$reset}\n";
        }
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

    private function getColor(TraceLevel $level): string
    {
        if (!$this->colorEnabled) {
            return "";
        }

        return match ($level) {
            TraceLevel::DEBUG => "\033[36m", // Cyan
            TraceLevel::INFO => "\033[32m", // Green
            TraceLevel::WARN => "\033[33m", // Yellow
            TraceLevel::ERROR => "\033[31m", // Red
            TraceLevel::CRITICAL => "\033[35m", // Magenta
        };
    }
}
