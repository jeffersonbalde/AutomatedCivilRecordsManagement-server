<?php
// app/Models/BirthAttendant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BirthAttendant extends Model
{
    use HasFactory;

    protected $fillable = [
        'birth_record_id',
        'attendant_type',
        'attendant_name',
        'attendant_license',
        'attendant_certification',
        'attendant_address',
        'attendant_title'
    ];

    // Relationship with birth record
    public function birthRecord()
    {
        return $this->belongsTo(BirthRecord::class, 'birth_record_id');
    }

    // Accessors
    public function getCertificationStatementAttribute()
    {
        if (!empty($this->attendant_certification)) {
            return $this->attendant_certification;
        }

        return "I hereby certify that I attended the birth of the child who was born alive at the time and date specified in the birth record.";
    }

    public function getDisplayTypeAttribute()
    {
        $types = [
            'Physician' => 'Physician',
            'Nurse' => 'Nurse',
            'Midwife' => 'Midwife',
            'Hilot' => 'Hilot (Traditional Birth Attendant)',
            'Other' => 'Other'
        ];

        return $types[$this->attendant_type] ?? $this->attendant_type;
    }
}