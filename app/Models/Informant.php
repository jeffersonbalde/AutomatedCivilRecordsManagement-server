<?php
// app/Models/Informant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Informant extends Model
{
    use HasFactory;

    protected $fillable = [
        'birth_record_id',
        'first_name',
        'middle_name',
        'last_name',
        'relationship',
        'address',
        'certification_accepted'
    ];

    protected $casts = [
        'certification_accepted' => 'boolean',
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

    public function getCertificationStatementAttribute()
    {
        return "I hereby certify that all information supplied are true and correct to my own knowledge and belief.";
    }
}