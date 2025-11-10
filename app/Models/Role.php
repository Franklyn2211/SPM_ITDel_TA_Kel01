<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    public $incrementing = false; // Karena primary key adalah string
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'category_id',
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
                $model->created_by ??= Auth::id();
                $model->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
    // Relasi dengan RefCategoryDetail
    public function category()
    {
        return $this->belongsTo(RefCategory::class, 'category_id', 'id');
    }

    // Relasi dengan UserRole
    public function userRoles()
    {
        return $this->hasMany(UserRole::class, 'role_id', 'id');
    }

    public function pic()
    {
        return $this->hasMany(AmiStandardIndicatorPic::class, 'role_id', 'id');
    }

    public static function generateNextId(): string
    {
        $max = (int) static::where('id','like','R%')
            ->selectRaw("MAX(CAST(SUBSTRING(id,3) AS UNSIGNED)) as m")
            ->value('m');
        $next = 'R'.str_pad((string)($max+1),3,'0',STR_PAD_LEFT);
        return static::where('id',$next)->exists() ? static::generateNextId() : $next;
    }
}
