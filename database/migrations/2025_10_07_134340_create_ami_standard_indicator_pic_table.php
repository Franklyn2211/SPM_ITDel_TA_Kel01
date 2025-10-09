<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ami_standard_indicator_pic', function (Blueprint $table) {
            $table->string('id')->primary(); // contoh: AIP001
            $table->string('standard_indicator_id'); // FK ke ami_standard_indicators
            $table->string('role_id'); // FK ke roles

            // audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('standard_indicator_id')->references('id')->on('ami_standard_indicators')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // 1 indikator tidak boleh punya PIC role yang sama dua kali
            $table->unique(['standard_indicator_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ami_standard_indicator_pic');
    }
};
