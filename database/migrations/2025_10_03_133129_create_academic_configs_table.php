<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_configs', function (Blueprint $table) {
            $table->string('id')->primary(); // contoh: AC001
            $table->string('academic_code')->unique(); // contoh: 2024/2025
            $table->string('name'); // contoh: Tahun Ajaran 2024/2025
            // audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('active')->default(true); // 1 = active, 0 = inactive
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_configs');
    }
};
