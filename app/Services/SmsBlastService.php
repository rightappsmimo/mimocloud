<?php

namespace App\Services;

use App\Models\SmsBlast;
use App\Models\SmsBlastRecipient;
use App\Models\M06;
use App\Models\M06Child;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SmsBlastService
{
    /**
     * Send SMS blast to selected recipients
     */
    public function sendBlast(SmsBlast $blast, $recipientIds = [])
    {
        try {
            if(!$blast->status === SmsBlast::STATUS_SENDING)
            {
                $blast->update(['status' => SmsBlast::STATUS_SENDING]);
            }

            $recipients = $this->getRecipients($blast, $recipientIds);

            if ($recipients->isEmpty()) {
                $blast->update(['status' => SmsBlast::STATUS_FAILED]);
                return ['success' => false, 'message' => 'No recipients found'];
            }

            $this->createRecipients($blast, $recipients);

            $sent = 0;
            $failed = 0;

            foreach ($recipients as $recipient) {
                $result = $this->sendToRecipient($blast, $recipient);

                if ($result['success']) {
                    $sent++;
                } else {
                    $failed++;
                }
            }

            $blast->update([
                'sent_count' => $sent,
                'failed_count' => $failed,
                'status' => $failed > 0 ? SmsBlast::STATUS_FAILED : SmsBlast::STATUS_SENT,
                'sent_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'sent' => $sent,
                'failed' => $failed,
                'blast' => $blast
            ];

        } catch (\Exception $e) {
            $blast->update(['status' => SmsBlast::STATUS_FAILED]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Schedule SMS blast
     */
    public function scheduleBlast(SmsBlast $blast, $recipientIds = [], $scheduledAt)
    {
        $recipients = $this->getRecipients($blast, $recipientIds);

        if ($recipients->isEmpty()) {
            return ['success' => false, 'message' => 'No recipients found'];
        }

        $blast->update([
            'status' => SmsBlast::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
        ]);

        $this->createRecipients($blast, $recipients);

        return [
            'success' => true,
            'blast' => $blast,
            'recipient_count' => $recipients->count()
        ];
    }

    /**
     * Send to scheduled blasts
     */
    public function processScheduledBlasts()
    {
        $blasts = SmsBlast::where('status', SmsBlast::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', Carbon::now())
            ->where('type', 'campaign')
            ->get();

        foreach ($blasts as $blast) {

            $updated = SmsBlast::where('id', $blast->id)
                ->where('status', SmsBlast::STATUS_SCHEDULED)
                ->update([
                    'status' => SmsBlast::STATUS_SENDING
                ]);

            if (!$updated) {
                continue;
            }

            $this->sendBlast($blast);
        }

        return $blasts->count();
    }

    /**
     * Get recipients for blast
     */
    private function getRecipients(SmsBlast $blast, $recipientIds = [])
    {
        if (!empty($recipientIds)) {
            return M06::whereIn('d_code', $recipientIds)
                ->whereNotNull('mobileno')
                ->get();
        }

        // If no specific recipients, use all from blast recipients table
        $recipientIds = $blast->recipients()->pluck('recipient_id')->toArray();

        if (empty($recipientIds)) {
            return collect();
        }

        return M06::whereIn('d_code', $recipientIds)
            ->whereNotNull('mobileno')
            ->get();
    }

    /**
     * Create recipient records
     */
    private function createRecipients(SmsBlast $blast, $recipients)
    {
        $existingIds = $blast->recipients()->pluck('recipient_id')->toArray();
        $newRecipients = [];

        foreach ($recipients as $recipient) {
            if (!in_array($recipient->d_code, $existingIds)) {
                $newRecipients[] = [
                    'sms_blast_id' => $blast->id,
                    'recipient_type' => $recipient->isparent ? 'parent' : ($recipient->isguardian ? 'guardian' : 'other'),
                    'recipient_id' => $recipient->d_code,
                    'recipient_name' => $recipient->d_name,
                    'mobile_number' => $recipient->mobileno,
                    'status' => SmsBlastRecipient::STATUS_PENDING,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        if (!empty($newRecipients)) {
            SmsBlastRecipient::insert($newRecipients);
        }

        // Update blast recipient count
        $blast->update(['total_recipients' => $blast->recipients()->count()]);
    }

    /**
     * Send SMS to a single recipient
     */
    private function sendToRecipient(SmsBlast $blast, $recipient)
    {
        $message = $this->prepareMessage($blast->message, $recipient);
        $message = mb_convert_encoding($message, 'ASCII', 'UTF-8');

        $result = SendSmsService::sendnowsms($recipient->mobileno, $message);

        $recipientRecord = $blast->recipients()
            ->where('recipient_id', $recipient->d_code)
            ->first();

        if ($recipientRecord) {
            if ($result['success']) {
                $recipientRecord->update([
                    'status' => SmsBlastRecipient::STATUS_SENT,
                    'sent_at' => Carbon::now(),
                ]);
            } else {
                $recipientRecord->update([
                    'status' => SmsBlastRecipient::STATUS_FAILED,
                    'error_message' => $result['response'],
                ]);
            }
        }

        return $result;
    }

    /**
     * Prepare message with variables
     */
    public function prepareMessage($message, $recipient)
    {
        // Get first child if available for variables
        $child = M06Child::where('d_code', $recipient->d_code)->first();

        $replacements = [
            '{child_name}' => $child ? $child->firstname : '',
            '{parent_name}' => $recipient->d_name ?? '',
            '{time_remaining}' => '10', // Default for template use
            '{minutes_over}' => '0',
            '{checkout_time}' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Get default templates
     */
    public function getDefaultTemplates()
    {
        return [
            [
                'name' => 'Birthday Greetings',
                'description' => 'Sent on child\'s birthday with warm wishes from the cafe',
                'slug' => 'birthday-greetings',
                'message' => "Happy Birthday {child_name}! From all of us at Mimo Play Cafe, we hope you have a wonderful day filled with joy and laughter! 🎂",
            ],
            [
                'name' => 'Time is Almost Up',
                'description' => '10-minute warning before session ends. Prepare for checkout',
                'slug' => 'timeout-reminder',
                'message' => "FRIENDLY REMINDER FROM MIMO PLAY CAFE\n\n{parent_name}, your child {child_name}'s session will end in {time_remaining} minutes. Please prepare for checkout. They will be ready for pick up."
            ],
            [
                'name' => 'Overtime',
                'description' => 'Notify when child exceeds allocated playtime',
                'slug' => 'overtime-reminder',
                'message' => "NOTICE: {child_name} has exceeded playtime by {minutes_over} minutes. Additional charges may apply. Please proceed to checkout immediately."
            ],
            [
                'name' => 'Check Out',
                'description' => 'Reminder to complete checkout',
                'slug' => 'checkout-reminder',
                'message' => "Thank you for visiting Mimo Play Cafe, {parent_name}! {child_name}'s checkout time is {checkout_time}. Please visit the counter to complete your checkout process."
            ],
            [
                'name' => 'Custom',
                'description' => '',
                'slug' => 'custom',
                'message' => "",
            ]
        ];
    }
}
