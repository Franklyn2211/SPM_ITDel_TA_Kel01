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
        Schema::create('self_evaluation_forms', function (Blueprint $table) {
            $table->string('id')->primary();

            // Relasi utama
            $table->string('category_detail_id');
            $table->string('academic_config_id');

            // Ketua auditee (diambil dari yang login, tapi tetap disimpan snapshot nama & jabatannya)
            $table->string('head_auditee_name');
            $table->string('head_auditee_position');

            // Anggota auditee 1 (nama & jabatan hasil pilihan dropdown user/role)
            $table->string('member_auditee_1_name')->nullable();
            $table->string('member_auditee_1_position')->nullable();

            // Anggota auditee 2
            $table->string('member_auditee_2_name')->nullable();
            $table->string('member_auditee_2_position')->nullable();

            // Anggota auditee 3
            $table->string('member_auditee_3_name')->nullable();
            $table->string('member_auditee_3_position')->nullable();

            // Status workflow evaluasi
            $table->string('status_id');

            // Tanggal submit
            $table->date('submitted_at')->nullable();

            // Audit trail pakai user_roles (sesuai ERD kamu)
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();
            $table->boolean('active')->default(true);

            // Foreign keys utama
            $table->foreign('category_detail_id')
                ->references('id')
                ->on('ref_category_details')
                ->cascadeOnDelete();

            $table->foreign('academic_config_id')
                ->references('id')
                ->on('academic_configs')
                ->cascadeOnDelete();

            $table->foreign('status_id')
                ->references('id')
                ->on('ref_evaluation_status')
                ->cascadeOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('user_roles')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')
                ->on('user_roles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_evaluation_forms');
    }
};
