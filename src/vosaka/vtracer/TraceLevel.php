<?php

declare(strict_types=1);

namespace vosaka\vtracer;

enum TraceLevel: string
{
    case DEBUG = "DEBUG";
    case INFO = "INFO";
    case WARN = "WARN";
    case ERROR = "ERROR";
    case CRITICAL = "CRITICAL";
}
