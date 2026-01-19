<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_follow_up_details', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('audit_follow_up_form_id');
            $table->string('audit_finding_id');

            /**
             * HANYA INI YANG DIISI:
             * - realisasi_tindak_lanjut (Auditee)
             * - efektivitas (Auditee)
             * - status (Auditor)
             *
             * Jadwal diambil dari audit_findings.due_date
             */

            // Filled by Auditee
            $table->longText('follow_up_realization')->nullable();
            $table->string('effectiveness', 50)->nullable();

            // Diisi Auditor
            $table->enum('status', ['Open', 'Toleran', 'Closed'])->nullable();
            $table->text('status_description')->nullable();

            // Audit trail (cukup timestamps biasa)
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->boolean('active')->default(true);

            // Index & FK
            $table->index('audit_follow_up_form_id');
            $table->index('audit_finding_id');
            $table->index('status');

            $table->foreign('audit_follow_up_form_id')
                ->references('id')->on('audit_follow_up_forms')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('audit_finding_id')
                ->references('id')->on('audit_findings')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // 1 detail ATL aktif per 1 temuan
            $table->unique(
                ['audit_follow_up_form_id', 'audit_finding_id', 'active'],
                'uq_atl_detail_per_finding_active'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_follow_up_details');
    }
};
