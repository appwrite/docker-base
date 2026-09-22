<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

/**
 * Classifies why GitHub refused a merge.
 *
 * The merge endpoint states the unmet requirement in its error body and
 * nowhere else - there is no field that enumerates which protections blocked
 * a pull request. Only a refusal recognised as the review requirement may be
 * bypassed; anything else, including an unrecognised message, is not.
 */
final readonly class Refusal
{
    private const array REVIEW = [
        'approving review',
        'approving reviews',
        'review required',
        'changes requested',
    ];

    public function __construct(public string $message)
    {
    }

    public function isReviewRequirement(): bool
    {
        $message = strtolower($this->message);

        foreach (self::REVIEW as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
