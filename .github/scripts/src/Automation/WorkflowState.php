<?php

declare(strict_types=1);

namespace DockerBase\Automation;

enum WorkflowState: string
{
    case Missing = 'missing';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
}
