<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionCategoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberPortalController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Use the URL generator so subdirectory installs (e.g. /gpspayments/public)
    // redirect correctly instead of jumping to domain root.
    return redirect()->to(url('/dashboard'));
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/member-portal', [MemberPortalController::class, 'index'])->name('member-portal.index');
    Route::get('/member-portal/profile', [MemberPortalController::class, 'profile'])->name('member-portal.profile');
    Route::post('/member-portal/password', [MemberPortalController::class, 'updatePassword'])->name('member-portal.password.update');
    Route::post('/member-portal/voluntary/{collectionItem}/skip', [MemberPortalController::class, 'skipVoluntaryContribution'])->name('member-portal.voluntary.skip');
    Route::post('/member-portal/voluntary/{collectionItem}/unskip', [MemberPortalController::class, 'unskipVoluntaryContribution'])->name('member-portal.voluntary.unskip');
    Route::get('/member-portal/statement', [MemberPortalController::class, 'statement'])->name('member-portal.statement');
    Route::get('/member-portal/statement/print', [MemberPortalController::class, 'statementPrint'])->name('member-portal.statement.print');
    Route::get('/member-portal/statement/export', [MemberPortalController::class, 'statementExport'])->name('member-portal.statement.export');

    Route::middleware('role:Administrator,Treasurer,Secretary,Auditor')->group(function (): void {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/export', [MemberController::class, 'export'])->name('members.export');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::get('/members/{member}/statement/export', [MemberController::class, 'statementExport'])->name('members.statement.export');
        Route::get('/members/{member}/statement/print', [MemberController::class, 'statementPrint'])->name('members.statement.print');
    });
    Route::middleware('role:Administrator,Secretary')->group(function (): void {
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::post('/members/{member}/status', [MemberController::class, 'updateStatus'])->name('members.status');
        Route::post('/members/bulk-status', [MemberController::class, 'bulkStatus'])->name('members.bulk-status');
        Route::post('/members/import', [MemberController::class, 'import'])->name('members.import');
    });

    Route::middleware('role:Administrator,Treasurer,Secretary,Auditor')->group(function (): void {
        Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
        Route::get('/collections/member-statement/{member}', [CollectionController::class, 'memberStatement'])
            ->name('collections.member-statement');
        Route::get('/collection-categories', [CollectionCategoryController::class, 'index'])->name('collection-categories.index');
    });
    Route::middleware('role:Administrator,Treasurer,Secretary')->group(function (): void {
        Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
        Route::post('/collections/{collectionItem}/update', [CollectionController::class, 'update'])->name('collections.update');
        Route::post('/collections/assign', [CollectionController::class, 'assign'])->name('collections.assign');
        Route::post('/collections/disburse-benefit', [CollectionController::class, 'disburseBenefit'])->name('collections.disburse-benefit');
        Route::post('/collection-categories', [CollectionCategoryController::class, 'store'])->name('collection-categories.store');
        Route::post('/collection-categories/{collectionCategory}', [CollectionCategoryController::class, 'update'])->name('collection-categories.update');
        Route::post('/collection-categories/{collectionCategory}/delete', [CollectionCategoryController::class, 'destroy'])->name('collection-categories.destroy');
    });

    Route::middleware('role:Administrator,Treasurer,Auditor')->group(function (): void {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/amount-suggestion', [PaymentController::class, 'amountSuggestion'])->name('payments.amount-suggestion');
    });
    Route::middleware('role:Administrator,Treasurer')->group(function (): void {
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    });

    Route::middleware('role:Administrator,Treasurer,Auditor')->group(function (): void {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::post('/reports/views', [ReportController::class, 'saveView'])->name('reports.views.save');
    });

    Route::middleware('role:Administrator,Treasurer')->group(function (): void {
        Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::post('/reconciliation/batches/open', [ReconciliationController::class, 'openBatch'])->name('reconciliation.open');
        Route::post('/reconciliation/batches/item', [ReconciliationController::class, 'addItem'])->name('reconciliation.add-item');
        Route::post('/reconciliation/batches/close', [ReconciliationController::class, 'closeBatch'])->name('reconciliation.close');
    });

    Route::middleware('role:Administrator,Auditor')->group(function (): void {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });

    Route::middleware('role:Administrator')->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::post('/members/{member}/user', [UserManagementController::class, 'createFromMember'])->name('members.create-user');
        Route::post('/users/{user}/member', [UserManagementController::class, 'createMemberFromUser'])->name('users.create-member');
        Route::post('/users/{user}/link-member', [UserManagementController::class, 'linkMember'])->name('users.link-member');
    });
});
