<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RefCategory extends Model
{
    protected $table = 'ref_categories';
    protected $primaryKey = 'id';
    public $incrementing = false;     // PK string
    protected $keyType = 'string';
    protected $fillable = ['id', 'name','active','created_by','updated_by'];
    protected $casts = ['active' => 'boolean'];

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
    public function details()   { return $this->hasMany(RefCategoryDetail::class, 'category_id', 'id'); }

    public static function generateNextId(): string
    {
        $max = (int) static::where('id','like','C%')
            ->selectRaw("MAX(CAST(SUBSTRING(id,3) AS UNSIGNED)) as m")
            ->value('m');
        $next = 'C'.str_pad((string)($max+1),3,'0',STR_PAD_LEFT);
        return static::where('id',$next)->exists() ? static::generateNextId() : $next;
    }

}
