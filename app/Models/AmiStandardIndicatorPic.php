<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Model;

class AmiStandardIndicatorPic extends Model
{
    protected $table = 'ami_standard_indicator_pic';
    protected $primaryKey = 'id';
    public $incrementing = false; // Karena primary key adalah string
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'standard_indicator_id',
        'role_id',
        'created_by',
        'updated_by',
        'active',
    ];
    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AmiStandardIndicatorPic $m) {
            // blameable
            if (Auth::check()) {
                $m->created_by ??= Auth::id();
                $m->updated_by ??= Auth::id();
            }
        });

        static::updating(function (AmiStandardIndicatorPic $m) {
            if (Auth::check()) {
                $m->updated_by = Auth::id();
            }
        });
    }

    public function indicator()
    {
        return $this->belongsTo(AmiStandardIndicator::class, 'standard_indicator_id', 'id');
    }
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by', 'id'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by', 'id'); }

public static function generateNextId()
{
    // Ambil nomor terbesar berdasarkan angka setelah prefix "AIP"
    $maxNum = (int) self::where('id', 'like', 'AIP%')
        ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
        ->value('maxnum');

    $nextNumber = $maxNum + 1;

    // 6 digit padding agar urutan string tetap benar
    return 'AIP' . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
}

}
