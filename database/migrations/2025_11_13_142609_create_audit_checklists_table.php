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
        Schema::create('audit_checklists', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('self_evaluation_detail_id');
            $table->text('item');                  // apa yang mau dicek / ditanyakan
            $table->text('note')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('self_evaluation_detail_id')
                ->references('id')
                ->on('self_evaluation_details')
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
        Schema::dropIfExists('audit_checklists');
    }
};
