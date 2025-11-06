<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RefCategory extends Model
{
    protected $table = 'ref_categories';
    protected $primaryKey = 'id';
    public $incrementing = false;     // PK string
    protected $keyType = 'string';
    protected $fillable = ['id', 'name','active','created_by','updated_by'];
    protected $casts = ['active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                // isi otomatis saat create
                $model->created_by ??= Auth::id();
                $model->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                // isi otomatis saat update
                $model->updated_by = Auth::id();
            }
        });
    }

    // Relasi opsional (biar enak dipakai)
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
    public function details()   { return $this->hasMany(RefCategoryDetail::class, 'category_id', 'id'); }

    public static function generateNextId()
    {
       // Ambil nomor terbesar berdasarkan angka setelah prefix "ASI"
        $maxNum = (int) self::where('id', 'like', 'C%')
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
            ->value('maxnum');

        // Increment the number by 1
        $nextNumber = $maxNum + 1;

        // Check if the next number already exists
        $nextId = 'C' . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);

        // If the ID already exists, recursively call the method until we get a unique one
        if (self::where('id', $nextId)->exists()) {
            return self::generateNextId(); // Recursive call until a unique ID is generated
        }

        // Return the unique ID
        return $nextId;
    }

}
