<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row counter used by EloquentWorkItemRepository::nextWorkNumber()
        // to generate collision-free sequential task_id values under row locking.
        Schema::create('work_item_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('next_number')->default(1);
        });

        DB::table('work_item_sequences')->insert([
            'id' => 1,
            'next_number' => 1,
        ]);

        Schema::create('work_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_id')->unique();
            $table->foreignUuid('department_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type');
            $table->foreignUuid('assigned_to_id')->constrained('users')->cascadeOnDelete();
            // Computed server-side only, from whoever performed the create/reassign
            // action — see WorkItemService. Never client-settable.
            $table->foreignUuid('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
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

        Schema::create('work_item_timelines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('assigned_to_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['work_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_timelines');
        Schema::dropIfExists('work_items');
        Schema::dropIfExists('work_item_sequences');
    }
};
