<?php
// app/Models/BirthRecord.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BirthRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'registry_number',
        'child_first_name',
        'child_middle_name',
        'child_last_name',
        'sex',
        'date_of_birth',
        'time_of_birth',
        'place_of_birth',
        'birth_address_house',
        'birth_address_barangay',
        'birth_address_city',
        'birth_address_province',
        'type_of_birth',
        'multiple_birth_order',
        'birth_order',
        'birth_weight',
        'birth_notes',
        'date_registered',
        'encoded_by',
        'is_active'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_registered' => 'date',
        'birth_weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function mother()
    {
        return $this->hasOne(ParentsInformation::class, 'birth_record_id')
                    ->where('parent_type', 'Mother');
    }

    public function father()
    {
        return $this->hasOne(ParentsInformation::class, 'birth_record_id')
                    ->where('parent_type', 'Father');
    }

    public function parents()
    {
        return $this->hasMany(ParentsInformation::class, 'birth_record_id');
    }

    public function parentsMarriage()
    {
        return $this->hasOne(ParentsMarriage::class, 'birth_record_id');
    }

    public function attendant()
    {
        return $this->hasOne(BirthAttendant::class, 'birth_record_id');
    }

    public function informant()
    {
        return $this->hasOne(Informant::class, 'birth_record_id');
    }

    /**
     * Relationship: Staff who encoded this record
     */
    public function encodedByStaff()
    {
        return $this->belongsTo(Staff::class, 'encoded_by');
    }

    /**
     * Relationship: Admin who encoded this record
     */
    public function encodedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'encoded_by');
    }

    /**
     * Accessor to get the encoder's full name regardless of type
     */
    public function getEncoderNameAttribute(): string
    {
        // First try to get from Staff relationship
        if ($this->relationLoaded('encodedByStaff') && $this->encodedByStaff) {
            return $this->encodedByStaff->full_name ?? 'Unknown Staff';
        }

        // Then try to get from Admin relationship
        if ($this->relationLoaded('encodedByAdmin') && $this->encodedByAdmin) {
            return $this->encodedByAdmin->full_name ?? 'Unknown Admin';
        }

        // If relationships are not loaded, try to lazy load
        if ($this->encodedByStaff) {
            return $this->encodedByStaff->full_name ?? 'Unknown Staff';
        }

        if ($this->encodedByAdmin) {
            return $this->encodedByAdmin->full_name ?? 'Unknown Admin';
        }

        return 'System';
    }

    /**
     * Accessor to get encoder type
     */
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

    /**
     * Accessor to get encoder email
     */
    public function getEncoderEmailAttribute(): ?string
    {
        if ($this->encodedByStaff) {
            return $this->encodedByStaff->email ?? null;
        }

        if ($this->encodedByAdmin) {
            return $this->encodedByAdmin->email ?? null;
        }

        return null;
    }

    /**
     * Accessor to get encoder position/details
     */
    public function getEncoderDetailsAttribute(): string
    {
        if ($this->encodedByAdmin) {
            return $this->encodedByAdmin->position ?? 'System Administrator';
        } elseif ($this->encodedByStaff) {
            return 'Registry Staff';
        }

        return 'System Account';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('child_first_name', 'like', "%{$searchTerm}%")
              ->orWhere('child_middle_name', 'like', "%{$searchTerm}%")
              ->orWhere('child_last_name', 'like', "%{$searchTerm}%")
              ->orWhere('registry_number', 'like', "%{$searchTerm}%")
              ->orWhere('place_of_birth', 'like', "%{$searchTerm}%");
        });
    }

    public function scopeDateRange($query, $dateFrom, $dateTo)
    {
        if ($dateFrom) {
            $query->where('date_of_birth', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date_of_birth', '<=', $dateTo);
        }
        return $query;
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->child_first_name} {$this->child_middle_name} {$this->child_last_name}");
    }

    public function getBirthAddressAttribute(): string
    {
        $addressParts = array_filter([
            $this->birth_address_house,
            $this->birth_address_barangay,
            $this->birth_address_city,
            $this->birth_address_province
        ]);
        
        return implode(', ', $addressParts);
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function getFormattedDateOfBirthAttribute(): string
    {
        return $this->date_of_birth->format('F j, Y');
    }

    public function getFormattedTimeOfBirthAttribute(): string
    {
        return $this->time_of_birth ? date('g:i A', strtotime($this->time_of_birth)) : 'Not specified';
    }

    // Business Logic Methods
    public function isMultipleBirth(): bool
    {
        return in_array($this->type_of_birth, ['Twin', 'Triplet', 'Quadruplet', 'Other']);
    }

    public function getBirthTypeDisplay(): string
    {
        $types = [
            'Single' => 'Single Birth',
            'Twin' => 'Twin',
            'Triplet' => 'Triplet',
            'Quadruplet' => 'Quadruplet',
            'Other' => 'Multiple Birth'
        ];

        return $types[$this->type_of_birth] ?? $this->type_of_birth;
    }
}