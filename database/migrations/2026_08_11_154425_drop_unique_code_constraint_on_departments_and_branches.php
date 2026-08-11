<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * A hard DB-level UNIQUE constraint doesn't know about soft deletes, so a
     * soft-deleted row's code would permanently block reuse of that code.
     * Uniqueness among non-deleted rows is enforced at the validation layer
     * instead (see Store/UpdateDepartmentRequest, Store/UpdateBranchRequest),
     * which is the only place it needs to hold.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_code_unique');
            $table->index('code');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique('branches_code_unique');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });
    }
};
