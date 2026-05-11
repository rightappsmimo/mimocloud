<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderItems;
use App\Models\SmsBlast;
use App\Models\M06;
use Carbon\Carbon;
use App\Services\SmsBlastService;
use App\Services\SendSmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Notify10MinutesBeforeTimeOut extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:timeout-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 10-minute timeout reminder SMS (uses SMS Blast automation)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        $items = OrderItems::with(['child.guardians' => function($query) {
                $query->orderBy('created_at', 'desc');
            }, 'child.parent'])
            ->where(function ($query) use ($now) {
                $query->whereRaw(
                    "ckin + (durationhours * interval '1 hour') <= ?",
                    [ $now->copy()->addMinutes(10) ]
                )
                ->orWhereRaw(
                    "ckin + (durationhours * interval '1 hour') BETWEEN ? AND ?",
                    [
                        $now,
                        $now->copy()->addMinutes(10)
                    ]
                );
            })
            ->where('checked_out', false)
            ->where('notified_timeout', false)
            ->where('durationhours', '!=', 5)
            ->get();

        $notifications = [];

        foreach ($items as $item)
        {
            $parent = $item->child->updatedby;
            if (!$parent) continue;

            if (!isset($notifications[$parent]))
            {
                $notifications[$parent] = [];
            }

            $notifications[$parent][] = $item->child;
        }

        foreach ($notifications as $parent => $children)
        {
            $childrenNames = [];
            $guardianMap = [];
            $parentData = M06::where('d_name', $parent)->select('mobileno', 'lastname')->first();

            foreach ($children as $child)
            {
                $childrenNames[] = $child->firstname . ' ' . $parentData->lastname;

                $latestGuardian = $child->guardians->first();
                if ($latestGuardian && $latestGuardian->guardianauthorized)
                {
                    $guardianMap[$latestGuardian->d_name][] = $child->firstname;
                }
            }

            $childrenString = implode("\n\t", $childrenNames);

            $guardianMessages = [];
            foreach ($guardianMap as $guardianName => $childNames)
            {
                $childList = implode("\n\t", $childNames);
                $guardianMessages[] = "{$guardianName} can pick up {$childList}";
            }

            $guardianString = implode("\n", $guardianMessages);

            $message = "FRIENDLY REMINDER FROM MIMO PLAY CAFE\n\n";
            $message .= "{$parent}, your children:\n";
            $message .= "\t{$childrenString} \n\n";
            $message .= "They will be ready for pick up.";
            // if (!empty($guardianString))
            // {
            //     $message .= "\n\n{$guardianString}";
            // }

            $message = mb_convert_encoding($message, 'ASCII', 'UTF-8');

            $this->sendNotification($message, $parentData->mobileno);

        }
        $sendNotifications = OrderItems::whereIn('id', $items->pluck('id'))->update(['notified_timeout' => true]);

        return 0;
    }

    private function sendNotification($msg, $recepientNum)
    {
        $this->info($msg);
        $response = SendSmsService::sendnowsms($recepientNum, $msg);
        $this->info("\nSMS response: {$response['response']}\n");
        return true;
    }

}
