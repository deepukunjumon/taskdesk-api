<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_id')->unique();
            $table->foreignUuid('department_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type');
            $table->string('assigned_by');
            $table->foreignUuid('assigned_to_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('source');
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('priority');
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->text('resolution')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['department_id', 'status']);
            $table->index(['assigned_to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
