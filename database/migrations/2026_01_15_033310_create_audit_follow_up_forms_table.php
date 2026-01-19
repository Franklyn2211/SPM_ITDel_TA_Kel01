<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_follow_up_forms', function (Blueprint $table) {
            $table->string('id')->primary();

            // Relasi ke Form Temuan
            $table->string('audit_finding_form_id');

            // Snapshot
            $table->string('area')->nullable();
            $table->date('audit_date')->nullable();

            // Auditor
            $table->string('auditor_user_role_id')->nullable();        // Ketua auditor (ROLE)
            $table->string('member_auditor_user_role_id')->nullable(); // Anggota auditor (USER)

            // Status
            $table->enum('status', ['Draft', 'Final'])->default('Draft');

            // Audit trail
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();
            $table->boolean('active')->default(true);

            /* ================= INDEX ================= */
            $table->index('audit_finding_form_id');
            $table->index('auditor_user_role_id');
            $table->index('member_auditor_user_role_id');

            /* ================= FK ================= */

            $table->foreign('audit_finding_form_id')
                ->references('id')
                ->on('audit_finding_forms')
                ->cascadeOnDelete();

            // Ketua auditor → user_roles
            $table->foreign('auditor_user_role_id')
                ->references('id')
                ->on('user_roles')
                ->nullOnDelete();

            // Anggota auditor → users
            $table->foreign('member_auditor_user_role_id')
                ->references('id')
                ->on('user_roles')
                ->nullOnDelete();

            // 1 ATL aktif per Form Temuan
            $table->unique(
                ['audit_finding_form_id', 'active'],
                'uq_atl_form_per_finding_form_active'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_follow_up_forms');
    }
};
