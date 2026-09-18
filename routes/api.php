<?php

use App\Http\Controllers\Api\PosAuthController;
use App\Http\Controllers\Api\PosBroadcastAuthController;
use App\Http\Controllers\Api\PosCommandController;
use App\Http\Controllers\Api\PosPrintJobController;
use App\Http\Controllers\Api\PosPrinterController;
use App\Http\Controllers\Api\PosReadController;
use App\Http\Controllers\Api\PrintJobDeliveryController;
use Illuminate\Support\Facades\Route;

Route::post('login', [PosAuthController::class, 'login'])
    ->middleware('throttle:pos-login')
    ->name('api.pos.login');

Route::middleware(['auth:sanctum', 'pos.device', 'throttle:pos-api'])->prefix('pos')->group(function (): void {
    Route::post('logout', [PosAuthController::class, 'logout'])->name('api.pos.logout');
    Route::post('broadcasting/auth', [PosBroadcastAuthController::class, 'authenticate'])->name('api.pos.broadcasting.auth');
    Route::get('bootstrap', [PosReadController::class, 'bootstrap'])->name('api.pos.bootstrap');
    Route::get('tables', [PosReadController::class, 'tables'])->name('api.pos.tables');
    Route::get('tables/{table}', [PosReadController::class, 'table'])->whereNumber('table')->name('api.pos.tables.show');
    Route::get('orders', [PosReadController::class, 'orders'])->name('api.pos.orders.index');
    Route::get('orders/{order}', [PosReadController::class, 'order'])->whereNumber('order')->name('api.pos.orders.show');
    Route::post('commands', [PosCommandController::class, 'store'])->name('api.pos.commands.store');
    Route::post('sync', [PosCommandController::class, 'sync'])->name('api.pos.sync');
    Route::post('reconcile', [PosCommandController::class, 'reconcile'])->name('api.pos.reconcile');
    Route::post('printers/{printer}/print-jobs/claim', [PosPrintJobController::class, 'claim'])->whereNumber('printer')->name('api.pos.print-jobs.claim');
     Route::post('printers', [PosPrinterController::class, 'store'])->name('api.pos.printers.store');
    Route::patch('printers/{printer}', [PosPrinterController::class, 'update'])->whereNumber('printer')->name('api.pos.printers.update');
    Route::post('printers/{printer}/test-print', [PosPrinterController::class, 'testPrint'])->whereNumber('printer')->name('api.pos.printers.test-print');
    Route::patch('print-jobs/{printJob}/result', [PosPrintJobController::class, 'updateResult'])->whereNumber('printJob')->name('api.pos.print-jobs.result');
    Route::post('print-jobs/{printJob}/retry', [PosPrintJobController::class, 'retry'])->whereNumber('printJob')->name('api.pos.print-jobs.retry');
});

/*
| API của agent in dùng token riêng theo Printer và không chia sẻ session Filament.
| Rate limit hạn chế dò token; action domain tiếp tục bảo vệ transition và concurrency.
*/
Route::middleware('throttle:print-agent')->prefix('printers/{printer}/print-jobs')->scopeBindings()->group(function (): void {
    Route::post('claim', [PrintJobDeliveryController::class, 'claim'])->name('api.print-jobs.claim');
    Route::post('{printJob}/printing', [PrintJobDeliveryController::class, 'printing'])->name('api.print-jobs.printing');
    Route::post('{printJob}/printed', [PrintJobDeliveryController::class, 'printed'])->name('api.print-jobs.printed');
    Route::post('{printJob}/failed', [PrintJobDeliveryController::class, 'failed'])->name('api.print-jobs.failed');
});
