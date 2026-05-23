<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_voluntary_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('collection_item_id')->constrained('collection_items')->cascadeOnDelete();
            $table->string('cycle_key', 50);
            $table->enum('action', ['skipped'])->default('skipped');
            $table->text('notes')->nullable();
            $table->dateTime('actioned_at');
            $table->timestamps();

            $table->unique(['member_id', 'collection_item_id', 'cycle_key'], 'uq_mva_member_collection_cycle');
            $table->index(['member_id', 'actioned_at'], 'idx_mva_member_actioned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_voluntary_actions');
    }
};
