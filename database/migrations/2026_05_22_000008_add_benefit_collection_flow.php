<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('collection_items', function (Blueprint $table): void {
            if (!Schema::hasColumn('collection_items', 'is_benefit_collection')) {
                $table->boolean('is_benefit_collection')->default(false)->after('allow_partial_payment');
            }
            if (!Schema::hasColumn('collection_items', 'beneficiary_member_id')) {
                $table->foreignId('beneficiary_member_id')
                    ->nullable()
                    ->after('is_benefit_collection')
                    ->constrained('members')
                    ->nullOnDelete();
            }
        });

        if (!Schema::hasTable('member_benefit_disbursements')) {
            Schema::create('member_benefit_disbursements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
                $table->foreignId('collection_item_id')->constrained('collection_items')->restrictOnDelete();
                $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
                $table->decimal('disbursed_amount', 14, 2);
                $table->date('disbursed_date');
                $table->string('reference', 60)->unique();
                $table->enum('status', ['posted', 'reversed'])->default('posted');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['member_id', 'disbursed_date'], 'mbd_member_date_idx');
                $table->index(['collection_item_id', 'disbursed_date'], 'mbd_collection_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_benefit_disbursements');

        Schema::table('collection_items', function (Blueprint $table): void {
            if (Schema::hasColumn('collection_items', 'beneficiary_member_id')) {
                $table->dropConstrainedForeignId('beneficiary_member_id');
            }
            if (Schema::hasColumn('collection_items', 'is_benefit_collection')) {
                $table->dropColumn('is_benefit_collection');
            }
        });
    }
};
