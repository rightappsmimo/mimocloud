<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsBlast extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'status',
        'slug',
        'total_recipients',
        'sent_count',
        'failed_count',
        'type',
        'send_mode',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
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

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
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
