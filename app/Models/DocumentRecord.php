<?php
// app/Models/DocumentRecord.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class DocumentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_type',
        'original_filename',
        'stored_filename',
        'file_path',
        'file_url',
        'extracted_text',
        'file_size',
        'mime_type',
        'uploaded_by',
        'uploader_type',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer'
    ];

    // Get uploader name without using polymorphic relationships
    public function getUploaderNameAttribute()
    {
        try {
            if (!$this->uploader_type || !$this->uploaded_by) {
                return 'System';
            }

            // Handle Admin
            if ($this->uploader_type === 'App\\Models\\Admin') {
                $admin = \App\Models\Admin::find($this->uploaded_by);
                return $admin ? ($admin->full_name ?? $admin->username ?? 'Admin') : 'Admin';
            }

            // Handle Staff
            if ($this->uploader_type === 'App\\Models\\Staff') {
                $staff = \App\Models\Staff::find($this->uploaded_by);
                return $staff ? ($staff->full_name ?? $staff->email ?? 'Staff') : 'Staff';
            }

            return 'Unknown';
        } catch (\Exception $e) {
            Log::error('Error getting uploader name: ' . $e->getMessage());
            return 'System';
        }
    }
}