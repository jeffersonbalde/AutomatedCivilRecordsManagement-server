<?php
// app/Models/ParentsInformation.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentsInformation extends Model
{
    use HasFactory;

    protected $fillable = [
        'birth_record_id',
        'parent_type',
        'first_name',
        'middle_name',
        'last_name',
        'citizenship',
        'religion',
        'occupation',
        'age_at_birth',
        'children_born_alive',
        'children_still_living',
        'children_deceased',
        'house_no',
        'barangay',
        'city',
        'province',
        'country'
    ];

    protected $casts = [
        'age_at_birth' => 'integer',
        'children_born_alive' => 'integer',
        'children_still_living' => 'integer',
        'children_deceased' => 'integer',
    ];

    // Relationship with birth record
    public function birthRecord()
    {
        return $this->belongsTo(BirthRecord::class, 'birth_record_id');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getFullAddressAttribute()
    {
        $addressParts = array_filter([
            $this->house_no,
            $this->barangay,
            $this->city,
            $this->province,
            $this->country
        ]);
        
        return implode(', ', $addressParts);
    }
}