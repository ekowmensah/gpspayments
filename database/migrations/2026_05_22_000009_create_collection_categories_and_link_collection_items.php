<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collection_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('association_id')->constrained('associations')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->enum('payment_mode', ['compulsory', 'voluntary'])->default('compulsory');
            $table->enum('default_charge_type', ['recurring', 'one_time', 'voluntary'])->default('recurring');
            $table->boolean('default_is_required')->default(true);
            $table->boolean('default_allow_partial_payment')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['association_id', 'code']);
            $table->index(['association_id', 'status']);
        });

        Schema::table('collection_items', function (Blueprint $table): void {
            if (!Schema::hasColumn('collection_items', 'collection_category_id')) {
                $table->foreignId('collection_category_id')
                    ->nullable()
                    ->after('association_id')
                    ->constrained('collection_categories')
                    ->nullOnDelete();
            }
        });

        $this->seedDefaultsAndBackfill();
    }

    public function down(): void
    {
        Schema::table('collection_items', function (Blueprint $table): void {
            if (Schema::hasColumn('collection_items', 'collection_category_id')) {
                $table->dropConstrainedForeignId('collection_category_id');
            }
        });

        Schema::dropIfExists('collection_categories');
    }

    private function seedDefaultsAndBackfill(): void
    {
        $defaults = [
            'dues' => [
                'name' => 'Dues',
                'payment_mode' => 'compulsory',
                'default_charge_type' => 'recurring',
                'default_is_required' => 1,
                'default_allow_partial_payment' => 1,
            ],
            'levy' => [
                'name' => 'Levy',
                'payment_mode' => 'compulsory',
                'default_charge_type' => 'one_time',
                'default_is_required' => 1,
                'default_allow_partial_payment' => 1,
            ],
            'welfare' => [
                'name' => 'Welfare',
                'payment_mode' => 'compulsory',
                'default_charge_type' => 'recurring',
                'default_is_required' => 1,
                'default_allow_partial_payment' => 1,
            ],
            'subscription' => [
                'name' => 'Subscription',
                'payment_mode' => 'compulsory',
                'default_charge_type' => 'recurring',
                'default_is_required' => 1,
                'default_allow_partial_payment' => 1,
            ],
            'special_fundraising' => [
                'name' => 'Special Fundraising',
                'payment_mode' => 'compulsory',
                'default_charge_type' => 'one_time',
                'default_is_required' => 1,
                'default_allow_partial_payment' => 1,
            ],
            'donation' => [
                'name' => 'Donation',
                'payment_mode' => 'voluntary',
                'default_charge_type' => 'voluntary',
                'default_is_required' => 0,
                'default_allow_partial_payment' => 1,
            ],
            'other' => [
                'name' => 'Other',
                'payment_mode' => 'compulsory',
                'default_charge_type' => 'one_time',
                'default_is_required' => 1,
                'default_allow_partial_payment' => 1,
            ],
        ];

        $associationIds = DB::table('associations')->pluck('id')->all();
        foreach ($associationIds as $associationId) {
            foreach ($defaults as $code => $meta) {
                DB::table('collection_categories')->updateOrInsert(
                    ['association_id' => $associationId, 'code' => $code],
                    [
                        'name' => $meta['name'],
                        'description' => $meta['name'] . ' category',
                        'payment_mode' => $meta['payment_mode'],
                        'default_charge_type' => $meta['default_charge_type'],
                        'default_is_required' => $meta['default_is_required'],
                        'default_allow_partial_payment' => $meta['default_allow_partial_payment'],
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $rows = DB::table('collection_items')
            ->select('id', 'association_id', 'category', 'charge_type', 'is_required', 'allow_partial_payment')
            ->get();

        foreach ($rows as $row) {
            $categoryCode = trim((string)($row->category ?? ''));
            if ($categoryCode === '') {
                $categoryCode = strtolower((string)($row->charge_type ?? '')) === 'voluntary' ? 'donation' : 'other';
            }

            $category = DB::table('collection_categories')
                ->where('association_id', (int)$row->association_id)
                ->where('code', $categoryCode)
                ->first();

            if (!$category) {
                $fallbackCode = 'other_' . strtolower(Str::random(6));
                $categoryId = DB::table('collection_categories')->insertGetId([
                    'association_id' => (int)$row->association_id,
                    'code' => $fallbackCode,
                    'name' => ucfirst(str_replace('_', ' ', $categoryCode)),
                    'description' => 'Backfilled category',
                    'payment_mode' => strtolower((string)$row->charge_type) === 'voluntary' ? 'voluntary' : 'compulsory',
                    'default_charge_type' => strtolower((string)$row->charge_type) === 'voluntary' ? 'voluntary' : 'one_time',
                    'default_is_required' => (int)$row->is_required === 1 ? 1 : 0,
                    'default_allow_partial_payment' => (int)$row->allow_partial_payment === 1 ? 1 : 0,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $categoryId = (int)$category->id;
            }

            DB::table('collection_items')
                ->where('id', (int)$row->id)
                ->update(['collection_category_id' => $categoryId]);
        }
    }
};
