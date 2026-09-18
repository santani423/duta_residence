<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationQueue extends Model
{
    use HasFactory;

    /** `recipient` value marking a row as a broadcast to every staff member (user_id stays null). */
    public const STAFF_RECIPIENT = 'staff';

    protected $fillable = [
        'unit_id', 'user_id', 'sender_id', 'type', 'title', 'channel', 'recipient', 'message',
        'reference_type', 'reference_id', 'data',
        'read_status', 'read_at', 'status', 'attempts', 'sent_at', 'failed_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Rows a staff member/collector may see: addressed to them personally, or a
     * staff-wide broadcast. Resident-addressed rows (unit_id set, recipient = phone/email)
     * are deliberately excluded - they were never meant for staff inboxes.
     */
    public function scopeForStaff(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhere(fn (Builder $broadcast) => $broadcast->whereNull('user_id')->where('recipient', self::STAFF_RECIPIENT)));
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('read_status', 'unread');
    }

    public function markAsRead(): void
    {
        if ($this->read_status !== 'read') {
            $this->update(['read_status' => 'read', 'read_at' => now()]);
        }
    }

    public function markAsUnread(): void
    {
        if ($this->read_status !== 'unread') {
            $this->update(['read_status' => 'unread', 'read_at' => null]);
        }
    }
}
