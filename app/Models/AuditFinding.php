<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

class AuditFinding extends Model
{
    protected $table = 'audit_findings';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'audit_finding_form_id',
        'self_evaluation_detail_id',
        'finding_no',
        'control',
        'improvement',
        'follow_up_plan',
        'auditor_recommendation',
        'corrective_action_plan',
        'severity',
        'due_date',
        'created_by',
        'updated_by',
        'active',
    ];

    protected $casts = [
        'finding_no' => 'integer',
        'due_date' => 'date',
        'active' => 'boolean',
    ];
    
    public const SEVERITY_OPTIONS = [
        'OBS'       => 'Observasi',
        'KTS_MINOR' => 'KTS Minor',
        'KTS_MAYOR' => 'KTS Mayor',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!Auth::check()) {
                return;
            }

            $actorRoleId = static::resolveActorUserRoleId();

            if (empty($model->created_by)) {
                $model->created_by = $actorRoleId;
            }

            if (empty($model->updated_by)) {
                $model->updated_by = $actorRoleId;
            }
        });

        static::updating(function ($model) {
            if (!Auth::check()) {
                return;
            }

            $model->updated_by = static::resolveActorUserRoleId();
        });
    }

    protected static function resolveActorUserRoleId(): ?string
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        if (!empty($user->user_role_id)) {
            return (string) $user->user_role_id;
        }

        if (method_exists($user, 'userRole') && $user->userRole) {
            return (string) $user->userRole->id;
        }

        return null;
    }

    // Relationships
    public function createdBy()
    {
        return $this->belongsTo(UserRole::class, 'created_by', 'id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(UserRole::class, 'updated_by', 'id');
    }

    public function form()
    {
        return $this->belongsTo(AuditFindingForm::class, 'audit_finding_form_id', 'id');
    }

    public function selfEvaluationDetail()
    {
        return $this->belongsTo(SelfEvaluationDetail::class, 'self_evaluation_detail_id', 'id');
    }

    public static function generateNextId(): string
    {
        $maxNum = (int) static::where('id', 'like', 'AFD%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        return 'AFD' . str_pad((string) ($maxNum + 1), 6, '0', STR_PAD_LEFT);
    }
}
