<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_department', function (Blueprint $table) {
            $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'department_id']);
        });

        // Carry forward each category's existing single department as its
        // first pivot row before the column disappears.
        DB::table('categories')
            ->whereNotNull('department_id')
            ->select('id', 'department_id')
            ->get()
            ->each(function ($category) {
                DB::table('category_department')->insert([
                    'category_id' => $category->id,
                    'department_id' => $category->department_id,
                ]);
            });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignUuid('department_id')->nullable()->after('name')->constrained()->cascadeOnDelete();
        });

        // Best-effort only — a category attached to several departments
        // can't be losslessly collapsed back into one column, so this keeps
        // just the first.
        DB::table('category_department')
            ->orderBy('category_id')
            ->get()
            ->groupBy('category_id')
            ->each(function ($rows, $categoryId) {
                DB::table('categories')
                    ->where('id', $categoryId)
                    ->update(['department_id' => $rows->first()->department_id]);
            });

        Schema::dropIfExists('category_department');
    }
};
