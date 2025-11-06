<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_categories', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name'); // contoh: Prodi, Fakultas, Unit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('active')->default(true); // 1 = active, 0 = inactive
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique('name', 'unique_category_name');
        });

        Schema::create('ref_category_details', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name'); // contoh: D3 TI
            $table->string('category_id');   // FK ke ref_categories
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('active')->default(true); // 1 = active, 0 = inactive
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('ref_categories')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique('name', 'unique_detail_per_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_category_details');
        Schema::dropIfExists('ref_categories');
    }
};
