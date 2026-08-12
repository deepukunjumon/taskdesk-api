<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->index();
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // A category attached to zero departments is a common one (e.g.
        // "General") that applies regardless of which department is
        // selected — see CategoryController::index()/Category::departments().
        Schema::create('category_department', function (Blueprint $table) {
            $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'department_id']);
        });

        Schema::create('sla_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('priority')->unique();
            $table->unsignedInteger('hours');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_settings');
        Schema::dropIfExists('category_department');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('branches');
    }
};
