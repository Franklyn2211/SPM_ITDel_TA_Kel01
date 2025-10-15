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

    // Generate the next available unique ID
    public static function generateNextId()
    {
        // Ambil nomor terbesar berdasarkan angka setelah prefix "ASI"
        $maxNum = (int) self::where('id', 'like', 'ASI%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        // Increment the number by 1
        $nextNumber = $maxNum + 1;

        // Check if the next number already exists
        $nextId = 'ASI' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

        // If the ID already exists, recursively call the method until we get a unique one
        if (self::where('id', $nextId)->exists()) {
            return self::generateNextId(); // Recursive call until a unique ID is generated
        }

        // Return the unique ID
        return $nextId;
    }
}
