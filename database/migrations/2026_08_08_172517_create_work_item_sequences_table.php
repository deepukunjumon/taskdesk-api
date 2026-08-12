<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row counter table used by EloquentWorkItemRepository::nextWorkNumber()
     * to generate collision-free sequential task_id values under row locking.
     */
    public function up(): void
    {
        Schema::create('work_item_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('next_number')->default(1);
        });

        \Illuminate\Support\Facades\DB::table('work_item_sequences')->insert([
            'id' => 1,
            'next_number' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_sequences');
    }
};
