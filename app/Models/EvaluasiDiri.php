<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserRole; // <— penting

class EvaluasiDiri extends Model
{
    protected $table = 'form_evaluasi_diri';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'category_detail_id',
        'academic_config_id',
        'ketua_auditee_nama',
        'ketua_auditee_jabatan',
        'anggota_auditee_satu',
        'anggota_auditee_jabatan_satu',
        'anggota_auditee_dua',
        'anggota_auditee_jabatan_dua',
        'anggota_auditee_tiga',
        'anggota_auditee_jabatan_tiga',
        'status_id',
        'tanggal_submit',
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
                    ? (UserRole::where('cis_user_id', $u->cis_user_id)->where('active',1)->value('id')
                      ?? UserRole::where('cis_user_id', $u->cis_user_id)->latest('created_at')->value('id'))
                    : null)
                ?? UserRole::where('id', $u->id)->where('active',1)->value('id')
                ?? UserRole::where('id', $u->id)->latest('created_at')->value('id');
        };

        static::creating(function ($m) use ($resolveRoleId) {
            $rid = $resolveRoleId();
            if ($rid) {
                $m->created_by ??= $rid;
                $m->updated_by ??= $rid;
            }
        });

        static::updating(function ($m) use ($resolveRoleId) {
            $rid = $resolveRoleId();
            $m->updated_by = $rid ?: null;
        });
    }

    public function createdBy() { return $this->belongsTo(UserRole::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(UserRole::class, 'updated_by'); }
    public function categoryDetail() { return $this->belongsTo(RefCategoryDetail::class, 'category_detail_id', 'id'); }
    public function academicConfig() { return $this->belongsTo(AcademicConfig::class, 'academic_config_id', 'id'); }
    public function status() { return $this->belongsTo(StatusEvaluasi::class, 'status_id', 'id'); }
    public function EvaluasiDiriDetails() { return $this->hasMany(EvaluasiDiriDetail::class, 'form_evaluasi_diri_id', 'id'); }

    public static function generateNextId()
    {
        $maxNum = (int) self::where('id', 'like', 'ED%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        $nextId = 'ED' . str_pad((string) ($maxNum + 1), 3, '0', STR_PAD_LEFT);
        return self::where('id', $nextId)->exists() ? self::generateNextId() : $nextId;
    }
}
