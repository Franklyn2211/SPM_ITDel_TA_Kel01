<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

class AcademicConfig extends Model
{
    protected $table = 'academic_configs';
    protected $primaryKey = 'id';
    public $incrementing = false; // Karena primary key adalah string
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'academic_code',
        'name',
        'created_by',
        'updated_by',
        'active',
    ];
    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                // isi otomatis saat create
                $model->created_by ??= Auth::id();
                $model->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                // isi otomatis saat update
                $model->updated_by = Auth::id();
            }
        });
    }

    // Relasi opsional (biar enak dipakai)
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
    public function userRole() { return $this->hasMany(UserRole::class, 'academic_config_id', 'id'); }

    public static function generateNextId()
    {
        // Mendapatkan ID terakhir dari database
        $latestId = self::orderBy('id', 'desc')->first();

        // Mengambil nomor dari ID terakhir
        $lastNumber = $latestId ? intval(substr($latestId->id, 2)) : 0;

        // Menambahkan 1 untuk mendapatkan nomor berikutnya
        $nextNumber = $lastNumber + 1;

        // Mengonversi nomor berikutnya ke format yang diinginkan (ACXXX)
        $nextId = 'AC' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        return $nextId;
    }
}
