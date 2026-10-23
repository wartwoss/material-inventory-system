<?php

use App\Http\Controllers\Api\V1\MaterialController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('materials', MaterialController::class)->except(['destroy']);
    Route::post('materials/{material}/adjust', [MaterialController::class, 'adjust'])->name('materials.adjust');
    Route::get('materials/{material}/movements', [MaterialController::class, 'movements'])->name('materials.movements');
});
