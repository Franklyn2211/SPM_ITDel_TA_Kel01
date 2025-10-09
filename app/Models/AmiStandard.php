<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

class AmiStandard extends Model
{
    protected $table = 'ami_standards';
    protected $primaryKey = 'id';
    public $incrementing = false; // Karena primary key adalah string
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'academic_config_id',
        'created_by',
        'updated_by',
        'active',
    ];
    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AmiStandard $m) {

            // Otomatis isi academic_config_id dari AC yang aktif (jika belum diisi)
            if (empty($m->academic_config_id)) {
                $activeAc = AcademicConfig::query()
                    ->where('active', true)
                    // kalau ada kolom 'academic_code', yang terbaru biasanya paling besar
                    ->orderByDesc('academic_code')
                    ->orderByDesc('created_at')
                    ->first();

                if (!$activeAc) {
                    throw new \RuntimeException('Tidak ada AcademicConfig yang aktif.');
                }
                $m->academic_config_id = $activeAc->id;
            }

            // blameable
            if (Auth::check()) {
                $m->created_by ??= Auth::id();
                $m->updated_by ??= Auth::id();
            }
        });

        static::updating(function (AmiStandard $m) {
            if (Auth::check()) {
                $m->updated_by = Auth::id();
            }
        });
    }

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
    public function academicConfig() { return $this->belongsTo(AcademicConfig::class, 'academic_config_id', 'id'); }
    public function amiStandardIndicators() { return $this->hasMany(AmiStandardIndicator::class, 'standard_id', 'id'); }

    public static function generateNextId()
    {
        // Mendapatkan ID terakhir dari database
        $latestId = self::orderBy('id', 'desc')->first();

        // Mengambil nomor dari ID terakhir
        $lastNumber = $latestId ? intval(substr($latestId->id, 2)) : 0;

        // Menambahkan 1 untuk mendapatkan nomor berikutnya
        $nextNumber = $lastNumber + 1;

        // Mengonversi nomor berikutnya ke format yang diinginkan (ACXXX)
        $nextId = 'AS' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        return $nextId;
    }
}
