<?php

declare(strict_types=1);

namespace vosaka\vtracer;

enum TraceEventType: string
{
    case TASK_SPAWN = "TASK_SPAWN";
    case TASK_RESUME = "TASK_RESUME";
    case TASK_YIELD = "TASK_YIELD";
    case TASK_COMPLETE = "TASK_COMPLETE";
    case TASK_ERROR = "TASK_ERROR";
    case IO_READ = "IO_READ";
    case IO_WRITE = "IO_WRITE";
    case TCP_CONNECT = "TCP_CONNECT";
    case TCP_ACCEPT = "TCP_ACCEPT";
    case TCP_CLOSE = "TCP_CLOSE";
    case UDP_SEND = "UDP_SEND";
    case UDP_RECEIVE = "UDP_RECEIVE";
    case FILE_READ = "FILE_READ";
    case FILE_WRITE = "FILE_WRITE";
    case PROCESS_START = "PROCESS_START";
    case PROCESS_END = "PROCESS_END";
    case TIMER_START = "TIMER_START";
    case TIMER_FIRE = "TIMER_FIRE";
}
