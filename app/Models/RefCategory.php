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
    protected $fillable = ['id','name','active','created_by','updated_by'];
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
        // Mendapatkan ID terakhir dari database
        $latestId = self::orderBy('id', 'desc')->first();

        // Mengambil nomor dari ID terakhir
        $lastNumber = $latestId ? intval(substr($latestId->id_news, 1)) : 0;

        // Menambahkan 1 untuk mendapatkan nomor berikutnya
        $nextNumber = $lastNumber + 1;

        // Mengonversi nomor berikutnya ke format yang diinginkan (NXX)
        $nextId = 'C' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        return $nextId;
    }

}
