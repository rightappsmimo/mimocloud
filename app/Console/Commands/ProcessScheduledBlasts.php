<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SmsBlastService;

class ProcessScheduledBlasts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:process-scheduled-blasts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled blasts';

    /**
     * Execute the console command.
     */
    public function handle(SmsBlastService $smsBlast)
    {
        $count = $smsBlast->processScheduledBlasts();

        $this->info("Processed {$count} scheduled SMS blasts.");

        return Command::SUCCESS;
    }
}
