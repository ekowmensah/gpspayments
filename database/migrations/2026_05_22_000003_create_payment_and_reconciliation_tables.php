<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('intent_reference', 80);
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('collection_item_id')->nullable()->constrained('collection_items')->nullOnDelete();
            $table->enum('payer_type', ['member', 'non_member', 'anonymous'])->default('member');
            $table->string('payer_name')->nullable();
            $table->string('payer_phone', 30)->nullable();
            $table->decimal('expected_amount', 12, 2);
            $table->char('currency_code', 3)->default('GHS');
            $table->enum('payment_method', ['cash', 'mobile_money', 'bank_transfer', 'ussd', 'card']);
            $table->string('provider_name', 100)->nullable();
            $table->string('provider_intent_reference', 120)->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->enum('status', ['initiated', 'pending', 'success', 'failed', 'expired', 'cancelled'])->default('initiated');
            $table->dateTime('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['association_id', 'intent_reference'], 'uq_pay_intent_assoc_ref');
            $table->unique(['association_id', 'idempotency_key'], 'uq_pay_intent_assoc_idem');
            $table->index(['association_id', 'status']);
            $table->index(['provider_name', 'provider_intent_reference']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('payment_reference', 80);
            $table->foreignId('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('collection_item_id')->nullable()->constrained('collection_items')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency_code', 3)->default('GHS');
            $table->enum('payment_method', ['cash', 'mobile_money', 'bank_transfer', 'ussd', 'card']);
            $table->enum('source', ['manual_entry', 'provider_callback', 'import'])->default('manual_entry');
            $table->string('transaction_reference', 120)->nullable();
            $table->string('provider_name', 100)->nullable();
            $table->string('provider_transaction_reference', 120)->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->dateTime('payment_date');
            $table->date('posting_date');
            $table->enum('status', [
                'recorded',
                'pending_verification',
                'verified',
                'posted',
                'failed',
                'reversed',
                'refunded',
                'voided',
            ])->default('recorded');
            $table->string('reversal_reason')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['association_id', 'payment_reference'], 'uq_pay_assoc_ref');
            $table->unique(['association_id', 'idempotency_key'], 'uq_pay_assoc_idem');
            $table->index(['association_id', 'status', 'posting_date']);
            $table->index(['member_id', 'posting_date']);
            $table->index(['provider_name', 'provider_transaction_reference']);
        });

        Schema::create('payment_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('provider_name', 100);
            $table->string('provider_event_id', 150);
            $table->string('provider_transaction_reference', 120)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->enum('processing_status', ['received', 'processed', 'duplicate', 'invalid', 'failed'])->default('received');
            $table->string('error_message', 500)->nullable();
            $table->json('payload');
            $table->dateTime('received_at')->useCurrent();
            $table->dateTime('processed_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->unique(['provider_name', 'provider_event_id'], 'uq_pay_cb_provider_event');
            $table->index(['association_id', 'processing_status']);
            $table->index('provider_transaction_reference');
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('member_charge_id')->constrained('member_charges')->restrictOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->unsignedSmallInteger('allocation_order')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['payment_id', 'member_charge_id'], 'uq_pay_alloc_payment_charge');
            $table->index('member_charge_id');
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('receipt_number', 60);
            $table->enum('receipt_type', ['payment', 'donation', 'refund', 'reversal'])->default('payment');
            $table->dateTime('issued_at')->useCurrent();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('qr_token', 120)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('verification_hash')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['association_id', 'receipt_number'], 'uq_receipts_assoc_number');
            $table->unique('payment_id');
            $table->index('issued_at');
        });

        Schema::create('donations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('donor_type', ['member', 'non_member', 'anonymous'])->default('member');
            $table->string('donor_name')->nullable();
            $table->string('donor_phone', 30)->nullable();
            $table->string('donor_email')->nullable();
            $table->string('purpose')->nullable();
            $table->string('project_code', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency_code', 3)->default('GHS');
            $table->date('donation_date');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['association_id', 'donation_date']);
            $table->index('member_id');
        });

        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('provider_name', 100);
            $table->string('settlement_reference', 120);
            $table->date('settlement_date');
            $table->char('currency_code', 3)->default('GHS');
            $table->decimal('expected_total', 14, 2)->default(0);
            $table->decimal('provider_total', 14, 2)->default(0);
            $table->decimal('variance_total', 14, 2)->default(0);
            $table->enum('status', ['open', 'matched', 'variance', 'closed'])->default('open');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('imported_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider_name', 'settlement_reference'], 'uq_settlement_provider_ref');
            $table->index(['association_id', 'settlement_date']);
            $table->index('status');
        });

        Schema::create('settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider_transaction_reference', 120);
            $table->decimal('provider_amount', 12, 2);
            $table->decimal('system_amount', 12, 2)->nullable();
            $table->decimal('variance_amount', 12, 2)->nullable();
            $table->enum('match_status', ['matched', 'amount_mismatch', 'missing_in_system', 'missing_in_provider'])->default('matched');
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('settlement_id');
            $table->index('provider_transaction_reference');
            $table->index('match_status');
        });

        Schema::create('reconciliation_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('reconciliation_reference', 80);
            $table->enum('reconciliation_type', ['cash_end_of_day', 'cash_mid_day', 'digital_auto', 'manual'])->default('cash_end_of_day');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->decimal('expected_total', 14, 2)->default(0);
            $table->decimal('recorded_total', 14, 2)->default(0);
            $table->decimal('discrepancy_total', 14, 2)->default(0);
            $table->enum('status', ['open', 'pending_review', 'resolved', 'closed'])->default('open');
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['association_id', 'reconciliation_reference'], 'uq_recon_assoc_ref');
            $table->index(['association_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('reconciliation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('reconciliation_batches')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->enum('action', ['include', 'exclude', 'flag_review', 'correct_amount'])->default('include');
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('recorded_amount', 12, 2)->nullable();
            $table->decimal('corrected_amount', 12, 2)->nullable();
            $table->string('discrepancy_reason')->nullable();
            $table->string('resolution_note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('batch_id');
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliation_batches');
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_callbacks');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_intents');
    }
};
