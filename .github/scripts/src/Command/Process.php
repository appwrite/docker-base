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

        if ($this->environment !== null || $this->directory !== null) {
            return $this->runWithEnvironment($command, $check);
        }

        $result = $this->executeConsole($command);
        if ($check && ! $result->succeeded()) {
            throw new Exception($command, $result);
        }

        return $result;
    }

    /** @param list<string> $command */
    private function executeConsole(array $command): Result
    {
        $stdout = '';
        $stderr = '';
        $code = Console::execute($command, '', $stdout, $stderr);

        return new Result($code, $stdout, $stderr);
    }

    /** @param list<string> $command */
    private function runWithEnvironment(array $command, bool $check): Result
    {
        if ($this->directory !== null && ! is_dir($this->directory)) {
            throw new Exception($command, message: "Unable to start command in directory: {$this->directory}");
        }

        $input = tmpfile();
        $output = tmpfile();
        $error = tmpfile();
        if ($input === false || $output === false || $error === false) {
            foreach ([$input, $output, $error] as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            throw new Exception($command, message: 'Unable to allocate command output streams');
        }

        try {
            $pipes = [];
            $process = proc_open($command, [0 => $input, 1 => $output, 2 => $error], $pipes, $this->directory, $this->environment, ['bypass_shell' => true]);
            if (! is_resource($process)) {
                $message = $this->directory === null
                    ? 'Unable to start command'
                    : "Unable to start command in directory: {$this->directory}";
                throw new Exception($command, message: $message);
            }
            $code = proc_close($process);
            rewind($output);
            rewind($error);
            $stdout = stream_get_contents($output);
            $stderr = stream_get_contents($error);
            if ($stdout === false || $stderr === false) {
                throw new Exception($command, message: 'Unable to read command output');
            }
            $result = new Result($code, $stdout, $stderr);
            if ($check && ! $result->succeeded()) {
                throw new Exception($command, $result);
            }
            return $result;
        } finally {
            fclose($input);
            fclose($output);
            fclose($error);
        }
    }
}
