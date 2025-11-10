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
        static::saving(function (AcademicConfig $model) {
        // Kalau entri ini mau diaktifkan, matikan yang lain
        if ($model->active) {
            static::where('id', '!=', $model->id)
                ->where('active', true)
                ->update(['active' => false]);
        }
    });
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

    public static function generateNextId(): string
    {
        $max = (int) static::where('id','like','AC%')
            ->selectRaw("MAX(CAST(SUBSTRING(id,3) AS UNSIGNED)) as m")
            ->value('m');
        $next = 'AC'.str_pad((string)($max+1),3,'0',STR_PAD_LEFT);
        return static::where('id',$next)->exists() ? static::generateNextId() : $next;
    }
}
