<?php

declare(strict_types=1);

namespace DockerBase\Command;

use Override;

final readonly class Process implements Runner
{
    /**
     * @param array<string, string>|null $environment
     */
    public function __construct(
        private ?string $directory = null,
        private ?array $environment = null,
    ) {
    }

    /**
     * @param list<string> $command
     */
    #[Override]
    public function run(array $command, bool $check = true): Result
    {
        if ($command === [] || in_array('', $command, true)) {
            throw new Exception(
                $command,
                message: 'A command must contain non-empty arguments',
            );
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

            throw new Exception(
                $command,
                message: 'Unable to allocate command output streams',
            );
        }

        try {
            $pipes = [];
            $process = proc_open(
                $command,
                [
                    0 => $input,
                    1 => $output,
                    2 => $error,
                ],
                $pipes,
                $this->directory,
                $this->environment,
                ['bypass_shell' => true],
            );

            if (! is_resource($process)) {
                throw new Exception(
                    $command,
                    message: 'Unable to start command',
                );
            }

            $code = proc_close($process);
            rewind($output);
            rewind($error);
            $standardOutput = stream_get_contents($output);
            $standardError = stream_get_contents($error);

            if ($standardOutput === false || $standardError === false) {
                throw new Exception(
                    $command,
                    message: 'Unable to read command output',
                );
            }

            $result = new Result($code, $standardOutput, $standardError);
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
