# SMS Blast Automation Ideas - Scheduler Integration

## Executive Summary

This document outlines automation opportunities for the existing SMS blast module. The current implementation supports manual sending and scheduled blasts via the admin panel, but lacks automated, event-driven SMS notifications that would enhance user experience and operational efficiency.

---

## Current System Overview

### Existing Architecture

```
SmsBlast (Model)
├── status: draft | scheduled | sending | sent | failed | cancelled
├── send_mode: now | scheduled | alltimes
├── scheduled_at: datetime
└── recipients() → SmsBlastRecipient

SmsBlastRecipient (Model)
├── status: pending | sent | failed
└── recipient: M06 (parent/guardian)

SmsBlastService
├── sendBlast()       - Immediate sending
├── scheduleBlast()   - Schedule for future
└── processScheduledBlasts() - Polls scheduled blasts

SendSmsService
└── sendnowsms()      - iSMS Malaysia API wrapper

Routes: admin-panel.php sms-blasts.*
Controller: SmsBlastController
```

### Existing Automation Example

**Notify10MinutesBeforeTimeOut Command** (`app:check-timeouts`):
- Runs via scheduler
- Queries `OrderItems` where checkout is within 10 minutes
- Sends SMS to parents/guardians
- Marks `notified_timeout` flag to prevent duplicate sends

---

## Automation Opportunities

### 1. Auto-Checkout Reminder (ALREADY EXISTS)

**Status:** ✅ Implemented

**Command:** `app:check-timeouts`

**Frequency:** Every 5-10 minutes

**Logic:**
- Finds order items ending within 10 minutes
- Groups by parent
- Sends consolidated SMS
- Sets `notified_timeout = true` to prevent duplicates

**Improvement Suggestions:**
- Move from `OrderItems` model to a dedicated `SmsQueue` or `NotificationLog` table
- Add retry mechanism for failed SMS
- Add template selection in admin panel
- Include guardian notifications (currently commented out)

---

### 2. Birthday Greetings Automation

**Business Value:** Customer retention, personal touch

**Frequency:** Daily at 6:00 AM

**Trigger:** Child's birthday (from `M06.dob` or `M06Child.birthdate`)

**Proposed Command:** `sms:birthday-greetings`

**Logic Flow:**
1. Query children with birthday = today
2. Get associated parent/guardian (`M06.d_code` link)
3. Retrieve "birthday-greetings" template
4. Replace `{child_name}` placeholder
5. Send via `SendSmsService::sendnowsms()`
6. Log in `sms_blast` table for tracking

**Database Considerations:**
```sql
-- Might need to add birthdate field if not present
ALTER TABLE m06 ADD COLUMN birthdate DATE;
-- or use M06Child table
SELECT c.*, p.d_name AS parent_name, p.mobileno 
FROM m06_child c 
JOIN m06 p ON c.d_code = p.d_code 
WHERE DATE(c.birthdate) = CURDATE();
```

**Prevention of Duplicates:**
- Use a `notification_log` table with (`child_id`, `notification_type`, `date`)
- Check before sending to avoid re-sending if command runs multiple times

---

### 3. First-Time Visitor Follow-Up

**Business Value:** Gather feedback, encourage repeat visits

**Frequency:** Daily at 4:00 PM (day after first visit)

**Trigger:** First `OrderItems` record for a child in last 24 hours

**Proposed Command:** `sms:follow-up-first-visit`

**Logic Flow:**
1. Find children with first visit yesterday
2. Get parent contact
3. Use custom "Thank You" template
4. Send appreciation message + upcoming promotions

**Edge Cases:**
- Distinguish between first visit and subsequent visits
- Exclude test accounts or staff children
- Optional opt-out mechanism (add `sms_opt_out` column to `M06`)

---

### 4. Inactive Customer Reactivation

**Business Value:** Win back lapsed customers

**Frequency:** Weekly (every Monday)

**Trigger:** No visits in last 30+ days

**Proposed Command:** `sms:reactivation-campaign`

**Logic Flow:**
1. Query `M06` where child has no order items in last 30 days
2. Filter active customers (not already marked `inactive` or `opted_out`)
3. Send "We Miss You" message with special discount code
4. Track response to measure campaign effectiveness

**Query Example:**
```sql
SELECT m.* 
FROM m06 m
WHERE NOT EXISTS (
    SELECT 1 FROM order_items oi 
    WHERE oi.d_code = m.d_code 
    AND oi.ckin >= DATE_SUB(NOW(), INTERVAL 30 DAY)
)
AND m.sms_opt_out = 0;
```

---

### 5. Session Extension / Overtime Alerts

**Status:** 🚀 New Feature

**Frequency:** Every 15 minutes

**Trigger:** Child session exceeded allocated duration

**Current Implementation:** 
- `Notify10MinutesBeforeTimeOut` handles 10-minute warning
- No current handling for actual overtime

**Proposed Command:** `sms:overtime-alerts`

**Logic Flow:**
1. Find `OrderItems` where `ckin + (durationhours * interval)` < NOW()
2. Filter `checked_out = false`
3. Group by parent
4. Send overtime notice with `{minutes_over}` variable
5. Optional: Escalate if > 30 minutes (call parent)

---

### 6. Weekly Play Session Summary

**Business Value:** Engagement, data-driven messaging

**Frequency:** Every Monday morning

**Trigger:** Summary of previous week's activities

**Proposed Command:** `sms:weekly-summary`

**Logic Flow:**
1. Gather stats per family:
   - Total visits last week
   - Total play hours
   - New milestones achieved
2. Personalize message per parent
3. Send "Here's What Your Child Enjoyed Last Week" message

**Data Sources:**
- `order_items` table aggregated
- `M06Child` details for personalization

---

### 7. Seasonal Promotions & Holiday Campaigns

**Business Value:** Revenue generation

**Frequency:** Configurable (one-time or recurring)

**Trigger:** Admin-defined date ranges

**Implementation Path:**

**Option A - Simple Config File:**
```php
// config/sms_campaigns.php
return [
    ' campaigns' => [
        'christmas-2026' => [
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-24',
            'template' => 'holiday-christmas',
            'target' => 'all_active',
        ],
        'summer-special' => [
            'schedule' => '0 9 * 6-8 *', // June-August, 9 AM daily
            'template' => 'summer-promo',
        ]
    ]
];
```

**Option B - Database Table (Recommended):**
```php
// Migration
Schema::create('sms_campaigns', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('template_type');
    $table->enum('target_audience', ['all', 'active', 'inactive', 'custom']);
    $table->json('target_filters')->nullable(); // Custom filters
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->time('send_time')->default('09:00:00');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Command:** `sms:run-campaigns`

Runs every hour, checks for active campaigns with scheduled send time, processes eligible recipients.

---

### 8. Reservation Reminders (if booking system exists)

**Business Value:** Reduce no-shows

**Frequency:** Daily at 8:00 AM for same-day bookings

**Trigger:** Bookings scheduled for today

**Proposed Command:** `sms:reservation-reminders`

**Logic:**
- Query bookings with date = today and mobile number present
- Send "We're excited to see you today at [time]" message
- Include parking/check-in instructions

---

### 9. Waitlist Notification

**Business Value:** Fill cancelled slots, maximize capacity

**Frequency:** Every 30 minutes during operational hours

**Trigger:** Slot becomes available (cancellation or no-show)

**Logic:**
- Monitor `order_items` flagged as `cancelled` or `no_show`
- Notify first 3-5 waitlisted families
- Include "claim this slot" link/instructions

---

### 10. Recurring Scheduled Blasts Monitor

**Status:** 🛠 Infrastructure Improvement

**Current Issue:** `processScheduledBlasts()` exists but needs to be invoked

**Proposed Implementation:**

**Artisan Command:**
```bash
php artisan sms:process-scheduled
```

**Kernel Registration:**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Every minute check for blasts ready to send
    $schedule->command('sms:process-scheduled')
             ->everyMinute();
    
    // Or every 5 minutes to reduce load
    $schedule->command('sms:process-scheduled')
             ->everyFiveMinutes();
}
```

**Enhancement:**
- Add `last_attempt_at` timestamp to retry failed sends
- Exponential backoff for API failures
- Alert admin if >5 blasts fail consecutively

---

## Implementation Priority Matrix

| # | Feature | Effort | Impact | Priority |
|---|---------|--------|--------|----------|
| 1 | Birthday Greetings Auto-Send | Low | High | P1 |
| 2 | Process Scheduled Blasts Daemon | Low | High | P1 |
| 3 | Overtime Alerts | Low | Medium | P2 |
| 4 | First-Time Follow-Up | Medium | High | P1 |
| 5 | Inactive Reactivation | Medium | Medium | P3 |
| 6 | Waitlist Notifications | High | Medium | P3 |
| 7 | Weekly Summary | Medium | Low | P4 |
| 8 | Seasonal Campaigns | High | High | P2 |
| 9 | Reservation Reminders | Low | Medium | P2 |

---

## Technical Implementation Guide

### 1. Create the Command Skeleton

```bash
php artisan make:command SendBirthdayGreetings
```

**Structure:**
```php
class SendBirthdayGreetings extends Command
{
    protected $signature = 'sms:birthday-greetings';
    protected $description = 'Send automated birthday greetings';
    
    public function handle()
    {
        // 1. Query recipients
        // 2. Prepare messages
        // 3. Send via SendSmsService
        // 4. Log results
        // 5. Output summary
    }
}
```

### 2. Use Existing SmsBlast Model (Recommended)

Instead of creating new tables, leverage existing `sms_blasts`:

```php
$blast = SmsBlast::create([
    'title' => 'Birthday Greetings - ' . date('Y-m-d'),
    'message' => $personalizedMessage,
    'status' => SmsBlast::STATUS_SENDING,
    'slug' => 'birthday-greetings-auto',
    'type' => 'automated',
    'send_mode' => 'now',
    'total_recipients' => $recipients->count(),
]);

$result = $smsBlastService->sendBlast($blast, $recipientIds);
```

**Benefits:**
- Unified reporting in admin panel
- Reuses existing tracking infrastructure
- Audit trail retained
- Can view failed messages in UI

### 3. Add Automation Metadata to SmsBlast

Optional schema additions:

```php
Schema::table('sms_blasts', function (Blueprint $table) {
    $table->string('automation_type')->nullable()->after('send_mode');
    $table->string('cron_schedule')->nullable()->after('automation_type');
    $table->boolean('is_automated')->default(false)->after('cron_schedule');
    $table->timestamp('last_run_at')->nullable()->after('is_automated');
});
```

---

## Scheduler Registration

**File:** `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    // Existing timeout notifications
    $schedule->command('app:check-timeouts')
             ->everyFiveMinutes();
    
    // New automations
    $schedule->command('sms:birthday-greetings')
             ->dailyAt('06:00');
             
    $schedule->command('sms:overtime-alerts')
             ->everyFifteenMinutes();
             
    $schedule->command('sms:process-scheduled')
             ->everyMinute();
             
    $schedule->command('sms:reactivation-campaign')
             ->weeklyOn(1, '09:00'); // Mondays
    
    // Seasonal campaigns - runs hourly
    $schedule->command('sms:run-campaigns')
             ->hourly();
}
```

**Server Cron:**
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Ensure this is in your server's crontab.

---

## Monitoring & Alerting

### 1. Dashboard Metrics

**Add to Admin Dashboard:**
```php
Stats to Display:
- Scheduled blasts count
- Pending automated sends (last 24h)
- Failed SMS rate (last 7 days)
- automation execution log (last 10 runs)
```

### 2. Failed SMS Retry Logic

In `SmsBlastService::sendToRecipient()`:
```php
private function sendToRecipient(SmsBlast $blast, $recipient)
{
    $attempts = 0;
    $maxAttempts = 3;
    
    while ($attempts < $maxAttempts) {
        $result = SendSmsService::sendnowsms(...);
        
        if ($result['success']) {
            return $result;
        }
        
        $attempts++;
        if ($attempts < $maxAttempts) {
            sleep(pow(2, $attempts)); // Exponential backoff
        }
    }
    
    // Mark as failed after retries
    return ['success' => false, 'message' => 'Max retries exceeded'];
}
```

### 3. Email Alerts for Failures

```php
if ($failedRate > 0.2) { // >20% failure rate
    Mail::raw("High SMS failure rate detected: {$failedRate}%", function($msg) {
        $msg->to('admin@playhouse.com')
            ->subject('SMS Blast Alert');
    });
}
```

---

## Best Practices & Recommendations

### 1. Rate Limiting

iSMS provider may have limits. Implement:

```php
// In command handle()
$batchSize = 50;
$recipients->chunk($batchSize, function($batch) {
    foreach ($batch as $recipient) {
        $this->sendToRecipient($recipient);
        sleep(1); // 1-second delay between sends
    }
});
```

### 2. Message Queueing (Advanced)

For high volume (1000+ SMS/day), migrate to Laravel Queues:

```bash
php artisan queue:work --tries=3
```

Refactor `sendToRecipient()` to dispatch `SendSmsJob` instead of immediate send.

### 3. Template Management

Create admin UI for editing templates instead of hardcoded array.

**Migration:**
```php
Schema::create('sms_templates', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->text('message');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Service:** `TemplateManagementService` with caching.

### 4. Opt-Out / Compliance

Add `sms_opt_out` column to `M06` table:

```php
Schema::table('m06', function (Blueprint $table) {
    $table->boolean('sms_opt_out')->default(false);
    $table->timestamp('sms_opt_out_at')->nullable();
});
```

Filter queries: `->where('sms_opt_out', false)`

### 5. Duplicate Prevention

Create `notification_logs` table:

```php
Schema::create('notification_logs', function (Blueprint $table) {
    $table->id();
    $table->string('recipient_id'); // M06 d_code
    $table->string('automation_type');
    $table->date('sent_date');
    $table->timestamps();
    $table->unique(['recipient_id', 'automation_type', 'sent_date']);
});
```

Check before sending:
```php
$alreadySent = NotificationLog::where('recipient_id', $recipient->d_code)
    ->where('automation_type', 'birthday')
    ->whereDate('sent_date', today())
    ->exists();

if (!$alreadySent) {
    // Send SMS
}
```

---

## Testing Strategy

### 1. Unit Tests

**Test Cases:**
- `SmsBlastService::processScheduledBlasts()` returns correct count
- `SendSmsService::formatPHNumber()` correctly formats various inputs
- Template parser replaces all variables

### 2. Feature Tests

```php
public function test_birthday_greeting_command_sends_to_children_with_birthday_today()
{
    // Arrange: Create child with birthday today
    // Act: Run command
    // Assert: SMS sent, log created, blast record exists
}
```

### 3. Dry-Run Mode

Add `--dry-run` flag to commands:

```bash
php artisan sms:birthday-greetings --dry-run
```

Outputs:
```
[DRY RUN] Would send 12 birthday messages
- Maria Santos (0917-123-4567) for child: Juan Santos
- ...
```

### 4. Mock iSMS API

Use Laravel's HTTP fake in tests:

```php
Http::fake([
    'www.isms.com.my/*' => Http::response('OK', 200)
]);
```

---

## Deployment Checklist

- [ ] All artisan commands registered in `Kernel.php`
- [ ] Server cron running `schedule:run` every minute
- [ ] Queue worker running (if using queues)
- [ ] `.env` iSMS credentials configured
- [ ] Admin notification preferences set (email alerts)
- [ ] Monitoring dashboard created
- [ ] Dry-run tests on staging environment
- [ ] Backup rollback plan documented

---

## Cost Estimation

**Current iSMS Rate:** ₱1.50 per SMS (from mockup)

**Monthly Projections:**

| Automation | Daily Volume | Monthly Cost |
|------------|-------------|--------------|
| Birthday Greetings | 5-10 SMS/day | ₱225-450 |
| Timeout Reminders | 20-30 SMS/day | ₱900-1,350 |
| Overtime Alerts | 2-5 SMS/day | ₱90-225 |
| Follow-Up | 10-15 SMS/day | ₱450-675 |
| **Total (Est.)** | **~50 SMS/day** | **₱1,665-2,700** |

**Note:** Adjust based on actual customer volume.

---

## Future Enhancements

1. **Two-Way SMS:** Receive replies from parents (requires iSMS inbound feature)
2. **Rich Media:** Send MMS with playground photos
3. **Personalization Engine:** Machine learning to recommend optimal send times
4. **A/B Testing:** Test message variants, measure response rates
5. **Webhook Integration:** Real-time sync with iSMS delivery receipts
6. **Multi-Channel:** Fallback to email if SMS fails (requires email service integration)
7. **SMS Survey:** Post-visit satisfaction survey via SMS

---

## Conclusion

The existing SMS blast module provides a solid foundation. By adding scheduled automation via Laravel Scheduler, you can:

- ✅ Reduce manual admin workload
- ✅ Improve customer experience through timely, relevant messages
- ✅ Increase retention and repeat visits
- ✅ Proactive issue resolution (overtime alerts)
- 💰 Potential revenue uplift through targeted promotions

**Next Steps:**
1. Prioritize by business impact (Birthday + Scheduled Blasts Daemon = immediate wins)
2. Implement one automation as proof-of-concept
3. Monitor delivery rates and user feedback
4. Iterate and expand to other use cases
