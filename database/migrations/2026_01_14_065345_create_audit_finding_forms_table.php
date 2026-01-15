<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_finding_forms', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('self_evaluation_form_id');
            $table->string('area', 255)->nullable();
            $table->string('auditor_user_role_id', 64)->nullable();
            $table->string('member_auditor_user_role_id', 64)->nullable();
            $table->date('audit_date')->nullable();
            $table->enum('status', ['Draft', 'Final'])->default('Draft');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->boolean('active')->default(true);

            // FK
            $table->foreign('self_evaluation_form_id')
                ->references('id')->on('self_evaluation_forms')
                ->cascadeOnDelete();

            $table->foreign('auditor_user_role_id')
                ->references('id')->on('user_roles')
                ->nullOnDelete();
            $table->foreign('member_auditor_user_role_id')
                ->references('id')->on('user_roles')
                ->nullOnDelete();

            $table->foreign('created_by')
                ->references('id')->on('user_roles')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')->on('user_roles')
                ->nullOnDelete();

            $table->index('status', 'idx_finding_form_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_finding_forms');
    }
};
