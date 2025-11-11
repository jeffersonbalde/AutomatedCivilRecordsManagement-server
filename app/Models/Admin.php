<?php
// app/Models/Admin.php - UPDATED with relationships
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admins';

    protected $guard = 'admin';

    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'admin_id',
        'position'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed'
    ];

    /**
     * Relationship: An admin can create many staff members
     */
    public function createdStaff()
    {
        return $this->hasMany(Staff::class, 'created_by');
    }

    /**
     * Relationship: An admin can deactivate staff members
     */
    public function deactivatedStaff()
    {
        return $this->hasMany(Staff::class, 'deactivated_by');
    }

    /**
     * Get the table name for this model
     */
    public function getTable()
    {
        return $this->table ?? 'admins';
    }

    // Accessor for display name
    public function getDisplayNameAttribute()
    {
        return $this->full_name ?: $this->username;
    }

        /**
     * Relationship: Admin can encode many birth records
     */
    public function encodedBirthRecords()
    {
        return $this->hasMany(BirthRecord::class, 'encoded_by');
    }
}