<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FurnitureModelController;
use App\Http\Controllers\Api\V1\MaterialController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('furniture-models', [FurnitureModelController::class, 'index'])->name('furniture-models.index');
    Route::get('furniture-models/{furnitureModel:slug}', [FurnitureModelController::class, 'show'])->name('furniture-models.show');

    Route::apiResource('materials', MaterialController::class)->except(['destroy']);
    Route::post('materials/{material}/adjust', [MaterialController::class, 'adjust'])->name('materials.adjust');
    Route::get('materials/{material}/movements', [MaterialController::class, 'movements'])->name('materials.movements');
});
