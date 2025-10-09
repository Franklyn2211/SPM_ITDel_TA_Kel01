<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->string('id')->primary();                 // isi pakai generator (UUID/string) di model/controller
            $table->string('cis_user_id');                   // FK -> users.cis_user_id (pastikan kolom itu ada & unique/indexed)
            $table->string('role_id');                       // FK -> roles.id
            $table->string('academic_config_id');            // FK -> academic_configs.id
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('cis_user_id')
                  ->references('cis_user_id')->on('users')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            $table->foreign('role_id')
                  ->references('id')->on('roles')
                  ->cascadeOnDelete();

            $table->foreign('academic_config_id')
                  ->references('id')->on('academic_configs')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            // Izinkan banyak role per user per tahun akademik,
            // tapi satu baris per kombinasi role yang sama:
            $table->unique(['cis_user_id', 'role_id', 'academic_config_id'], 'user_roles_unique_triplet');

            // Index bantu
            $table->index('cis_user_id');
            $table->index('role_id');
            $table->index('academic_config_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
