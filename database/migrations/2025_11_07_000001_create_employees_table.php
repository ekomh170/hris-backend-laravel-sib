<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('employee_code')->unique();
            $table->string('position');
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->date('join_date')->index();
            $table->enum('employment_status', ['permanent', 'contract', 'intern', 'resigned'])->index();
            $table->string('contact')->nullable();
            $table->timestamps();

            // $table->index(['manager_id', 'department']);
        });
    }

    /**
     * Rollback migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
