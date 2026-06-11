<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketApproval extends Model
{
    protected $fillable = [
        'ticket_id', 'approval_level_id', 'level_order',
        'approver_id', 'status', 'notes', 'responded_by', 'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function approvalLevel(): BelongsTo
    {
        return $this->belongsTo(ApprovalLevel::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
