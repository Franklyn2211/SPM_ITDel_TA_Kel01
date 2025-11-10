<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserRole;

class SelfEvaluationForm extends Model
{
    protected $table = 'self_evaluation_forms';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'category_detail_id',
        'academic_config_id',
        'head_auditee_name',
        'head_auditee_position',
        'member_auditee_1_name',
        'member_auditee_1_position',
        'member_auditee_2_name',
        'member_auditee_2_position',
        'member_auditee_3_name',
        'member_auditee_3_position',
        'status_id',
        'submitted_at',
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
    public function createdBy()       { return $this->belongsTo(UserRole::class, 'created_by'); }
    public function updatedBy()       { return $this->belongsTo(UserRole::class, 'updated_by'); }
    public function categoryDetail()  { return $this->belongsTo(RefCategoryDetail::class, 'category_detail_id', 'id'); }
    public function academicConfig()  { return $this->belongsTo(AcademicConfig::class, 'academic_config_id', 'id'); }
    public function status()          { return $this->belongsTo(EvaluationStatus::class, 'status_id', 'id'); }
    public function details()         { return $this->hasMany(SelfEvaluationDetail::class, 'self_evaluation_form_id', 'id'); }

    public static function generateNextId(): string
    {
        $maxNum = (int) self::where('id', 'like', 'SEF%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        $nextId = 'SEF' . str_pad((string)($maxNum + 1), 3, '0', STR_PAD_LEFT);
        return self::where('id', $nextId)->exists() ? self::generateNextId() : $nextId;
    }
}
