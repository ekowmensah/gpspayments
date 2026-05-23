<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('associations', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('legal_name')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->char('currency_code', 3)->default('GHS');
            $table->string('timezone', 64)->default('Africa/Accra');
            $table->string('logo_path')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('org_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('parent_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->enum('unit_type', [
                'branch',
                'zone',
                'department',
                'unit',
                'chapter',
                'class',
                'electoral_area',
                'local_group',
                'other',
            ])->default('branch');
            $table->string('code', 50)->nullable();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['association_id', 'code']);
            $table->unique(['association_id', 'name', 'parent_unit_id']);
        });

        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('member_code', 60);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('full_name')->storedAs("TRIM(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name))");
            $table->string('phone', 30)->nullable();
            $table->string('alt_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('occupation', 120)->nullable();
            $table->date('date_joined');
            $table->enum('status', ['active', 'inactive', 'suspended', 'exited', 'deceased'])->default('active');
            $table->string('status_reason')->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone', 30)->nullable();
            $table->string('next_of_kin_relationship', 100)->nullable();
            $table->string('photo_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['association_id', 'member_code']);
            $table->unique(['association_id', 'email']);
            $table->index(['association_id', 'status']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('username', 100);
            $table->string('email');
            $table->string('password_hash')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_mfa_enabled')->default(false);
            $table->enum('status', ['active', 'inactive', 'suspended', 'locked'])->default('active');
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['association_id', 'username']);
            $table->unique(['association_id', 'email']);
            $table->index(['association_id', 'status']);
        });

        Schema::create('member_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('from_status', ['active', 'inactive', 'suspended', 'exited', 'deceased'])->nullable();
            $table->enum('to_status', ['active', 'inactive', 'suspended', 'exited', 'deceased']);
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->index('member_id');
            $table->index('changed_at');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['association_id', 'name']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamp('granted_at')->useCurrent();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('member_status_history');
        Schema::dropIfExists('users');
        Schema::dropIfExists('members');
        Schema::dropIfExists('org_units');
        Schema::dropIfExists('associations');
    }
};

