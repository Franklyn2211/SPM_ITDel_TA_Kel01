<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserRole extends Model
{
    protected $table = 'user_roles';
    protected $primaryKey = 'id';
    public $incrementing = false;   // string PK
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cis_user_id',
        'role_id',
        'academic_config_id',
        'category_detail_id',
        'created_by',
        'updated_by',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (Auth::check()) {
                $m->created_by ??= Auth::id();
                $m->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($m) {
            if (Auth::check()) {
                $m->updated_by = Auth::id();
            }
        });
    }

    // === Relations ===
    public function user()
    {
        // relasi dengan users via cis_user_id (bukan PK)
        return $this->belongsTo(User::class, 'cis_user_id', 'cis_user_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function academicConfig()
    {
        return $this->belongsTo(AcademicConfig::class, 'academic_config_id', 'id');
    }
    public function categoryDetail()
    {
        return $this->belongsTo(RefCategoryDetail::class, 'category_detail_id', 'id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
    public static function generateNextId(): string
    {
        $max = (int) static::where('id','like','UR%')
            ->selectRaw("MAX(CAST(SUBSTRING(id,3) AS UNSIGNED)) as m")
            ->value('m');
        $next = 'UR'.str_pad((string)($max+1),3,'0',STR_PAD_LEFT);
        return static::where('id',$next)->exists() ? static::generateNextId() : $next;
    }
}
