@extends('adminlte::page')

@section('title', 'Collections')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Collections</h1>
            <p class="text-muted mb-0">Configure collection policies, assignment, and member benefit disbursements.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('collection-categories.index') }}" class="btn btn-outline-dark">
                <i class="fas fa-tags mr-1"></i> Categories
            </a>
            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#assignCollectionModal">
                <i class="fas fa-user-check mr-1"></i> Assign Collection
            </button>
            <button type="button" class="btn btn-outline-success" data-toggle="modal" data-target="#disburseBenefitModal">
                <i class="fas fa-hand-holding-usd mr-1"></i> Disburse Benefit
            </button>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createCollectionModal">
                <i class="fas fa-plus mr-1"></i> New Collection
            </button>
        </div>
    </div>
@stop

@section('css')
<style>
    :root {
        --ops-blue: #0d6efd;
        --ops-ink: #0b1f33;
        --ops-slate: #5a6a7a;
        --ops-soft: #f4f7fb;
        --ops-line: #e4ebf3;
    }
    .ops-stat {
        border: 1px solid var(--ops-line);
        border-radius: .9rem;
        background: #fff;
        height: 100%;
        box-shadow: 0 8px 20px rgba(11, 31, 51, .05);
    }
    .ops-stat .label {
        color: var(--ops-slate);
        font-size: .74rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        font-weight: 700;
    }
    .ops-stat .value {
        color: var(--ops-ink);
        font-size: 1.3rem;
        font-weight: 700;
        line-height: 1.15;
    }
    .ops-card {
        border: 1px solid var(--ops-line);
        border-radius: .9rem;
        box-shadow: 0 10px 24px rgba(11, 31, 51, .05);
    }
    .ops-card .card-header {
        background: linear-gradient(180deg, #ffffff 0%, #f9fbfe 100%);
        border-bottom: 1px solid var(--ops-line);
    }
    .badge-soft {
        background: var(--ops-soft);
        color: var(--ops-ink);
        border: 1px solid var(--ops-line);
        border-radius: 999px;
        font-weight: 600;
        padding: .28rem .55rem;
    }
    .collection-row td {
        vertical-align: middle;
    }
    .benefit-pill {
        display: inline-block;
        padding: .2rem .5rem;
        border-radius: 999px;
        font-size: .74rem;
        font-weight: 700;
        background: #e7f6ec;
        color: #1f7a3e;
        border: 1px solid #c9ebd4;
    }
</style>
@stop

@section('content')
    @if(session('success'))
        <x-adminlte-alert theme="success" title="Success">
            {{ session('success') }}
        </x-adminlte-alert>
    @endif

    @if($errors->any())
        <x-adminlte-alert theme="danger" title="Please check the form">
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-adminlte-alert>
    @endif

    <div class="row mb-3">
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="ops-stat p-3">
                <div class="label">Total Collections</div>
                <div class="value">{{ number_format((int)($stats['total_collections'] ?? 0)) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="ops-stat p-3">
                <div class="label">Active</div>
                <div class="value">{{ number_format((int)($stats['active_collections'] ?? 0)) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="ops-stat p-3">
                <div class="label">Assignments</div>
                <div class="value">{{ number_format((int)($stats['assigned_links'] ?? 0)) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="ops-stat p-3">
                <div class="label">Benefit Collections</div>
                <div class="value">{{ number_format((int)($stats['benefit_collections'] ?? 0)) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="ops-stat p-3">
                <div class="label">Benefits Disbursed</div>
                <div class="value">{{ number_format((float)($stats['benefits_disbursed_total'] ?? 0), 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="ops-stat p-3">
                <div class="label">Outstanding</div>
                <div class="value">{{ number_format((float)($stats['outstanding_balance'] ?? 0), 2) }}</div>
            </div>
        </div>
    </div>

    <div class="card ops-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="card-title mb-2 mb-md-0">Collection Registry</h3>
            <div style="min-width: 320px;">
                <input id="collectionTableSearch" type="text" class="form-control form-control-sm" placeholder="Search by name, type, beneficiary, or status">
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0" id="collectionTable">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Schedule</th>
                    <th>Beneficiary</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Assigned</th>
                    <th class="text-right">Realized</th>
                    <th class="text-right">Disbursed</th>
                    <th class="text-right">Available</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($collections as $item)
                    <tr class="collection-row">
                        <td>
                            <div class="font-weight-bold">{{ $item->name }}</div>
                            <div class="small text-muted">
                                {{ $item->categoryConfig?->name ?? ucfirst(str_replace('_', ' ', $item->category)) }}
                                @if($item->categoryConfig)
                                    | {{ ucfirst($item->categoryConfig->payment_mode) }}
                                @endif
                                | {{ ucfirst($item->status) }}
                            </div>
                        </td>
                        <td>
                            <span class="badge-soft mr-1">{{ ucfirst(str_replace('_', ' ', $item->charge_type)) }}</span>
                            <span class="badge-soft">{{ ucfirst(str_replace('_', ' ', $item->frequency)) }}</span>
                        </td>
                        <td>
                            @if((bool)$item->is_benefit_collection)
                                <span class="benefit-pill mb-1">Benefit</span>
                                <div class="small text-muted">
                                    @if($item->beneficiary)
                                        {{ $item->beneficiary->member_code }} - {{ $item->beneficiary->first_name }} {{ $item->beneficiary->last_name }}
                                    @else
                                        Not set
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-right font-weight-bold">{{ number_format((float)$item->amount, 2) }}</td>
                        <td class="text-right">{{ number_format((int)$item->members_count) }}</td>
                        <td class="text-right">{{ number_format((float)($item->collected_total ?? 0), 2) }}</td>
                        <td class="text-right">{{ number_format((float)($item->disbursed_total ?? 0), 2) }}</td>
                        <td class="text-right font-weight-bold {{ (float)($item->available_for_disbursement ?? 0) > 0 ? 'text-success' : 'text-muted' }}">
                            {{ number_format((float)($item->available_for_disbursement ?? 0), 2) }}
                        </td>
                        <td class="text-right">
                            <button
                                type="button"
                                class="btn btn-xs btn-outline-primary js-edit-collection"
                                data-id="{{ $item->id }}"
                                data-name="{{ $item->name }}"
                                data-description="{{ e((string)($item->description ?? '')) }}"
                                data-amount="{{ $item->amount }}"
                                data-category-id="{{ $item->collection_category_id }}"
                                data-frequency="{{ $item->frequency }}"
                                data-start-date="{{ optional($item->start_date)->format('Y-m-d') }}"
                                data-end-date="{{ optional($item->end_date)->format('Y-m-d') }}"
                                data-due-day="{{ $item->due_day_of_month }}"
                                data-status="{{ $item->status }}"
                                data-is-benefit="{{ $item->is_benefit_collection ? '1' : '0' }}"
                                data-beneficiary-id="{{ $item->beneficiary_member_id }}"
                                data-has-history="{{ ($item->has_financial_history ?? false) ? '1' : '0' }}"
                                data-toggle="modal"
                                data-target="#editCollectionModal"
                            >
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center py-4 text-muted">No collections yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $collections->links() }}</div>
    </div>

    <div class="modal fade" id="createCollectionModal" tabindex="-1" role="dialog" aria-labelledby="createCollectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <form method="POST" action="{{ route('collections.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createCollectionModalLabel">Create Collection</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-7">
                                    <x-adminlte-input name="name" label="Collection Name" value="{{ old('name') }}" required />
                                </div>
                                <div class="col-md-5">
                                    <x-adminlte-input name="amount" id="collection_amount" label="Default Amount (GHS)" type="number" step="0.01" value="{{ old('amount') }}">
                                        <x-slot name="bottomSlot">
                                            <small class="text-muted">Optional for voluntary collections.</small>
                                        </x-slot>
                                    </x-adminlte-input>
                                </div>
                                <div class="col-12">
                                    <x-adminlte-textarea name="description" label="Description">{{ old('description') }}</x-adminlte-textarea>
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-select name="collection_category_id" id="collection_category_id" label="Category" required>
                                        <option value="">Select category</option>
                                        @foreach($categories as $category)
                                            <option
                                                value="{{ $category->id }}"
                                                data-payment-mode="{{ $category->payment_mode }}"
                                                data-charge-type="{{ $category->default_charge_type }}"
                                                data-required="{{ $category->default_is_required ? '1' : '0' }}"
                                                data-partial="{{ $category->default_allow_partial_payment ? '1' : '0' }}"
                                                @selected((int)old('collection_category_id') === (int)$category->id)
                                            >
                                                {{ $category->name }} ({{ ucfirst($category->payment_mode) }})
                                            </option>
                                        @endforeach
                                    </x-adminlte-select>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-2" id="category_defaults_panel" style="margin-top:31px;background:#f7faff;">
                                        <div class="small text-muted">Category Defaults</div>
                                        <div class="small" id="category_defaults_text">Select a category to preview behavior.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-select name="frequency" id="frequency" label="Frequency" required>
                                        <option value="monthly" @selected(old('frequency') === 'monthly')>Monthly</option>
                                        <option value="quarterly" @selected(old('frequency') === 'quarterly')>Quarterly</option>
                                        <option value="yearly" @selected(old('frequency') === 'yearly')>Yearly</option>
                                        <option value="one_time" @selected(old('frequency') === 'one_time')>One-time</option>
                                        <option value="custom" @selected(old('frequency') === 'custom')>Custom</option>
                                    </x-adminlte-select>
                                </div>
                                <div class="col-md-4" id="due_day_wrap">
                                    <x-adminlte-input name="due_day_of_month" label="Due Day (1-28)" type="number" min="1" max="28" value="{{ old('due_day_of_month') }}" />
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-input name="start_date" label="Start Date" type="date" value="{{ old('start_date') }}" required />
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-input name="end_date" label="End Date (optional)" type="date" value="{{ old('end_date') }}" />
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-select name="status" label="Status">
                                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                        <option value="draft" @selected(old('status') === 'draft')>Draft</option>
                                        <option value="paused" @selected(old('status') === 'paused')>Paused</option>
                                    </x-adminlte-select>
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-select name="auto_assign_mode" id="auto_assign_mode" label="Assign Members Now">
                                        <option value="none" @selected(old('auto_assign_mode', 'none') === 'none')>No, assign later</option>
                                        <option value="all" @selected(old('auto_assign_mode') === 'all')>Yes, all active members</option>
                                        <option value="selected" @selected(old('auto_assign_mode') === 'selected')>Yes, selected members</option>
                                    </x-adminlte-select>
                                </div>
                                <div class="col-md-8" id="auto_member_wrap" style="display:none;">
                                    <label class="font-weight-bold">Select Members</label>
                                    <select name="member_ids[]" id="auto_member_ids" class="form-control" multiple size="5">
                                        @foreach($members as $member)
                                            <option value="{{ $member->id }}" @selected(in_array((string)$member->id, array_map('strval', old('member_ids', [])), true))>
                                                {{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Hold Ctrl/Cmd to select multiple members.</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="border rounded p-3" style="background:#f8fbff;border-color:#dbe7f6 !important;">
                                <h6 class="font-weight-bold mb-3">Category Driven Rules</h6>
                                <div class="small text-muted mb-2">
                                    Payment flow (compulsory vs voluntary), required flag, and default charge type are inherited from the selected category.
                                </div>
                                <a href="{{ route('collection-categories.index') }}" class="btn btn-sm btn-outline-primary mb-3">
                                    <i class="fas fa-cog mr-1"></i> Manage Categories
                                </a>
                                <hr>
                                <input type="hidden" name="is_benefit_collection" value="0">
                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="is_benefit_collection" name="is_benefit_collection" value="1" @checked(old('is_benefit_collection') === '1')>
                                    <label class="custom-control-label" for="is_benefit_collection">This is a member benefit collection</label>
                                </div>
                                <div id="beneficiary_wrap" style="display:none;">
                                    <x-adminlte-select name="beneficiary_member_id" id="beneficiary_member_id" label="Beneficiary Member">
                                        <option value="">Select beneficiary member</option>
                                        @foreach($members as $member)
                                            <option value="{{ $member->id }}" @selected((string)old('beneficiary_member_id') === (string)$member->id)>
                                                {{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}
                                            </option>
                                        @endforeach
                                    </x-adminlte-select>
                                    <small class="text-muted d-block">Used when contributions are raised for one member and paid out later.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check mr-1"></i> Create Collection
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="assignCollectionModal" tabindex="-1" role="dialog" aria-labelledby="assignCollectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('collections.assign') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="assignCollectionModalLabel">Assign Collection to Members</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-adminlte-select name="collection_item_id" label="Collection Item" required>
                                @foreach($collectionOptions as $item)
                                    <option value="{{ $item->id }}" @selected((int)old('collection_item_id') === (int)$item->id)>{{ $item->name }}</option>
                                @endforeach
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="assign_mode" label="Assignment Mode" id="assign_mode" required>
                                <option value="all" @selected(old('assign_mode') === 'all')>All active members</option>
                                <option value="selected" @selected(old('assign_mode') === 'selected')>Selected members</option>
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-12" id="member_select_wrap" style="display:none;">
                            <label class="font-weight-bold">Members</label>
                            <select name="member_ids[]" id="member_ids" class="form-control" multiple size="7">
                                @foreach($members as $member)
                                    <option value="{{ $member->id }}" @selected(in_array((string)$member->id, array_map('strval', old('member_ids', [])), true))>
                                        {{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple members.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap">
                    <small class="text-muted">Assignments auto-generate charges for recurring/one-time collections.</small>
                    <div>
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-check mr-1"></i> Assign Collection
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div
        class="modal fade"
        id="editCollectionModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="editCollectionModalLabel"
        aria-hidden="true"
        data-action-template="{{ route('collections.update', ['collectionItem' => '__ID__']) }}"
    >
        <div class="modal-dialog modal-xl" role="document">
            <form method="POST" action="#" class="modal-content" id="editCollectionForm">
                @csrf
                <input type="hidden" name="update_collection_item_id" id="update_collection_item_id" value="{{ old('update_collection_item_id') }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCollectionModalLabel">Edit Collection</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning d-none" id="edit_collection_history_notice">
                        This collection already has member charges/payments. To protect historical records, only
                        <strong>Name</strong>, <strong>Description</strong>, <strong>Status</strong>, and <strong>End Date</strong>
                        can be changed.
                    </div>
                    <div class="row">
                        <div class="col-md-7">
                            <x-adminlte-input name="update_name" id="update_name" label="Collection Name" value="{{ old('update_name') }}" required />
                        </div>
                        <div class="col-md-5">
                            <x-adminlte-input name="update_amount" id="update_amount" label="Default Amount (GHS)" type="number" step="0.01" value="{{ old('update_amount') }}" />
                        </div>
                        <div class="col-12">
                            <x-adminlte-textarea name="update_description" id="update_description" label="Description">{{ old('update_description') }}</x-adminlte-textarea>
                        </div>
                        <div class="col-md-4">
                            <x-adminlte-select name="update_collection_category_id" id="update_collection_category_id" label="Category" required>
                                <option value="">Select category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((int)old('update_collection_category_id') === (int)$category->id)>
                                        {{ $category->name }} ({{ ucfirst($category->payment_mode) }})
                                    </option>
                                @endforeach
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-4">
                            <x-adminlte-select name="update_frequency" id="update_frequency" label="Frequency" required>
                                <option value="monthly" @selected(old('update_frequency') === 'monthly')>Monthly</option>
                                <option value="quarterly" @selected(old('update_frequency') === 'quarterly')>Quarterly</option>
                                <option value="yearly" @selected(old('update_frequency') === 'yearly')>Yearly</option>
                                <option value="one_time" @selected(old('update_frequency') === 'one_time')>One-time</option>
                                <option value="custom" @selected(old('update_frequency') === 'custom')>Custom</option>
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-4">
                            <x-adminlte-input name="update_due_day_of_month" id="update_due_day_of_month" label="Due Day (1-28)" type="number" min="1" max="28" value="{{ old('update_due_day_of_month') }}" />
                        </div>
                        <div class="col-md-4">
                            <x-adminlte-input name="update_start_date" id="update_start_date" label="Start Date" type="date" value="{{ old('update_start_date') }}" required />
                        </div>
                        <div class="col-md-4">
                            <x-adminlte-input name="update_end_date" id="update_end_date" label="End Date (optional)" type="date" value="{{ old('update_end_date') }}" />
                        </div>
                        <div class="col-md-4">
                            <x-adminlte-select name="update_status" id="update_status" label="Status" required>
                                <option value="active" @selected(old('update_status') === 'active')>Active</option>
                                <option value="draft" @selected(old('update_status') === 'draft')>Draft</option>
                                <option value="paused" @selected(old('update_status') === 'paused')>Paused</option>
                                <option value="archived" @selected(old('update_status') === 'archived')>Archived</option>
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-4">
                            <div class="custom-control custom-switch mt-4 pt-2">
                                <input type="hidden" name="update_is_benefit_collection" value="0">
                                <input type="checkbox" class="custom-control-input" id="update_is_benefit_collection" name="update_is_benefit_collection" value="1" @checked(old('update_is_benefit_collection') === '1')>
                                <label class="custom-control-label" for="update_is_benefit_collection">Benefit collection</label>
                            </div>
                        </div>
                        <div class="col-md-8" id="update_beneficiary_wrap">
                            <x-adminlte-select name="update_beneficiary_member_id" id="update_beneficiary_member_id" label="Beneficiary Member">
                                <option value="">Select beneficiary member</option>
                                @foreach($members as $member)
                                    <option value="{{ $member->id }}" @selected((string)old('update_beneficiary_member_id') === (string)$member->id)>
                                        {{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}
                                    </option>
                                @endforeach
                            </x-adminlte-select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="disburseBenefitModal" tabindex="-1" role="dialog" aria-labelledby="disburseBenefitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('collections.disburse-benefit') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="disburseBenefitModalLabel">Disburse Benefit to Member</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if($benefitCollectionOptions->isEmpty())
                        <div class="alert alert-warning mb-0">
                            No active beneficiary collections found. Create a benefit collection first.
                        </div>
                    @else
                        <div class="row">
                            <div class="col-md-8">
                                <x-adminlte-select name="benefit_collection_item_id" label="Benefit Collection" required>
                                    <option value="">Select collection</option>
                                    @foreach($benefitCollectionOptions as $item)
                                        <option value="{{ $item->id }}" @selected((int)old('benefit_collection_item_id') === (int)$item->id)>
                                            {{ $item->name }} - {{ $item->beneficiary?->member_code }} {{ $item->beneficiary?->first_name }} {{ $item->beneficiary?->last_name }}
                                        </option>
                                    @endforeach
                                </x-adminlte-select>
                            </div>
                            <div class="col-md-4">
                                <x-adminlte-input name="disbursed_amount" label="Amount (GHS)" type="number" step="0.01" min="0.01" value="{{ old('disbursed_amount') }}" required />
                            </div>
                            <div class="col-md-6">
                                <x-adminlte-input name="disbursed_date" label="Disbursement Date" type="date" value="{{ old('disbursed_date', now()->toDateString()) }}" required />
                            </div>
                            <div class="col-md-6">
                                <x-adminlte-input name="notes" label="Notes" value="{{ old('notes') }}" />
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" @disabled($benefitCollectionOptions->isEmpty())>
                        <i class="fas fa-hand-holding-usd mr-1"></i> Post Disbursement
                    </button>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
<script>
(() => {
    const assignMode = document.getElementById('assign_mode');
    const memberWrap = document.getElementById('member_select_wrap');
    const autoAssignMode = document.getElementById('auto_assign_mode');
    const autoMemberWrap = document.getElementById('auto_member_wrap');
    const categorySelect = document.getElementById('collection_category_id');
    const frequency = document.getElementById('frequency');
    const dueDayWrap = document.getElementById('due_day_wrap');
    const amountInput = document.getElementById('collection_amount');
    const categoryDefaultsText = document.getElementById('category_defaults_text');
    const isBenefitCollection = document.getElementById('is_benefit_collection');
    const beneficiaryWrap = document.getElementById('beneficiary_wrap');
    const tableSearch = document.getElementById('collectionTableSearch');
    const table = document.getElementById('collectionTable');
    const editButtons = document.querySelectorAll('.js-edit-collection');
    const editModal = document.getElementById('editCollectionModal');
    const editForm = document.getElementById('editCollectionForm');
    const editHistoryNotice = document.getElementById('edit_collection_history_notice');
    const updateBenefitToggle = document.getElementById('update_is_benefit_collection');
    const updateBeneficiaryWrap = document.getElementById('update_beneficiary_wrap');

    const syncAssignMode = () => {
        const showMembers = assignMode && assignMode.value === 'selected';
        if (memberWrap) {
            memberWrap.style.display = showMembers ? 'block' : 'none';
        }
    };

    const syncAutoAssignMode = () => {
        const showMembers = autoAssignMode && autoAssignMode.value === 'selected';
        if (autoMemberWrap) {
            autoMemberWrap.style.display = showMembers ? 'block' : 'none';
        }
    };

    const syncCategoryRules = () => {
        if (!categorySelect || !frequency) return;
        const option = categorySelect.options[categorySelect.selectedIndex];
        if (!option || !option.value) {
            if (categoryDefaultsText) {
                categoryDefaultsText.textContent = 'Select a category to preview behavior.';
            }
            return;
        }

        const paymentMode = option.getAttribute('data-payment-mode') || 'compulsory';
        const defaultChargeType = option.getAttribute('data-charge-type') || 'one_time';
        const defaultIsRequired = option.getAttribute('data-required') === '1' ? 'Required' : 'Optional';
        const defaultPartial = option.getAttribute('data-partial') === '1' ? 'Partial allowed' : 'Full payment only';

        if (defaultChargeType === 'one_time') {
            frequency.value = 'one_time';
        }

        if (dueDayWrap) {
            dueDayWrap.style.display = defaultChargeType === 'one_time' ? 'none' : 'block';
        }

        if (paymentMode === 'voluntary') {
            if (amountInput) amountInput.required = false;
        } else {
            if (amountInput) amountInput.required = true;
        }

        if (categoryDefaultsText) {
            categoryDefaultsText.textContent =
                `Mode: ${paymentMode}. Charge: ${defaultChargeType.replace('_', ' ')}. ${defaultIsRequired}. ${defaultPartial}.`;
        }
    };

    const syncBenefitState = () => {
        if (!isBenefitCollection || !beneficiaryWrap) return;
        beneficiaryWrap.style.display = isBenefitCollection.checked ? 'block' : 'none';
    };

    const syncUpdateBenefitState = () => {
        if (!updateBenefitToggle || !updateBeneficiaryWrap) return;
        updateBeneficiaryWrap.style.display = updateBenefitToggle.checked ? 'block' : 'none';
    };

    const setFieldValue = (id, value) => {
        const field = document.getElementById(id);
        if (!field) return;
        field.value = value ?? '';
    };

    const setSelectValue = (id, value) => {
        const field = document.getElementById(id);
        if (!field) return;
        const stringValue = value == null ? '' : String(value);
        field.value = stringValue;
    };

    const applyEditPayload = (payload) => {
        if (!editModal || !editForm) return;
        const id = String(payload.id ?? '').trim();
        const actionTemplate = editModal.getAttribute('data-action-template') || '';
        if (id !== '' && actionTemplate.includes('__ID__')) {
            editForm.setAttribute('action', actionTemplate.replace('__ID__', id));
        }

        setFieldValue('update_collection_item_id', id);
        setFieldValue('update_name', payload.name ?? '');
        setFieldValue('update_description', payload.description ?? '');
        setFieldValue('update_amount', payload.amount ?? '');
        setSelectValue('update_collection_category_id', payload.categoryId ?? '');
        setSelectValue('update_frequency', payload.frequency ?? '');
        setFieldValue('update_start_date', payload.startDate ?? '');
        setFieldValue('update_end_date', payload.endDate ?? '');
        setFieldValue('update_due_day_of_month', payload.dueDay ?? '');
        setSelectValue('update_status', payload.status ?? 'active');
        if (updateBenefitToggle) {
            updateBenefitToggle.checked = String(payload.isBenefit ?? '0') === '1';
        }
        setSelectValue('update_beneficiary_member_id', payload.beneficiaryId ?? '');
        syncUpdateBenefitState();

        const hasHistory = String(payload.hasHistory ?? '0') === '1';
        if (editHistoryNotice) {
            editHistoryNotice.classList.toggle('d-none', !hasHistory);
        }
    };

    const setupEditButtons = () => {
        if (!editButtons.length) return;
        editButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                applyEditPayload({
                    id: btn.getAttribute('data-id') || '',
                    name: btn.getAttribute('data-name') || '',
                    description: btn.getAttribute('data-description') || '',
                    amount: btn.getAttribute('data-amount') || '',
                    categoryId: btn.getAttribute('data-category-id') || '',
                    frequency: btn.getAttribute('data-frequency') || '',
                    startDate: btn.getAttribute('data-start-date') || '',
                    endDate: btn.getAttribute('data-end-date') || '',
                    dueDay: btn.getAttribute('data-due-day') || '',
                    status: btn.getAttribute('data-status') || 'active',
                    isBenefit: btn.getAttribute('data-is-benefit') || '0',
                    beneficiaryId: btn.getAttribute('data-beneficiary-id') || '',
                    hasHistory: btn.getAttribute('data-has-history') || '0',
                });
            });
        });
    };

    const setupSearch = () => {
        if (!tableSearch || !table) return;
        tableSearch.addEventListener('input', function () {
            const value = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((row) => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    };

    if (assignMode) assignMode.addEventListener('change', syncAssignMode);
    if (autoAssignMode) autoAssignMode.addEventListener('change', syncAutoAssignMode);
    if (categorySelect) categorySelect.addEventListener('change', syncCategoryRules);
    if (isBenefitCollection) isBenefitCollection.addEventListener('change', syncBenefitState);
    if (updateBenefitToggle) updateBenefitToggle.addEventListener('change', syncUpdateBenefitState);

    syncAssignMode();
    syncAutoAssignMode();
    syncCategoryRules();
    syncBenefitState();
    syncUpdateBenefitState();
    setupSearch();
    setupEditButtons();

    const hasCollectionCreateErrors = @json(
        $errors->has('name') || $errors->has('amount') || $errors->has('collection_category_id') ||
        $errors->has('frequency') || $errors->has('start_date') || $errors->has('end_date') ||
        $errors->has('due_day_of_month') || $errors->has('is_benefit_collection') ||
        $errors->has('beneficiary_member_id') || $errors->has('auto_assign_mode') || $errors->has('member_ids') ||
        $errors->has('member_ids.*')
    );
    const hasAssignErrors = @json($errors->has('collection_item_id') || $errors->has('assign_mode'));
    const hasDisburseErrors = @json($errors->has('benefit_collection_item_id') || $errors->has('disbursed_amount') || $errors->has('disbursed_date') || $errors->has('notes'));
    const hasCollectionUpdateErrors = @json(
        $errors->has('update_collection_item_id') || $errors->has('update_name') || $errors->has('update_description') ||
        $errors->has('update_collection_category_id') || $errors->has('update_amount') || $errors->has('update_frequency') ||
        $errors->has('update_start_date') || $errors->has('update_end_date') || $errors->has('update_due_day_of_month') ||
        $errors->has('update_status') || $errors->has('update_is_benefit_collection') || $errors->has('update_beneficiary_member_id') ||
        $errors->has('update_locked')
    );

    if (hasCollectionCreateErrors) {
        $('#createCollectionModal').modal('show');
    } else if (hasCollectionUpdateErrors) {
        const oldUpdateId = @json((string)old('update_collection_item_id', ''));
        const matchedEditBtn = oldUpdateId
            ? document.querySelector(`.js-edit-collection[data-id="${oldUpdateId}"]`)
            : null;
        applyEditPayload({
            id: oldUpdateId,
            name: @json((string)old('update_name', '')),
            description: @json((string)old('update_description', '')),
            amount: @json((string)old('update_amount', '')),
            categoryId: @json((string)old('update_collection_category_id', '')),
            frequency: @json((string)old('update_frequency', 'monthly')),
            startDate: @json((string)old('update_start_date', '')),
            endDate: @json((string)old('update_end_date', '')),
            dueDay: @json((string)old('update_due_day_of_month', '')),
            status: @json((string)old('update_status', 'active')),
            isBenefit: @json((string)old('update_is_benefit_collection', '0')),
            beneficiaryId: @json((string)old('update_beneficiary_member_id', '')),
            hasHistory: matchedEditBtn ? (matchedEditBtn.getAttribute('data-has-history') || '0') : '0',
        });
        $('#editCollectionModal').modal('show');
    } else if (hasAssignErrors) {
        $('#assignCollectionModal').modal('show');
    } else if (hasDisburseErrors) {
        $('#disburseBenefitModal').modal('show');
    }
})();
</script>
@stop
