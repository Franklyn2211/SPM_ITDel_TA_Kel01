<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserRole; // <— penting

class EvaluasiDiriDetail extends Model
{
    protected $table = 'form_evaluasi_diri_detail';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'form_evaluasi_diri_id',
        'ami_standard_indicator_id',
        'ketercapaian_standard_id',
        'status_id',
        'hasil',
        'bukti_pendukung',
        'faktor_penghambat_pendukung',
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

    public function createdBy()            { return $this->belongsTo(UserRole::class, 'created_by'); }
    public function updatedBy()            { return $this->belongsTo(UserRole::class, 'updated_by'); }
    public function EvaluasiDiri()         { return $this->belongsTo(EvaluasiDiri::class, 'form_evaluasi_diri_id', 'id'); }
    public function AmiStandardIndicator() { return $this->belongsTo(AmiStandardIndicator::class, 'ami_standard_indicator_id', 'id'); }
    public function KetercapaianStandard() { return $this->belongsTo(KetercapaianStandard::class, 'ketercapaian_standard_id', 'id'); }
    public function status()               { return $this->belongsTo(StatusEvaluasi::class, 'status_id', 'id'); }

    public static function generateNextId(): string
    {
        $maxNum = (int) static::where('id', 'like', 'EDD%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        return 'EDD' . str_pad((string)($maxNum + 1), 6, '0', STR_PAD_LEFT);
    }
}
