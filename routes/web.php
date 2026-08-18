<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Tools\ColorContrastController;
use App\Http\Controllers\Tools\ColorConvertController;
use App\Http\Controllers\Tools\ColorGradientController;
use App\Http\Controllers\Tools\ColorPaletteController;
use App\Http\Controllers\Tools\ColorSchemeController;
use App\Http\Controllers\Tools\ImageColorExtractController;
use App\Http\Controllers\Tools\ImageCompressController;
use App\Http\Controllers\Tools\ImageConvertController;
use App\Http\Controllers\Tools\ImageResizeController;
use App\Http\Controllers\Tools\ImageRotateController;
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

Route::get('/image-color-extract', [ImageColorExtractController::class, 'show'])->name('image-color-extract.show');

Route::post('/image-color-extract', [ImageColorExtractController::class, 'process'])
    ->middleware('throttle:10,1')
    ->name('image-color-extract.process');

Route::get('/image-rotate', [ImageRotateController::class, 'show'])->name('image-rotate.show');

Route::post('/image-rotate', [ImageRotateController::class, 'process'])
    ->middleware('throttle:10,1')
    ->name('image-rotate.process');

Route::get('/color-convert', [ColorConvertController::class, 'show'])->name('color-convert.show');
Route::post('/color-convert/track', [ColorConvertController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-convert.track');

Route::get('/color-contrast', [ColorContrastController::class, 'show'])->name('color-contrast.show');
Route::post('/color-contrast/track', [ColorContrastController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-contrast.track');

Route::get('/color-palette', [ColorPaletteController::class, 'show'])->name('color-palette.show');
Route::post('/color-palette/track', [ColorPaletteController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-palette.track');

Route::get('/color-gradient', [ColorGradientController::class, 'show'])->name('color-gradient.show');
Route::post('/color-gradient/track', [ColorGradientController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-gradient.track');

Route::get('/color-scheme', [ColorSchemeController::class, 'show'])->name('color-scheme.show');
Route::post('/color-scheme/track', [ColorSchemeController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-scheme.track');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
