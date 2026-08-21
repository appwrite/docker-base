<?php

declare(strict_types=1);

namespace DockerBase\Automation;

enum ReviewDecision: string
{
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case ReviewRequired = 'review_required';
}
