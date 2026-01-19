<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

class AuditFollowUpDetail extends Model
{
    protected $table = 'audit_follow_up_details';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'audit_follow_up_form_id',
        'audit_finding_id',

        // Filled by Auditee
        'follow_up_realization',
        'effectiveness',

        // Filled by Auditor
        'status', // enum: Open, Tolerant, Closed
        'status_description',

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

    // ================= Relationships =================

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
        return $this->belongsTo(AuditFollowUpForm::class, 'audit_follow_up_form_id', 'id');
    }

    public function finding()
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id', 'id');
    }

    // ================= Helpers =================

    public static function generateNextId(): string
    {
        $maxNum = (int) static::where('id', 'like', 'ATLD%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        return 'ATLD' . str_pad((string) ($maxNum + 1), 6, '0', STR_PAD_LEFT);
    }
}
