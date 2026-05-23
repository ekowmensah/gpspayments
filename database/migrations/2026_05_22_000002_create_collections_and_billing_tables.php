<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->enum('category', [
                'dues',
                'levy',
                'welfare',
                'subscription',
                'special_fundraising',
                'donation',
                'other',
            ])->default('dues');
            $table->enum('charge_type', ['recurring', 'one_time', 'voluntary'])->default('recurring');
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly', 'one_time', 'custom'])->default('monthly');
            $table->decimal('amount', 12, 2)->nullable();
            $table->char('currency_code', 3)->default('GHS');
            $table->boolean('is_required')->default(true);
            $table->boolean('allow_partial_payment')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('due_day_of_month')->nullable();
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->enum('penalty_type', ['none', 'fixed', 'percent'])->default('none');
            $table->decimal('penalty_value', 12, 2)->default(0);
            $table->enum('applies_scope', ['all_members', 'selected_units', 'selected_members'])->default('all_members');
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['association_id', 'code']);
            $table->index(['association_id', 'status']);
            $table->index('applies_scope');
        });

        Schema::create('collection_item_units', function (Blueprint $table): void {
            $table->foreignId('collection_item_id')->constrained('collection_items')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['collection_item_id', 'unit_id']);
        });

        Schema::create('collection_item_members', function (Blueprint $table): void {
            $table->foreignId('collection_item_id')->constrained('collection_items')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['collection_item_id', 'member_id']);
        });

        Schema::create('billing_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('period_code', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['open', 'closed', 'locked'])->default('open');
            $table->timestamps();

            $table->unique(['association_id', 'period_code']);
            $table->index(['association_id', 'period_start', 'period_end']);
        });

        Schema::create('charge_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('collection_item_id')->constrained('collection_items')->cascadeOnDelete();
            $table->foreignId('billing_period_id')->nullable()->constrained('billing_periods')->nullOnDelete();
            $table->enum('run_type', ['scheduled', 'manual', 'backfill'])->default('scheduled');
            $table->enum('run_status', ['queued', 'running', 'completed', 'failed', 'cancelled'])->default('queued');
            $table->unsignedInteger('generated_count')->default(0);
            $table->decimal('expected_total', 14, 2)->default(0);
            $table->string('error_message', 500)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['association_id', 'run_status']);
            $table->index('collection_item_id');
        });

        Schema::create('member_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('charge_reference', 80);
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('collection_item_id')->constrained('collection_items')->restrictOnDelete();
            $table->foreignId('billing_period_id')->nullable()->constrained('billing_periods')->nullOnDelete();
            $table->foreignId('charge_run_id')->nullable()->constrained('charge_runs')->nullOnDelete();
            $table->date('charge_date');
            $table->date('due_date');
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('waived_amount', 12, 2)->default(0);
            $table->enum('status', ['open', 'partial', 'paid', 'waived', 'cancelled'])->default('open');
            $table->dateTime('status_updated_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['association_id', 'charge_reference']);
            $table->index(['member_id', 'status']);
            $table->index('due_date');
            $table->index(['collection_item_id', 'billing_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_charges');
        Schema::dropIfExists('charge_runs');
        Schema::dropIfExists('billing_periods');
        Schema::dropIfExists('collection_item_members');
        Schema::dropIfExists('collection_item_units');
        Schema::dropIfExists('collection_items');
    }
};

