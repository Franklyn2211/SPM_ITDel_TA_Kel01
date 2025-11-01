<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

class StatusEvaluasi extends Model
{
    protected $table = 'ref_status_evaluasi';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','name','created_by','updated_by','active'];
    protected $casts = ['active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (!Auth::check()) return;
            $roleId = optional(Auth::user()->userRole)->id
                ?? UserRole::where('cis_user_id', Auth::id())->where('active',1)->value('id')
                ?? UserRole::where('cis_user_id', Auth::id())->latest('created_at')->value('id');
            $m->created_by ??= $roleId;
            $m->updated_by ??= $roleId;
        });

        static::updating(function ($m) {
            if (!Auth::check()) return;
            $roleId = optional(Auth::user()->userRole)->id
                ?? UserRole::where('cis_user_id', Auth::id())->where('active',1)->value('id')
                ?? UserRole::where('cis_user_id', Auth::id())->latest('created_at')->value('id');
            $m->updated_by = $roleId ?: null;
        });
    }

    public function createdBy()         { return $this->belongsTo(UserRole::class, 'created_by'); }
    public function updatedBy()         { return $this->belongsTo(UserRole::class, 'updated_by'); }
    public function EvaluasiDiri()      { return $this->hasMany(EvaluasiDiri::class, 'status_id', 'id'); }
    public function EvaluasiDiriDetails(){ return $this->hasMany(EvaluasiDiriDetail::class, 'status_id', 'id'); }

    public static function generateNextId(): string
    {
        $maxNum = (int) self::where('id', 'like', 'SE%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        $next = 'SE' . str_pad((string)($maxNum + 1), 3, '0', STR_PAD_LEFT);
        return self::where('id',$next)->exists() ? self::generateNextId() : $next;
    }
}
