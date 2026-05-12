<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmsBlast;
use App\Models\M06Child;
use Carbon\Carbon;
use App\Services\SmsBlastService;
use Illuminate\Support\Facades\Log;

class NotifyBirthdays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:birthday-greetings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automatically birthdays notifications';

    /**
     * Execute the console command.
     */
    public function handle(SmsBlastService $smsBlastService)
    {
        $blast = SmsBlast::getAutomatedBlast(SmsBlast::SLUG_BIRTHDAY);
        if (!$blast)
        {
            $this->error('Birthday greeting blast not found.');

            return 1;
        }

        $items = $this->querySessions();
        if (!$items)
        {
            return 0;
        }

        $recipientIds = [];

        foreach ($items as $item)
        {
            $parent = $item->parent;

            if (!$parent || !$parent->mobileno) {
                continue;
            }

            $recipientIds[] = $parent->d_code;
        }

        $recipientIds = array_unique($recipientIds);

        if (empty($recipientIds))
        {
            $this->info('No valid recipients.');

            return 0;
        }

        $result = $smsBlastService->sendBlast(
            $blast,
            $recipientIds
        );

        $this->info("Birthday greetings processed.");
        $this->info("Sent: {$result['sent']}");
        $this->info("Failed: {$result['failed']}");

        return 0;
    }

    private function querySessions()
    {
        $today = Carbon::now();
        $children = M06Child::with('parent')
            ->whereMonth('birthday', $today->month)
            ->whereDay('birthday', $today->day)
            ->get();

        if ($children->isEmpty()) {
            $this->info('No birthdays today.');
            return [];
        }

        return $children;
    }
}
