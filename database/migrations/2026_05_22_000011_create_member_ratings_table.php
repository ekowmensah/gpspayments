<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(100);
            $table->decimal('minimum_required_score', 5, 2)->default(80);
            $table->boolean('eligible_for_benefit')->default(true);
            $table->string('band', 40)->default('excellent');
            $table->date('as_of_date');
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->unique(['association_id', 'member_id'], 'uq_member_rating_assoc_member');
            $table->index(['association_id', 'eligible_for_benefit'], 'idx_member_rating_assoc_elig');
            $table->index(['association_id', 'score'], 'idx_member_rating_assoc_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_ratings');
    }
};

