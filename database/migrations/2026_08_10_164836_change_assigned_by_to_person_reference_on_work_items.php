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
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn('assigned_by');
            $table->foreignUuid('assigned_by_id')->nullable()->after('assigned_to_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropForeign(['assigned_by_id']);
            $table->dropColumn('assigned_by_id');
            $table->string('assigned_by')->default('self');
        });
    }
};
