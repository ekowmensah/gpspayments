<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CollectionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionCategoryController extends Controller
{
    public function index(): View
    {
        $categories = CollectionCategory::query()
            ->where('association_id', 1)
            ->withCount('collectionItems')
            ->orderBy('name')
            ->paginate(20);

        return view('collection-categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('collection_categories', 'code')->where(fn ($q) => $q->where('association_id', 1)),
            ],
            'description' => ['nullable', 'string'],
            'payment_mode' => ['required', 'in:compulsory,voluntary'],
            'default_charge_type' => ['required', 'in:recurring,one_time,voluntary'],
            'default_is_required' => ['nullable', 'boolean'],
            'default_allow_partial_payment' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $code = trim((string)($validated['code'] ?? ''));
        if ($code === '') {
            $code = $this->generateCode((string)$validated['name']);
        }

        $effectiveChargeType = $validated['payment_mode'] === 'voluntary'
            ? 'voluntary'
            : (string)$validated['default_charge_type'];
        $effectiveRequired = $validated['payment_mode'] === 'voluntary'
            ? false
            : $request->boolean('default_is_required', true);
        $effectivePartial = $request->boolean('default_allow_partial_payment', true);

        $category = CollectionCategory::create([
            'association_id' => 1,
            'code' => $code,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'payment_mode' => $validated['payment_mode'],
            'default_charge_type' => $effectiveChargeType,
            'default_is_required' => $effectiveRequired,
            'default_allow_partial_payment' => $effectivePartial,
            'status' => $validated['status'],
            'created_by' => $request->user()?->id,
        ]);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'COLLECTION_CATEGORY_CREATED',
            'entity_type' => 'CollectionCategory',
            'entity_id' => $category->id,
            'change_summary' => 'Collection category created',
            'after_data' => $category->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()->route('collection-categories.index')->with('success', 'Collection category created.');
    }

    public function update(Request $request, CollectionCategory $collectionCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('collection_categories', 'code')
                    ->where(fn ($q) => $q->where('association_id', 1))
                    ->ignore($collectionCategory->id),
            ],
            'description' => ['nullable', 'string'],
            'payment_mode' => ['required', 'in:compulsory,voluntary'],
            'default_charge_type' => ['required', 'in:recurring,one_time,voluntary'],
            'default_is_required' => ['nullable', 'boolean'],
            'default_allow_partial_payment' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($collectionCategory->association_id !== 1) {
            abort(403);
        }

        $effectiveChargeType = $validated['payment_mode'] === 'voluntary'
            ? 'voluntary'
            : (string)$validated['default_charge_type'];
        $effectiveRequired = $validated['payment_mode'] === 'voluntary'
            ? false
            : $request->boolean('default_is_required', true);
        $effectivePartial = $request->boolean('default_allow_partial_payment', true);

        $before = $collectionCategory->toArray();

        $collectionCategory->update([
            'code' => strtolower(trim((string)$validated['code'])),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'payment_mode' => $validated['payment_mode'],
            'default_charge_type' => $effectiveChargeType,
            'default_is_required' => $effectiveRequired,
            'default_allow_partial_payment' => $effectivePartial,
            'status' => $validated['status'],
        ]);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'COLLECTION_CATEGORY_UPDATED',
            'entity_type' => 'CollectionCategory',
            'entity_id' => $collectionCategory->id,
            'change_summary' => 'Collection category updated',
            'before_data' => $before,
            'after_data' => $collectionCategory->fresh()?->toArray() ?? [],
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()->route('collection-categories.index')->with('success', 'Collection category updated.');
    }

    public function destroy(Request $request, CollectionCategory $collectionCategory): RedirectResponse
    {
        if ($collectionCategory->association_id !== 1) {
            abort(403);
        }

        $usage = (int)$collectionCategory->collectionItems()->count();
        if ($usage > 0) {
            $collectionCategory->update(['status' => 'inactive']);
            return redirect()
                ->route('collection-categories.index')
                ->with('success', 'Category is in use and was set to inactive instead of deleted.');
        }

        $before = $collectionCategory->toArray();
        $collectionCategory->delete();

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'COLLECTION_CATEGORY_DELETED',
            'entity_type' => 'CollectionCategory',
            'entity_id' => (int)($before['id'] ?? 0),
            'change_summary' => 'Collection category deleted',
            'before_data' => $before,
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()->route('collection-categories.index')->with('success', 'Collection category deleted.');
    }

    private function generateCode(string $name): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'category';
        }
        $base = strtolower(substr($base, 0, 40));

        $code = $base;
        $n = 1;
        while (CollectionCategory::query()->where('association_id', 1)->where('code', $code)->exists()) {
            $suffix = '_' . $n;
            $code = substr($base, 0, max(1, 50 - strlen($suffix))) . $suffix;
            $n++;
        }

        return $code;
    }
}
