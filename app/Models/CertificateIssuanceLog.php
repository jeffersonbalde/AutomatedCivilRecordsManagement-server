<?php
// app/Models/CertificateIssuanceLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateIssuanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_type',
        'record_id',
        'certificate_number',
        'issued_to',
        'amount_paid',
        'or_number',
        'date_paid',
        'purpose',
        'issued_by',
        'issued_by_type',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'date_paid' => 'date',
    ];

    /**
     * Get the user who issued the certificate
     */
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Scope for filtering by certificate type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('certificate_type', $type);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('date_paid', [$from, $to]);
    }

    /**
     * Scope for searching
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('issued_to', 'like', "%{$searchTerm}%")
                    ->orWhere('certificate_number', 'like', "%{$searchTerm}%")
                    ->orWhere('or_number', 'like', "%{$searchTerm}%");
    }
}