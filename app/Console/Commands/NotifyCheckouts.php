<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderItems;
use App\Models\SmsBlast;
use App\Services\SmsBlastService;
use Carbon\Carbon;

class NotifyCheckouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:checkout-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automatically checkout reminders';

    /**
     * Execute the console command.
     */
    public function handle(SmsBlastService $smsBlastService)
    {
        $blast = SmsBlast::getAutomatedBlast(SmsBlast::SLUG_CHECKOUT);
        if (!$blast)
        {
            $this->error('Checkout reminder blast not found.');

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
            $parent = $item->child->parent;

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

        OrderItems::whereIn('id', $items->pluck('id'))
            ->update([
                'notified_checkout' => true
            ]);

        $this->info("Checkout reminders processed.");
        $this->info("Sent: {$result['sent']}");
        $this->info("Failed: {$result['failed']}");

        return 0;
    }

    private function querySessions()
    {
        $now = Carbon::now();

        $items = OrderItems::with(['child.parent'])
            ->where(function ($query) use ($now) {
                $query->whereRaw(
                    "ckin + (durationhours * interval '1 hour') BETWEEN ? AND ?",
                    [
                        $now,
                        $now->copy()->addMinute()
                    ]
                );
            })
            ->where('checked_out', false)
            ->where('notified_checkout', false)
            ->where('durationhours', '!=', 5)
            ->get();

        if ($items->isEmpty())
        {
            $this->info('No checkout reminders.');
            return [];
        }

        return $items;
    }
}
