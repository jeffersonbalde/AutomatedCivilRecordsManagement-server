<?php
// app/Models/Staff.php - UPDATED with birth records relationship
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'email',
        'password',
        'full_name',
        'contact_number',
        'address',
        'avatar',
        'is_active',
        'last_login_at',
        'created_by',
        'deactivate_reason',
        'deactivated_at',
        'deactivated_by'
    ];

    protected $appends = [
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationship with admin who created this staff
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    // Relationship with admin who deactivated this staff
    public function deactivator()
    {
        return $this->belongsTo(Admin::class, 'deactivated_by');
    }

    // Relationship with birth records encoded by this staff
    public function encodedBirthRecords()
    {
        return $this->hasMany(BirthRecord::class, 'encoded_by');
    }

    // Scope for active staff
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar) {
            return null;
        }

        return Storage::url($this->avatar);
    }

    // Accessor for display name
    public function getDisplayNameAttribute()
    {
        return $this->full_name ?: $this->email;
    }

}