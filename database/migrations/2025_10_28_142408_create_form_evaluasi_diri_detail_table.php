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
        Schema::create('form_evaluasi_diri_detail', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('form_evaluasi_diri_id');
            $table->string('ami_standard_indicator_id');
            $table->string('ketercapaian_standard_id')->nullable();
            $table->string('status_id')->nullable();
            $table->text('hasil')->nullable();
            $table->text('bukti_pendukung')->nullable();
            $table->text('faktor_penghambat_pendukung')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();
            $table->boolean('active')->default(true);

            $table->foreign('form_evaluasi_diri_id')->references('id')->on('form_evaluasi_diri')->cascadeOnDelete();
            $table->foreign('ami_standard_indicator_id')->references('id')->on('ami_standard_indicators')->cascadeOnDelete();
            $table->foreign('ketercapaian_standard_id')->references('id')->on('ref_ketercapaian_standard')->cascadeOnDelete();
            $table->foreign('status_id')->references('id')->on('ref_status_evaluasi')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('user_roles')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('user_roles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_evaluasi_diri_detail');
    }
};
