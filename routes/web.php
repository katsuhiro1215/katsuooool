<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Tools\ImageCompressController;
use App\Http\Controllers\Tools\ImageConvertController;
use App\Http\Controllers\Tools\ImageResizeController;
use App\Http\Controllers\Tools\ToolDownloadController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/tool-download/{token}', [ToolDownloadController::class, 'show'])
    ->middleware('signed')
    ->name('tools.download');

Route::get('/image-compress', [ImageCompressController::class, 'show'])->name('image-compress.show');

Route::post('/image-compress', [ImageCompressController::class, 'process'])
    ->middleware('throttle:10,1')
    ->name('image-compress.process');

Route::get('/image-convert', [ImageConvertController::class, 'show'])->name('image-convert.show');

Route::post('/image-convert', [ImageConvertController::class, 'process'])
    ->middleware('throttle:10,1')
    ->name('image-convert.process');

Route::get('/image-resize', [ImageResizeController::class, 'show'])->name('image-resize.show');

Route::post('/image-resize', [ImageResizeController::class, 'process'])
    ->middleware('throttle:10,1')
    ->name('image-resize.process');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
