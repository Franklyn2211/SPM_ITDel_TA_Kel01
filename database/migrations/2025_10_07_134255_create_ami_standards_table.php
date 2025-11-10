<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ami_standards', function (Blueprint $table) {
            $table->string('id')->primary(); // contoh: AS001
            $table->string('name'); // nama standar AMI
            $table->string('academic_config_id'); // FK ke academic_configs

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->boolean('active')->default(false);
            $table->timestamps();

            // Foreign keys
            $table->foreign('academic_config_id')->references('id')->on('academic_configs')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ami_standards');
    }
};
