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
        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('period_month', 20)->comment('Format: 2025-10');
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('allowance', 12, 2)->default(0);
            $table->decimal('deduction', 12, 2)->default(0);
            $table->decimal('total_salary', 12, 2)->comment('basic + allowance - deduction');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period_month'], 'unique_employee_period');
            $table->index('created_by');
        });
    }

    /**
     * Rollback migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
    }
};
