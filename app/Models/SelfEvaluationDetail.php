<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserRole;

class SelfEvaluationDetail extends Model
{
    protected $table = 'self_evaluation_details';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'self_evaluation_form_id',
        'ami_standard_indicator_id',
        'standard_achievement_id',
        'status_id',
        'result',
        'supporting_evidence',
        'contributing_factors',
        'created_by',
        'updated_by',
        'active',
    ];

    protected $casts = ['active' => 'boolean'];

    protected static function booted(): void
    {
        $resolveRoleId = function () {
            if (!Auth::check()) return null;
            $u = Auth::user();
            return optional($u->userRole)->id
                ?? ($u->user_role_id ?? null)
                ?? (isset($u->cis_user_id)
                    ? (UserRole::where('cis_user_id', $u->cis_user_id)->where('active', 1)->value('id')
                        ?? UserRole::where('cis_user_id', $u->cis_user_id)->latest('created_at')->value('id'))
                    : null)
                ?? UserRole::where('id', $u->id)->where('active', 1)->value('id')
                ?? UserRole::where('id', $u->id)->latest('created_at')->value('id');
        };

        static::creating(function ($model) use ($resolveRoleId) {
            $roleId = $resolveRoleId();
            if ($roleId) {
                $model->created_by ??= $roleId;
                $model->updated_by ??= $roleId;
            }
        });

        static::updating(function ($model) use ($resolveRoleId) {
            $roleId = $resolveRoleId();
            $model->updated_by = $roleId ?: null;
        });
    }

    // Relationships
    public function createdBy()              { return $this->belongsTo(UserRole::class, 'created_by'); }
    public function updatedBy()              { return $this->belongsTo(UserRole::class, 'updated_by'); }
    public function form()                   { return $this->belongsTo(SelfEvaluationForm::class, 'self_evaluation_form_id', 'id'); }
    public function indicator()              { return $this->belongsTo(AmiStandardIndicator::class, 'ami_standard_indicator_id', 'id'); }
    public function standardAchievement()    { return $this->belongsTo(StandardAchievement::class, 'standard_achievement_id', 'id'); }
    public function status()                 { return $this->belongsTo(EvaluationStatus::class, 'status_id', 'id'); }

    public static function generateNextId(): string
    {
        $maxNum = (int) static::where('id', 'like', 'SED%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        return 'SED' . str_pad((string)($maxNum + 1), 6, '0', STR_PAD_LEFT);
    }
}
