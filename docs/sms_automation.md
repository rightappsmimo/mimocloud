# SMS Automation Module - Development Documentation

**Date:** May 2026  
**Developer:** [To be filled]  
**Project:** Mimo Play Cafe - SMS Blast Automation System

---

## Table of Contents
1. [Foundation Layer](#phase-1-foundation-layer)
2. [Database Migration](#phase-2-database-migration)
3. [Model Implementation](#phase-3-model-implementation)
4. [Service Layer](#phase-4-service-layer)
5. [Console Commands](#phase-5-console-commands)
6. [HTTP Scheduler Layer](#phase-6-http-scheduler-layer)
7. [Controller Layer](#phase-7-controller-layer)
8. [API Integration](#phase-8-api-integration)
9. [Frontend Integration](#phase-9-frontend-integration)

---

## Phase 1: Foundation Layer

### Models Structure

**File:** `app/Models/SmsBlast.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsBlast extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'message', 'status', 'slug',
        'total_recipients', 'sent_count', 'failed_count',
        'type', 'send_mode', 'scheduled_at', 'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Slug constants for templates
    const SLUG_BIRTHDAY = 'birthday-greetings';
    const SLUG_TIMEOUT = 'timeout-reminder';
    const SLUG_OVERTIME = 'overtime-reminder';
    const SLUG_CHECKOUT = 'checkout-reminder';
    const SLUG_CUSTOM = 'custom';

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsBlastRecipient::class);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeAutomation($query)
    {
        return $query->where('type', 'automation');
    }

    public static function getAutomatedBlast(string $slug): ?self
    {
        return self::automation()->firstWhere('slug', $slug);
    }
}
```

**File:** `app/Models/SmsBlastRecipient.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsBlastRecipient extends Model
{
    protected $fillable = [
        'sms_blast_id', 'recipient_type', 'recipient_id',
        'recipient_name', 'mobile_number', 'status',
        'error_message', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const DEFAULT_RECIPIENT_TYPE = 'parent';

    public function smsBlast(): BelongsTo
    {
        return $this->belongsTo(SmsBlast::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(M06::class, 'recipient_id', 'd_code');
    }
}
```

---

## Phase 2: Database Migration

**File:** `database/migrations/2026_04_27_000000_create_sms_blasts_table.php`

```php
Schema::create('sms_blasts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('message');
    $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled'])->default('draft');
    $table->integer('total_recipients')->default(0);
    $table->integer('sent_count')->default(0);
    $table->integer('failed_count')->default(0);
    $table->enum('type', ['automation', 'campaign'])->default('automation');
    $table->string('slug')->nullable();
    $table->enum('send_mode', ['now', 'scheduled', 'alltimes'])->default('alltimes');
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamps();
    $table->index(['status', 'scheduled_at']);
    $table->index('send_mode');
    $table->index('type');
});
```

**File:** `database/migrations/2026_04_27_000001_create_sms_blast_recipients_table.php`

```php
Schema::create('sms_blast_recipients', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sms_blast_id')->constrained('sms_blasts')->onDelete('cascade');
    $table->string('recipient_type')->default('parent');
    $table->string('recipient_id');
    $table->string('recipient_name');
    $table->string('mobile_number');
    $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
    $table->text('error_message')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamps();
    $table->index(['sms_blast_id', 'status']);
    $table->index('mobile_number');
});
```

---

## Phase 3: Service Layer

**File:** `app/Services/SmsBlastService.php`

```php
<?php
namespace App\Services;

use App\Models\SmsBlast;
use App\Models\SmsBlastRecipient;
use App\Models\M06;
use App\Models\M06Child;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;

class SmsBlastService
{
    public function sendBlast(SmsBlast $blast, $recipientIds = [])
    {
        try {
            if ($blast->status !== SmsBlast::STATUS_SENDING) {
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
                RateLimiter::attempt(
                    key: "sms-blast:{$blast->id}",
                    maxAttempts: 50,
                    callback: function () use ($blast, $recipient, &$sent, &$failed) {
                        $result = $this->sendToRecipient($blast, $recipient);
                        if ($result['success']) {
                            $sent++;
                        } else {
                            $failed++;
                        }
                    },
                    decaySeconds: 0.5
                );
            }

            $blast->update([
                'sent_count' => $sent,
                'failed_count' => $failed,
                'status' => $failed > 0 ? SmsBlast::STATUS_FAILED : SmsBlast::STATUS_SENT,
                'sent_at' => Carbon::now(),
            ]);

            return ['success' => true, 'sent' => $sent, 'failed' => $failed, 'blast' => $blast];
        } catch (\Exception $e) {
            $blast->update(['status' => SmsBlast::STATUS_FAILED]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function prepareMessage($message, $recipient)
    {
        $child = M06Child::where('d_code', $recipient->d_code)->first();

        $replacements = [
            '{child_name}' => $child ? $child->firstname : '',
            '{parent_name}' => $recipient->d_name ?? '',
            '{time_remaining}' => '10',
            '{minutes_over}' => '0',
            '{checkout_time}' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    public function getDefaultTemplates()
    {
        return [
            ['name' => 'Birthday Greetings', 'slug' => 'birthday-greetings',
             'message' => "Happy Birthday {child_name}! From all of us at Mimo Play Cafe..."],
            ['name' => 'Time is Almost Up', 'slug' => 'timeout-reminder',
             'message' => "FRIENDLY REMINDER FROM MIMO PLAY CAFE\n{parent_name}, your child {child_name}'s session will end in {time_remaining} minutes..."],
            ['name' => 'Overtime', 'slug' => 'overtime-reminder',
             'message' => "NOTICE: {child_name} has exceeded playtime by {minutes_over} minutes..."],
            ['name' => 'Check Out', 'slug' => 'checkout-reminder',
             'message' => "Thank you for visiting Mimo Play Cafe, {parent_name}!..."],
        ];
    }
}
```

---

## Phase 4: Console Commands

**File:** `app/Console/Commands/NotifyBirthdays.php`

```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmsBlast;
use App\Models\M06Child;
use Carbon\Carbon;
use App\Services\SmsBlastService;

class NotifyBirthdays extends Command
{
    protected $signature = 'sms:birthday-greetings';
    protected $description = 'Send automatically birthdays notifications';

    public function handle(SmsBlastService $smsBlastService)
    {
        $blast = SmsBlast::getAutomatedBlast(SmsBlast::SLUG_BIRTHDAY);
        if (!$blast) {
            $this->error('Birthday greeting blast not found.');
            return Command::FAILURE;
        }

        $items = M06Child::with('parent')
            ->whereMonth('birthday', Carbon::now()->month)
            ->whereDay('birthday', Carbon::now()->day)
            ->get();

        if ($items->isEmpty()) {
            return Command::SUCCESS;
        }

        $recipientIds = [];
        foreach ($items as $item) {
            if ($item->parent && $item->parent->mobileno) {
                $recipientIds[] = $item->parent->d_code;
            }
        }

        $recipientIds = array_unique($recipientIds);
        if (empty($recipientIds)) {
            return Command::SUCCESS;
        }

        $result = $smsBlastService->sendBlast($blast, $recipientIds);
        $this->info("Birthday greetings processed. Sent: {$result['sent']}, Failed: {$result['failed']}");
        return Command::SUCCESS;
    }
}
```

---

## Phase 5: HTTP Scheduler Layer

**File:** `routes/http-reqs.php`

```php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RunCommandsViaHttp;

Route::prefix('/run-scheduler')->group(function () {
    Route::get('/reccured', [RunCommandsViaHttp::class, 'recurring']);
    Route::get('/scheduled', [RunCommandsViaHttp::class, 'scheduled']);
    Route::get('/time-based', [RunCommandsViaHttp::class, 'timeBased']);
});
```

**File:** `app/Http/Controllers/RunCommandsViaHttp.php`

```php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunCommandsViaHttp extends Controller
{
    private function commandCall($command, &$logs)
    {
        try {
            Artisan::call($command);
            $logs[] = "✔ {$command} executed";
            $logs[] = trim(Artisan::output()) ?: '(no output)';
        } catch (\Throwable $e) {
            $logs[] = "✖ {$command} failed: " . $e->getMessage();
        }
    }

    private function initiateScheduler($request, callable $callback)
    {
        if ($request->query('key') !== env('SCHEDULER_KEY')) {
            abort(403, 'Unauthorized');
        }

        $logs = [];
        $start = now();
        $logs[] = "Scheduler started at: " . $start;

        try {
            $callback($logs);
        } catch (\Exception $e) {
            $logs[] = "Scheduler Fatal Error: " . $e->getMessage();
        }

        $end = now();
        $logs[] = "Finished at: " . $end;
        Log::info('Scheduler run', $logs);

        return response("<pre>" . implode("\n", $logs) . "</pre>");
    }

    public function recurring(Request $request)
    {
        return $this->initiateScheduler($request, function (&$logs) {
            $this->commandCall('otp:clean-expired', $logs);
            $this->commandCall('sms:timeout-reminder', $logs);
            $this->commandCall('sms:checkout-reminder', $logs);
            $this->commandCall('sms:overtime-reminder', $logs);
        });
    }

    public function scheduled(Request $request)
    {
        return $this->initiateScheduler($request, function (&$logs) {
            $this->commandCall('sms:process-scheduled-blasts', $logs);
        });
    }

    public function timeBased(Request $request)
    {
        return $this->initiateScheduler($request, function (&$logs) {
            $this->commandCall('sms:birthday-greetings', $logs);
        });
    }
}
```

---

## Phase 6: Controller Layer

**File:** `app/Http/Controllers/SmsBlastController.php`

Key methods:
- `index()` - Paginate and display blasts with stats
- `create()` - Show create form with templates
- `store()` - Validate and save blast (draft/scheduled/now)
- `show()` - Display blast details with recipients
- `edit()` - Show edit form
- `update()` - Update existing blast
- `resendFailed()` - Resend failed recipients
- `destroy()` - Delete draft blast

**Routes:** `routes/admin-panel.php`

```php
Route::prefix('/sms-blasts')->name('sms_blast.')->group(function () {
    Route::get('/', [SmsBlastController::class, 'index'])->name('index');
    Route::get('/create', [SmsBlastController::class, 'create'])->name('create');
    Route::post('/', [SmsBlastController::class, 'store'])->name('store');
    Route::get('/{smsBlast}', [SmsBlastController::class, 'show'])->name('show');
    Route::get('/edit/{smsBlast}', [SmsBlastController::class, 'edit'])->name('edit');
    Route::put('/{smsBlast}', [SmsBlastController::class, 'update'])->name('update');
    Route::post('/{smsBlast}/resend', [SmsBlastController::class, 'resendFailed'])->name('resend-failed');
    Route::delete('/{smsBlast}', [SmsBlastController::class, 'destroy'])->name('destroy');
});
```

---

## Phase 7: API Integration

**File:** `app/Services/SendSmsService.php`

```php
<?php
namespace App\Services;

class SendSmsService
{
    public static function sendnowsms($to, $msg)
    {
        $mobile = static::formatPHNumber($to);
        $destination = $mobile;
        $message = html_entity_decode($msg, ENT_QUOTES, 'utf-8');
        $message = urlencode($message);

        $username = urlencode(config('services.isms.user'));
        $password = urlencode(config('services.isms.password'));
        $sender_id = urlencode(config('services.isms.sender_id'));
        $type = config('services.isms.type');

        $url = config('services.isms.api') . "?un={$username}&pwd={$password}&dstno={$destination}&msg={$message}&type={$type}&sendid={$sender_id}&agreedterm=YES";
        
        $response = static::ismscURL($url);

        return $response['status'] == 200
            ? ['success' => true, 'response' => $response['result']]
            : ['success' => false, 'status' => $response['status'], 'response' => $response['result']];
    }

    private static function formatPHNumber($number)
    {
        $number = preg_replace('/[^0-9]/', '', $number);
        if (substr($number, 0, 2) === '63') return $number;
        if (substr($number, 0, 1) === '0') return '63' . substr($number, 1);
        if (strlen($number) === 10 && substr($number, 0, 1) === '9') return '63' . $number;
        return $number;
    }

    private static function ismscURL($link)
    {
        $http = curl_init($link);
        curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);
        $http_result = curl_exec($http);
        $http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);
        curl_close($http);
        return ['status' => $http_status, 'result' => $http_result];
    }
}
```

---

## Phase 8: Kernel Configuration

**File:** `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule): void
{
    // Note: Automations are triggered via HTTP endpoints in RunCommandsViaHttp
    // External cron jobs call: /run-scheduler/reccured, /run-scheduler/scheduled, /run-scheduler/time-based
}
```

---

## Deployment Checklist

- [x] Models created and migrated
- [x] Service layer implemented
- [x] Console commands registered
- [x] HTTP scheduler endpoints created
- [x] Controller and routes configured
- [ ] `.env` configured with `SCHEDULER_KEY` and iSMS credentials
- [ ] Cron jobs configured on server
- [ ] SMS templates reviewed and tested