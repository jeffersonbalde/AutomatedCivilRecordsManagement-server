<?php
// app/Models/DeathRecord.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeathRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'registry_number',
        'first_name',
        'middle_name',
        'last_name',
        'sex',
        'civil_status',
        'date_of_death',
        'date_of_birth',
        'age_years',
        'age_months',
        'age_days',
        'age_hours',
        'age_minutes',
        'age_under_1',
        'place_of_death',
        'religion',
        'citizenship',
        'residence',
        'occupation',
        'father_name',
        'mother_maiden_name',
        'immediate_cause',
        'antecedent_cause',
        'underlying_cause',
        'other_significant_conditions',
        'maternal_condition',
        'manner_of_death',
        'place_of_occurrence',
        'autopsy',
        'attendant',
        'attendant_other',
        'attended_from',
        'attended_to',
        'certifier_signature',
        'certifier_name',
        'certifier_title',
        'certifier_address',
        'certifier_date',
        'attended_deceased',
        'death_occurred_time',
        'corpse_disposal',
        'burial_permit_number',
        'burial_permit_date',
        'transfer_permit_number',
        'transfer_permit_date',
        'cemetery_name',
        'cemetery_address',
        'informant_signature',
        'informant_name',
        'informant_relationship',
        'informant_address',
        'informant_date',
        'date_registered',
        'encoded_by',
        'is_active'
    ];

    protected $casts = [
        'date_of_death' => 'date',
        'date_of_birth' => 'date',
        'date_registered' => 'date',
        'attended_from' => 'date',
        'attended_to' => 'date',
        'certifier_date' => 'date',
        'informant_date' => 'date',
        'burial_permit_date' => 'date',
        'transfer_permit_date' => 'date',
        'age_under_1' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function encodedByStaff()
    {
        return $this->belongsTo(Staff::class, 'encoded_by');
    }

    public function encodedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'encoded_by');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getEncoderNameAttribute(): string
    {
        if ($this->relationLoaded('encodedByStaff') && $this->encodedByStaff) {
            return $this->encodedByStaff->full_name ?? 'Unknown Staff';
        }

        if ($this->relationLoaded('encodedByAdmin') && $this->encodedByAdmin) {
            return $this->encodedByAdmin->full_name ?? 'Unknown Admin';
        }

        if ($this->encodedByStaff) {
            return $this->encodedByStaff->full_name ?? 'Unknown Staff';
        }

        if ($this->encodedByAdmin) {
            return $this->encodedByAdmin->full_name ?? 'Unknown Admin';
        }

        return 'System';
    }

    public function getEncoderTypeAttribute(): string
    {
        if ($this->encodedByStaff) {
            return 'Staff';
        }

        if ($this->encodedByAdmin) {
            return 'Admin';
        }

        return 'System';
    }

    public function getAgeAtDeathAttribute(): string
    {
        if ($this->age_under_1) {
            $parts = [];
            if ($this->age_months) $parts[] = "{$this->age_months} months";
            if ($this->age_days) $parts[] = "{$this->age_days} days";
            if ($this->age_hours) $parts[] = "{$this->age_hours} hours";
            if ($this->age_minutes) $parts[] = "{$this->age_minutes} minutes";
            return implode(', ', $parts) ?: 'Under 1 year';
        }

        return $this->age_years ? "{$this->age_years} years" : 'Not specified';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('first_name', 'like', "%{$searchTerm}%")
              ->orWhere('middle_name', 'like', "%{$searchTerm}%")
              ->orWhere('last_name', 'like', "%{$searchTerm}%")
              ->orWhere('registry_number', 'like', "%{$searchTerm}%")
              ->orWhere('place_of_death', 'like', "%{$searchTerm}%")
              ->orWhere('father_name', 'like', "%{$searchTerm}%")
              ->orWhere('mother_maiden_name', 'like', "%{$searchTerm}%");
        });
    }
}