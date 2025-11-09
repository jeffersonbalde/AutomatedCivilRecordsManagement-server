<?php
// app/Models/BirthRecord.php - UPDATED
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    public function mother(): HasOne
    {
        return $this->hasOne(ParentsInformation::class, 'birth_record_id')
                    ->where('parent_type', 'Mother');
    }

    public function father(): HasOne
    {
        return $this->hasOne(ParentsInformation::class, 'birth_record_id')
                    ->where('parent_type', 'Father');
    }

    public function parents(): HasMany
    {
        return $this->hasMany(ParentsInformation::class, 'birth_record_id');
    }

    public function parentsMarriage(): HasOne
    {
        return $this->hasOne(ParentsMarriage::class, 'birth_record_id');
    }

    public function attendant(): HasOne
    {
        return $this->hasOne(BirthAttendant::class, 'birth_record_id');
    }

    public function informant(): HasOne
    {
        return $this->hasOne(Informant::class, 'birth_record_id');
    }

    public function encodedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'encoded_by');
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