<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 120);
            $table->enum('channel', ['sms', 'email', 'in_app'])->default('sms');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['association_id', 'code']);
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('channel', ['sms', 'email', 'in_app']);
            $table->string('recipient');
            $table->string('message_subject')->nullable();
            $table->text('message_body');
            $table->enum('reference_type', ['member_charge', 'payment', 'donation', 'generic'])->default('generic');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('provider_name', 100)->nullable();
            $table->string('provider_message_id', 150)->nullable();
            $table->enum('delivery_status', ['pending', 'queued', 'sent', 'delivered', 'failed'])->default('pending');
            $table->string('error_message', 500)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['association_id', 'delivery_status']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->nullable()->constrained('associations')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 80)->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('change_summary')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 120)->nullable();
            $table->enum('status', ['success', 'failed', 'attempted'])->default('success');
            $table->string('error_message', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['association_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('actor_user_id');
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('setting_key', 120);
            $table->text('setting_value')->nullable();
            $table->enum('value_type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->boolean('is_encrypted')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['association_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};

