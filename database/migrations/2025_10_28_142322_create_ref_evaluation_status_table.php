<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ref_evaluation_status', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('user_roles')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('user_roles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_evaluation_status');
    }
};
