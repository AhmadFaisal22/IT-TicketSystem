<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'google_id', 'microsoft_id',
        'avatar', 'role', 'department_id', 'locale', 'active',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id', 'microsoft_id'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isItStaff(): bool
    {
        return in_array($this->role, ['admin', 'it_staff']);
    }

    public function sendPasswordResetNotification($token): void
    {
        $frontend = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $url = $frontend . '/reset-password?token=' . $token . '&email=' . urlencode($this->email);
        $this->notify(new ResetPasswordNotification($url));
    }
}
