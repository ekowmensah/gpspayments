<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'member_id')) {
                $table->foreignId('member_id')
                    ->nullable()
                    ->after('association_id')
                    ->constrained('members')
                    ->nullOnDelete();
                $table->unique('member_id', 'uq_users_member_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'member_id')) {
                $table->dropUnique('uq_users_member_id');
                $table->dropConstrainedForeignId('member_id');
            }
        });
    }
};
