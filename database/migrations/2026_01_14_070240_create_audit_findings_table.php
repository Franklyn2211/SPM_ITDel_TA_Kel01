<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('audit_finding_form_id');
            $table->string('self_evaluation_detail_id');
            // nomor temuan (001, 002, ...)
            // format TN-AMI/D3TI/2324/001 dibuat di layer export, bukan disimpan string
            $table->unsignedInteger('finding_no');
            // tipe temuan diset otomatis dari FED (Melampaui/Mencapai=POSITIVE)

            // POSITIVE (Melampaui/Mencapai)
            $table->text('control')->nullable();
            $table->text('improvement')->nullable();
            $table->text('follow_up_plan')->nullable();

            // NEGATIVE (Tidak Mencapai/Menyimpang)
            $table->text('auditor_recommendation')->nullable();
            $table->text('corrective_action_plan')->nullable();
            $table->enum('severity', ['OBS', 'KTS_MINOR', 'KTS_MAYOR'])->nullable();

            // kalau F-220 ada target waktu
            $table->date('due_date')->nullable();

            // audit trail (lagi-lagi konsisten dengan user_roles)
            $table->string('created_by', 64)->nullable();
            $table->string('updated_by', 64)->nullable();

            $table->timestamps();
            $table->boolean('active')->default(true);

            // FK
            $table->foreign('audit_finding_form_id')
                ->references('id')->on('audit_finding_forms')
                ->cascadeOnDelete();

            $table->foreign('self_evaluation_detail_id')
                ->references('id')->on('self_evaluation_details')
                ->cascadeOnDelete();

            $table->foreign('created_by')
                ->references('id')->on('user_roles')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')->on('user_roles')
                ->nullOnDelete();

            // 1 butir FED idealnya 1 temuan (kalau kamu mau 1 butir bisa punya banyak temuan, hapus unique ini)
            $table->unique(['self_evaluation_detail_id'], 'uq_one_finding_per_detail');

            // nomor temuan unik dalam 1 form temuan
            $table->unique(['audit_finding_form_id', 'finding_no'], 'uq_finding_no_per_form');

            $table->index(['audit_finding_form_id'], 'idx_form_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
    }
};
