<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UnifiedAuthController;

// ====== AUDITEE ======
use App\Http\Controllers\Auditee\DashboardController as AuditeeDashboardController;
use App\Http\Controllers\Auditee\EvaluasiDiriController;
use App\Http\Controllers\Auditee\AuditFindingAuditeeController;
use App\Http\Controllers\Auditee\AuditFollowUpAuditeeController; // <<< buat controller ini (index/show/updateRow)

// ====== AUDITOR ======
use App\Http\Controllers\Auditor\DashboardController as AuditorDashboardController;
use App\Http\Controllers\Auditor\FedReviewController;
use App\Http\Controllers\Auditor\AuditChecklistController;
use App\Http\Controllers\Auditor\AuditFindingHeaderController;
use App\Http\Controllers\Auditor\AuditFindingExportController;
use App\Http\Controllers\Auditor\AuditFollowUpHeaderController;
use App\Http\Controllers\Auditor\AuditFollowUpDetailController;
use App\Http\Controllers\Auditor\AuditFollowUpExportController;

// ====== SHARED ======
use App\Http\Controllers\AuditFindingRowController;

// ====== ADMIN ======
use App\Http\Controllers\Admin\AcademicConfigController;
use App\Http\Controllers\Admin\AuditChecklistRecapController;
use App\Http\Controllers\Admin\CisSyncController;
use App\Http\Controllers\Admin\FedRecapController;
use App\Http\Controllers\Admin\RefCategoryController;
use App\Http\Controllers\Admin\RefCategoryDetailController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\AmiIndicatorController;
use App\Http\Controllers\Admin\AmiStandardController;
use App\Http\Controllers\Admin\IndicatorPicController;

// Landing (public)
Route::get('/', fn () => view('landing.home'));

// ==== Auth (public) ====
Route::get('/login', [UnifiedAuthController::class, 'show'])->name('login');
Route::post('/login', [UnifiedAuthController::class, 'login'])->name('login.do');
Route::post('/logout', [UnifiedAuthController::class, 'logout'])->name('logout');

// Debug (remove in prod)
Route::get('/debug/pdf-export', [\App\Http\Controllers\DebugPdfController::class, 'check'])->name('debug.pdf');

/*
|--------------------------------------------------------------------------
| AUDITEE
|--------------------------------------------------------------------------
| Auditee: isi FED, lihat Temuan, isi ATL (Realisasi + Efektivitas)
*/
Route::prefix('auditee')
    ->name('auditee.')
    ->middleware(['auth', 'role:Ketua Program Studi|Dekan|Ketua PPKHA|SPM'])
    ->group(function () {

        Route::get('/dashboard', [AuditeeDashboardController::class, 'index'])->name('dashboard');

        // FED
        Route::get('/fed', [EvaluasiDiriController::class, 'index'])->name('fed.index');
        Route::post('/fed', [EvaluasiDiriController::class, 'store'])->name('fed.store');
        Route::put('/fed/{form}', [EvaluasiDiriController::class, 'updateHeader'])->name('fed.updateHeader');
        Route::put('/fed/{form}/detail/{detail}', [EvaluasiDiriController::class, 'updateDetail'])->name('fed.updateDetail');
        Route::post('/fed/{form}/submit', [EvaluasiDiriController::class, 'submit'])->name('fed.submit');
        Route::get('/user/search', [EvaluasiDiriController::class, 'searchUsers'])->name('fed.searchUsers');
        Route::get('/fed/{form}/export', [EvaluasiDiriController::class, 'exportDoc'])->name('fed.export');
        Route::get('/fed/{form}/export-pdf', [EvaluasiDiriController::class, 'exportPdf'])->name('fed.exportPdf');

        // TEMUAN (auditee view)
        Route::get('/temuan', [AuditFindingAuditeeController::class, 'index'])->name('temuan.index');
        Route::get('/temuan/{fed}', [AuditFindingAuditeeController::class, 'show'])->name('temuan.show');
        Route::put('/temuan/{form}/row/{row}/auditee', [AuditFindingRowController::class, 'updateByAuditee'])
            ->name('temuan.row.update.auditee');
        Route::get('/temuan/{form}/export-pdf', [AuditFindingExportController::class, 'exportPdf'])
            ->name('temuan.exportPdf');

        // ATL (auditee view + isi realisasi & efektivitas)
        Route::get('/atl', [AuditFollowUpAuditeeController::class, 'index'])->name('atl.index');
        Route::get('/atl/{atl}', [AuditFollowUpAuditeeController::class, 'show'])->name('atl.show');
        Route::put('/atl/{atl}/detail/{detail}', [AuditFollowUpAuditeeController::class, 'updateRow'])
            ->name('atl.row.update');
    });

/*
|--------------------------------------------------------------------------
| AUDITOR
|--------------------------------------------------------------------------
| Auditor: buat/lihat ATL dari Temuan Final, isi status+desc, finalize, export
*/
Route::prefix('auditor')
    ->name('auditor.')
    ->middleware(['auth', 'role:Ketua Auditor|Anggota Auditor'])
    ->group(function () {

        Route::get('/dashboard', [AuditorDashboardController::class, 'index'])->name('dashboard');

        Route::get('/rekap', [\App\Http\Controllers\Auditor\FedRecapController::class, 'index'])->name('fed.rekap.index');

        // FED review
        Route::get('/fed', [FedReviewController::class, 'index'])->name('fed.index');
        Route::get('/fed/{form}', [FedReviewController::class, 'show'])->name('fed.show');
        Route::post('/fed/{form}/details/{detail}/approve', [FedReviewController::class, 'approveDetail'])->name('fed.details.approve');
        Route::post('/fed/{form}/details/{detail}/reject', [FedReviewController::class, 'rejectDetail'])->name('fed.details.reject');
        Route::put('/fed/{form}/details/{detail}', [FedReviewController::class, 'updateDetail'])->name('fed.details.update');

        // Checklist
        Route::post('/details/{detail}/checklists', [AuditChecklistController::class, 'store'])->name('checklists.store');
        Route::delete('/checklists/{checklist}', [AuditChecklistController::class, 'destroy'])->name('checklists.destroy');
        Route::post('/fed/details/{detailId}/checklists/bulk', [AuditChecklistController::class, 'bulkUpsert'])->name('checklists.bulkUpsert');
        Route::get('/fed/{form}/export-pdf', [FedReviewController::class, 'exportPdf'])->name('fed.exportPdf');

        // TEMUAN (auditor)
        Route::get('/finding', [AuditFindingHeaderController::class, 'index'])->name('temuan.index');
        Route::get('/temuan/search-users', [AuditFindingHeaderController::class, 'searchAuditors'])->name('temuan.searchAuditors');
        Route::get('/finding/{fed}', [AuditFindingHeaderController::class, 'show'])->name('temuan.show');
        Route::put('/temuan/{form}/header', [AuditFindingHeaderController::class, 'updateHeader'])->name('temuan.header.update');
        Route::post('/temuan/{form}/finalize', [AuditFindingHeaderController::class, 'finalize'])->name('temuan.finalize');
        Route::put('/temuan/{form}/row/{row}/auditor', [AuditFindingRowController::class, 'updateByAuditor'])
            ->name('temuan.row.update.auditor');
        Route::get('/temuan/{form}/export-pdf', [AuditFindingExportController::class, 'exportPdf'])
            ->name('temuan.exportPdf');

        // ATL (auditor)
        Route::get('/atl', [AuditFollowUpHeaderController::class, 'index'])->name('atl.index');

        Route::get('/atl/search-users', [AuditFollowUpHeaderController::class, 'searchAuditors'])->name('atl.searchAuditors');
        // show menerima ID temuan form (AuditFindingForm) supaya bisa create-if-not-exist
        Route::get('/atl/{findingForm}', [AuditFollowUpHeaderController::class, 'show'])->name('atl.show');

        // yang ini pakai ATL id
        Route::put('/atl/{form}/header', [AuditFollowUpHeaderController::class, 'updateHeader'])->name('atl.header.update');
        Route::post('/atl/{form}/finalize', [AuditFollowUpHeaderController::class, 'finalize'])->name('atl.finalize');
        Route::put('/atl/{form}/detail/{detail}', [AuditFollowUpDetailController::class, 'updateRow'])->name('atl.row.update');
        Route::get('/atl/{form}/export-pdf', [AuditFollowUpExportController::class, 'exportPdf'])->name('atl.exportPdf');
    });

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin.byname'])
    ->group(function () {

        Route::post('/cis-sync', [CisSyncController::class, 'run'])->name('cis.sync');

        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/fed/rekap', [FedRecapController::class, 'index'])->name('fed.rekap.index');

        Route::get('/audit-checklists', [AuditChecklistRecapController::class, 'index'])->name('audit_checklists.index');
        Route::get('/audit-checklists/{form}', [AuditChecklistRecapController::class, 'show'])->name('audit_checklists.show');

        // RefCategory
        Route::get('/ref_category', [RefCategoryController::class, 'index'])->name('ref_category.index');
        Route::post('/ref_category', [RefCategoryController::class, 'store'])->name('ref_category.store');
        Route::put('/ref_category/{category}', [RefCategoryController::class, 'update'])->name('ref_category.update');
        Route::delete('/ref_category/{category}', [RefCategoryController::class, 'destroy'])->name('ref_category.destroy');

        // RefCategory Detail
        Route::get('/ref_category/detail', [RefCategoryDetailController::class, 'index'])->name('ref_category.detail');
        Route::post('/ref_category/detail', [RefCategoryDetailController::class, 'store'])->name('ref_category.detail.store');
        Route::put('/ref_category/detail/{categoryDetail}', [RefCategoryDetailController::class, 'update'])->name('ref_category.detail.update');
        Route::delete('/ref_category/detail/{categoryDetail}', [RefCategoryDetailController::class, 'destroy'])->name('ref_category.detail.destroy');

        // Academic Config
        Route::get('/academic_config', [AcademicConfigController::class, 'index'])->name('academic_config.index');
        Route::post('/academic_config', [AcademicConfigController::class, 'store'])->name('academic_config.store');
        Route::put('/academic_config/{academicConfig}', [AcademicConfigController::class, 'update'])->name('academic_config.update');
        Route::delete('/academic_config/{academicConfig}', [AcademicConfigController::class, 'destroy'])->name('academic_config.destroy');
        Route::post('/academic_config/{academicConfig}/set-active', [AcademicConfigController::class, 'setActive'])->name('academic_config.set_active');

        // Roles / UserRole
        Route::get('/roles', [UserRoleController::class, 'index'])->name('roles.index');
        Route::post('/roles/assign-role', [UserRoleController::class, 'assign'])->name('users.assign-role');
        Route::get('/roles/add', [RolesController::class, 'index'])->name('roles.add');
        Route::post('/roles', [RolesController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');

        // AMI Standard/Indicator/PIC
        Route::get('/ami/standard', [AmiStandardController::class, 'index'])->name('ami.standard');
        Route::post('/ami/standard', [AmiStandardController::class, 'store'])->name('ami.standard.store');
        Route::put('/ami/standard/{amiStandard}', [AmiStandardController::class, 'update'])->name('ami.standard.update');
        Route::delete('/ami/standard/{amiStandard}', [AmiStandardController::class, 'destroy'])->name('ami.standard.destroy');
        Route::post('/ami/standard/submit', [AmiStandardController::class, 'submit'])->name('ami.standard.submit');

        Route::get('/ami/indicator', [AmiIndicatorController::class, 'index'])->name('ami.indicator');
        Route::post('/ami/indicator', [AmiIndicatorController::class, 'store'])->name('ami.indicator.store');
        Route::put('/ami/indicator/{amiIndicator}', [AmiIndicatorController::class, 'update'])->name('ami.indicator.update');
        Route::delete('/ami/indicator/{amiIndicator}', [AmiIndicatorController::class, 'destroy'])->name('ami.indicator.destroy');

        Route::post('/ami/pic/{indicator}', [IndicatorPicController::class, 'store'])->name('ami.pic.store');
        Route::put('/ami/pic/{indicator}', [IndicatorPicController::class, 'update'])->name('ami.pic.update');
        Route::delete('/ami/pic/{indicator}', [IndicatorPicController::class, 'destroy'])->name('ami.pic.destroy');
    });
