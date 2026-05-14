# SMS Blast User Guide

## Overview

The SMS Blast module enables sending bulk SMS messages to parents and guardians at Mimo Play Cafe. It supports immediate sending, scheduled campaigns, and automated notifications triggered by specific events.

## Architecture

### Core Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `SmsBlast` | `app/Models/SmsBlast.php` | Main blast model with status tracking |
| `SmsBlastRecipient` | `app/Models/SmsBlastRecipient.php` | Individual recipient records |
| `SmsBlastService` | `app/Services/SmsBlastService.php` | Core sending logic |
| `SmsBlastController` | `app/Http/Controllers/SmsBlastController.php` | Admin panel controller |
| `SendSmsService` | `app/Services/SendSmsService.php` | iSMS Malaysia API integration |

### Database Schema

**sms_blasts table:**
- `id` - Primary key
- `title` - Blast title/name
- `message` - SMS content (max 255 chars)
- `status` - draft/scheduled/sending/sent/failed/cancelled
- `type` - automation/campaign
- `slug` - Template identifier (birthday-greetings, timeout-reminder, etc.)
- `send_mode` - now/scheduled/alltimes
- `scheduled_at` - Scheduled datetime
- `sent_at` - Actual send datetime
- `total_recipients` - Total recipient count
- `sent_count` - Successfully sent count
- `failed_count` - Failed send count

**sms_blast_recipients table:**
- `sms_blast_id` - Foreign key to sms_blasts
- `recipient_id` - M06 d_code (parent/guardian)
- `recipient_type` - parent/guardian/other
- `mobile_number` - Formatted phone number
- `status` - pending/sent/failed
- `error_message` - Failure reason (if any)

## SMS Blast Types

### 1. Automation Blasts

Automated, event-driven SMS triggered by system events. These run on a schedule and cannot be manually triggered.

| Slug | Description | Trigger |
|------|-------------|---------|
| `birthday-greetings` | Birthday wishes to children | Daily at 9:00 AM (Asia/Manila) |
| `timeout-reminder` | 10-minute session warning | Every minute while sessions ending |
| `checkout-reminder` | Session end notification | Every minute while sessions ending |
| `overtime-reminder` | Overtime exceeded alert | Every minute while overtime occurs |

### 2. Campaign Blasts

Manual/one-time campaigns created and sent by administrators.

| Send Mode | Description |
|-----------|-------------|
| `now` | Send immediately to selected recipients |
| `scheduled` | Send at a specific date/time |
| `alltimes` | Template-only mode (used by automations) |

## Message Variables

Personalize messages using these placeholders:

| Variable | Description | Source |
|----------|-------------|--------|
| `{child_name}` | Child's first name | M06Child.firstname |
| `{parent_name}` | Parent's name | M06.d_name |
| `{time_remaining}` | Minutes until session ends | Calculated |
| `{minutes_over}` | Minutes exceeded | Calculated |
| `{checkout_time}` | Current timestamp | Carbon::now() |

## Admin Panel Usage

### Creating a Campaign Blast

1. Navigate to **Admin Panel → SMS Blasts**
2. Click **Create New Blast**
3. Fill in the form:
   - **Title**: Descriptive name for the blast
   - **Type**: Select "campaign" for manual blasts
   - **Message**: SMS content (max 255 characters)
   - **Send Mode**: Choose timing option
   - **Recipients**: Select specific contacts or all

### Predefined Templates

| Template | Default Message |
|----------|-----------------|
| Birthday Greetings | "Happy Birthday {child_name}! From all of us at Mimo Play Cafe..." |
| Time is Almost Up | "FRIENDLY REMINDER FROM MIMO PLAY CAFE\n{parent_name}, your child {child_name}'s session will end in {time_remaining} minutes..." |
| Overtime | "NOTICE: {child_name} has exceeded playtime by {minutes_over} minutes..." |
| Check Out | "Thank you for visiting Mimo Play Cafe, {parent_name}!..." |

### Managing Blasts

- **View**: Click the eye icon to see details and recipient status
- **Edit**: Draft blasts or automations can be modified
- **Resend Failed**: After a blast completes, resend to failed recipients
- **Delete**: Remove draft blasts (cannot delete sent records)

## Cron Jobs & Automations

### Server Configuration

The automations are triggered via HTTP requests. Set up external cron jobs to call these endpoints every minute:

```bash
# Every minute - Recurring automations
* * * * * curl -s "https://your-domain.com/run-scheduler/reccured?key=YOUR_SCHEDULER_KEY" >/dev/null 2>&1

# Every minute - Process scheduled blasts
* * * * * curl -s "https://your-domain.com/run-scheduler/scheduled?key=YOUR_SCHEDULER_KEY" >/dev/null 2>&1

# Daily at 9:00 AM - Birthday greetings (Asia/Manila timezone)
0 9 * * * curl -s "https://your-domain.com/run-scheduler/time-based?key=YOUR_SCHEDULER_KEY" >/dev/null 2>&1
```

### HTTP Trigger Endpoints

| Endpoint | Purpose | Commands Executed |
|----------|---------|-------------------|
| `/run-scheduler/reccured` | Recurring checks | `sms:timeout-reminder`, `sms:checkout-reminder`, `sms:overtime-reminder` |
| `/run-scheduler/scheduled` | Scheduled blasts | `sms:process-scheduled-blasts` |
| `/run-scheduler/time-based` | Time-based automations | `sms:birthday-greetings` |

**Security:** All endpoints require `?key=YOUR_SCHEDULER_KEY` query parameter matching `SCHEDULER_KEY` in `.env`.

### Environment Configuration

Add to your `.env` file:
```env
SCHEDULER_KEY=your-secret-key-here
```

### Configured Schedules

| Command | Frequency | HTTP Trigger | Purpose |
|---------|-----------|--------------|---------|
| `sms:timeout-reminder` | Every minute | `/run-scheduler/reccured` | 10-min session warnings |
| `sms:checkout-reminder` | Every minute | `/run-scheduler/reccured` | Session end notifications |
| `sms:overtime-reminder` | Every minute | `/run-scheduler/reccured` | Overtime alerts |
| `sms:process-scheduled-blasts` | Every minute | `/run-scheduler/scheduled` | Process scheduled campaigns |
| `sms:birthday-greetings` | Daily at 9:00 AM | `/run-scheduler/time-based` | Birthday SMS (Asia/Manila) |

### Automation Logic Details

All automations are triggered via HTTP endpoints defined in `routes/http-reqs.php` and handled by `RunCommandsViaHttp` controller.

#### Birthday Greetings (`sms:birthday-greetings`)
- Triggered via `/run-scheduler/time-based` HTTP endpoint
- Runs daily at 9:00 AM (Asia/Manila timezone)
- Queries `m06_child` table for birthdays matching today's date
- Retrieves parent contact from `m06.d_code` relationship
- Uses `birthday-greetings` slug template

#### Timeout Reminder (`sms:timeout-reminder`)
- Triggered via `/run-scheduler/reccured` HTTP endpoint
- Checks `order_items` where `ckin + durationhours * 1 hour` is within next 10 minutes
- Filters `checked_out = false` and `notified_timeout = false`
- Marks records with `notified_timeout = true` after sending

#### Checkout Reminder (`sms:checkout-reminder`)
- Triggered via `/run-scheduler/reccured` HTTP endpoint
- Checks `order_items` where session end time has passed
- Filters `checked_out = false` and `notified_checkout = false`
- Marks records with `notified_checkout = true` after sending

#### Overtime Reminder (`sms:overtime-reminder`)
- Triggered via `/run-scheduler/reccured` HTTP endpoint
- Checks `order_items` where session end time exceeded by up to 1 hour
- Filters `checked_out = false` and `notified_overtime = false`
- Marks records with `notified_overtime = true` after sending

#### Process Scheduled Blasts (`sms:process-scheduled-blasts`)
- Triggered via `/run-scheduler/scheduled` HTTP endpoint
- Queries `sms_blasts` where `status = scheduled` and `scheduled_at <= now`
- Only processes `type = campaign` (not automations)
- Atomically updates status to `sending` before processing

## Monitoring

### Admin Dashboard Metrics

- **Total Blasts**: All-time count
- **Sent**: Successfully completed blasts
- **Scheduled**: Pending future sends
- **Failed**: Blasts with delivery failures

### Viewing Results

Each blast shows:
- Total recipients
- Sent count / Failed count
- Individual recipient status in detail view

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Blast shows "failed" status | Check individual recipient errors in blast details |
| No SMS received | Verify parent mobile number in M06 table |
| Automation not triggering | Verify cron job is running and check Laravel logs |
| Rate limiting errors | Reduce frequency or batch size in SmsBlastService |

### Checking Logs

```bash
# View recent SMS-related logs
tail -f storage/logs/laravel.log | grep -i sms

# Test command manually
php artisan sms:birthday-greetings --dry-run
```

## API Integration

SMS is sent via iSMS Malaysia service:

**Required .env variables:**
```env
SERVICES_ISMS_API=https://www.isms.com.my/
SERVICES_ISMS_USER=your_username
SERVICES_ISMS_PASSWORD=your_password
SERVICES_ISMS_SENDER_ID=MIMO
SERVICES_ISMS_TYPE=1
```

## Best Practices

1. **Test before sending**: Use draft status to review before scheduling
2. **Personalize**: Use `{child_name}` and `{parent_name}` for better engagement
3. **Monitor failures**: Regularly check and resend to failed recipients
4. **Character limit**: Keep under 160 characters for single-SMS delivery
5. **Timezone awareness**: All scheduled times use Asia/Manila timezone
