<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

class AuditFindingForm extends Model
{
    protected $table = 'audit_finding_forms';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'self_evaluation_form_id',
        'area',
        'auditor_user_role_id',
        'member_auditor_user_role_id',
        'audit_date',
        'status',
        'created_by',
        'updated_by',
        'active',
    ];

    protected $casts = [
        'audit_date' => 'date',
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

    /**
     * Karena FK created_by/updated_by mengarah ke user_roles.id (string),
     * maka yang disimpan harus ID user_role, bukan users.id.
     *
     * Catatan: ini mengasumsikan User punya kolom user_role_id (umum di project kamu).
     */
    protected static function resolveActorUserRoleId(): ?string
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        // paling umum: ada kolom user_role_id di tabel users
        if (!empty($user->user_role_id)) {
            return (string) $user->user_role_id;
        }

        // fallback kalau kamu pakai relation userRole()
        if (method_exists($user, 'userRole') && $user->userRole) {
            return (string) $user->userRole->id;
        }

        // kalau tidak ada, biar kelihatan error FK ketimbang data ngaco
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

    public function auditorUserRole()
    {
        return $this->belongsTo(UserRole::class, 'auditor_user_role_id', 'id');
    }

    public function memberAuditorUserRole()
    {
        return $this->belongsTo(UserRole::class, 'member_auditor_user_role_id', 'id');
    }

    public function selfEvaluationForm()
    {
        return $this->belongsTo(SelfEvaluationForm::class, 'self_evaluation_form_id', 'id');
    }

    public function findings()
    {
        return $this->hasMany(AuditFinding::class, 'audit_finding_form_id', 'id');
    }

    public static function generateNextId(): string
    {
        $maxNum = (int) static::where('id', 'like', 'AFF%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        return 'AFF' . str_pad((string) ($maxNum + 1), 6, '0', STR_PAD_LEFT);
    }
}
