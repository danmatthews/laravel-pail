<?php

namespace Laravel\Pail\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Pail\File;
use Laravel\Pail\Guards\EnsurePcntlIsAvailable;
use Laravel\Pail\Options;
use Laravel\Pail\ProcessFactory;
use Symfony\Component\Process\Exception\ProcessSignaledException;

use function Termwind\render;
use function Termwind\renderUsing;

class PailCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'pail
        {--filter= : Filter the logs}
        {--message= : Filter the logs by the message}
        {--level= : Filter the logs by the level}
        {--auth= : Filter the logs by the authenticated ID}
        {--user= : Filter the logs by the authenticated ID}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Tails the application logs.';

    /**
     * The file instance, if any.
     */
    protected ?File $file = null;

    /**
     * {@inheritdoc}
     */
    public function handle(ProcessFactory $processFactory): void
    {
        EnsurePcntlIsAvailable::check();

        renderUsing($this->output);
        render(<<<'HTML'
            <div class="max-w-150 mx-2 mt-1 mb-1 flex">
                <div>
                    <span class="px-1 bg-blue uppercase text-white">INFO</span>
                    <span class="flex-1">
                        <span class="ml-1 ">Tailing application logs.</span>
                    </span>
                </div>
                <span class="flex-1"></span>
                <span class="text-gray ml-1">
                    <span class="text-gray">Press Ctrl+C to exit</span>
                </span>
            </div>
            HTML,
        );

        $this->file = new File(storage_path('pail/'.uniqid().'.pail'));
        $this->file->create();
        $this->trap([SIGINT, SIGTERM], fn () => $this->file->destroy());

        $options = Options::fromCommand($this);

        try {
            assert($this->file instanceof File);
            $processFactory->run($this->file, $this->output, $this->laravel->basePath(), $options);
        } catch (ProcessSignaledException $e) {
            if (in_array($e->getSignal(), [SIGINT, SIGTERM], true)) {
                $this->newLine();
            }
        } finally {
            $this->file->destroy();
        }
    }

    /**
     * Handles the object destruction.
     */
    public function __destruct()
    {
        if ($this->file) {
            $this->file->destroy();
        }
    }
}
