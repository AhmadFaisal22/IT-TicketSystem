<?php

namespace App\Models;

use App\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TicketApproval;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasOptimisticLock;

    protected $fillable = [
        'ticket_number', 'title', 'description', 'status', 'priority',
        'category', 'subcategory', 'department_id', 'asset_id', 'created_by', 'assigned_to',
        'sla_response_due_at', 'sla_resolution_due_at',
        'first_response_at', 'resolved_at', 'closed_at',
        'sla_response_breached', 'sla_resolution_breached',
    ];

    protected $casts = [
        'sla_response_due_at' => 'datetime',
        'sla_resolution_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_response_breached' => 'boolean',
        'sla_resolution_breached' => 'boolean',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        // A temporary unique value satisfies the NOT NULL + unique column at
        // insert time; the real number is derived from the auto-increment id
        // once it exists. Deriving from the id is concurrency-safe and never
        // reuses a number after deletions (unlike the old max(id)+1 scheme).
        static::creating(function (Ticket $ticket) {
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = 'TKT-tmp-' . Str::uuid();
            }
        });

        static::created(function (Ticket $ticket) {
            if (str_starts_with((string) $ticket->ticket_number, 'TKT-tmp-')) {
                $ticket->ticket_number = 'TKT-' . str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT);
                $ticket->saveQuietly();
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TicketApproval::class)->orderBy('level_order');
    }

    public function setSlaDeadlines(): void
    {
        $policy = SlaPolicy::findForTicket($this->department_id, $this->priority);
        $this->sla_response_due_at = now()->addHours($policy->response_hours);
        $this->sla_resolution_due_at = now()->addHours($policy->resolution_hours);
    }
}
