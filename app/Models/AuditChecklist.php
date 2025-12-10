<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditChecklist extends Model
{
    protected $table = 'audit_checklists';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'self_evaluation_detail_id',
        'item',
        'note',
        'created_by',
        'updated_by',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function detail()
    {
        return $this->belongsTo(SelfEvaluationDetail::class, 'self_evaluation_detail_id');
    }

    public static function generateNextId(): string
    {
        $maxNum = (int) self::where('id', 'like', 'ACL%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        $next = 'ACL' . str_pad((string) ($maxNum + 1), 3, '0', STR_PAD_LEFT);

        return self::where('id', $next)->exists()
            ? self::generateNextId()
            : $next;
    }
}
