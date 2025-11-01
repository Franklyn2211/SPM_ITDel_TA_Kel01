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
        Schema::create('form_evaluasi_diri', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('category_detail_id');
            $table->string('academic_config_id');
            $table->string('ketua_auditee_nama');
            $table->string('ketua_auditee_jabatan');

            // Anggota Auditee (maks 2 orang, bisa ditambah kolom kalau perlu)
            $table->string('anggota_auditee_satu')->nullable();
            $table->string('anggota_auditee_jabatan_satu')->nullable();
            $table->string('anggota_auditee_dua')->nullable();
            $table->string('anggota_auditee_jabatan_dua')->nullable();
            $table->string('anggota_auditee_tiga')->nullable();
            $table->string('anggota_auditee_jabatan_tiga')->nullable();

            $table->string('status_id');
            $table->date('tanggal_submit')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->boolean('active')->default(true);

            $table->foreign('category_detail_id')->references('id')->on('ref_category_details')->cascadeOnDelete();
            $table->foreign('academic_config_id')->references('id')->on('academic_configs')->cascadeOnDelete();
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
        Schema::dropIfExists('form_evaluasi_diri');
    }
};
