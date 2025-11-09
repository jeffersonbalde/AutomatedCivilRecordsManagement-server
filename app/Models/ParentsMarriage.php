<?php
// app/Models/ParentsMarriage.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentsMarriage extends Model
{
    use HasFactory;

    // ADD THIS LINE to specify the table name
    protected $table = 'parents_marriages';

    protected $fillable = [
        'birth_record_id',
        'marriage_date',
        'marriage_place_city',
        'marriage_place_province',
        'marriage_place_country'
    ];

    protected $casts = [
        'marriage_date' => 'date',
    ];

    // Relationship with birth record
    public function birthRecord()
    {
        return $this->belongsTo(BirthRecord::class, 'birth_record_id');
    }

    // Accessors
    public function getMarriagePlaceAttribute()
    {
        $placeParts = array_filter([
            $this->marriage_place_city,
            $this->marriage_place_province,
            $this->marriage_place_country
        ]);
        
        return implode(', ', $placeParts);
    }

    public function getIsMarriedAttribute()
    {
        return !empty($this->marriage_date) || !empty($this->marriage_place_city);
    }
}