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
    public function indicators() { return $this->hasMany(AmiStandardIndicator::class, 'standard_id', 'id'); }

    public static function generateNextId(): string
    {
        $max = (int) static::where('id','like','AS%')
            ->selectRaw("MAX(CAST(SUBSTRING(id,3) AS UNSIGNED)) as m")
            ->value('m');
        $next = 'AS'.str_pad((string)($max+1),3,'0',STR_PAD_LEFT);
        return static::where('id',$next)->exists() ? static::generateNextId() : $next;
    }
}
