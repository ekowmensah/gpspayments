@extends('adminlte::page')

@section('title', 'Collection Categories')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Collection Categories</h1>
            <p class="text-muted mb-0">Define whether categories are compulsory or voluntary, then collections inherit the flow.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('collections.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Back to Collections
            </a>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createCategoryModal">
                <i class="fas fa-plus mr-1"></i> New Category
            </button>
        </div>
    </div>
@stop

@section('css')
<style>
    .cat-card { border:1px solid #e4ebf3; border-radius:.9rem; box-shadow:0 10px 24px rgba(11,31,51,.05); }
    .cat-mode { border-radius:999px; padding:.18rem .58rem; font-size:.72rem; font-weight:700; border:1px solid transparent; }
    .cat-mode.compulsory { background:#eaf3ff; color:#1f4b87; border-color:#d5e4f8; }
    .cat-mode.voluntary { background:#e7f7ec; color:#1c6f3a; border-color:#c9ebd5; }
</style>
@stop

@section('content')
    @if(session('success'))
        <x-adminlte-alert theme="success" title="Success">{{ session('success') }}</x-adminlte-alert>
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

    <div class="card cat-card">
        <div class="card-header">
            <h3 class="card-title mb-0">Category Registry</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Mode</th>
                    <th>Default Charge</th>
                    <th>Defaults</th>
                    <th class="text-right">Used By</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>
                            <div class="font-weight-bold">{{ $category->name }}</div>
                            <div class="small text-muted">{{ $category->description ?: '-' }}</div>
                        </td>
                        <td><code>{{ $category->code }}</code></td>
                        <td>
                            <span class="cat-mode {{ $category->payment_mode }}">{{ ucfirst($category->payment_mode) }}</span>
                            <div class="small text-muted mt-1">{{ ucfirst($category->status) }}</div>
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $category->default_charge_type)) }}</td>
                        <td>
                            <div class="small">Required: <strong>{{ $category->default_is_required ? 'Yes' : 'No' }}</strong></div>
                            <div class="small">Partial: <strong>{{ $category->default_allow_partial_payment ? 'Yes' : 'No' }}</strong></div>
                        </td>
                        <td class="text-right">{{ number_format((int)$category->collection_items_count) }}</td>
                        <td class="text-right">
                            <button
                                type="button"
                                class="btn btn-xs btn-outline-primary"
                                data-toggle="modal"
                                data-target="#editCategoryModal"
                                data-id="{{ $category->id }}"
                                data-name="{{ $category->name }}"
                                data-code="{{ $category->code }}"
                                data-description="{{ $category->description }}"
                                data-payment_mode="{{ $category->payment_mode }}"
                                data-default_charge_type="{{ $category->default_charge_type }}"
                                data-default_is_required="{{ $category->default_is_required ? '1' : '0' }}"
                                data-default_allow_partial_payment="{{ $category->default_allow_partial_payment ? '1' : '0' }}"
                                data-status="{{ $category->status }}"
                            >
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <form method="POST" action="{{ route('collection-categories.destroy', $category) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-outline-danger" onclick="return confirm('Delete this category? If in use, it will be set inactive.');">
                                    <i class="fas fa-trash-alt mr-1"></i>Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted">No categories yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $categories->links() }}</div>
    </div>

    <div class="modal fade" id="createCategoryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('collection-categories.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create Category</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <x-adminlte-input name="name" label="Name" value="{{ old('name') }}" required />
                    <x-adminlte-input name="code" label="Code (optional, auto generated if blank)" value="{{ old('code') }}" />
                    <x-adminlte-textarea name="description" label="Description">{{ old('description') }}</x-adminlte-textarea>
                    <x-adminlte-select name="payment_mode" id="create_payment_mode" label="Payment Mode" required>
                        <option value="compulsory" @selected(old('payment_mode','compulsory') === 'compulsory')>Compulsory</option>
                        <option value="voluntary" @selected(old('payment_mode') === 'voluntary')>Voluntary</option>
                    </x-adminlte-select>
                    <x-adminlte-select name="default_charge_type" id="create_default_charge_type" label="Default Charge Type" required>
                        <option value="recurring" @selected(old('default_charge_type','recurring') === 'recurring')>Recurring</option>
                        <option value="one_time" @selected(old('default_charge_type') === 'one_time')>One-time</option>
                        <option value="voluntary" @selected(old('default_charge_type') === 'voluntary')>Voluntary</option>
                    </x-adminlte-select>
                    <input type="hidden" name="default_is_required" value="0">
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="create_default_is_required" name="default_is_required" value="1" @checked(old('default_is_required', '1') === '1')>
                        <label class="custom-control-label" for="create_default_is_required">Default required</label>
                    </div>
                    <input type="hidden" name="default_allow_partial_payment" value="0">
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="create_default_allow_partial_payment" name="default_allow_partial_payment" value="1" @checked(old('default_allow_partial_payment', '1') === '1')>
                        <label class="custom-control-label" for="create_default_allow_partial_payment">Default allow partial payment</label>
                    </div>
                    <x-adminlte-select name="status" label="Status" required>
                        <option value="active" @selected(old('status','active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </x-adminlte-select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="#" id="editCategoryForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <x-adminlte-input name="name" id="edit_name" label="Name" required />
                    <x-adminlte-input name="code" id="edit_code" label="Code" required />
                    <x-adminlte-textarea name="description" id="edit_description" label="Description"></x-adminlte-textarea>
                    <x-adminlte-select name="payment_mode" id="edit_payment_mode" label="Payment Mode" required>
                        <option value="compulsory">Compulsory</option>
                        <option value="voluntary">Voluntary</option>
                    </x-adminlte-select>
                    <x-adminlte-select name="default_charge_type" id="edit_default_charge_type" label="Default Charge Type" required>
                        <option value="recurring">Recurring</option>
                        <option value="one_time">One-time</option>
                        <option value="voluntary">Voluntary</option>
                    </x-adminlte-select>
                    <input type="hidden" name="default_is_required" value="0">
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="edit_default_is_required" name="default_is_required" value="1">
                        <label class="custom-control-label" for="edit_default_is_required">Default required</label>
                    </div>
                    <input type="hidden" name="default_allow_partial_payment" value="0">
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="edit_default_allow_partial_payment" name="default_allow_partial_payment" value="1">
                        <label class="custom-control-label" for="edit_default_allow_partial_payment">Default allow partial payment</label>
                    </div>
                    <x-adminlte-select name="status" id="edit_status" label="Status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </x-adminlte-select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
<script>
(() => {
    const setModeRules = (modeSelect, chargeTypeSelect, requiredSwitch) => {
        if (!modeSelect || !chargeTypeSelect || !requiredSwitch) return;
        const mode = modeSelect.value;
        if (mode === 'voluntary') {
            chargeTypeSelect.value = 'voluntary';
            chargeTypeSelect.setAttribute('disabled', 'disabled');
            requiredSwitch.checked = false;
            requiredSwitch.setAttribute('disabled', 'disabled');
        } else {
            chargeTypeSelect.removeAttribute('disabled');
            requiredSwitch.removeAttribute('disabled');
            if (chargeTypeSelect.value === 'voluntary') {
                chargeTypeSelect.value = 'one_time';
            }
        }
    };

    const createMode = document.getElementById('create_payment_mode');
    const createCharge = document.getElementById('create_default_charge_type');
    const createRequired = document.getElementById('create_default_is_required');

    if (createMode) {
        createMode.addEventListener('change', () => setModeRules(createMode, createCharge, createRequired));
        setModeRules(createMode, createCharge, createRequired);
    }

    $('#editCategoryModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const form = document.getElementById('editCategoryForm');
        const id = button.data('id');
        if (form && id) {
            form.action = '{{ url('/collection-categories') }}/' + id;
        }

        $('#edit_name').val(button.data('name') || '');
        $('#edit_code').val(button.data('code') || '');
        $('#edit_description').val(button.data('description') || '');
        $('#edit_payment_mode').val(button.data('payment_mode') || 'compulsory');
        $('#edit_default_charge_type').val(button.data('default_charge_type') || 'one_time');
        $('#edit_default_is_required').prop('checked', String(button.data('default_is_required')) === '1');
        $('#edit_default_allow_partial_payment').prop('checked', String(button.data('default_allow_partial_payment')) === '1');
        $('#edit_status').val(button.data('status') || 'active');

        const editMode = document.getElementById('edit_payment_mode');
        const editCharge = document.getElementById('edit_default_charge_type');
        const editRequired = document.getElementById('edit_default_is_required');
        setModeRules(editMode, editCharge, editRequired);
    });

    const editMode = document.getElementById('edit_payment_mode');
    if (editMode) {
        editMode.addEventListener('change', function () {
            const editCharge = document.getElementById('edit_default_charge_type');
            const editRequired = document.getElementById('edit_default_is_required');
            setModeRules(editMode, editCharge, editRequired);
        });
    }

    const hasErrors = @json($errors->any());
    if (hasErrors) {
        $('#createCategoryModal').modal('show');
    }
})();
</script>
@stop
