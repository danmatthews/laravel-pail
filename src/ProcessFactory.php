<?php

namespace Laravel\Pail;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Pail\Printers\CliPrinter;
use Laravel\Pail\ValueObjects\MessageLogged;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessFactory
{
    /**
     * Creates a new instance of the process factory.
     */
    public function run(File $file, OutputInterface $output, string $basePath, Options $options): void
    {
        $printer = new CliPrinter($output, $basePath);

        Process::timeout(3600)
            ->tty(false)
            ->run(
                $this->command($file),
                function (string $type, string $buffer) use ($options, $printer) {
                    Str::of($buffer)
                        ->explode("\n")
                        ->filter(fn (string $line) => $line !== '')
                        ->map(fn (string $line) => MessageLogged::fromJson($line))
                        ->filter(fn (MessageLogged $messageLogged) => $options->accepts($messageLogged))
                        ->each(fn (MessageLogged $messageLogged) => $printer->print($messageLogged));
                }
            );
    }

    /**
     * Returns the raw command.
     */
    protected function command(File $file): string
    {
        return '\\tail -F "'.$file->__toString().'"';
    }
}
