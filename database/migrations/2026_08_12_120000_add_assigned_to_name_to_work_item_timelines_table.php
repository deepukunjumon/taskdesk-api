<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_item_timelines', function (Blueprint $table) {
            $table->string('assigned_to_name')->nullable()->after('to_status');
        });
    }

    public function down(): void
    {
        Schema::table('work_item_timelines', function (Blueprint $table) {
            $table->dropColumn('assigned_to_name');
        });
    }
};
