<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

/**
 * Classifies why GitHub refused a merge.
 *
 * The merge endpoint states the unmet requirements in its error body and
 * nowhere else - there is no field that enumerates which protections blocked a
 * pull request. Only a refusal that names a review requirement and nothing
 * else may be bypassed: a message naming a review *and* a pending deployment
 * is not review-only, and neither is a message this does not recognise.
 */
final readonly class Refusal
{
    private const array REVIEW = [
        'approving review',
        'approving reviews',
        'review required',
        'changes requested',
    ];

    /**
     * Protections a correct release waits for or fails on. None of them is
     * something a second identity could satisfy on the automation's behalf.
     */
    private const array OTHER = [
        'status check',
        'deployment',
        'signature',
        'signed commit',
        'conversation',
        'not authorized',
        'not allowed to',
        'restriction',
        'merge queue',
        'linear history',
        'out of date',
        'behind the base',
        'conflict',
    ];

    public function __construct(public string $message)
    {
    }

    public function isReviewRequirement(): bool
    {
        $message = strtolower($this->message);

        foreach (self::OTHER as $phrase) {
            if (str_contains($message, $phrase)) {
                return false;
            }
        }

        foreach (self::REVIEW as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
