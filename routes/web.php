<?php

use App\Http\Controllers\Admin\AcademicConfigController;
use App\Http\Controllers\Admin\CisSyncController;
use App\Http\Controllers\Admin\RefCategoryController;
use App\Http\Controllers\Admin\RefCategoryDetailController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\AmiIndicatorController;
use App\Http\Controllers\Admin\AmiStandardController;
use App\Http\Controllers\Admin\IndicatorPicController;
use App\Http\Controllers\Auditee\DashboardController;
use App\Http\Controllers\Auditee\EvaluasiDiriController;
use App\Http\Controllers\UnifiedAuthController;
use Illuminate\Support\Facades\Route;

// Landing (public)
Route::get('/', fn() => view('landing.home'));

// ==== Auth (public) ====
Route::get('/login', [UnifiedAuthController::class, 'show'])->name('login');
Route::post('/login', [UnifiedAuthController::class, 'login'])->name('login.do');
Route::post('/logout', [UnifiedAuthController::class, 'logout'])->name('logout');

Route::prefix('auditee')->name('auditee.')->middleware(['auth', 'role:Ketua Program Studi|Dekan|Ketua PPKHA'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Halaman khusus FED (index menampilkan header + detail)
    Route::get('/fed', [EvaluasiDiriController::class, 'index'])->name('fed.index');
        Route::post('/fed', [EvaluasiDiriController::class, 'store'])->name('fed.store'); // buat header+detail
        Route::put('/fed/{form}', [EvaluasiDiriController::class, 'updateHeader'])->name('fed.updateHeader');
        Route::put('/fed/{form}/detail/{detail}', [EvaluasiDiriController::class, 'updateDetail'])->name('fed.updateDetail');
        Route::post('/fed/{form}/submit', [EvaluasiDiriController::class, 'submit'])->name('fed.submit');
        Route::get('/fed/{form}/export', [EvaluasiDiriController::class, 'exportDoc'])->name('fed.export');
});

// ==== AREA ADMIN ====
// Semua route admin WAJIB lewat 'auth' + 'admin.byname'
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin.byname'])->group(function () {

    Route::post('cis-sync', [CisSyncController::class, 'run'])->name('cis.sync');

    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // RefCategory
    Route::get('ref_category', [RefCategoryController::class, 'index'])->name('ref_category.index');
    Route::post('ref_category', [RefCategoryController::class, 'store'])->name('ref_category.store');
    Route::put('ref_category/{category}', [RefCategoryController::class, 'update'])->name('ref_category.update');
    Route::delete('ref_category/{category}', [RefCategoryController::class, 'destroy'])->name('ref_category.destroy');

    // RefCategory Detail
    Route::get('ref_category/detail', [RefCategoryDetailController::class, 'index'])->name('ref_category.detail');
    Route::post('ref_category/detail', [RefCategoryDetailController::class, 'store'])->name('ref_category.detail.store');
    Route::put('ref_category/detail/{categoryDetail}', [RefCategoryDetailController::class, 'update'])->name('ref_category.detail.update');
    Route::delete('ref_category/detail/{categoryDetail}', [RefCategoryDetailController::class, 'destroy'])->name('ref_category.detail.destroy');

    // Academic Config
    Route::get('academic_config', [AcademicConfigController::class, 'index'])->name('academic_config.index');
    Route::post('academic_config', [AcademicConfigController::class, 'store'])->name('academic_config.store');
    Route::put('academic_config/{academicConfig}', [AcademicConfigController::class, 'update'])->name('academic_config.update');
    Route::delete('academic_config/{academicConfig}', [AcademicConfigController::class, 'destroy'])->name('academic_config.destroy');
    Route::post('academic_config/{academicConfig}/set-active', [AcademicConfigController::class, 'setActive'])->name('academic_config.set_active');

    // Roles (contoh ringkas)
    Route::get('roles', [UserRoleController::class, 'index'])->name('roles.index');
    Route::post('roles/assign-role', [UserRoleController::class, 'assign'])->name('users.assign-role');
    Route::get('roles/add', [RolesController::class, 'index'])->name('roles.add');
    Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
    Route::put('roles/{role}', [RolesController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');

    Route::get('ami/standard', [AmiStandardController::class, 'index'])->name('ami.standard');
    Route::post('ami/standard', [AmiStandardController::class, 'store'])->name('ami.standard.store');
    Route::put('ami/standard/{amiStandard}', [AmiStandardController::class, 'update'])->name('ami.standard.update');
    Route::delete('ami/standard/{amiStandard}', [AmiStandardController::class, 'destroy'])->name('ami.standard.destroy');

    Route::get('ami/indicator', [AmiIndicatorController::class, 'index'])->name('ami.indicator');
    Route::post('ami/indicator', [AmiIndicatorController::class, 'store'])->name('ami.indicator.store');
    Route::put('ami/indicator/{amiIndicator}', [AmiIndicatorController::class, 'update'])->name('ami.indicator.update');
    Route::delete('ami/indicator/{amiIndicator}', [AmiIndicatorController::class, 'destroy'])->name('ami.indicator.destroy');

    Route::post('ami/pic/{indicator}', [IndicatorPicController::class, 'store'])->name('ami.pic.store');
    Route::put('ami/pic/{indicator}', [IndicatorPicController::class, 'update'])->name('ami.pic.update');
    Route::delete('ami/pic/{indicator}', [IndicatorPicController::class, 'destroy'])->name('ami.pic.destroy');
});
