<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WorkCommand extends Command
{
    protected $signature = 'catalogix:work
        {--discovery=2 : Number of discovery workers}
        {--pages=2 : Number of product-page workers}
        {--images=2 : Number of image workers}
        {--exports=1 : Number of export workers}
        {--default=1 : Number of default workers}
        {--dry-run : Print worker commands without starting them}';

    protected $description = 'Keep independent Catalogix queue workers running, restarting workers when they exit';

    public function handle(): int
    {
        if (! in_array(config('queue.default'), ['database', 'redis', 'beanstalkd', 'sqs'], true)) {
            $this->error('Choose a persistent QUEUE_CONNECTION (database or redis recommended).');

            return self::FAILURE;
        }

        /** @var array<string, Process> $workers */
        $workers = [];

        foreach (['discovery' => 960, 'pages' => 240, 'images' => 90, 'exports' => 960, 'default' => 960] as $queue => $timeout) {
            $count = filter_var($this->option($queue), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 16]]);

            if ($count === false) {
                $this->error("--{$queue} must be between 0 and 16.");

                return self::FAILURE;
            }

            for ($index = 1; $index <= $count; $index++) {
                $process = new Process([
                    PHP_BINARY, base_path('artisan'), 'queue:work', "--queue={$queue}",
                    "--timeout={$timeout}", '--sleep=1', $queue === 'default' ? '--tries=3' : '--tries=0', '--memory=256', '--max-time=3600',
                ], base_path(), timeout: null);
                $workers["{$queue}-{$index}"] = $process;
            }
        }

        if ($workers === []) {
            $this->error('Enable at least one worker.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            foreach ($workers as $name => $process) {
                $this->line("[{$name}] ".$process->getCommandLine());
            }

            return self::SUCCESS;
        }

        $stopping = false;

        if (extension_loaded('pcntl')) {
            $this->trap([SIGTERM, SIGINT, SIGQUIT], function () use (&$stopping): void {
                $stopping = true;
            });
        }

        $this->info('Catalogix background workers are running. Press Ctrl+C to stop.');

        try {
            foreach ($workers as $process) {
                $process->start();
            }

            while (! $stopping) {
                foreach ($workers as $name => $process) {
                    foreach ([$process->getIncrementalOutput(), $process->getIncrementalErrorOutput()] as $output) {
                        if ($output !== '') {
                            $this->output->write("[{$name}] ".$output);
                        }
                    }

                    if (! $process->isRunning()) {
                        $this->warn("Restarting {$name} (exit ".$process->getExitCode().').');
                        $process->start();
                    }
                }

                usleep(500_000);
            }
        } finally {
            // Give active jobs their full timeout to finish before stopping the process tree.
            if (extension_loaded('pcntl')) {
                foreach ($workers as $process) {
                    if ($process->isRunning()) {
                        $process->signal(SIGTERM);
                    }
                }
            }

            foreach ($workers as $process) {
                $process->stop(970);
            }
        }

        return self::SUCCESS;
    }
}
