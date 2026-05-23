<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'uq_report_views_user_name');
            $table->index(['association_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_views');
    }
};

