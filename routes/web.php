<?php

use Illuminate\Support\Facades\Route;
use Iyzico\IyzipayLaravel\Controller\CallbackController;

Route::middleware('web')->group(function () {

    Route::any('/iyzipay/threeds/callback', [CallbackController::class, 'threedsCallback'])
        ->name('threeds.callback');

    Route::any('/iyzipay/bkm/callback', [CallbackController::class, 'bkmCallback'])
        ->name('bkm.callback');
});
