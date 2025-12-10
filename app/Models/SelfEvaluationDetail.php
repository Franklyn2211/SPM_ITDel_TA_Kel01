<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

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

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!Auth::check()) {
                return;
            }

            $userId = Auth::id();

            if (empty($model->created_by)) {
                $model->created_by = $userId;
            }

            if (empty($model->updated_by)) {
                $model->updated_by = $userId;
            }
        });

        static::updating(function ($model) {
            if (!Auth::check()) {
                return;
            }

            $model->updated_by = Auth::id();
        });
    }

    // Relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function form()
    {
        return $this->belongsTo(SelfEvaluationForm::class, 'self_evaluation_form_id', 'id');
    }

    public function indicator()
    {
        return $this->belongsTo(AmiStandardIndicator::class, 'ami_standard_indicator_id', 'id');
    }

    public function standardAchievement()
    {
        return $this->belongsTo(StandardAchievement::class, 'standard_achievement_id', 'id');
    }

    public function status()
    {
        return $this->belongsTo(EvaluationStatus::class, 'status_id', 'id');
    }
    public function auditChecklists()
    {
        return $this->hasMany(AuditChecklist::class, 'self_evaluation_detail_id');
    }


    public static function generateNextId(): string
    {
        $maxNum = (int) static::where('id', 'like', 'SED%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        return 'SED' . str_pad((string) ($maxNum + 1), 6, '0', STR_PAD_LEFT);
    }
}
