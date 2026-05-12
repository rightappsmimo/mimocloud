<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MimoAdminController;
use App\Http\Controllers\FileManagementController;
use App\Http\Controllers\SmsBlastController;
use Illuminate\Support\Facades\Route;

Route::get('/admin-panel', function () {
    return view('pages.admin-panel.welcome');
})->name('admin.panel');

Route::prefix('admin-panel')->middleware('auth')->group(function () {
    Route::get('/dashboard', [MimoAdminController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

    Route::prefix('/files')->group(function () {
        Route::get('/', [FileManagementController::class,'index'])->name('files.index');
        Route::delete('/delete/{file}', [FileManagementController::class,'delete'])->where('file', '.*');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // SMS Blasts Routes
    Route::prefix('/sms-blasts')->name('sms_blast.')->group(function () {
        Route::get('/', [SmsBlastController::class, 'index'])->name('index');
        Route::get('/create', [SmsBlastController::class, 'create'])->name('create');
        Route::post('/', [SmsBlastController::class, 'store'])->name('store');
        Route::get('/{smsBlast}', [SmsBlastController::class, 'show'])->name('show');
        Route::get('/edit/{smsBlast}', [SmsBlastController::class, 'edit'])->name('edit');
        Route::put('/{smsBlast}', [SmsBlastController::class, 'update'])->name('update');
        Route::post('/{smsBlast}/resend', [SmsBlastController::class, 'resendFailed'])->name('resend-failed');
        Route::delete('/{smsBlast}', [SmsBlastController::class, 'destroy'])->name('destroy');
        Route::get('/templates', [SmsBlastController::class, 'templates'])->name('templates');
    });
});

Route::put('/admin/order-item/{selectedId}', [MimoAdminController::class, 'updateQr'])->name('order.updateQr');



