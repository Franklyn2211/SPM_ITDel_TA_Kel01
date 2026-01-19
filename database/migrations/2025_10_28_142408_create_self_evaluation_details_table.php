<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_evaluation_details', function (Blueprint $table) {
            $table->string('id')->primary();

            // Main relations
            $table->string('self_evaluation_form_id');
            $table->string('ami_standard_indicator_id');
            $table->string('standard_achievement_id')->nullable();
            $table->string('status_id')->nullable();

            // Evaluation contents
            $table->text('result')->nullable();                   // hasil (HTML dari summernote)
            $table->string('supporting_evidence_url', 2048)->nullable(); // bukti pendukung (URL saja)
            $table->text('contributing_factors')->nullable();     // faktor_penghambat_pendukung (HTML)

            // Audit trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->boolean('active')->default(true);

            // Foreign keys
            $table->foreign('self_evaluation_form_id')
                ->references('id')
                ->on('self_evaluation_forms')
                ->cascadeOnDelete();

            $table->foreign('ami_standard_indicator_id')
                ->references('id')
                ->on('ami_standard_indicators')
                ->cascadeOnDelete();

            $table->foreign('standard_achievement_id')
                ->references('id')
                ->on('ref_standard_achievements')
                ->cascadeOnDelete();

            $table->foreign('status_id')
                ->references('id')
                ->on('ref_evaluation_status')
                ->cascadeOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_evaluation_details');
    }
};
