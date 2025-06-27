<?php

declare(strict_types=1);

namespace vosaka\vtracer;

interface TracerHandler
{
    public function handle(TraceEvent $event): void;
}
