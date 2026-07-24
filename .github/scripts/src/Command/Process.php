<?php

declare(strict_types=1);

namespace DockerBase\Command;

use Override;
use Utopia\Console;

final readonly class Process implements Runner
{
    /** @param array<string, string>|null $environment */
    public function __construct(private ?string $directory = null, private ?array $environment = null)
    {
    }

    /** @param list<string> $command */
    #[Override]
    public function run(array $command, bool $check = true): Result
    {
        if ($command === [] || in_array('', $command, true)) {
            throw new Exception($command, message: 'A command must contain non-empty arguments');
        }

        $stdout = '';
        $stderr = '';
        $previous = getcwd();
        $environment = [];
        foreach ($this->environment ?? [] as $key => $value) {
            $environment[$key] = getenv($key);
            putenv($key . '=' . $value);
        }

        try {
            if ($this->directory !== null && ! chdir($this->directory)) {
                throw new Exception($command, message: 'Unable to start command');
            }
            $code = Console::execute($command, '', $stdout, $stderr);
            $result = new Result($code, $stdout, $stderr);
            if ($check && ! $result->succeeded()) {
                throw new Exception($command, $result);
            }
            return $result;
        } finally {
            if ($previous !== false) {
                chdir($previous);
            }
            foreach ($environment as $key => $value) {
                putenv($key . ($value === false ? '' : '=' . $value));
            }
        }
    }
}
