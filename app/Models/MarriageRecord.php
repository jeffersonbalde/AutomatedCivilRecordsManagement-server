<?php
// app/Models/MarriageRecord.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarriageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'registry_number',
        'province',
        'city_municipality',
        'date_of_marriage',
        'time_of_marriage',
        'place_of_marriage',
        'marriage_type',
        'license_number',
        'license_date',
        'license_place',
        'property_regime',

        // Husband Information
        'husband_first_name',
        'husband_middle_name',
        'husband_last_name',
        'husband_birthdate',
        'husband_birthplace',
        'husband_sex',
        'husband_citizenship',
        'husband_religion',
        'husband_civil_status',
        'husband_occupation',
        'husband_address',

        // Husband Parents
        'husband_father_name',
        'husband_father_citizenship',
        'husband_mother_name',
        'husband_mother_citizenship',

        // Husband Consent
        'husband_consent_giver',
        'husband_consent_relationship',
        'husband_consent_address',

        // Wife Information
        'wife_first_name',
        'wife_middle_name',
        'wife_last_name',
        'wife_birthdate',
        'wife_birthplace',
        'wife_sex',
        'wife_citizenship',
        'wife_religion',
        'wife_civil_status',
        'wife_occupation',
        'wife_address',

        // Wife Parents
        'wife_father_name',
        'wife_father_citizenship',
        'wife_mother_name',
        'wife_mother_citizenship',

        // Wife Consent
        'wife_consent_giver',
        'wife_consent_relationship',
        'wife_consent_address',

        // Ceremony Details
        'officiating_officer',
        'officiant_title',
        'officiant_license',

        // Legal Basis
        'legal_basis',
        'legal_basis_article',

        // Witnesses
        'witness1_name',
        'witness1_address',
        'witness1_relationship',
        'witness2_name',
        'witness2_address',
        'witness2_relationship',

        // Additional
        'marriage_remarks',

        // System
        'date_registered',
        'encoded_by',
        'is_active'
    ];

    protected $casts = [
        'date_of_marriage' => 'date',
        'license_date' => 'date',
        'husband_birthdate' => 'date',
        'wife_birthdate' => 'date',
        'date_registered' => 'date',
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

    public function getEncoderNameAttribute(): string
    {
        // Check if relationships are loaded and return the appropriate name
        if ($this->relationLoaded('encodedByAdmin') && $this->encodedByAdmin) {
            return $this->encodedByAdmin->full_name ?? 'Unknown Admin';
        }

        if ($this->relationLoaded('encodedByStaff') && $this->encodedByStaff) {
            return $this->encodedByStaff->full_name ?? 'Unknown Staff';
        }

        // If relationships aren't loaded, check the actual relationships
        if ($this->encodedByAdmin()->exists()) {
            return $this->encodedByAdmin->full_name ?? 'Unknown Admin';
        }

        if ($this->encodedByStaff()->exists()) {
            return $this->encodedByStaff->full_name ?? 'Unknown Staff';
        }

        return 'System';
    }

    public function getEncoderTypeAttribute(): string
    {
        // Check if relationships are loaded
        if ($this->relationLoaded('encodedByAdmin') && $this->encodedByAdmin) {
            return 'Admin';
        }

        if ($this->relationLoaded('encodedByStaff') && $this->encodedByStaff) {
            return 'Staff';
        }

        // If relationships aren't loaded, check the actual relationships
        if ($this->encodedByAdmin()->exists()) {
            return 'Admin';
        }

        if ($this->encodedByStaff()->exists()) {
            return 'Staff';
        }

        return 'System';
    }

    public function getHusbandFullNameAttribute(): string
    {
        return trim("{$this->husband_first_name} {$this->husband_middle_name} {$this->husband_last_name}");
    }

    public function getWifeFullNameAttribute(): string
    {
        return trim("{$this->wife_first_name} {$this->wife_middle_name} {$this->wife_last_name}");
    }

    public function getCoupleNamesAttribute(): string
    {
        return $this->husband_full_name . ' & ' . $this->wife_full_name;
    }

    public function getFormattedDateOfMarriageAttribute(): string
    {
        return $this->date_of_marriage->format('F j, Y');
    }

    public function getFormattedTimeOfMarriageAttribute(): string
    {
        return $this->time_of_marriage ? date('g:i A', strtotime($this->time_of_marriage)) : 'Not specified';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('husband_first_name', 'like', "%{$searchTerm}%")
                ->orWhere('husband_middle_name', 'like', "%{$searchTerm}%")
                ->orWhere('husband_last_name', 'like', "%{$searchTerm}%")
                ->orWhere('wife_first_name', 'like', "%{$searchTerm}%")
                ->orWhere('wife_middle_name', 'like', "%{$searchTerm}%")
                ->orWhere('wife_last_name', 'like', "%{$searchTerm}%")
                ->orWhere('registry_number', 'like', "%{$searchTerm}%")
                ->orWhere('place_of_marriage', 'like', "%{$searchTerm}%");
        });
    }

    public function scopeDateRange($query, $dateFrom, $dateTo)
    {
        if ($dateFrom) {
            $query->where('date_of_marriage', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date_of_marriage', '<=', $dateTo);
        }
        return $query;
    }
}
