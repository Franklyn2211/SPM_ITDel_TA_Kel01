<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class AmiStandardIndicator extends Model
{
    protected $table = 'ami_standard_indicators';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id',
        'description',
        'standard_id',
        'created_by',
        'updated_by',
        'active',
    ];
    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            // blameable
            if (Auth::check()) {
                $m->created_by ??= Auth::id();
                $m->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($m) {
            if (Auth::check()) {
                $m->updated_by = Auth::id();
            }
        });
    }

    public function standard()
    {
        return $this->belongsTo(AmiStandard::class, 'standard_id', 'id');
    }

    public function pics()
    {
        return $this->hasMany(AmiStandardIndicatorPic::class, 'standard_indicator_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    public static function generateNextId()
    {
        // Mendapatkan ID terakhir dari database
        $latestId = self::orderBy('id', 'desc')->first();

        // Mengambil nomor dari ID terakhir
        $lastNumber = $latestId ? intval(substr($latestId->id, 2)) : 0;

        // Menambahkan 1 untuk mendapatkan nomor berikutnya
        $nextNumber = $lastNumber + 1;

        // Mengonversi nomor berikutnya ke format yang diinginkan (URXXX)
        $nextId = 'ASI' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        return $nextId;
    }

}
