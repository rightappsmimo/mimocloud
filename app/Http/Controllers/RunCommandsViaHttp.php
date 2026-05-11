<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunCommandsViaHttp extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('key') !== env('SCHEDULER_KEY')) 
        {
            abort(403, 'Unauthorized');
        }

        $logs = [];
        $start = now();

        $logs[] = "Scheduler started at: " . $start;

        try {
            $this->commandCall(
                'otp:clean-expired', 
                "otp:clean-expired executed"
            );

            $this->commandCall(
                'sms:process-scheduled-blasts', 
                "sms:process-scheduled-blasts executed"
            );

            $this->commandCall(
                'sms:timeout-reminder', 
                "sms:timeout-reminder executed"
            );

        } catch (\Exception $e) {
            $logs[] = "Error: " . $e->getMessage();
        }

        $end = now();
        $logs[] = "Finished at: " . $end;
        $logs[] = "Duration: " . $start->diffInSeconds($end) . " seconds";

        Log::info('Scheduler run', $logs);

        return response("<pre>" . implode("\n", $logs) . "</pre>");
    }

    private function commandCall($command, $log)
    {
        Artisan::call($command);
        $logs[] = $log;

        $logs[] = Artisan::output();
    }
}
