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
        Schema::create('self_evaluation_details', function (Blueprint $table) {
            $table->string('id')->primary();

            // Main relations
            $table->string('self_evaluation_form_id');
            $table->string('ami_standard_indicator_id');
            $table->string('standard_achievement_id')->nullable();
            $table->string('status_id')->nullable();

            // Evaluation contents
            $table->text('result')->nullable();                   // hasil
            $table->text('supporting_evidence')->nullable();      // bukti_pendukung
            $table->text('contributing_factors')->nullable();     // faktor_penghambat_pendukung

            // Audit trail
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

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
        Schema::dropIfExists('self_evaluation_details');
    }
};
