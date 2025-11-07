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
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 20)->comment('Periode: 2025-10, Q4-2025, dll');
            $table->integer('total_star')->comment('Rating 1-10 bintang');
            $table->text('review_description');
            $table->timestamps();

            $table->index(['employee_id', 'period']);
            $table->index('reviewer_id');
        });
    }

    /**
     * Rollback migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
