<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserRole;
use App\Models\SelfEvaluationDetail;

class StandardAchievement extends Model
{
    protected $table = 'ref_standard_achievements';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'created_by',
        'updated_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();

            $roleId = optional($user->userRole)->id
                ?? UserRole::where('cis_user_id', $user->id)->where('active', 1)->value('id')
                ?? UserRole::where('cis_user_id', $user->id)->latest('created_at')->value('id');

            $model->created_by ??= $roleId;
            $model->updated_by ??= $roleId;
        });

        static::updating(function ($model) {
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();

            $roleId = optional($user->userRole)->id
                ?? UserRole::where('cis_user_id', $user->id)->where('active', 1)->value('id')
                ?? UserRole::where('cis_user_id', $user->id)->latest('created_at')->value('id');

            $model->updated_by = $roleId ?: null;
        });
    }

    // Relationships
    public function createdBy()
    {
        return $this->belongsTo(UserRole::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(UserRole::class, 'updated_by');
    }

    public function selfEvaluationDetails()
    {
        return $this->hasMany(SelfEvaluationDetail::class, 'standard_achievement_id', 'id');
    }

    public static function generateNextId(): string
    {
        $maxNum = (int) self::where('id', 'like', 'SA%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        $next = 'SA' . str_pad((string) ($maxNum + 1), 3, '0', STR_PAD_LEFT);

        return self::where('id', $next)->exists()
            ? self::generateNextId()
            : $next;
    }
}
